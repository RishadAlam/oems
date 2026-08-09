<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class DashboardMetricsRepositoryTest extends TestCase
{
    public function testReturnsVisiblePlatformTotalsAsIntegers(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec("INSERT INTO users (deleted_at) VALUES (NULL), (NULL), ('2026-08-01 09:00:00')");
        $connection->exec('INSERT INTO organizers (user_id) VALUES (1), (3)');
        $connection->exec("INSERT INTO events (deleted_at) VALUES (NULL), (NULL), (NULL), ('2026-08-02 09:00:00')");

        $repository = new DashboardMetricsRepository($connection);

        $this->assertSame([
            'users' => 2,
            'organizers' => 1,
            'events' => 3,
        ], $repository->totals());
    }

    public function testReviewSummariesUseNarrowSqlAggregatesWithParticipantOrganizerAndAdminScope(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, organizer_reply TEXT NULL)');
        $connection->exec("INSERT INTO users (id, deleted_at) VALUES (1, NULL), (2, NULL), (3, NULL), (4, '2026-08-01 00:00:00')");
        $connection->exec('INSERT INTO organizers (id, user_id) VALUES (10, 2), (11, 3)');
        $connection->exec("INSERT INTO events (id, organizer_id, deleted_at) VALUES (20, 10, NULL), (21, 11, NULL), (22, 10, '2026-08-02 00:00:00')");
        $connection->exec("INSERT INTO reviews (id, event_id, user_id, status, organizer_reply) VALUES
            (1, 20, 1, 'pending', NULL),
            (2, 20, 1, 'published', NULL),
            (3, 20, 3, 'published', 'Thanks'),
            (4, 21, 1, 'published', NULL),
            (5, 22, 1, 'pending', NULL),
            (6, 20, 4, 'published', NULL)");

        $repository = new DashboardMetricsRepository($connection);

        $this->assertSame(
            ['submitted' => 3, 'pending' => 1, 'published' => 2],
            $repository->reviewsForParticipant(1),
        );
        $this->assertSame(
            ['published' => 2, 'awaiting_reply' => 1],
            $repository->reviewsForOrganizer(2),
        );
        $this->assertSame(
            ['total' => 4, 'pending' => 1, 'published' => 3],
            $repository->reviewsForAdmin(),
        );
        $this->assertSame(
            ['published' => 1, 'awaiting_reply' => 1],
            $repository->reviewsForOrganizer(3),
        );
        $this->assertSame(
            ['submitted' => 0, 'pending' => 0, 'published' => 0],
            $repository->reviewsForParticipant(999),
        );
    }

    public function testParticipantWorkspaceUsesScopedUpcomingFavoritesAndReviewActionQueries(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, end_date TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, status TEXT NOT NULL, registration_number TEXT NOT NULL)');
        $connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $connection->exec('CREATE TABLE favorites (user_id INTEGER NOT NULL, event_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, status TEXT NOT NULL, organizer_reply TEXT NULL)');
        $connection->exec("INSERT INTO users (id, deleted_at) VALUES (7, NULL), (8, NULL)");
        $connection->exec("INSERT INTO events (id, title, slug, start_date, end_date, status, deleted_at) VALUES
            (11, 'Owned future event', 'owned-future', datetime('now', '+5 days'), datetime('now', '+5 days', '+2 hours'), 'published', NULL),
            (12, 'Owned completed event', 'owned-completed', datetime('now', '-5 days'), datetime('now', '-5 days', '+2 hours'), 'published', NULL),
            (13, 'Foreign future event', 'foreign-future', datetime('now', '+2 days'), datetime('now', '+2 days', '+2 hours'), 'published', NULL),
            (14, 'Owned ongoing event', 'owned-ongoing', datetime('now', '-2 hours'), datetime('now', '+2 hours'), 'published', NULL)");
        $connection->exec("INSERT INTO registrations (id, user_id, event_id, status, registration_number) VALUES
            (21, 7, 11, 'confirmed', 'REG-OWNED'), (22, 7, 12, 'confirmed', 'REG-REVIEW'), (23, 8, 13, 'confirmed', 'REG-FOREIGN'), (24, 7, 14, 'confirmed', 'REG-ONGOING')");
        $connection->exec("INSERT INTO payments (registration_id, status) VALUES (21, 'paid'), (21, 'pending'), (22, 'paid'), (23, 'paid')");
        $connection->exec('INSERT INTO favorites (user_id, event_id) VALUES (7, 11), (7, 12), (8, 13)');
        $connection->exec("INSERT INTO reviews (id, user_id, event_id, status, organizer_reply) VALUES (1, 8, 13, 'published', NULL)");

        $workspace = (new DashboardMetricsRepository($connection))->participantWorkspace(7);

        $this->assertSame(2, $workspace['favorite_count']);
        $this->assertSame(1, $workspace['review_actions']);
        $this->assertSame(1, count($workspace['upcoming']));
        $this->assertSame('Owned future event', $workspace['upcoming'][0]['event_title']);
        $this->assertSame('pending', $workspace['upcoming'][0]['payment_status']);
    }
}
