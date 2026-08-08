<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\RegistrationRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use PDOStatement;

final class RegistrationRepositoryRecordingPdo extends PDO
{
    public array $preparedQueries = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedQueries[] = $query;

        return parent::prepare($query, $options);
    }
}

final class RegistrationRepositoryTest extends TestCase
{
    private RegistrationRepositoryRecordingPdo $connection;

    private RegistrationRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new RegistrationRepositoryRecordingPdo('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->repository = new RegistrationRepository($this->connection);
    }

    public function testReservationRequiresEveryPublicEligibilityCondition(): void
    {
        $this->assertNotNull($this->repository->reserve(3, 17, $this->attributes('REG-ELIGIBLE')));

        foreach ([11, 12, 13, 14, 15, 18] as $eventId) {
            $this->assertNull(
                $this->repository->reserve(1, $eventId, $this->attributes('REG-INELIGIBLE-' . $eventId)),
            );
        }

        $this->connection->exec("UPDATE organizers SET approval_status = 'pending' WHERE id = 1");
        $this->assertNull($this->repository->reserve(3, 10, $this->attributes('REG-UNAPPROVED')));
    }

    public function testParticipantReadsUseTheAuthenticatedUserIdAndDeterministicOrder(): void
    {
        $registration = $this->repository->findForParticipant(1, 101);

        $this->assertNotNull($registration);
        $this->assertSame('Eligible Event', $registration['event_title']);
        $this->assertSame('cancelled', $registration['registration_status']);
        $this->assertNull($this->repository->findForParticipant(2, 101));
        $this->assertNull($this->repository->findForParticipantEvent(1, 16));
        $this->assertSame([105, 101], array_column($this->repository->forParticipant(1), 'id'));
    }

    public function testDuplicateEventUserRowIsNotInsertedAndCancelledRowCanBeReactivated(): void
    {
        $this->assertNull($this->repository->reserve(1, 10, $this->attributes('REG-DUPLICATE')));
        $this->assertSame(1, $this->registrationCount(10, 1));

        $this->assertTrue($this->repository->reactivate(101, $this->attributes('REG-REACTIVATED')));
        $reactivated = $this->repository->findForParticipantEvent(1, 10);

        $this->assertNotNull($reactivated);
        $this->assertSame(101, $reactivated['id']);
        $this->assertSame('REG-REACTIVATED', $reactivated['registration_number']);
        $this->assertSame('pending', $reactivated['registration_status']);
        $this->assertNull($reactivated['cancelled_at']);
        $this->assertNull($reactivated['cancellation_reason']);
        $this->assertSame(1, $this->registrationCount(10, 1));
        $this->assertFalse($this->repository->reactivate(101, $this->attributes('REG-SECOND-REACTIVATION')));
    }

    public function testPendingAndConfirmedRegistrationsBothConsumeCapacity(): void
    {
        $this->assertNull($this->repository->reserve(1, 16, $this->attributes('REG-PENDING-FULL')));
        $this->assertNull($this->repository->reserve(1, 19, $this->attributes('REG-CONFIRMED-FULL')));

        $this->connection->exec("UPDATE registrations SET status = 'cancelled' WHERE id = 103");
        $this->connection->exec("UPDATE registrations SET status = 'refunded' WHERE id = 104");

        $this->assertNotNull($this->repository->reserve(1, 16, $this->attributes('REG-AFTER-CANCEL')));
        $this->assertNotNull($this->repository->reserve(1, 19, $this->attributes('REG-AFTER-REFUND')));

        $queries = implode("\n", $this->connection->preparedQueries);
        $this->assertTrue(str_contains($queries, 'COUNT(*)'));
        $this->assertTrue(str_contains($queries, "'pending', 'confirmed'"));
        $this->assertFalse(str_contains($queries, 'FOR UPDATE'), 'SQLite queries must remain executable.');
    }

    public function testReactivationCannotOversellAFullEvent(): void
    {
        $this->connection->exec("UPDATE events SET capacity = 1 WHERE id = 10");

        $this->assertFalse($this->repository->reactivate(101, $this->attributes('REG-OVERSELL')));
        $this->assertSame('cancelled', $this->registrationStatus(101));
    }

    public function testConfirmationAndCancellationUseEligibleStatusCompareAndSetGuards(): void
    {
        $this->assertTrue($this->repository->confirm(103));
        $this->assertSame('confirmed', $this->registrationStatus(103));
        $this->assertFalse($this->repository->confirm(103));

        $this->assertNull($this->repository->cancelForParticipant(2, 105, 'Not mine'));
        $cancelled = $this->repository->cancelForParticipant(1, 105, 'Schedule conflict');

        $this->assertNotNull($cancelled);
        $this->assertSame('cancelled', $cancelled['registration_status']);
        $this->assertSame('Schedule conflict', $cancelled['cancellation_reason']);
        $this->assertNotNull($cancelled['cancelled_at']);
        $this->assertNull($this->repository->cancelForParticipant(1, 105, 'Repeat'));
        $this->assertSame('Schedule conflict', $this->registrationReason(105));
    }

    public function testDashboardSummariesUseStatusAggregatesWithParticipantOrganizerAndAdminScope(): void
    {
        $this->assertSame(
            ['active' => 1, 'pending' => 0, 'confirmed' => 1],
            $this->repository->summaryForParticipant(1),
        );
        $this->assertSame(
            ['active' => 3, 'pending' => 1, 'confirmed' => 2],
            $this->repository->summaryForOrganizer(100),
        );
        $this->assertSame(
            ['active' => 1, 'pending' => 0, 'confirmed' => 1],
            $this->repository->summaryForOrganizer(200),
        );
        $this->assertSame(
            ['active' => 4, 'pending' => 1, 'confirmed' => 3],
            $this->repository->summaryForAdmin(),
        );
        $this->assertSame(
            ['active' => 0, 'pending' => 0, 'confirmed' => 0],
            $this->repository->summaryForParticipant(999),
        );

        $queries = implode("\n", $this->connection->preparedQueries);
        $this->assertTrue(str_contains($queries, 'SUM(CASE'));
        $this->assertTrue(str_contains($queries, 'organizers.user_id = :organizer_user_id'));
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, is_active INTEGER NOT NULL)');
        $this->connection->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY,
                organizer_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                start_date TEXT NOT NULL,
                registration_deadline TEXT NOT NULL,
                capacity INTEGER NOT NULL,
                ticket_price NUMERIC NOT NULL,
                currency TEXT NOT NULL,
                status TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
        $this->connection->exec(
            'CREATE TABLE registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                coupon_id INTEGER NULL,
                registration_number TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                amount NUMERIC NOT NULL,
                currency TEXT NOT NULL,
                registered_at TEXT NOT NULL,
                cancelled_at TEXT NULL,
                cancellation_reason TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (event_id, user_id)
            )',
        );
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status) VALUES (1, 100, 'Approved Org', 'approved'), (2, 200, 'Other Approved Org', 'approved'), (3, 300, 'Pending Org', 'pending')");
        $this->connection->exec("INSERT INTO categories (id, name, slug, is_active) VALUES (1, 'Active', 'active', 1), (2, 'Inactive', 'inactive', 0)");
        $this->connection->exec(
            "INSERT INTO events (id, organizer_id, category_id, title, slug, start_date, registration_deadline, capacity, ticket_price, currency, status, deleted_at) VALUES
                (10, 1, 1, 'Eligible Event', 'eligible-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 100, 'BDT', 'published', NULL),
                (11, 1, 1, 'Draft Event', 'draft-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 3, 0, 'BDT', 'draft', NULL),
                (12, 1, 1, 'Deleted Event', 'deleted-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 3, 0, 'BDT', 'published', CURRENT_TIMESTAMP),
                (13, 1, 2, 'Inactive Category', 'inactive-category', datetime('now', '+10 days'), datetime('now', '+9 days'), 3, 0, 'BDT', 'published', NULL),
                (14, 1, 1, 'Closed Registration', 'closed-registration', datetime('now', '+10 days'), datetime('now', '-1 minute'), 3, 0, 'BDT', 'published', NULL),
                (15, 3, 1, 'Pending Organizer', 'pending-organizer', datetime('now', '+10 days'), datetime('now', '+9 days'), 3, 0, 'BDT', 'published', NULL),
                (16, 1, 1, 'Pending Full', 'pending-full', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 'BDT', 'published', NULL),
                (17, 2, 1, 'Other Eligible Event', 'other-eligible-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 3, 0, 'BDT', 'published', NULL),
                (18, 1, 1, 'Started Event', 'started-event', datetime('now', '-1 minute'), datetime('now', '-1 day'), 3, 0, 'BDT', 'published', NULL),
                (19, 1, 1, 'Confirmed Full', 'confirmed-full', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 'BDT', 'published', NULL)",
        );
        $this->connection->exec(
            "INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at, cancelled_at, cancellation_reason, created_at, updated_at) VALUES
                (101, 10, 1, 'REG-CANCELLED', 'cancelled', 100, 'BDT', '2026-08-01 09:00:00', '2026-08-02 09:00:00', 'Changed plans', '2026-08-01 09:00:00', '2026-08-02 09:00:00'),
                (102, 10, 2, 'REG-CONFIRMED', 'confirmed', 100, 'BDT', '2026-08-01 10:00:00', NULL, NULL, '2026-08-01 10:00:00', '2026-08-01 10:00:00'),
                (103, 16, 2, 'REG-PENDING', 'pending', 0, 'BDT', '2026-08-03 10:00:00', NULL, NULL, '2026-08-03 10:00:00', '2026-08-03 10:00:00'),
                (104, 19, 2, 'REG-CONFIRMED-FULL', 'confirmed', 0, 'BDT', '2026-08-04 10:00:00', NULL, NULL, '2026-08-04 10:00:00', '2026-08-04 10:00:00'),
                (105, 17, 1, 'REG-OWNED', 'confirmed', 0, 'BDT', '2026-08-05 10:00:00', NULL, NULL, '2026-08-05 10:00:00', '2026-08-05 10:00:00')",
        );
    }

    private function attributes(string $number): array
    {
        return [
            'coupon_id' => null,
            'registration_number' => $number,
            'status' => 'pending',
            'amount' => '100.00',
            'currency' => 'BDT',
            'registered_at' => '2026-08-08 12:00:00',
        ];
    }

    private function registrationCount(int $eventId, int $userId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM registrations WHERE event_id = :event_id AND user_id = :user_id');
        $statement->execute(['event_id' => $eventId, 'user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    private function registrationStatus(int $registrationId): string
    {
        return (string) $this->connection->query("SELECT status FROM registrations WHERE id = {$registrationId}")->fetchColumn();
    }

    private function registrationReason(int $registrationId): string
    {
        return (string) $this->connection->query("SELECT cancellation_reason FROM registrations WHERE id = {$registrationId}")->fetchColumn();
    }
}
