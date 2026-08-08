<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\TicketRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use RuntimeException;

final class TicketRepositoryTest extends TestCase
{
    private PDO $connection;

    private TicketRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->repository = new TicketRepository($this->connection);
    }

    public function testParticipantReadsUseUserOwnershipIncludeLifecycleStateAndHideDigest(): void
    {
        $tickets = $this->repository->forParticipant(1);

        $this->assertSame([202, 201], array_column($tickets, 'id'));
        $this->assertSame('pending', $tickets[0]['registration_status']);
        $this->assertSame('published', $tickets[0]['event_status']);
        $this->assertFalse(array_key_exists('qr_payload_hash', $tickets[0]));
        $this->assertNotNull($this->repository->findForParticipant(1, 201));
        $this->assertNull($this->repository->findForParticipant(2, 201));
    }

    public function testCreationStoresDigestButParticipantResultNeverReturnsIt(): void
    {
        $ticketId = $this->repository->createForRegistration(105, [
            'ticket_number' => 'OEMS-NEW-TICKET',
            'qr_payload_hash' => str_repeat('e', 64),
            'qr_path' => 'tickets/new.png',
            'pdf_path' => 'tickets/new.pdf',
            'status' => 'valid',
            'issued_at' => '2026-08-08 12:00:00',
        ]);

        $storedDigest = $this->connection->query("SELECT qr_payload_hash FROM tickets WHERE id = {$ticketId}")->fetchColumn();
        $participantTicket = $this->repository->findForParticipant(1, $ticketId);

        $this->assertSame(str_repeat('e', 64), $storedDigest);
        $this->assertNotNull($participantTicket);
        $this->assertFalse(array_key_exists('qr_payload_hash', $participantTicket));
    }

    public function testOrganizerLookupUsesOrganizerUserOwnershipAndOnlyAcceptsDigestOrNumber(): void
    {
        $digest = str_repeat('a', 64);
        $byDigest = $this->repository->findForOrganizerByTokenDigest(100, $digest);
        $byNumber = $this->repository->findForOrganizerByNumber(100, 'OEMS-TICKET-201');

        $this->assertNotNull($byDigest);
        $this->assertSame(201, $byDigest['id']);
        $this->assertNotNull($byNumber);
        $this->assertSame(201, $byNumber['id']);
        $this->assertNull($this->repository->findForOrganizerByTokenDigest(10, $digest));
        $this->assertNull($this->repository->findForOrganizerByTokenDigest(200, $digest));
        $this->assertNull($this->repository->findForOrganizerByTokenDigest(100, str_repeat('b', 64)));
        $this->assertNull($this->repository->findForOrganizerByNumber(100, 'OEMS-TICKET-204'));
    }

    public function testVoidingUsesValidOnlyCompareAndSet(): void
    {
        $this->assertTrue($this->repository->voidForRegistration(101));
        $this->assertSame('cancelled', $this->ticketStatus(201));
        $this->assertFalse($this->repository->voidForRegistration(101));

        $this->connection->exec("UPDATE tickets SET status = 'used' WHERE id = 202");
        $this->assertFalse($this->repository->voidForRegistration(102));
        $this->assertSame('used', $this->ticketStatus(202));
    }

    public function testCheckInRequiresUserOwnedValidTicketAndConfirmedRegistration(): void
    {
        $this->assertNull($this->repository->recordAttendance(200, 201, 100, '127.0.0.1'));
        $this->assertNull($this->repository->recordAttendance(10, 201, 100, '127.0.0.1'));
        $this->assertNull($this->repository->recordAttendance(100, 202, 100, '127.0.0.1'));
        $this->assertNull($this->repository->recordAttendance(100, 204, 100, '127.0.0.1'));
        $this->assertSame(0, $this->attendanceCount());

        $attendance = $this->repository->recordAttendance(100, 201, 100, '127.0.0.1');
        $this->assertNotNull($attendance);
        $this->assertSame(101, $attendance['registration_id']);
        $this->assertSame(201, $attendance['ticket_id']);
        $this->assertSame('present', $attendance['attendance_status']);
        $this->assertSame('used', $this->ticketStatus(201));
        $this->assertSame(1, $this->attendanceCount());
    }

    public function testDuplicateCheckInReturnsTheOriginalAttendanceWithoutAnotherInsert(): void
    {
        $first = $this->repository->recordAttendance(100, 201, 100, '127.0.0.1');
        $second = $this->repository->recordAttendance(100, 201, 999, '203.0.113.10');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($first['scanned_at'], $second['scanned_at']);
        $this->assertSame(100, $second['scanned_by']);
        $this->assertSame('127.0.0.1', $second['scanner_ip']);
        $this->assertSame(1, $this->attendanceCount());
    }

    public function testLostTicketUseCompareAndSetDoesNotReportAttendanceSuccess(): void
    {
        $this->connection->exec(
            "CREATE TRIGGER lose_ticket_use_compare_and_set
             BEFORE UPDATE OF status ON tickets
             WHEN OLD.id = 201 AND OLD.status = 'valid' AND NEW.status = 'used'
             BEGIN SELECT RAISE(IGNORE); END",
        );
        $lostCompareAndSetWasSurfaced = false;
        $this->connection->beginTransaction();

        try {
            $this->repository->recordAttendance(100, 201, 100, '127.0.0.1');
        } catch (RuntimeException) {
            $lostCompareAndSetWasSurfaced = true;
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }

        $this->assertTrue($lostCompareAndSetWasSurfaced, 'A lost valid-to-used ticket CAS must be surfaced.');
        $this->assertSame(0, $this->attendanceCount());
        $this->assertSame('valid', $this->ticketStatus(201));
    }

    public function testConcurrentCheckInCasLoserReturnsTheWinnersExistingAttendance(): void
    {
        $this->connection->exec(
            "CREATE TRIGGER complete_concurrent_ticket_scan
             BEFORE UPDATE OF status ON tickets
             WHEN OLD.id = 201 AND OLD.status = 'valid' AND NEW.status = 'used'
             BEGIN
                 UPDATE tickets SET status = 'used', updated_at = '2026-08-09 10:00:00' WHERE id = OLD.id;
                 INSERT INTO attendance
                     (registration_id, ticket_id, scanned_by, status, scanned_at, scanner_ip, created_at)
                 VALUES
                     (OLD.registration_id, OLD.id, 777, 'present', '2026-08-09 10:00:00', '198.51.100.7', '2026-08-09 10:00:00');
                 SELECT RAISE(IGNORE);
             END",
        );

        $attendance = $this->repository->recordAttendance(100, 201, 999, '203.0.113.10');

        $this->assertNotNull($attendance);
        $this->assertSame(1, $attendance['id']);
        $this->assertSame(101, $attendance['registration_id']);
        $this->assertSame(201, $attendance['ticket_id']);
        $this->assertSame(777, $attendance['scanned_by']);
        $this->assertSame('2026-08-09 10:00:00', $attendance['scanned_at']);
        $this->assertSame('198.51.100.7', $attendance['scanner_ip']);
        $this->assertSame(1, $this->attendanceCount());
        $this->assertSame('used', $this->ticketStatus(201));
    }

    public function testDashboardSummariesUseTicketAggregatesWithParticipantOrganizerAndAdminScope(): void
    {
        $this->assertSame(
            ['issued' => 1, 'checked_in' => 0],
            $this->repository->summaryForParticipant(1),
        );
        $this->assertSame(
            ['issued' => 1, 'checked_in' => 0],
            $this->repository->summaryForOrganizer(100),
        );
        $this->assertSame(
            ['issued' => 2, 'checked_in' => 0],
            $this->repository->summaryForAdmin(),
        );
        $this->assertSame(
            ['issued' => 0, 'checked_in' => 0],
            $this->repository->summaryForParticipant(999),
        );

        $attendance = $this->repository->recordAttendance(100, 201, 100, '127.0.0.1');
        $this->assertNotNull($attendance);
        $this->assertSame(
            ['issued' => 1, 'checked_in' => 1],
            $this->repository->summaryForParticipant(1),
        );
        $this->assertSame(
            ['issued' => 1, 'checked_in' => 1],
            $this->repository->summaryForOrganizer(100),
        );
        $this->assertSame(
            ['issued' => 2, 'checked_in' => 1],
            $this->repository->summaryForAdmin(),
        );
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE, organization_name TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, registration_number TEXT NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec(
            'CREATE TABLE tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                registration_id INTEGER NOT NULL UNIQUE,
                ticket_number TEXT NOT NULL UNIQUE,
                qr_payload_hash TEXT NOT NULL UNIQUE,
                qr_path TEXT NULL,
                pdf_path TEXT NULL,
                status TEXT NOT NULL,
                issued_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
        $this->connection->exec(
            'CREATE TABLE attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                registration_id INTEGER NOT NULL UNIQUE,
                ticket_id INTEGER NOT NULL UNIQUE,
                scanned_by INTEGER NOT NULL,
                status TEXT NOT NULL,
                scanned_at TEXT NOT NULL,
                scanner_ip TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name) VALUES (10, 100, 'Organizer One'), (20, 200, 'Organizer Two')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, slug, status) VALUES (10, 10, 'Owned Event', 'owned-event', 'published'), (20, 20, 'Foreign Event', 'foreign-event', 'published')");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status) VALUES (101, 10, 1, 'REG-101', 'confirmed'), (102, 10, 1, 'REG-102', 'pending'), (103, 20, 2, 'REG-103', 'confirmed'), (104, 10, 3, 'REG-104', 'confirmed'), (105, 10, 1, 'REG-105', 'confirmed')");
        $this->connection->exec(
            "INSERT INTO tickets (id, registration_id, ticket_number, qr_payload_hash, qr_path, pdf_path, status, issued_at, created_at, updated_at) VALUES
                (201, 101, 'OEMS-TICKET-201', '" . str_repeat('a', 64) . "', 'tickets/201.png', 'tickets/201.pdf', 'valid', '2026-08-01 09:00:00', '2026-08-01 09:00:00', '2026-08-01 09:00:00'),
                (202, 102, 'OEMS-TICKET-202', '" . str_repeat('b', 64) . "', 'tickets/202.png', 'tickets/202.pdf', 'valid', '2026-08-02 09:00:00', '2026-08-02 09:00:00', '2026-08-02 09:00:00'),
                (203, 103, 'OEMS-TICKET-203', '" . str_repeat('c', 64) . "', 'tickets/203.png', 'tickets/203.pdf', 'valid', '2026-08-03 09:00:00', '2026-08-03 09:00:00', '2026-08-03 09:00:00'),
                (204, 104, 'OEMS-TICKET-204', '" . str_repeat('d', 64) . "', 'tickets/204.png', 'tickets/204.pdf', 'cancelled', '2026-08-04 09:00:00', '2026-08-04 09:00:00', '2026-08-04 09:00:00')",
        );
    }

    private function ticketStatus(int $ticketId): string
    {
        return (string) $this->connection->query("SELECT status FROM tickets WHERE id = {$ticketId}")->fetchColumn();
    }

    private function attendanceCount(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
    }
}
