<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\ReviewRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use PDOStatement;

final class ReviewRepositoryRecordingPdo extends PDO
{
    public array $preparedQueries = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedQueries[] = $query;

        return parent::prepare($query, $options);
    }
}

final class ReviewRepositoryZeroChangedRowsStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function rowCount(): int
    {
        return 0;
    }
}

final class ReviewRepositoryTest extends TestCase
{
    private PDO $connection;

    private mixed $repository = null;

    protected function setUp(): void
    {
        $this->connection = new ReviewRepositoryRecordingPdo('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();

        if (class_exists(ReviewRepository::class)) {
            $this->repository = new ReviewRepository($this->connection);
        }
    }

    public function testEligibilityRequiresConfirmedRegistrationAndCompletedOrPastEvent(): void
    {
        $repository = $this->repository();

        $this->assertNotNull($repository->reviewableEventForParticipant(1, 101));
        $this->assertNotNull($repository->reviewableEventForParticipant(1, 102));
        $this->assertNull($repository->reviewableEventForParticipant(1, 103));
        $this->assertNull($repository->reviewableEventForParticipant(3, 101));
        $this->assertNull($repository->reviewableEventForParticipant(4, 101));
        $this->assertNull($repository->reviewableEventForParticipant(1, 105));
        $this->assertSame([102], array_column($repository->reviewableEventsForParticipant(1), 'id'));
    }

    public function testEligibilityAndAtomicSaveUseTheSuppliedApplicationClockInsteadOfDatabaseTime(): void
    {
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, slug, end_date, status, deleted_at) VALUES (106, 10, 'Clock Boundary Event', 'clock-boundary-event', '2000-01-01 10:00:00', 'published', NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, status) VALUES (16, 106, 1, 'confirmed')");
        $beforeEnd = new ReviewRepository(
            $this->connection,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2000-01-01 09:59:59'),
        );

        $this->assertNull($beforeEnd->reviewableEventForParticipant(1, 106));
        $this->assertFalse(in_array(106, array_column($beforeEnd->reviewableEventsForParticipant(1), 'id'), true));
        $this->assertSame(0, $beforeEnd->saveForParticipant(1, 106, [
            'rating' => 5,
            'review' => 'The application clock has not reached the end.',
        ]));
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM reviews WHERE event_id = 106')->fetchColumn());

        $atEnd = new ReviewRepository(
            $this->connection,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2000-01-01 10:00:00'),
        );
        $this->assertNotNull($atEnd->reviewableEventForParticipant(1, 106));
        $this->assertTrue($atEnd->saveForParticipant(1, 106, [
            'rating' => 5,
            'review' => 'The application clock reached the event end.',
        ]) > 0);
    }

    public function testSaveCreatesOnePendingReviewAndEditingReturnsItToPendingWithoutClearingReply(): void
    {
        $repository = $this->repository();

        $createdId = $repository->saveForParticipant(1, 102, ['rating' => 5, 'review' => 'A genuinely useful event.']);
        $sameId = $repository->saveForParticipant(1, 102, ['rating' => 4, 'review' => 'Updated after reflecting on it.']);
        $editedId = $repository->saveForParticipant(2, 101, ['rating' => 3, 'review' => 'A revised participant review.']);

        $this->assertSame($createdId, $sameId);
        $this->assertTrue($createdId > 0);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM reviews WHERE event_id = 102 AND user_id = 1')->fetchColumn());
        $this->assertSame('pending', $repository->findForParticipantEvent(1, 102)['status']);
        $this->assertSame('pending', $repository->findForParticipantEvent(2, 101)['status']);
        $this->assertSame('<b>Organizer reply</b>', $repository->findForParticipantEvent(2, 101)['organizer_reply']);
        $this->assertSame(0, $repository->saveForParticipant(3, 101, ['rating' => 5, 'review' => 'Cancelled registration cannot review.']));
    }

    public function testSaveUsesUniqueBindingsUnderNativePrepareContract(): void
    {
        $repository = $this->repository();
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $before = count($this->connection->preparedQueries);

        $repository->saveForParticipant(1, 102, ['rating' => 5, 'review' => 'A valid review for native prepares.']);

        foreach (array_slice($this->connection->preparedQueries, $before) as $query) {
            preg_match_all('/:(\w+)/', $query, $bindings);
            $this->assertSame(count($bindings[1]), count(array_unique($bindings[1])));
        }
    }

    public function testFirstSubmissionUsesOneAtomicWriteAndReturnsTheExistingRowOnRepeat(): void
    {
        $repository = $this->repository();
        $before = count($this->connection->preparedQueries);

        $first = $repository->saveForParticipant(1, 102, ['rating' => 4, 'review' => 'An atomic first participant review.']);
        $queries = array_slice($this->connection->preparedQueries, $before);
        $writes = array_values(array_filter($queries, static fn (string $query): bool => preg_match('/^\s*(?:INSERT|UPDATE)\b/i', $query) === 1));
        $second = $repository->saveForParticipant(1, 102, ['rating' => 5, 'review' => 'An atomic updated participant review.']);

        $this->assertSame(1, count($writes));
        $this->assertSame($first, $second);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM reviews WHERE event_id = 102 AND user_id = 1')->fetchColumn());
        $this->assertSame(5, (int) $repository->findForParticipantEvent(1, 102)['rating']);
    }

    public function testIdenticalEligibleUpdateSucceedsWhenDriverReportsZeroChangedRows(): void
    {
        $repository = $this->repository();
        $this->connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ReviewRepositoryZeroChangedRowsStatement::class]);

        $reviewId = $repository->saveForParticipant(2, 101, [
            'rating' => 5,
            'review' => 'Published participant review',
        ]);

        $this->assertSame(1, $reviewId);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM reviews WHERE event_id = 101 AND user_id = 2')->fetchColumn());
    }

    public function testPublicReadsAndAggregateExposePublishedRowsOnlyInNewestDeterministicOrder(): void
    {
        $repository = $this->repository();
        $this->connection->exec("UPDATE reviews SET updated_at = '2099-08-08 10:00:00' WHERE id = 1");
        $reviews = $repository->publicForEvent(101);
        $summary = $repository->summaryForEvent(101);

        $this->assertSame([7, 1], array_column($reviews, 'id'));
        $this->assertSame(['Newest published', 'Published participant review'], array_column($reviews, 'review'));
        $this->assertTrue((bool) $reviews[1]['verified_attendee']);
        $this->assertFalse((bool) $reviews[0]['verified_attendee']);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(4.5, (float) $summary['average']);
        $this->assertFalse(in_array('Pending secret text', array_column($reviews, 'review'), true));
        $this->assertFalse(in_array('Hidden secret text', array_column($reviews, 'review'), true));
        $this->assertFalse(in_array('Deleted review PII body', array_column($reviews, 'review'), true));
        $this->assertFalse(in_array('Deleted Review PII Name', array_column($reviews, 'participant_name'), true));
    }

    public function testOrganizerReadsAndRepliesAreSqlScopedToOwnedNondeletedPublishedEvents(): void
    {
        $repository = $this->repository();
        $owned = $repository->forOrganizer(50);

        $this->assertSame([7, 1], array_column($owned, 'id'));
        $this->assertNotNull($repository->replyForOrganizer(50, 1, 'Thank you for the thoughtful feedback.'));
        $this->assertNull($repository->replyForOrganizer(60, 1, 'Cross organizer reply'));
        $this->assertNull($repository->replyForOrganizer(50, 2, 'Pending reviews cannot receive replies.'));
        $this->assertNull($repository->replyForOrganizer(50, 6, 'Deleted events cannot receive replies.'));
        $this->assertNull($repository->replyForOrganizer(50, 8, 'Deleted users cannot receive replies.'));
        $this->assertNull($repository->findForOrganizer(50, 8));
        $this->assertSame('Thank you for the thoughtful feedback.', $repository->findForParticipantEvent(2, 101)['organizer_reply']);
        $this->assertFalse(in_array('Deleted review PII body', array_column($owned, 'review'), true));
        $this->assertFalse(in_array('Deleted Review PII Name', array_column($owned, 'participant_name'), true));
    }

    public function testIdenticalOrganizerReplyUsesScopedPostconditionWhenDriverReportsZeroChangedRows(): void
    {
        $repository = $this->repository();
        $this->connection->exec("UPDATE reviews SET organizer_reply = 'Same normalized reply', replied_at = '2026-08-08 10:00:00' WHERE id = 1");
        $this->connection->exec("UPDATE reviews SET organizer_reply = 'Same normalized reply', replied_at = '2026-08-08 10:00:00' WHERE id = 5");
        $this->connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ReviewRepositoryZeroChangedRowsStatement::class]);

        $samePublished = $repository->replyForOrganizer(50, 1, 'Same normalized reply');
        $sameHidden = $repository->replyForOrganizer(50, 5, 'Same normalized reply');

        $this->assertNotNull($samePublished);
        $this->assertSame('Same normalized reply', $samePublished['organizer_reply']);
        $this->assertNull($sameHidden);
    }

    public function testAdminQueueUsesBoundedFilterPendingFirstOldestOrderAndModerationCas(): void
    {
        $repository = $this->repository();

        $this->assertSame([2, 3, 6, 1, 4, 5, 7], array_column($repository->pendingForAdmin(), 'id'));
        $this->assertSame([2, 3, 6], array_column($repository->pendingForAdmin('pending'), 'id'));
        $this->assertSame([2, 3, 6, 1, 4, 5, 7], array_column($repository->pendingForAdmin('not-a-status'), 'id'));
        $this->assertNull($repository->findForAdmin(8));

        $published = $repository->moderate(900, 2, 'published');
        $this->assertSame('published', $published['status']);
        $this->assertSame('published', $repository->moderate(901, 2, 'published')['status']);
        $this->assertNull($repository->moderate(901, 2, 'hidden'));

        $hidden = $repository->moderate(900, 3, 'hidden');
        $this->assertSame('hidden', $hidden['status']);
        $this->assertSame('hidden', $repository->moderate(901, 3, 'hidden')['status']);
        $this->assertNull($repository->moderate(901, 3, 'published'));
        $this->assertNull($repository->moderate(900, 999, 'published'));
        $this->assertNull($repository->moderate(900, 6, 'invalid'));
    }

    private function repository(): ReviewRepository
    {
        $this->assertTrue($this->repository instanceof ReviewRepository, 'Review repository is missing.');

        return $this->repository;
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, end_date TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, ticket_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec(
            'CREATE TABLE reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                rating INTEGER NOT NULL,
                review TEXT NOT NULL,
                organizer_reply TEXT NULL,
                replied_at TEXT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (event_id, user_id)
            )',
        );
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO users (id, name, deleted_at) VALUES (1, 'Participant One', NULL), (2, 'Participant Two', NULL), (3, 'Cancelled Participant', NULL), (4, 'Pending Participant', NULL), (5, 'Hidden Participant', NULL), (6, 'Deleted Review PII Name', '2026-08-08 00:00:00')");
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name) VALUES (10, 50, 'Organizer One'), (20, 60, 'Organizer Two')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, slug, end_date, status, deleted_at) VALUES
            (101, 10, 'Completed Event', 'completed-event', '2099-08-01 12:00:00', 'completed', NULL),
            (102, 10, 'Past Published Event', 'past-published-event', '2000-08-01 12:00:00', 'published', NULL),
            (103, 10, 'Future Published Event', 'future-published-event', '2099-08-01 12:00:00', 'published', NULL),
            (104, 20, 'Other Organizer Event', 'other-organizer-event', '2000-08-01 12:00:00', 'completed', NULL),
            (105, 10, 'Deleted Event', 'deleted-event', '2000-08-01 12:00:00', 'completed', '2026-08-01 00:00:00')");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, status) VALUES
            (11, 101, 1, 'confirmed'), (12, 102, 1, 'confirmed'), (13, 103, 1, 'confirmed'), (14, 104, 1, 'confirmed'), (15, 105, 1, 'confirmed'),
            (21, 101, 2, 'confirmed'), (31, 101, 3, 'cancelled'), (41, 101, 4, 'pending'), (61, 101, 6, 'confirmed')");
        $this->connection->exec("INSERT INTO tickets (id, registration_id) VALUES (201, 21)");
        $this->connection->exec("INSERT INTO attendance (id, registration_id, ticket_id, status) VALUES (301, 21, 201, 'present')");
        $this->connection->exec("INSERT INTO reviews (id, event_id, user_id, rating, review, organizer_reply, replied_at, status, created_at, updated_at) VALUES
            (1, 101, 2, 5, 'Published participant review', '<b>Organizer reply</b>', '2026-08-04 10:00:00', 'published', '2026-08-01 09:00:00', '2026-08-04 10:00:00'),
            (2, 101, 3, 3, 'Pending secret text', NULL, NULL, 'pending', '2026-08-02 09:00:00', '2026-08-02 09:00:00'),
            (3, 101, 4, 2, 'Another pending text', NULL, NULL, 'pending', '2026-08-03 09:00:00', '2026-08-03 09:00:00'),
            (4, 104, 1, 4, 'Other organizer published', NULL, NULL, 'published', '2026-08-04 09:00:00', '2026-08-04 09:00:00'),
            (5, 101, 5, 1, 'Hidden secret text', NULL, NULL, 'hidden', '2026-08-05 09:00:00', '2026-08-05 09:00:00'),
            (6, 105, 1, 4, 'Deleted event pending', NULL, NULL, 'pending', '2026-08-06 09:00:00', '2026-08-06 09:00:00'),
            (7, 101, 1, 4, 'Newest published', NULL, NULL, 'published', '2026-08-07 09:00:00', '2026-08-07 09:00:00'),
            (8, 101, 6, 1, 'Deleted review PII body', NULL, NULL, 'published', '2026-08-08 09:00:00', '2026-08-08 09:00:00')");
    }
}
