<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\PaymentRepository;
use OEMS\App\Repositories\CouponRepository;
use OEMS\App\Repositories\RegistrationRepository;
use OEMS\App\Repositories\TicketRepository;
use OEMS\App\Repositories\WaitlistRepository;
use OEMS\App\Services\RegistrationService;
use OEMS\App\Services\CouponService;
use OEMS\App\Services\NotificationService;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\App\Services\TransactionMailer;
use OEMS\Core\Config;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\FakePaymentRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeTicketRepository;
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

    public function testParticipantCancellationEligibilityUsesLifecycleScheduleAndAttendanceState(): void
    {
        $eligible = [
            'registration_status' => 'confirmed',
            'event_start_date' => '2099-08-22 10:00:00',
        ];

        $this->assertTrue($this->service->canCancel($eligible, null));
        $this->assertFalse($this->service->canCancel(array_merge($eligible, ['registration_status' => 'cancelled']), null));
        $this->assertFalse($this->service->canCancel(array_merge($eligible, ['event_start_date' => '2000-08-22 10:00:00']), null));
        $this->assertFalse($this->service->canCancel($eligible, ['attendance_id' => 91]));
    }

    public function testCancellationStateIsOwnershipScopedAndExplainsWhyCancellationIsUnavailable(): void
    {
        $allowedRegistration = $this->service->register(1, 10);
        $registrationId = (int) $allowedRegistration['registration']['id'];
        $ticketId = (int) $allowedRegistration['ticket']['id'];

        $this->assertSame([
            'allowed' => true,
            'code' => 'allowed',
            'reason' => null,
        ], $this->service->cancellationState(1, $registrationId));
        $this->assertSame('not_found', $this->service->cancellationState(5, $registrationId)['code']);

        $this->connection->exec("INSERT INTO attendance (registration_id, ticket_id, scanned_by, status, scanned_at) VALUES ({$registrationId}, {$ticketId}, 2, 'present', CURRENT_TIMESTAMP)");
        $checkedIn = $this->service->cancellationState(1, $registrationId);
        $this->assertFalse($checkedIn['allowed']);
        $this->assertSame('checked_in', $checkedIn['code']);
        $this->assertSame('Cancellation is unavailable after event check-in.', $checkedIn['reason']);

        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES (80, 15, 1, 'REG-STARTED-STATE', 'confirmed', 0, 'BDT', CURRENT_TIMESTAMP)");
        $started = $this->service->cancellationState(1, 80);
        $this->assertFalse($started['allowed']);
        $this->assertSame('started', $started['code']);
        $this->assertSame('Cancellation is unavailable because this event has already started.', $started['reason']);

        $this->connection->exec("UPDATE registrations SET status = 'cancelled' WHERE id = {$registrationId}");
        $terminal = $this->service->cancellationState(1, $registrationId);
        $this->assertFalse($terminal['allowed']);
        $this->assertSame('terminal', $terminal['code']);
        $this->assertSame('This registration is already cancelled.', $terminal['reason']);
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

        $this->assertTrue($result['success'], json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertSame('confirmed', $result['registration']['registration_status']);
        $this->assertSame('0', (string) $result['registration']['amount']);
        $this->assertSame('BDT', $result['registration']['currency']);
        $this->assertSame('paid', $result['payment']['payment_status']);
        $this->assertSame('0.00', (string) $result['payment']['amount']);
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

    public function testCouponIsRevalidatedConsumedAndPersistedAtomicallyWithExactPaymentAmount(): void
    {
        $result = $this->service->register(1, 11, [
            'coupon_code' => ' save-2550 ',
            'transaction_reference' => 'COUPON-PAY-001',
            'channel' => 'bank',
        ]);

        $this->assertTrue($result['success'], 'Initial discounted registration failed: ' . json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertSame('100', (string) $result['registration']['amount']);
        $this->assertSame(1, (int) $result['registration']['coupon_id']);
        $this->assertSame('100.00', (string) $result['payment']['amount']);
        $this->assertSame(1, $this->countRows('coupon_usage'));
        $this->assertSame(1, (int) $this->connection->query('SELECT used_count FROM coupons WHERE id = 1')->fetchColumn());

        $repeat = $this->service->register(1, 11, ['coupon_code' => 'SAVE-2550']);
        $this->assertTrue($repeat['success'], 'Idempotent discounted registration failed: ' . json_encode($repeat, JSON_THROW_ON_ERROR));
        $this->assertSame((int) $result['registration']['id'], (int) $repeat['registration']['id']);
        $this->assertSame(1, $this->countRows('coupon_usage'));
    }

    public function testFullDiscountCouponConfirmsAndIssuesWithoutManualPaymentFields(): void
    {
        $result = $this->service->register(1, 11, ['coupon_code' => 'COMP-ENTRY']);

        $this->assertTrue($result['success'], json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertSame('0', (string) $result['registration']['amount']);
        $this->assertSame('confirmed', $result['registration']['registration_status']);
        $this->assertSame('paid', $result['payment']['status']);
        $this->assertSame('valid', $result['ticket']['status']);
        $this->assertSame(1, $this->countRows('coupon_usage'));
        $this->assertSame(1, (int) $this->connection->query('SELECT used_count FROM coupons WHERE id = 2')->fetchColumn());
    }

    public function testFailureAfterCouponConsumptionRollsBackUsageRegistrationAndSeat(): void
    {
        $this->connection->exec("CREATE TRIGGER fail_coupon_payment BEFORE INSERT ON payments BEGIN SELECT RAISE(FAIL, 'payment failed'); END");

        $result = $this->service->register(1, 11, [
            'coupon_code' => 'SAVE-2550',
            'transaction_reference' => 'ROLLBACK-COUPON-001',
            'channel' => 'bank',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $this->countRows('registrations'));
        $this->assertSame(0, $this->countRows('coupon_usage'));
        $this->assertSame(0, (int) $this->connection->query('SELECT used_count FROM coupons WHERE id = 1')->fetchColumn());
        $this->assertSame(2, $this->availableSeats(11));
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
        $this->assertSame('125.50', (string) $result['payment']['amount']);
        $this->assertSame('BANK-REFERENCE-001', $result['payment']['transaction_reference']);
        $this->assertSame('bank', $result['payment']['payment_channel']);
        $this->assertFalse(array_key_exists('gateway_response', $result['payment']));
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

    public function testSettlementCannotActOnSoftDeletedParticipantOrEventPayments(): void
    {
        $this->connection->exec("UPDATE users SET deleted_at = '2026-08-08 00:00:00' WHERE id = 3");
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, deleted_at) VALUES (19, 1, 1, 1, 'Deleted Settlement Event', 'deleted-settlement-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 90, 'BDT', 'published', '2026-08-08 00:00:00')");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES (80, 11, 3, 'REG-DELETED-PARTICIPANT', 'pending', 125.50, 'BDT', CURRENT_TIMESTAMP), (81, 19, 1, 'REG-DELETED-EVENT', 'pending', 90, 'BDT', CURRENT_TIMESTAMP)");
        $this->connection->exec("INSERT INTO payments (id, registration_id, payment_method_id, transaction_reference, amount, currency, status) VALUES (80, 80, 2, 'BANK-DELETED-PARTICIPANT', 125.50, 'BDT', 'pending'), (81, 81, 2, 'BANK-DELETED-EVENT', 90, 'BDT', 'pending')");

        $verified = $this->service->verifyPayment(9, 80, 'Must not verify');
        $rejected = $this->service->rejectPayment(9, 81, 'Must not reject');

        $this->assertFalse($verified['success']);
        $this->assertFalse($rejected['success']);
        $this->assertSame(['Payment not found.'], $verified['errors']['payment']);
        $this->assertSame(['Payment not found.'], $rejected['errors']['payment']);
        $this->assertSame('pending', $this->connection->query('SELECT status FROM payments WHERE id = 80')->fetchColumn());
        $this->assertSame('pending', $this->connection->query('SELECT status FROM payments WHERE id = 81')->fetchColumn());
        $this->assertSame('pending', $this->connection->query('SELECT status FROM registrations WHERE id = 80')->fetchColumn());
        $this->assertSame('pending', $this->connection->query('SELECT status FROM registrations WHERE id = 81')->fetchColumn());
        $this->assertSame(0, $this->connection->query('SELECT COUNT(*) FROM tickets WHERE registration_id IN (80, 81)')->fetchColumn());
        $this->assertSame(0, $this->availableSeats(19));
    }

    public function testVerificationAcquiresLocksInEventRegistrationPaymentTicketOrder(): void
    {
        $trace = new \ArrayObject();
        $registrations = new FakeRegistrationRepository();
        $registrations->lockTrace = $trace;
        $registrations->eligibleEvents[11] = ['id' => 11];
        $registrations->registrations[101] = [
            'id' => 101,
            'event_id' => 11,
            'user_id' => 1,
            'status' => 'confirmed',
            'registration_number' => 'REG-LOCK-ORDER',
        ];
        $payments = new FakePaymentRepository();
        $payments->lockTrace = $trace;
        $payments->payments[201] = [
            'id' => 201,
            'registration_id' => 101,
            'participant_id' => 1,
            'participant_name' => 'Participant One',
            'participant_email' => 'participant@example.test',
            'event_id' => 11,
            'status' => 'paid',
        ];
        $tickets = new FakeTicketRepository();
        $tickets->lockTrace = $trace;
        $tickets->tickets[301] = [
            'id' => 301,
            'registration_id' => 101,
            'participant_id' => 1,
            'status' => 'valid',
        ];
        $service = new RegistrationService(
            $this->connection,
            $this->users,
            $registrations,
            $payments,
            new TicketService(
                $this->connection,
                $tickets,
                new TicketArtifactService($this->ticketRoot, 'uploads/tickets'),
            ),
            new TransactionMailer(
                $this->transport,
                $this->mailLogs,
                new Config(['url' => 'http://localhost:8000']),
                new Logger($this->logPath),
            ),
            new Logger($this->logPath),
        );

        $result = $service->verifyPayment(9, 201, 'Repeat verification');

        $this->assertTrue($result['success']);
        $this->assertSame(
            ['event', 'registration', 'payment', 'ticket'],
            $trace->getArrayCopy(),
        );
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
        $this->assertSame('paid', $cancelledFree['payment']['payment_status']);
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

    public function testNotificationFailuresDoNotRollBackCommittedParticipantTransactions(): void
    {
        $notifications = new FakeNotificationRepository();
        $notifications->throwOnCreate = true;
        $service = $this->service($this->connection, new NotificationService($notifications, new Logger($this->logPath)));

        $result = $service->register(1, 10);

        $this->assertTrue($result['success']);
        $this->assertSame('confirmed', $result['registration']['registration_status']);
        $this->assertSame(2, $this->countRows('registrations'));
        $this->assertSame(1, $this->countRows('tickets'));
    }

    public function testPaidRegistrationNotificationFailuresDoNotRollBackCommittedParticipantTransactions(): void
    {
        $notifications = new FakeNotificationRepository();
        $notifications->throwOnCreate = true;
        $service = $this->service($this->connection, new NotificationService($notifications, new Logger($this->logPath)));

        $result = $service->register(1, 11, ['transaction_reference' => 'NOTICE-FAIL-PAID', 'channel' => 'bank']);

        $this->assertTrue($result['success']);
        $this->assertSame('pending', $result['registration']['registration_status']);
        $this->assertSame(2, $this->countRows('registrations'));
        $this->assertSame(1, $this->countRows('payments'));
    }

    public function testWaitlistPromotionCreatesAPaidClaimThenAcceptsParticipantPayment(): void
    {
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled, deleted_at) VALUES (19, 1, 1, 1, 'Paid Waitlist Event', 'paid-waitlist-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 1, 90, 'BDT', 'published', 1, NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at, waitlisted_at) VALUES (70, 19, 5, 'OEMS-WAIT-PAID', 'waitlisted', 90, 'BDT', datetime('now', '-1 hour'), datetime('now', '-1 hour'))");

        $promoted = $this->service->promoteWaitlist(19);

        $this->assertTrue($promoted['success'], json_encode($promoted) . (is_file($this->logPath) ? file_get_contents($this->logPath) : ''));
        $this->assertTrue($promoted['promoted']);
        $this->assertSame('pending', $promoted['registration']['registration_status']);
        $this->assertSame(0, $this->availableSeats(19));
        $this->assertSame(0, $this->countRows('payments'));
        $this->assertNotNull($this->connection->query('SELECT waitlist_claim_expires_at FROM registrations WHERE id = 70')->fetchColumn());

        $paid = $this->service->submitPromotedPayment(5, 70, [
            'transaction_reference' => 'WAITLIST-PAY-001',
            'channel' => 'bank_transfer',
        ]);
        $this->assertTrue($paid['success']);
        $this->assertSame('pending', $paid['payment']['payment_status']);
        $this->assertSame(1, $this->countRows('payments'));
        $this->assertSame(null, $this->connection->query('SELECT waitlist_claim_expires_at FROM registrations WHERE id = 70')->fetchColumn());

        $repeat = $this->service->submitPromotedPayment(5, 70, [
            'transaction_reference' => 'WAITLIST-PAY-001',
            'channel' => 'bank_transfer',
        ]);
        $this->assertTrue($repeat['success']);
        $this->assertSame(1, $this->countRows('payments'));
    }

    public function testPaidWaitlistPromotionRequiresManualPaymentAndCapsClaimAtRegistrationDeadline(): void
    {
        $now = new DateTimeImmutable('2026-08-10 10:00:00');
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled, deleted_at) VALUES (22, 1, 1, 1, 'Bounded Waitlist Event', 'bounded-waitlist-event', '2026-08-12 10:00:00', '2026-08-10 12:00:00', 1, 1, 90, 'BDT', 'published', 1, NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at, waitlisted_at) VALUES (73, 22, 5, 'OEMS-WAIT-BOUNDED', 'waitlisted', 70, 'BDT', '2026-08-10 09:00:00', '2026-08-10 09:00:00')");
        $this->connection->exec("UPDATE payment_methods SET is_active = 0 WHERE slug = 'manual'");

        $unavailable = $this->service->promoteWaitlist(22, $now);
        $this->assertFalse($unavailable['success']);
        $this->assertSame('waitlisted', $this->connection->query('SELECT status FROM registrations WHERE id = 73')->fetchColumn());
        $this->assertSame(1, $this->availableSeats(22));

        $this->connection->exec("UPDATE payment_methods SET is_active = 1 WHERE slug = 'manual'");
        $promoted = $this->service->promoteWaitlist(22, $now);
        $this->assertTrue($promoted['success']);
        $this->assertSame('2026-08-10 12:00:00', $this->connection->query('SELECT waitlist_claim_expires_at FROM registrations WHERE id = 73')->fetchColumn());
        $this->assertSame('90', (string) $this->connection->query('SELECT amount FROM registrations WHERE id = 73')->fetchColumn());
    }

    public function testFreeWaitlistPromotionConfirmsAndIssuesTicketAtomically(): void
    {
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled, deleted_at) VALUES (20, 1, 1, 1, 'Free Waitlist Event', 'free-waitlist-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 1, 0, 'BDT', 'published', 1, NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at, waitlisted_at) VALUES (71, 20, 5, 'OEMS-WAIT-FREE', 'waitlisted', 0, 'BDT', datetime('now', '-1 hour'), datetime('now', '-1 hour'))");

        $result = $this->service->promoteWaitlist(20);

        $this->assertTrue($result['success'], json_encode($result) . (is_file($this->logPath) ? file_get_contents($this->logPath) : ''));
        $this->assertTrue($result['promoted']);
        $this->assertSame('confirmed', $result['registration']['registration_status']);
        $this->assertSame('paid', $result['payment']['payment_status']);
        $this->assertSame('valid', $result['ticket']['ticket_status']);
        $this->assertSame(0, $this->availableSeats(20));
        $this->assertSame(null, $this->connection->query('SELECT waitlist_claim_expires_at FROM registrations WHERE id = 71')->fetchColumn());
    }

    public function testWaitlistPromotionRollsBackWhenRequiredPaymentMethodIsUnavailable(): void
    {
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled, deleted_at) VALUES (21, 1, 1, 1, 'Unavailable Method Event', 'unavailable-method-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 1, 0, 'BDT', 'published', 1, NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at, waitlisted_at) VALUES (72, 21, 5, 'OEMS-WAIT-METHOD', 'waitlisted', 0, 'BDT', datetime('now', '-1 hour'), datetime('now', '-1 hour'))");
        $this->connection->exec("UPDATE payment_methods SET is_active = 0 WHERE slug = 'free'");

        $result = $this->service->promoteWaitlist(21);

        $this->assertFalse($result['success']);
        $this->assertSame('waitlisted', $this->connection->query('SELECT status FROM registrations WHERE id = 72')->fetchColumn());
        $this->assertSame(1, $this->availableSeats(21));
        $this->assertSame(0, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('tickets'));
    }

    public function testParticipantCancellationAutomaticallyOffersReleasedSeatToOldestWaitlistEntry(): void
    {
        $digest = str_repeat('b', 64);
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled, deleted_at) VALUES (24, 1, 1, 1, 'Automatic Promotion', 'automatic-promotion', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 0, 'BDT', 'published', 1, NULL)");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at, waitlisted_at) VALUES
            (80, 24, 1, 'OEMS-AUTO-OWNER', 'confirmed', 0, 'BDT', datetime('now', '-2 hours'), NULL),
            (81, 24, 5, 'OEMS-AUTO-WAIT', 'waitlisted', 0, 'BDT', datetime('now', '-1 hour'), datetime('now', '-1 hour'))");
        $this->connection->exec("INSERT INTO payments (registration_id, payment_method_id, transaction_reference, amount, currency, status, paid_at) VALUES (80, 1, 'FREE-AUTO-OWNER', 0, 'BDT', 'paid', CURRENT_TIMESTAMP)");
        $this->connection->exec("INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at) VALUES (80, 'OEMS-AUTO-TICKET', '{$digest}', 'valid', CURRENT_TIMESTAMP)");

        $result = $this->service->cancel(1, 80, 'Plans changed');

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $this->connection->query('SELECT status FROM registrations WHERE id = 80')->fetchColumn());
        $this->assertSame('confirmed', $this->connection->query('SELECT status FROM registrations WHERE id = 81')->fetchColumn());
        $this->assertSame(0, $this->availableSeats(24));
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM tickets WHERE registration_id = 81')->fetchColumn());
    }

    public function testDispatchesRegistrationPaymentTicketAndCancellationUpdatesAfterCommit(): void
    {
        $repository = new FakeNotificationRepository();
        $service = $this->service($this->connection, new NotificationService($repository, new Logger($this->logPath)));

        $free = $service->register(1, 10);
        $paid = $service->register(1, 11, ['transaction_reference' => 'NOTICE-PAID-001', 'channel' => 'bank']);
        $verified = $service->verifyPayment(9, (int) $paid['payment']['id']);
        $cancelled = $service->cancel(1, (int) $free['registration']['id'], 'Schedule conflict');

        $this->assertTrue($free['success']);
        $this->assertTrue($paid['success']);
        $this->assertTrue($verified['success']);
        $this->assertTrue($cancelled['success']);
        $this->assertSame(
            ['registration_confirmed', 'ticket_issued', 'registration_pending', 'payment_pending', 'payment_verified', 'ticket_issued', 'registration_cancelled'],
            array_column($repository->notifications, 'type'),
        );
        $notifications = array_values($repository->notifications);
        $this->assertSame('/participant/registrations/' . (int) $paid['registration']['id'], $notifications[2]['action_url']);
        $this->assertSame(['registration_id' => (int) $paid['registration']['id']], $notifications[2]['data']);
        $this->assertSame('/participant/tickets/' . (int) $verified['ticket']['id'], $notifications[5]['action_url']);
    }

    private function service(PDO $connection, ?NotificationService $notifications = null): RegistrationService
    {
        $registrations = new RegistrationRepository($connection);
        $payments = new PaymentRepository($connection);
        $tickets = new TicketRepository($connection);
        $waitlists = new WaitlistRepository($connection);
        $artifacts = new TicketArtifactService($this->ticketRoot, 'uploads/tickets');
        $ticketService = new TicketService($connection, $tickets, $artifacts);
        $mailer = new TransactionMailer(
            $this->transport,
            $this->mailLogs,
            new Config(['url' => 'http://localhost:8000']),
            new Logger($this->logPath),
        );
        $couponService = new CouponService(new CouponRepository($connection), new Logger($this->logPath));

        return new RegistrationService(
            $connection,
            $this->users,
            $registrations,
            $payments,
            $ticketService,
            $mailer,
            new Logger($this->logPath),
            $notifications,
            $couponService,
            $waitlists,
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
        $this->connection->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'active', email_verified_at TEXT NULL DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT NULL)");
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE venues (id INTEGER PRIMARY KEY, name TEXT NOT NULL, address_line TEXT NULL, city TEXT NULL, country TEXT NULL, postal_code TEXT NULL, latitude NUMERIC NULL, longitude NUMERIC NULL, map_url TEXT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, category_id INTEGER NOT NULL, venue_id INTEGER NULL, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, registration_deadline TEXT NOT NULL, capacity INTEGER NOT NULL, available_seats INTEGER NOT NULL, ticket_price NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, waitlist_enabled INTEGER NOT NULL DEFAULT 1, location_visibility TEXT NOT NULL DEFAULT "public", arrival_notes TEXT NULL, deleted_at TEXT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, coupon_id INTEGER NULL, registration_number TEXT NOT NULL UNIQUE, status TEXT NOT NULL, amount NUMERIC NOT NULL, currency TEXT NOT NULL, registered_at TEXT NOT NULL, waitlisted_at TEXT NULL, promoted_at TEXT NULL, waitlist_claim_expires_at TEXT NULL, cancelled_at TEXT NULL, cancellation_reason TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (event_id, user_id))');
        $this->connection->exec('CREATE TABLE coupons (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NULL, organizer_id INTEGER NOT NULL, code TEXT NOT NULL UNIQUE, discount_type TEXT NOT NULL, discount_value NUMERIC NOT NULL, usage_limit INTEGER NULL, used_count INTEGER NOT NULL DEFAULT 0, starts_at TEXT NULL, expires_at TEXT NULL, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE coupon_usage (id INTEGER PRIMARY KEY AUTOINCREMENT, coupon_id INTEGER NOT NULL, user_id INTEGER NOT NULL, registration_id INTEGER NOT NULL UNIQUE, discount_amount NUMERIC NOT NULL, used_at TEXT NOT NULL, UNIQUE(coupon_id, user_id))');
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
        $this->connection->exec("INSERT INTO coupons (id, event_id, organizer_id, code, discount_type, discount_value, usage_limit, starts_at, expires_at, is_active) VALUES
            (1, 11, 1, 'SAVE-2550', 'fixed', 25.50, 10, datetime('now', '-1 day'), datetime('now', '+5 days'), 1),
            (2, 11, 1, 'COMP-ENTRY', 'fixed', 125.50, 10, datetime('now', '-1 day'), datetime('now', '+5 days'), 1)");
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
