<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\PaymentRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use PDOStatement;

final class PaymentRepositoryRecordingPdo extends PDO
{
    public array $preparedQueries = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedQueries[] = $query;

        return parent::prepare($query, $options);
    }
}

final class ExactDecimalPaymentSummaryStatement extends PDOStatement
{
    public function __construct(private readonly array $summary)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        return $this->summary;
    }
}

final class ExactDecimalPaymentSummaryPdo extends PDO
{
    public function __construct(private readonly array $summary)
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ExactDecimalPaymentSummaryStatement($this->summary);
    }
}

final class PaymentRepositoryTest extends TestCase
{
    private PaymentRepositoryRecordingPdo $connection;

    private PaymentRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PaymentRepositoryRecordingPdo('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->repository = new PaymentRepository($this->connection);
    }

    public function testCreatePersistsBoundedPaymentDataAndReferencesAreGloballyUnique(): void
    {
        $paymentId = $this->repository->createForRegistration(102, [
            'payment_method_id' => 1,
            'transaction_reference' => 'MANUAL-UNIQUE',
            'amount' => '450.00',
            'currency' => 'BDT',
            'status' => 'pending',
            'gateway_response' => ['channel' => 'bank'],
            'paid_at' => null,
        ]);

        $payment = $this->repository->findForAdmin($paymentId);
        $this->assertNotNull($payment);
        $this->assertSame('MANUAL-UNIQUE', $payment['transaction_reference']);
        $this->assertSame('bank', $payment['payment_channel']);
        $this->assertFalse(array_key_exists('gateway_response', $payment));

        try {
            $this->repository->createForRegistration(103, [
                'payment_method_id' => 1,
                'transaction_reference' => 'MANUAL-UNIQUE',
                'amount' => '450.00',
                'currency' => 'BDT',
                'status' => 'pending',
                'gateway_response' => null,
                'paid_at' => null,
            ]);
            $this->assertTrue(false, 'A duplicate transaction reference must fail.');
        } catch (PDOException) {
            $this->assertTrue(true);
        }
    }

    public function testRegistrationLookupReturnsTheLatestAttemptDeterministically(): void
    {
        $payment = $this->repository->findForRegistration(101);
        $current = $this->repository->findForRegistrationCurrent(101);

        $this->assertNotNull($payment);
        $this->assertSame(3, $payment['id']);
        $this->assertSame('Manual Bank', $payment['payment_method_name']);
        $this->assertNotNull($current);
        $this->assertSame($payment, $current);
        $this->assertSame('Participant One', $current['participant_name']);
        $this->assertSame('Transaction Event', $current['event_title']);
        $this->assertSame('failed', $current['payment_status']);
    }

    public function testAdminQueueFiltersSearchesPaginatesAndOrdersWithoutHydratingSecrets(): void
    {
        $queue = $this->repository->forAdmin(['status' => 'all'], 2, 0);

        $this->assertSame([1, 4], array_column($queue, 'id'));
        $this->assertSame([3, 2], array_column(
            $this->repository->forAdmin(['status' => 'all'], 2, 2),
            'id',
        ));
        $this->assertSame([2], array_column(
            $this->repository->forAdmin(['status' => 'paid'], 25, 0),
            'id',
        ));
        $this->assertSame([1, 4], array_column(
            $this->repository->forAdmin(['status' => 'not-allowed'], 25, 0),
            'id',
        ));
        $this->assertSame([2], array_column(
            $this->repository->forAdmin(['status' => 'all', 'search' => 'PARTICIPANT TWO'], 25, 0),
            'id',
        ));
        $this->assertSame([2], array_column(
            $this->repository->forAdmin(['status' => 'paid', 'search' => 'transaction event'], 25, 0),
            'id',
        ));
        $this->assertSame([2], array_column(
            $this->repository->forAdmin(['status' => 'all', 'search' => 'REF-PAID'], 25, 0),
            'id',
        ));
        $this->assertSame(4, $this->repository->countForAdmin(['status' => 'all']));
        $this->assertSame(2, $this->repository->countForAdmin(['status' => 'pending']));
        $this->assertSame(0, $this->repository->countForAdmin([
            'status' => 'all',
            'search' => str_repeat('x', 121),
        ]));

        $searchQueries = array_values(array_filter(
            $this->connection->preparedQueries,
            static fn (string $query): bool => str_contains($query, ':admin_search_participant_name'),
        ));
        $lastQueueQuery = $searchQueries[array_key_last($searchQueries)];
        $this->assertTrue(str_contains($lastQueueQuery, ':admin_search_participant_name'));
        $this->assertTrue(str_contains($lastQueueQuery, ':admin_search_participant_email'));
        $this->assertTrue(str_contains($lastQueueQuery, ':admin_search_event'));
        $this->assertTrue(str_contains($lastQueueQuery, ':admin_search_reference'));
        $this->assertSame(4, preg_match_all('/:admin_search_[a-z_]+/', $lastQueueQuery, $matches));
        $this->assertSame(4, count(array_unique($matches[0])));

        foreach ($queue as $payment) {
            $this->assertFalse(array_key_exists('gateway_response', $payment));
        }
    }

    public function testAdminDetailConnectsOnlySafeSettlementAndFulfillmentContext(): void
    {
        $detail = $this->repository->findForAdmin(1);
        $currentDetail = $this->repository->findForAdminCurrent(1);
        $this->assertNotNull($detail);
        $this->assertSame('Participant One', $detail['participant_name']);
        $this->assertSame('participant@example.test', $detail['participant_email']);
        $this->assertSame('REG-101', $detail['registration_number']);
        $this->assertSame('pending', $detail['registration_status']);
        $this->assertSame('Transaction Event', $detail['event_title']);
        $this->assertSame('Organizer Company', $detail['organizer_name']);
        $this->assertSame('Manual Bank', $detail['payment_method_name']);
        $this->assertSame('bank', $detail['payment_channel']);
        $this->assertSame('none', $detail['ticket_status']);
        $this->assertArrayHasKey('reviewed_by', $detail);
        $this->assertArrayHasKey('reviewed_at', $detail);
        $this->assertArrayHasKey('review_note', $detail);
        $this->assertFalse(array_key_exists('gateway_response', $detail));
        $this->assertFalse(array_key_exists('qr_payload_hash', $detail));
        $this->assertNotNull($currentDetail);
        $this->assertSame($detail, $currentDetail);
        $this->assertNull($this->repository->findForAdminCurrent(999));
    }

    public function testAdminDetailAndCurrentLookupExcludeSoftDeletedParticipantsAndEvents(): void
    {
        foreach ([5, 6] as $hiddenPaymentId) {
            $this->assertNull($this->repository->findForAdmin($hiddenPaymentId));
            $this->assertNull($this->repository->findForAdminCurrent($hiddenPaymentId));
        }
    }

    public function testReviewUsesPendingOnlyCompareAndSetAndStoresAdministratorAudit(): void
    {
        $paid = $this->repository->review(1, 900, 'paid', 'Reference verified');

        $this->assertNotNull($paid);
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertSame(900, $paid['reviewed_by']);
        $this->assertSame('Reference verified', $paid['review_note']);
        $this->assertNotNull($paid['reviewed_at']);
        $this->assertNotNull($paid['paid_at']);
        $this->assertNull($this->repository->review(1, 901, 'failed', 'Late conflicting review'));

        $failed = $this->repository->review(4, 901, 'failed', null);
        $this->assertNotNull($failed);
        $this->assertSame('failed', $failed['payment_status']);
        $this->assertSame(901, $failed['reviewed_by']);
        $this->assertNull($failed['paid_at']);
        $this->assertNull($this->repository->review(3, 900, 'paid', null));
        $this->assertNull($this->repository->review(2, 900, 'invalid', null));
    }

    public function testDashboardSummariesUsePaymentAggregatesWithParticipantOrganizerAndAdminScope(): void
    {
        $this->assertSame(
            ['pending' => 2, 'paid' => 0, 'paid_total' => '0.00'],
            $this->repository->summaryForParticipant(1),
        );
        $this->assertSame(
            ['pending' => 0, 'paid' => 1, 'paid_total' => '450.00'],
            $this->repository->summaryForParticipant(2),
        );
        $this->assertSame(
            ['pending' => 2, 'paid' => 1, 'paid_total' => '450.00'],
            $this->repository->summaryForOrganizer(50),
        );
        $this->assertSame(
            ['pending' => 2, 'paid' => 1, 'paid_total' => '450.00'],
            $this->repository->summaryForAdmin(),
        );
        $this->assertSame(
            ['pending' => 0, 'paid' => 0, 'paid_total' => '0.00'],
            $this->repository->summaryForOrganizer(999),
        );

        $queries = implode("\n", $this->connection->preparedQueries);
        $this->assertTrue(str_contains($queries, 'SUM(CASE'));
        $this->assertTrue(str_contains($queries, 'organizers.user_id = :organizer_user_id'));
    }

    public function testDashboardSummaryPreservesExactDecimalStringsBeyondFloatPrecision(): void
    {
        $repository = new PaymentRepository(new ExactDecimalPaymentSummaryPdo([
            'pending' => '0',
            'paid' => '1',
            'paid_total' => '9007199254740993.24',
        ]));

        $this->assertSame(
            ['pending' => 0, 'paid' => 1, 'paid_total' => '9007199254740993.24'],
            $repository->summaryForAdmin(),
        );
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, registration_number TEXT NOT NULL, status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE payment_methods (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL)');
        $this->connection->exec(
            'CREATE TABLE payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                registration_id INTEGER NOT NULL,
                payment_method_id INTEGER NULL,
                transaction_reference TEXT NULL UNIQUE,
                amount NUMERIC NOT NULL,
                currency TEXT NOT NULL,
                status TEXT NOT NULL,
                gateway_response TEXT NULL,
                paid_at TEXT NULL,
                refunded_at TEXT NULL,
                reviewed_by INTEGER NULL,
                reviewed_at TEXT NULL,
                review_note TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, status TEXT NOT NULL)');
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO users (id, name, email, deleted_at) VALUES (1, 'Participant One', 'participant@example.test', NULL), (2, 'Participant Two', 'participant-two@example.test', NULL), (4, 'Deleted Participant', 'deleted@example.test', '2026-08-06 00:00:00'), (900, 'Admin One', 'admin@example.test', NULL), (901, 'Admin Two', 'admin-two@example.test', NULL)");
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name) VALUES (10, 50, 'Organizer Company')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, slug) VALUES (20, 10, 'Transaction Event', 'transaction-event')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, slug, deleted_at) VALUES (21, 10, 'Deleted Event', 'deleted-event', '2026-08-05 00:00:00')");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status) VALUES (101, 20, 1, 'REG-101', 'pending'), (102, 20, 1, 'REG-102', 'pending'), (103, 20, 2, 'REG-103', 'confirmed'), (104, 21, 1, 'REG-104', 'confirmed'), (105, 20, 4, 'REG-105', 'confirmed')");
        $this->connection->exec("INSERT INTO payment_methods (id, name, slug) VALUES (1, 'Manual Bank', 'manual-bank')");
        $this->connection->exec(
            "INSERT INTO payments (id, registration_id, payment_method_id, transaction_reference, amount, currency, status, gateway_response, paid_at, reviewed_by, reviewed_at, review_note, created_at, updated_at) VALUES
                (1, 101, 1, 'REF-PENDING-OLD', 450, 'BDT', 'pending', '{\"channel\":\"bank\"}', NULL, NULL, NULL, NULL, '2026-08-01 09:00:00', '2026-08-01 09:00:00'),
                (2, 103, 1, 'REF-PAID', 450, 'BDT', 'paid', NULL, '2026-08-02 09:00:00', 900, '2026-08-02 09:00:00', 'Verified', '2026-08-02 09:00:00', '2026-08-02 09:00:00'),
                (3, 101, 1, 'REF-FAILED-LATEST', 450, 'BDT', 'failed', NULL, NULL, 900, '2026-08-03 09:00:00', 'Rejected', '2026-08-03 09:00:00', '2026-08-03 09:00:00'),
                (4, 102, 1, 'REF-PENDING-NEW', 450, 'BDT', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-08-04 09:00:00', '2026-08-04 09:00:00'),
                (5, 104, 1, 'REF-DELETED-EVENT', 999, 'BDT', 'paid', NULL, '2026-08-05 09:00:00', 900, '2026-08-05 09:00:00', 'Verified', '2026-08-05 09:00:00', '2026-08-05 09:00:00'),
                (6, 105, 1, 'REF-DELETED-PARTICIPANT', 777, 'BDT', 'paid', NULL, '2026-08-06 09:00:00', 900, '2026-08-06 09:00:00', 'Verified', '2026-08-06 09:00:00', '2026-08-06 09:00:00')",
        );
    }
}
