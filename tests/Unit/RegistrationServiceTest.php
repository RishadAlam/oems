<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\PaymentRepository;
use OEMS\App\Repositories\RegistrationRepository;
use OEMS\App\Repositories\TicketRepository;
use OEMS\App\Services\RegistrationService;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\App\Services\TransactionMailer;
use OEMS\Core\Config;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use RuntimeException;

final class CommitFailingPdo extends PDO
{
    public bool $failCommit = false;

    public function commit(): bool
    {
        if ($this->failCommit) {
            throw new RuntimeException('commit failed with a path and secret reference');
        }

        return parent::commit();
    }
}

final class RegistrationServiceTest extends TestCase
{
    private CommitFailingPdo $connection;

    private FakeUserRepository $users;

    private FakeMailTransport $transport;

    private FakeEmailLogRepository $mailLogs;

    private RegistrationService $service;

    private string $ticketRoot;

    private string $logPath;

    protected function setUp(): void
    {
        $this->connection = new CommitFailingPdo('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();

        $this->users = new FakeUserRepository();
        $this->users->users = [
            1 => $this->user(1, 3, 'Participant One', 'participant@example.test'),
            2 => $this->user(2, 2, 'Organizer One', 'organizer@example.test'),
            3 => $this->user(3, 3, 'Inactive Participant', 'inactive@example.test', 'inactive'),
            4 => $this->user(4, 3, 'Unverified Participant', 'unverified@example.test', 'active', null),
            5 => $this->user(5, 3, 'Other Participant', 'other-active@example.test'),
            9 => $this->user(9, 1, 'Administrator', 'admin@example.test'),
        ];
        $this->transport = new FakeMailTransport('<transaction-message-id>');
        $this->mailLogs = new FakeEmailLogRepository();
        $this->ticketRoot = sys_get_temp_dir() . '/oems-registration-service-' . bin2hex(random_bytes(6));
        $this->logPath = sys_get_temp_dir() . '/oems-registration-service-' . bin2hex(random_bytes(6)) . '.log';
        $this->service = $this->service($this->connection);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->ticketRoot)) {
            foreach (glob($this->ticketRoot . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($this->ticketRoot);
        }

        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testOnlyActiveVerifiedParticipantsCanRegister(): void
    {
        foreach ([2, 3, 4, 999] as $actorId) {
            $result = $this->service->register($actorId, 10);

            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('account', $result['errors']);
        }

        $this->assertSame(1, $this->countRows('registrations'));
    }

    public function testRepositoryEligibilityRejectsUnpublishedInactiveClosedStartedAndFullEvents(): void
    {
        foreach ([12, 13, 14, 15, 16] as $eventId) {
            $result = $this->service->register(1, $eventId);

            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('event', $result['errors']);
        }

        $this->assertSame(1, $this->countRows('registrations'));
    }

    public function testFreeRegistrationUsesDatabasePriceConfirmsAndIssuesExactlyOnce(): void
    {
        $result = $this->service->register(1, 10, [
            'amount' => '999999.00',
            'currency' => 'USD',
            'transaction_reference' => 'ATTACKER-CONTROLLED',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('confirmed', $result['registration']['registration_status']);
        $this->assertSame('0', (string) $result['registration']['amount']);
        $this->assertSame('BDT', $result['registration']['currency']);
        $this->assertSame('paid', $result['payment']['payment_status']);
        $this->assertSame('0', (string) $result['payment']['amount']);
        $this->assertSame('free', $result['payment']['payment_method_slug']);
        $this->assertNotSame('ATTACKER-CONTROLLED', $result['payment']['transaction_reference']);
        $this->assertSame('valid', $result['ticket']['ticket_status']);
        $this->assertFalse(array_key_exists('raw_token', $result['ticket']));
        $this->assertFalse(array_key_exists('qr_payload_hash', $result['ticket']));
        $this->assertSame(2, count($this->transport->messages));
        $this->assertSame('sent', $result['delivery_status']);

        $repeat = $this->service->register(1, 10, ['amount' => '1.00', 'currency' => 'EUR']);

        $this->assertTrue($repeat['success']);
        $this->assertSame($result['registration']['id'], $repeat['registration']['id']);
        $this->assertSame($result['payment']['id'], $repeat['payment']['id']);
        $this->assertSame($result['ticket']['id'], $repeat['ticket']['id']);
        $this->assertSame('not_attempted', $repeat['delivery_status']);
        $this->assertSame(1, $this->countRows('payments'));
        $this->assertSame(1, $this->countRows('tickets'));
        $this->assertSame(1, $this->availableSeats(10));
    }

    public function testPaidRegistrationRequiresActiveManualMethodValidReferenceAndAllowListedChannel(): void
    {
        foreach ([
            ['', 'bank'],
            ['short', 'bank'],
            [str_repeat('x', 191), 'bank'],
            ['VALID-REFERENCE', 'card'],
        ] as [$reference, $channel]) {
            $result = $this->service->register(1, 11, [
                'transaction_reference' => $reference,
                'channel' => $channel,
            ]);

            $this->assertFalse($result['success']);
            $this->assertSame(1, $this->countRows('registrations'));
        }

        $this->connection->exec("UPDATE payment_methods SET is_active = 0 WHERE slug = 'manual'");
        $inactive = $this->service->register(1, 11, [
            'transaction_reference' => 'MANUAL-ACTIVE-CHECK',
            'channel' => 'bank',
        ]);
        $this->assertFalse($inactive['success']);
        $this->assertArrayHasKey('payment_method', $inactive['errors']);
    }

    public function testPaidRegistrationPersistsOnlyServerPriceAndBoundedPaymentMetadata(): void
    {
        $result = $this->service->register(1, 11, [
            'transaction_reference' => '  BANK-REFERENCE-001  ',
            'channel' => 'bank',
            'amount' => '0.01',
            'currency' => 'USD',
            'card_number' => '4111111111111111',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('pending', $result['registration']['registration_status']);
        $this->assertSame('125.5', (string) $result['registration']['amount']);
        $this->assertSame('BDT', $result['registration']['currency']);
        $this->assertSame('pending', $result['payment']['payment_status']);
        $this->assertSame('125.5', (string) $result['payment']['amount']);
        $this->assertSame('BANK-REFERENCE-001', $result['payment']['transaction_reference']);
        $this->assertSame(['channel' => 'bank'], $result['payment']['gateway_response']);
        $this->assertSame(0, $this->countRows('tickets'));
        $this->assertSame(1, count($this->transport->messages));
    }

    public function testVerificationAndRejectionAreAtomicCompareAndSetTransitions(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-VERIFY-001',
            'channel' => 'bank',
        ]);
        $paymentId = (int) $pending['payment']['id'];

        $verified = $this->service->verifyPayment(9, $paymentId, 'Reference verified');

        $this->assertTrue($verified['success']);
        $this->assertSame('paid', $verified['payment']['payment_status']);
        $this->assertSame('confirmed', $verified['payment']['registration_status']);
        $this->assertSame('confirmed', $verified['registration']['registration_status']);
        $this->assertSame('valid', $verified['ticket']['ticket_status']);
        $this->assertSame(1, $this->countRows('tickets'));

        $repeat = $this->service->verifyPayment(9, $paymentId, 'Repeat');
        $this->assertTrue($repeat['success']);
        $this->assertSame($verified['ticket']['id'], $repeat['ticket']['id']);
        $this->assertSame(1, $this->countRows('tickets'));

        $this->connection->exec("UPDATE events SET available_seats = 0 WHERE id = 17");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES (70, 17, 1, 'REG-REJECT', 'pending', 80, 'BDT', CURRENT_TIMESTAMP)");
        $this->connection->exec("INSERT INTO payments (id, registration_id, payment_method_id, transaction_reference, amount, currency, status) VALUES (70, 70, 2, 'BANK-REJECT-001', 80, 'BDT', 'pending')");

        $rejected = $this->service->rejectPayment(9, 70, 'Reference not found');

        $this->assertTrue($rejected['success']);
        $this->assertSame('failed', $rejected['payment']['payment_status']);
        $this->assertSame('cancelled', $rejected['payment']['registration_status']);
        $this->assertSame('cancelled', $rejected['registration']['registration_status']);
        $repeatRejected = $this->service->rejectPayment(9, 70, 'Repeat');
        $this->assertTrue($repeatRejected['success']);
        $this->assertSame('not_attempted', $repeatRejected['delivery_status']);
        $this->assertSame(1, $this->availableSeats(17));
        $this->assertTrue($this->service->register(1, 17, [
            'transaction_reference' => 'BANK-AFTER-RELEASE',
            'channel' => 'bank',
        ])['success']);
    }

    public function testParticipantCancellationIsOwnedBeforeStartAndNotCheckedInAndSettlesRelatedState(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-CANCEL-PENDING',
            'channel' => 'bank',
        ]);
        $registrationId = (int) $pending['registration']['id'];

        $foreign = $this->service->cancel(5, $registrationId, 'Not mine');
        $this->assertFalse($foreign['success']);

        $cancelled = $this->service->cancel(1, $registrationId, '  Schedule conflict  ');
        $this->assertTrue($cancelled['success']);
        $this->assertSame('cancelled', $cancelled['registration']['registration_status']);
        $this->assertSame('failed', $cancelled['payment']['payment_status']);
        $this->assertSame(2, $this->availableSeats(11));
        $repeatCancelled = $this->service->cancel(1, $registrationId, 'Repeat');
        $this->assertTrue($repeatCancelled['success']);
        $this->assertSame('not_attempted', $repeatCancelled['delivery_status']);
        $this->assertSame(2, $this->availableSeats(11));

        $free = $this->service->register(1, 10);
        $freeRegistrationId = (int) $free['registration']['id'];
        $cancelledFree = $this->service->cancel(1, $freeRegistrationId, 'No longer attending');
        $this->assertTrue($cancelledFree['success']);
        $this->assertSame('refunded', $cancelledFree['payment']['payment_status']);
        $this->assertSame('cancelled', $cancelledFree['ticket']['ticket_status']);

        $reactivated = $this->service->register(1, 10);
        $this->assertTrue($reactivated['success']);
        $this->assertSame($freeRegistrationId, $reactivated['registration']['id']);
        $this->assertSame($free['ticket']['id'], $reactivated['ticket']['id']);
        $this->assertSame(1, (int) $this->connection->query("SELECT COUNT(*) FROM tickets WHERE registration_id = {$freeRegistrationId}")->fetchColumn());

        $attended = $this->service->register(1, 18);
        $attendedRegistrationId = (int) $attended['registration']['id'];
        $attendedTicketId = (int) $attended['ticket']['id'];
        $this->connection->exec("INSERT INTO attendance (registration_id, ticket_id, scanned_by, status, scanned_at) VALUES ({$attendedRegistrationId}, {$attendedTicketId}, 2, 'present', CURRENT_TIMESTAMP)");
        $checkedIn = $this->service->cancel(1, $attendedRegistrationId, 'Too late');
        $this->assertFalse($checkedIn['success']);

        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES (80, 15, 1, 'REG-STARTED', 'confirmed', 0, 'BDT', CURRENT_TIMESTAMP)");
        $started = $this->service->cancel(1, 80, 'Too late');
        $this->assertFalse($started['success']);
    }

    public function testArtifactAndDatabaseWritesRollBackWhenCommitFails(): void
    {
        $this->connection->failCommit = true;

        $result = $this->service->register(1, 10);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $this->countRows('registrations'));
        $this->assertSame(0, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('tickets'));
        $this->assertSame([], glob($this->ticketRoot . '/*') ?: []);
        $this->assertSame(0, count($this->transport->messages));
    }

    public function testCancellationRollsBackWhenRelatedPaymentCompareAndSetLoses(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-CAS-LOSS-001',
            'channel' => 'bank',
        ]);
        $registrationId = (int) $pending['registration']['id'];
        $this->connection->exec("CREATE TRIGGER suppress_payment_cancel BEFORE UPDATE OF status ON payments WHEN OLD.status = 'pending' BEGIN SELECT RAISE(IGNORE); END");

        $cancelled = $this->service->cancel(1, $registrationId, 'Schedule conflict');

        $this->assertFalse($cancelled['success']);
        $this->assertSame('pending', (string) $this->connection->query("SELECT status FROM registrations WHERE id = {$registrationId}")->fetchColumn());
        $this->assertSame('pending', (string) $this->connection->query("SELECT status FROM payments WHERE registration_id = {$registrationId}")->fetchColumn());
    }

    public function testParticipantCancellationRollsBackWhenSeatCannotBeRestored(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-CANCEL-SEAT-RESTORE',
            'channel' => 'bank',
        ]);
        $registrationId = (int) $pending['registration']['id'];
        $this->connection->exec("CREATE TRIGGER suppress_cancel_seat_restore BEFORE UPDATE OF available_seats ON events WHEN OLD.id = 11 BEGIN SELECT RAISE(IGNORE); END");

        $cancelled = $this->service->cancel(1, $registrationId, 'Schedule conflict');

        $this->assertFalse($cancelled['success']);
        $this->assertSame('pending', (string) $this->connection->query("SELECT status FROM registrations WHERE id = {$registrationId}")->fetchColumn());
        $this->assertSame('pending', (string) $this->connection->query("SELECT status FROM payments WHERE registration_id = {$registrationId}")->fetchColumn());
        $this->assertSame(1, $this->availableSeats(11));
    }

    public function testPaymentRejectionRollsBackWhenSeatCannotBeRestored(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-REJECT-SEAT-RESTORE',
            'channel' => 'bank',
        ]);
        $paymentId = (int) $pending['payment']['id'];
        $registrationId = (int) $pending['registration']['id'];
        $this->connection->exec("CREATE TRIGGER suppress_reject_seat_restore BEFORE UPDATE OF available_seats ON events WHEN OLD.id = 11 BEGIN SELECT RAISE(IGNORE); END");

        $rejected = $this->service->rejectPayment(9, $paymentId, 'Reference not found');

        $this->assertFalse($rejected['success']);
        $this->assertSame('pending', (string) $this->connection->query("SELECT status FROM registrations WHERE id = {$registrationId}")->fetchColumn());
        $this->assertSame('pending', (string) $this->connection->query("SELECT status FROM payments WHERE id = {$paymentId}")->fetchColumn());
        $this->assertSame(1, $this->availableSeats(11));
        $this->assertSame(0, $this->countRows('tickets'));
    }

    public function testVerificationCasLoserReturnsTheWinnersTruthfulTerminalState(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-VERIFY-WINNER',
            'channel' => 'bank',
        ]);
        $paymentId = (int) $pending['payment']['id'];
        $registrationId = (int) $pending['registration']['id'];
        $digest = str_repeat('a', 64);
        $this->connection->exec("CREATE TRIGGER simulate_verify_winner BEFORE UPDATE OF status ON payments WHEN OLD.id = {$paymentId} AND OLD.status = 'pending' BEGIN UPDATE payments SET status = 'paid', paid_at = CURRENT_TIMESTAMP, reviewed_by = 9, reviewed_at = CURRENT_TIMESTAMP WHERE id = OLD.id; UPDATE registrations SET status = 'confirmed' WHERE id = {$registrationId}; INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at) VALUES ({$registrationId}, 'OEMS-WINNER-VERIFY', '{$digest}', 'valid', CURRENT_TIMESTAMP); SELECT RAISE(IGNORE); END");

        $result = $this->service->verifyPayment(9, $paymentId, 'Concurrent review');

        $this->assertTrue($result['success']);
        $this->assertSame('paid', $result['payment']['payment_status']);
        $this->assertSame('confirmed', $result['registration']['registration_status']);
        $this->assertSame('valid', $result['ticket']['ticket_status']);
        $this->assertSame('not_attempted', $result['delivery_status']);
        $this->assertSame(1, $this->countRows('tickets'));
    }

    public function testRejectionCasLoserReturnsTheWinnersTruthfulTerminalState(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-REJECT-WINNER',
            'channel' => 'bank',
        ]);
        $paymentId = (int) $pending['payment']['id'];
        $registrationId = (int) $pending['registration']['id'];
        $this->connection->exec("CREATE TRIGGER simulate_reject_winner BEFORE UPDATE OF status ON payments WHEN OLD.id = {$paymentId} AND OLD.status = 'pending' BEGIN UPDATE payments SET status = 'failed', reviewed_by = 9, reviewed_at = CURRENT_TIMESTAMP WHERE id = OLD.id; UPDATE registrations SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP, cancellation_reason = 'Payment rejected' WHERE id = {$registrationId}; UPDATE events SET available_seats = CASE WHEN available_seats < capacity THEN available_seats + 1 ELSE capacity END WHERE id = 11; SELECT RAISE(IGNORE); END");

        $result = $this->service->rejectPayment(9, $paymentId, 'Concurrent review');

        $this->assertTrue($result['success']);
        $this->assertSame('failed', $result['payment']['payment_status']);
        $this->assertSame('cancelled', $result['registration']['registration_status']);
        $this->assertSame('not_attempted', $result['delivery_status']);
        $this->assertSame(2, $this->availableSeats(11));
    }

    public function testCancellationCasLoserReturnsWinnerOnlyWhenEveryRelatedStateIsTerminal(): void
    {
        $pending = $this->service->register(1, 11, [
            'transaction_reference' => 'BANK-CANCEL-WINNER',
            'channel' => 'bank',
        ]);
        $registrationId = (int) $pending['registration']['id'];
        $this->connection->exec("CREATE TRIGGER simulate_cancel_winner BEFORE UPDATE OF status ON payments WHEN OLD.status = 'pending' BEGIN UPDATE payments SET status = 'failed' WHERE id = OLD.id; SELECT RAISE(IGNORE); END");

        $result = $this->service->cancel(1, $registrationId, 'Schedule conflict');

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $result['registration']['registration_status']);
        $this->assertSame('failed', $result['payment']['payment_status']);
        $this->assertSame(2, $this->availableSeats(11));
    }

    public function testMailFailureAfterCommitIsSurfacedWithoutRollingBackDomainState(): void
    {
        $this->transport->failure = new RuntimeException('SMTP password=secret transaction=BANK-SECRET');

        $result = $this->service->register(1, 10);

        $this->assertTrue($result['success']);
        $this->assertSame('failed', $result['delivery_status']);
        $this->assertSame(2, $this->countRows('registrations'));
        $this->assertSame(1, $this->countRows('payments'));
        $this->assertSame(1, $this->countRows('tickets'));
    }

    public function testCaughtTransactionFailuresLogOnlySafeIdentifiersAndExceptionClass(): void
    {
        $this->connection->exec("CREATE TRIGGER reject_ticket BEFORE INSERT ON tickets BEGIN SELECT RAISE(ABORT, 'BANK-SECRET /private/ticket token=raw-token'); END");

        $result = $this->service->register(1, 10, [
            'transaction_reference' => 'BANK-SECRET',
        ]);
        $contents = file_get_contents($this->logPath) ?: '';

        $this->assertFalse($result['success']);
        $this->assertTrue(str_contains($contents, 'registration'));
        $this->assertTrue(str_contains($contents, 'exception_class'));
        $this->assertFalse(str_contains($contents, 'BANK-SECRET'));
        $this->assertFalse(str_contains($contents, '/private/ticket'));
        $this->assertFalse(str_contains($contents, 'raw-token'));
        $this->assertSame([], glob($this->ticketRoot . '/*') ?: []);
    }

    private function service(PDO $connection): RegistrationService
    {
        $registrations = new RegistrationRepository($connection);
        $payments = new PaymentRepository($connection);
        $tickets = new TicketRepository($connection);
        $artifacts = new TicketArtifactService($this->ticketRoot, 'uploads/tickets');
        $ticketService = new TicketService($connection, $tickets, $artifacts);
        $mailer = new TransactionMailer(
            $this->transport,
            $this->mailLogs,
            new Config(['url' => 'http://localhost:8000']),
            new Logger($this->logPath),
        );

        return new RegistrationService(
            $connection,
            $this->users,
            $registrations,
            $payments,
            $ticketService,
            $mailer,
            new Logger($this->logPath),
        );
    }

    private function user(
        int $id,
        int $roleId,
        string $name,
        string $email,
        string $status = 'active',
        ?string $verifiedAt = '2026-08-01 00:00:00',
    ): array {
        return [
            'id' => $id,
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'status' => $status,
            'email_verified_at' => $verifiedAt,
        ];
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE venues (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, category_id INTEGER NOT NULL, venue_id INTEGER NULL, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, registration_deadline TEXT NOT NULL, capacity INTEGER NOT NULL, available_seats INTEGER NOT NULL, ticket_price NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, coupon_id INTEGER NULL, registration_number TEXT NOT NULL UNIQUE, status TEXT NOT NULL, amount NUMERIC NOT NULL, currency TEXT NOT NULL, registered_at TEXT NOT NULL, cancelled_at TEXT NULL, cancellation_reason TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (event_id, user_id))');
        $this->connection->exec('CREATE TABLE payment_methods (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, configuration TEXT NULL, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL, payment_method_id INTEGER NULL, transaction_reference TEXT NULL UNIQUE, amount NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, gateway_response TEXT NULL, paid_at TEXT NULL, refunded_at TEXT NULL, reviewed_by INTEGER NULL, reviewed_at TEXT NULL, review_note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL UNIQUE, ticket_number TEXT NOT NULL UNIQUE, qr_payload_hash TEXT NOT NULL UNIQUE, qr_path TEXT NULL, pdf_path TEXT NULL, status TEXT NOT NULL, issued_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL UNIQUE, ticket_id INTEGER NOT NULL UNIQUE, scanned_by INTEGER NOT NULL, status TEXT NOT NULL, scanned_at TEXT NOT NULL, scanner_ip TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO users (id, name, email) VALUES (1, 'Participant One', 'participant@example.test'), (2, 'Organizer One', 'organizer@example.test'), (3, 'Other Participant', 'other@example.test'), (5, 'Other Active Participant', 'other-active@example.test'), (9, 'Administrator', 'admin@example.test')");
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status) VALUES (1, 2, 'Approved Org', 'approved')");
        $this->connection->exec("INSERT INTO categories (id, name, slug, is_active) VALUES (1, 'Active', 'active', 1), (2, 'Inactive', 'inactive', 0)");
        $this->connection->exec("INSERT INTO venues (id, name) VALUES (1, 'Main Hall')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, deleted_at) VALUES
            (10, 1, 1, 1, 'Free Event', 'free-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 2, 0, 'BDT', 'published', NULL),
            (11, 1, 1, 1, 'Paid Event', 'paid-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 2, 125.50, 'BDT', 'published', NULL),
            (12, 1, 1, 1, 'Draft Event', 'draft-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 2, 0, 'BDT', 'draft', NULL),
            (13, 1, 2, 1, 'Inactive Category', 'inactive-category', datetime('now', '+10 days'), datetime('now', '+9 days'), 2, 2, 0, 'BDT', 'published', NULL),
            (14, 1, 1, 1, 'Closed Event', 'closed-event', datetime('now', '+10 days'), datetime('now', '-1 minute'), 2, 2, 0, 'BDT', 'published', NULL),
            (15, 1, 1, 1, 'Started Event', 'started-event', datetime('now', '-1 minute'), datetime('now', '-1 day'), 2, 2, 0, 'BDT', 'published', NULL),
            (16, 1, 1, 1, 'Full Event', 'full-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 0, 'BDT', 'published', NULL),
            (17, 1, 1, 1, 'Reject Event', 'reject-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 1, 80, 'BDT', 'published', NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES (50, 16, 3, 'REG-FULL', 'pending', 0, 'BDT', CURRENT_TIMESTAMP)");
        $this->connection->exec("INSERT INTO payment_methods (id, name, slug, configuration, is_active) VALUES (1, 'Free registration', 'free', '{}', 1), (2, 'Manual payment', 'manual', '{}', 1)");
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, deleted_at) VALUES (18, 1, 1, 1, 'Attendance Event', 'attendance-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 1, 0, 'BDT', 'published', NULL)");
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function availableSeats(int $eventId): int
    {
        return (int) $this->connection->query("SELECT available_seats FROM events WHERE id = {$eventId}")->fetchColumn();
    }
}
