<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\WaitlistRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class WaitlistRepositoryTest extends TestCase
{
    private PDO $connection;

    private WaitlistRepository $waitlists;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->waitlists = new WaitlistRepository($this->connection);
    }

    public function testJoinRequiresAFullEnabledEligibleEventAndDoesNotConsumeCapacity(): void
    {
        $this->assertNotNull($this->waitlists->findJoinableEvent(10));
        foreach ([11, 12, 13, 14] as $eventId) {
            $this->assertNull($this->waitlists->findJoinableEvent($eventId));
        }

        $entry = $this->waitlists->join(1, 10, [
            'registration_number' => 'OEMS-WAIT-1',
            'waitlisted_at' => '2026-08-10 10:00:00',
        ]);
        $this->assertNotNull($entry);
        $this->assertSame('waitlisted', $entry['status']);
        $this->assertSame('125.50', $entry['amount']);
        $this->assertSame(0, $this->availableSeats(10));
        $this->assertSame(0, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('tickets'));
    }

    public function testJoinReplayLeaveAndCleanRejoinAreTruthfulAndOwned(): void
    {
        $first = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-1', 'waitlisted_at' => '2026-08-10 10:00:00']);
        $repeat = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-2', 'waitlisted_at' => '2026-08-10 11:00:00']);
        $this->assertSame($first['id'], $repeat['id']);
        $this->assertNull($this->waitlists->leave(2, (int) $first['id'], 'Foreign', new DateTimeImmutable('2026-08-10 11:00:00')));
        $left = $this->waitlists->leave(1, (int) $first['id'], 'Plans changed', new DateTimeImmutable('2026-08-10 11:00:00'));
        $this->assertSame('cancelled', $left['status']);

        $rejoined = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-3', 'waitlisted_at' => '2026-08-10 12:00:00']);
        $this->assertSame($first['id'], $rejoined['id']);
        $this->assertSame('waitlisted', $rejoined['status']);
        $this->assertSame('OEMS-WAIT-3', $rejoined['registration_number']);
    }

    public function testPositionUsesOldestTimestampThenIdAndParticipantListIsScoped(): void
    {
        $this->waitlists->join(2, 10, ['registration_number' => 'OEMS-WAIT-2', 'waitlisted_at' => '2026-08-10 09:00:00']);
        $entry = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-1', 'waitlisted_at' => '2026-08-10 10:00:00']);
        $this->assertSame(2, $this->waitlists->position((int) $entry['id']));
        $rows = $this->waitlists->forParticipant(1);
        $this->assertSame(1, count($rows));
        $this->assertSame('Full paid event', $rows[0]['event_title']);
        $this->assertNull($this->waitlists->position(999));
    }

    public function testClaimOldestConsumesExactlyOneSeatAndSetsBoundedClaim(): void
    {
        $first = $this->waitlists->join(2, 10, ['registration_number' => 'OEMS-WAIT-2', 'waitlisted_at' => '2026-08-10 09:00:00']);
        $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-1', 'waitlisted_at' => '2026-08-10 10:00:00']);
        $this->connection->exec('UPDATE events SET available_seats = 1 WHERE id = 10');

        $claim = $this->waitlists->claimOldest(
            10,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            new DateTimeImmutable('2026-08-11 12:00:00'),
        );
        $this->assertSame($first['id'], $claim['id']);
        $this->assertSame('pending', $claim['status']);
        $this->assertSame('2026-08-11 12:00:00', $claim['waitlist_claim_expires_at']);
        $this->assertSame(0, $this->availableSeats(10));
        $this->assertNull($this->waitlists->claimOldest(10, new DateTimeImmutable(), new DateTimeImmutable('+1 day')));
    }

    public function testPromotionRefreshesPriceAndCurrencyFromTheLockedEvent(): void
    {
        $entry = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-PRICE', 'waitlisted_at' => '2026-08-10 10:00:00']);
        $this->connection->exec("UPDATE events SET available_seats = 1, ticket_price = 175.75, currency = 'USD' WHERE id = 10");

        $claim = $this->waitlists->claimOldest(
            10,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            new DateTimeImmutable('2026-08-11 12:00:00'),
        );

        $this->assertSame($entry['id'], $claim['id']);
        $this->assertSame('175.75', $claim['amount']);
        $this->assertSame('USD', $claim['currency']);
    }

    public function testCompleteClaimClearsOnlyAnActivePromotedClaim(): void
    {
        $entry = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-1', 'waitlisted_at' => '2026-08-10 10:00:00']);
        $this->connection->exec('UPDATE events SET available_seats = 1 WHERE id = 10');
        $this->waitlists->claimOldest(10, new DateTimeImmutable('2026-08-10 12:00:00'), new DateTimeImmutable('2026-08-11 12:00:00'));

        $this->assertTrue($this->waitlists->completeClaim((int) $entry['id']));
        $this->assertSame(null, $this->value('registrations', (int) $entry['id'], 'waitlist_claim_expires_at'));
        $this->assertTrue($this->waitlists->completeClaim((int) $entry['id']));
        $this->assertFalse($this->waitlists->completeClaim(999));
    }

    public function testEventsWithAvailableSeatsAreBoundedAndRequireQueuedEntries(): void
    {
        $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-1', 'waitlisted_at' => '2026-08-10 10:00:00']);
        $this->connection->exec('UPDATE events SET available_seats = 1 WHERE id = 10');
        $this->assertSame([10], $this->waitlists->eventsWithAvailableSeats(50));
        $this->assertSame([10], $this->waitlists->eventsWithAvailableSeats(1000));
    }

    public function testExpiredUnpaidClaimIsReleasedAtomicallyAndRestoresOneSeat(): void
    {
        $entry = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-EXPIRY', 'waitlisted_at' => '2026-08-10 08:00:00']);
        $this->connection->exec('UPDATE events SET available_seats = 1 WHERE id = 10');
        $this->waitlists->claimOldest(10, new DateTimeImmutable('2026-08-10 09:00:00'), new DateTimeImmutable('2026-08-10 10:00:00'));

        $expired = $this->waitlists->expiredClaims(new DateTimeImmutable('2026-08-10 10:00:00'), 25);
        $this->assertSame([(int) $entry['id']], array_column($expired, 'id'));

        $released = $this->waitlists->releaseExpiredClaim((int) $entry['id'], new DateTimeImmutable('2026-08-10 10:01:00'));
        $this->assertSame('cancelled', $released['status']);
        $this->assertSame('Waitlist payment window expired', $released['cancellation_reason']);
        $this->assertSame(1, $this->availableSeats(10));
        $this->assertNull($this->waitlists->releaseExpiredClaim((int) $entry['id'], new DateTimeImmutable('2026-08-10 10:02:00')));
        $this->assertSame(1, $this->availableSeats(10));
    }

    public function testExpiredClaimWithPaymentIsNeverReleased(): void
    {
        $entry = $this->waitlists->join(1, 10, ['registration_number' => 'OEMS-WAIT-PAID', 'waitlisted_at' => '2026-08-10 08:00:00']);
        $this->connection->exec('UPDATE events SET available_seats = 1 WHERE id = 10');
        $this->waitlists->claimOldest(10, new DateTimeImmutable('2026-08-10 09:00:00'), new DateTimeImmutable('2026-08-10 10:00:00'));
        $this->connection->exec('INSERT INTO payments (registration_id) VALUES (' . (int) $entry['id'] . ')');

        $this->assertSame([], $this->waitlists->expiredClaims(new DateTimeImmutable('2026-08-10 11:00:00'), 25));
        $this->assertNull($this->waitlists->releaseExpiredClaim((int) $entry['id'], new DateTimeImmutable('2026-08-10 11:00:00')));
        $this->assertSame(0, $this->availableSeats(10));
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL, status TEXT NOT NULL, email_verified_at TEXT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, approval_status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, category_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, registration_deadline TEXT NOT NULL, capacity INTEGER NOT NULL, available_seats INTEGER NOT NULL, ticket_price NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, waitlist_enabled INTEGER NOT NULL DEFAULT 1, deleted_at TEXT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, registration_number TEXT NOT NULL UNIQUE, status TEXT NOT NULL, amount NUMERIC NOT NULL, currency TEXT NOT NULL, registered_at TEXT NOT NULL, waitlisted_at TEXT NULL, promoted_at TEXT NULL, waitlist_claim_expires_at TEXT NULL, cancelled_at TEXT NULL, cancellation_reason TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(event_id, user_id))');
        $this->connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL)');
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO users (id, name, email, status, email_verified_at, deleted_at) VALUES (1, 'Participant One', 'one@example.test', 'active', CURRENT_TIMESTAMP, NULL), (2, 'Participant Two', 'two@example.test', 'active', CURRENT_TIMESTAMP, NULL), (3, 'Inactive', 'inactive@example.test', 'inactive', CURRENT_TIMESTAMP, NULL)");
        $this->connection->exec("INSERT INTO organizers (id, user_id, approval_status) VALUES (1, 20, 'approved')");
        $this->connection->exec('INSERT INTO categories (id, is_active) VALUES (1, 1), (2, 0)');
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled, deleted_at) VALUES
            (10, 1, 1, 'Full paid event', 'full-paid', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 0, 125.50, 'BDT', 'published', 1, NULL),
            (11, 1, 1, 'Available event', 'available', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 1, 0, 'BDT', 'published', 1, NULL),
            (12, 1, 1, 'Disabled waitlist', 'disabled', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 0, 'BDT', 'published', 0, NULL),
            (13, 1, 1, 'Draft event', 'draft', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 0, 'BDT', 'draft', 1, NULL),
            (14, 1, 2, 'Inactive category', 'inactive', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 0, 'BDT', 'published', 1, NULL)");
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function availableSeats(int $eventId): int
    {
        return (int) $this->connection->query("SELECT available_seats FROM events WHERE id = {$eventId}")->fetchColumn();
    }

    private function value(string $table, int $id, string $column): mixed
    {
        return $this->connection->query("SELECT {$column} FROM {$table} WHERE id = {$id}")->fetchColumn();
    }
}
