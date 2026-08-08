<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\TicketRepository;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\Tests\Support\TestCase;
use PDO;
use Throwable;

final class TicketIssueFailingPdo extends PDO
{
    public bool $failPostInsertRead = false;

    private bool $postInsertReadArmed = false;

    public function lastInsertId(?string $name = null): string|false
    {
        $id = parent::lastInsertId($name);

        if ($this->failPostInsertRead) {
            $this->postInsertReadArmed = true;
        }

        return $id;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if ($this->postInsertReadArmed
            && str_contains($query, 'WHERE tickets.registration_id = :registration_id')) {
            $this->postInsertReadArmed = false;

            throw new \RuntimeException('post-insert ticket read failed');
        }

        return parent::prepare($query, $options);
    }
}

final class TicketServiceTest extends TestCase
{
    private TicketIssueFailingPdo $connection;

    private string $ticketRoot;

    private TicketService $service;

    protected function setUp(): void
    {
        $this->connection = new TicketIssueFailingPdo('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->ticketRoot = sys_get_temp_dir() . '/oems-ticket-service-' . bin2hex(random_bytes(6));
        $this->service = new TicketService(
            $this->connection,
            new TicketRepository($this->connection),
            new TicketArtifactService($this->ticketRoot, 'uploads/tickets'),
        );
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->ticketRoot)) {
            return;
        }

        foreach (glob($this->ticketRoot . '/*') ?: [] as $path) {
            unlink($path);
        }

        rmdir($this->ticketRoot);
    }

    public function testIssuancePersistsOnlyDigestAndSafePathsAndIsIdempotent(): void
    {
        $issued = $this->service->issue(
            ['id' => 10, 'registration_number' => 'REG-10'],
            ['id' => 1, 'name' => 'Participant One'],
            [
                'title' => 'Event One',
                'start_date' => '2026-08-20 10:00:00',
                'venue_name' => 'Main Hall',
            ],
        );

        $this->assertTrue($issued['created']);
        $this->assertFalse(array_key_exists('raw_token', $issued['ticket']));
        $this->assertFalse(array_key_exists('qr_payload_hash', $issued['ticket']));
        $this->assertSame(2, count(glob($this->ticketRoot . '/*') ?: []));
        $digest = (string) $this->connection->query('SELECT qr_payload_hash FROM tickets')->fetchColumn();
        $this->assertSame(64, strlen($digest));

        $repeat = $this->service->issue(
            ['id' => 10, 'registration_number' => 'REG-10'],
            ['id' => 1, 'name' => 'Participant One'],
            ['title' => 'Event One'],
        );
        $this->assertFalse($repeat['created']);
        $this->assertSame($issued['ticket']['id'], $repeat['ticket']['id']);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        $this->assertSame(2, count(glob($this->ticketRoot . '/*') ?: []));
    }

    public function testCheckInHashesTheRawTokenAndUsesOrganizerOwnership(): void
    {
        $issued = $this->service->issue(
            ['id' => 10, 'registration_number' => 'REG-10'],
            ['id' => 1, 'name' => 'Participant One'],
            ['title' => 'Event One'],
        );
        $qrFile = $this->service->downloadPath(1, (int) $issued['ticket']['id'], 'qr');
        $this->assertNotNull($qrFile);

        $digest = (string) $this->connection->query('SELECT qr_payload_hash FROM tickets')->fetchColumn();
        $wrong = $this->service->checkInByToken(99, str_repeat('a', 64), 2, '127.0.0.1');
        $this->assertNull($wrong);
        $this->assertNull($this->service->downloadPath(3, (int) $issued['ticket']['id'], 'pdf'));
        $this->assertNull($this->service->downloadPath(1, (int) $issued['ticket']['id'], 'exe'));
        $this->assertSame(64, strlen($digest));
    }

    public function testCheckInRollsBackTicketUseWhenAttendanceInsertFails(): void
    {
        $rawToken = str_repeat('b', 64);
        $digest = hash('sha256', $rawToken);
        $this->connection->exec("INSERT INTO tickets (id, registration_id, ticket_number, qr_payload_hash, status, issued_at) VALUES (20, 10, 'OEMS-CHECKIN', '{$digest}', 'valid', CURRENT_TIMESTAMP)");
        $this->connection->exec("CREATE TRIGGER fail_attendance BEFORE INSERT ON attendance BEGIN SELECT RAISE(ABORT, 'attendance unavailable'); END");
        $thrown = false;

        try {
            $this->service->checkInByToken(2, $rawToken, 2, '127.0.0.1');
        } catch (Throwable) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame('valid', (string) $this->connection->query('SELECT status FROM tickets WHERE id = 20')->fetchColumn());
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM attendance')->fetchColumn());
    }

    public function testStandaloneIssueRollsBackRowAndArtifactsWhenPostInsertReadFails(): void
    {
        $this->connection->failPostInsertRead = true;
        $thrown = false;

        try {
            $this->service->issue(
                ['id' => 10, 'registration_number' => 'REG-10'],
                ['id' => 1, 'name' => 'Participant One'],
                ['title' => 'Event One'],
            );
        } catch (Throwable) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        $this->assertSame([], glob($this->ticketRoot . '/*') ?: []);
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testIssueInsideOuterTransactionLeavesCommitAndRollbackToCaller(): void
    {
        $this->connection->beginTransaction();

        $issuance = $this->service->issue(
            ['id' => 10, 'registration_number' => 'REG-10'],
            ['id' => 1, 'name' => 'Participant One'],
            ['title' => 'Event One'],
        );

        $this->assertTrue($this->connection->inTransaction());
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        $this->connection->rollBack();
        $this->service->cleanupCreated($issuance);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM tickets')->fetchColumn());
        $this->assertSame([], glob($this->ticketRoot . '/*') ?: []);
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, registration_number TEXT NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL UNIQUE, ticket_number TEXT NOT NULL UNIQUE, qr_payload_hash TEXT NOT NULL UNIQUE, qr_path TEXT NULL, pdf_path TEXT NULL, status TEXT NOT NULL, issued_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL UNIQUE, ticket_id INTEGER NOT NULL UNIQUE, scanned_by INTEGER NOT NULL, status TEXT NOT NULL, scanned_at TEXT NOT NULL, scanner_ip TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO organizers (id, user_id) VALUES (1, 2)");
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, slug, status) VALUES (5, 1, 'Event One', 'event-one', 'published')");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status) VALUES (10, 5, 1, 'REG-10', 'confirmed')");
    }
}
