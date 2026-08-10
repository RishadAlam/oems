<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\AnalyticsRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class AnalyticsRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();
        $this->fixtures();
    }

    public function testOrganizerSummaryScopesOwnershipAndPreservesHistoricalAggregateRows(): void
    {
        $repository = new AnalyticsRepository($this->connection);
        $summary = $repository->organizerSummary(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00');

        $this->assertNotNull($summary);
        $this->assertSame(3, $summary['lifecycle']['total']);
        $this->assertSame(1, $summary['lifecycle']['published']);
        $this->assertSame(2, $summary['lifecycle']['completed']);
        $this->assertSame(2, $summary['registrations']['confirmed']);
        $this->assertSame(1, $summary['registrations']['cancelled']);
        $this->assertSame(2, $summary['attendance_count']);
        $this->assertSame(3, $summary['favorites_count']);
        $this->assertSame(3, $summary['reviews']['published']);
        $this->assertSame('4.00', $summary['reviews']['average']);
        $this->assertSame(['BDT' => '35.30', 'USD' => '7.05'], $summary['verified_payments']);
        $this->assertSame(1, $summary['refund_attention_count']);
        $this->assertSame('20.0', $summary['capacity_utilization_rate']);
        $this->assertSame('100.0', $summary['attendance_rate']);

        $this->assertNull($repository->organizerSummary(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00', 4));
        $this->assertNotNull($repository->organizerSummary(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00', 3));
    }

    public function testOrganizerEventRowsAvoidJoinMultiplicationAndHideNoHistoricalTransactions(): void
    {
        $repository = new AnalyticsRepository($this->connection);
        $rows = $repository->organizerEventRows(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00', null, 25, 0);

        $this->assertNotNull($rows);
        $this->assertSame([3, 2, 1], array_column($rows, 'event_id'));
        $first = $rows[2];
        $this->assertSame(2, $first['registration_counts']['confirmed']);
        $this->assertSame(1, $first['registration_counts']['cancelled']);
        $this->assertSame(2, $first['attendance_count']);
        $this->assertSame(3, $first['favorites_count']);
        $this->assertSame(3, $first['review_count']);
        $this->assertSame('4.00', $first['review_average']);
        $this->assertSame(['BDT' => '35.30', 'USD' => '7.05'], $first['verified_payments']);
        $this->assertSame(1, $first['refund_attention_count']);
        $this->assertSame(1, $rows[0]['archived']);
        $this->assertNull($repository->organizerEventRows(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00', 4, 25, 0));
    }

    public function testAdminSummaryUsesAllowlistedFiltersAndZeroSafeRates(): void
    {
        $repository = new AnalyticsRepository($this->connection);
        $summary = $repository->adminSummary('2026-08-01 00:00:00', '2026-08-11 00:00:00', [
            'event_status' => 'published',
            'currency' => 'BDT',
        ]);

        $this->assertSame(3, $summary['active_users']);
        $this->assertSame(2, $summary['approved_organizers']);
        $this->assertSame(2, $summary['lifecycle']['total']);
        $this->assertSame(['BDT' => '35.30'], $summary['verified_payments']);
        $this->assertSame(1, $summary['pending_event_queue']);
        $this->assertSame(1, $summary['pending_payment_queue']);
        $this->assertTrue(count($summary['top_events']) <= 5);
        $this->assertTrue(count($summary['top_categories']) <= 5);

        $zero = $repository->organizerSummary(100, '2026-01-01 00:00:00', '2026-01-02 00:00:00');
        $this->assertSame('0.0', $zero['capacity_utilization_rate']);
        $this->assertSame('0.0', $zero['attendance_rate']);
    }

    public function testAdminReportsContainOnlyAggregateNonPiiColumnsAndExactMoney(): void
    {
        $repository = new AnalyticsRepository($this->connection);

        foreach (['events', 'registrations', 'payments', 'attendance', 'organizers'] as $type) {
            $rows = $repository->adminReportRows(
                $type,
                '2026-08-01 00:00:00',
                '2026-08-11 00:00:00',
                [],
                100,
                0,
            );
            $this->assertTrue($rows !== [], 'Expected report rows for ' . $type);
            $serialized = strtolower(serialize($rows));
            foreach (['email', 'participant_name', 'transaction_reference', 'gateway_response', 'qr_', 'ticket_', 'address', 'latitude', 'longitude', 'password', 'token'] as $secret) {
                $this->assertFalse(str_contains($serialized, $secret), $type . ' exposed ' . $secret);
            }
        }

        $payments = $repository->adminReportRows('payments', '2026-08-01 00:00:00', '2026-08-11 00:00:00', [], 100, 0);
        $this->assertTrue(in_array('35.30', array_column($payments, 'amount_total'), true));
    }

    public function testChartSeriesUseIndependentAggregatesExactMoneyAndHistoricalRows(): void
    {
        $repository = new AnalyticsRepository($this->connection);
        $series = $repository->organizerSeries(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00');

        $this->assertNotNull($series);
        $this->assertSame('day', $series['granularity']);
        $this->assertSame(10, count($series['periods']));
        $this->assertSame(1, $series['events']['2026-08-08']);
        $this->assertSame(2, $series['registrations']['2026-08-05']);
        $this->assertSame(2, $series['attendance']['2026-08-05']);
        $this->assertSame('30.30', $series['payments']['BDT']['2026-08-05']);
        $this->assertSame('7.05', $series['payments']['USD']['2026-08-07']);
        $this->assertSame([['label' => 'Technology', 'count' => 4]], $series['categories']);
        $this->assertNull($repository->organizerSeries(100, '2026-08-01 00:00:00', '2026-08-11 00:00:00', 4));

        $admin = $repository->adminSeries('2026-08-01 00:00:00', '2026-08-11 00:00:00', [
            'event_status' => 'published',
            'currency' => 'BDT',
        ]);
        $this->assertSame(['BDT'], array_keys($admin['payments']));
        $serialized = strtolower(serialize($admin));
        foreach (['email', 'participant_name', 'reference', 'gateway', 'address', 'latitude', 'longitude'] as $secret) {
            $this->assertFalse(str_contains($serialized, $secret));
        }
    }

    private function schema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, approval_status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, category_id INTEGER NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL, start_date TEXT NOT NULL, capacity INTEGER NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, registered_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, amount DECIMAL(12,2) NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, paid_at TEXT NULL, created_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, status TEXT NOT NULL, scanned_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, rating INTEGER NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE favorites (user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, created_at TEXT NOT NULL)');
    }

    private function fixtures(): void
    {
        $this->connection->exec("INSERT INTO users VALUES (100, 'active', NULL), (101, 'active', NULL), (200, 'active', NULL), (201, 'active', '2026-08-09 00:00:00'), (202, 'suspended', NULL)");
        $this->connection->exec("INSERT INTO organizers VALUES (10, 100, 'approved'), (11, 101, 'approved')");
        $this->connection->exec("INSERT INTO categories VALUES (1, 'Technology'), (2, 'Community')");
        $this->connection->exec("INSERT INTO events VALUES
            (1, 10, 1, '=Launch summit', 'published', '2026-08-05 10:00:00', 10, NULL),
            (2, 10, 2, 'Quiet meetup', 'completed', '2026-08-07 10:00:00', 0, NULL),
            (3, 10, 1, 'Archived summit', 'completed', '2026-08-08 10:00:00', 0, '2026-08-09 00:00:00'),
            (4, 11, 1, 'Foreign summit', 'published', '2026-08-06 10:00:00', 20, NULL),
            (5, 10, 1, 'Outside summit', 'published', '2026-07-05 10:00:00', 10, NULL),
            (6, 11, 2, 'Pending moderation', 'pending', '2026-09-06 10:00:00', 20, NULL)");
        $this->connection->exec("INSERT INTO registrations VALUES
            (1, 1, 200, 'confirmed', '2026-08-05 11:00:00'),
            (2, 1, 201, 'confirmed', '2026-08-05 12:00:00'),
            (3, 1, 202, 'cancelled', '2026-08-06 11:00:00'),
            (4, 3, 201, 'pending', '2026-08-08 12:00:00'),
            (5, 4, 200, 'confirmed', '2026-08-06 12:00:00')");
        $this->connection->exec("INSERT INTO payments VALUES
            (1, 1, 10.10, 'BDT', 'paid', '2026-08-05 11:10:00', '2026-08-05 11:00:00'),
            (2, 2, 20.20, 'BDT', 'paid', '2026-08-05 12:10:00', '2026-08-05 12:00:00'),
            (3, 3, 5.00, 'BDT', 'paid', '2026-08-06 11:10:00', '2026-08-06 11:00:00'),
            (4, 1, 7.05, 'USD', 'paid', '2026-08-07 11:10:00', '2026-08-07 11:00:00'),
            (5, 4, 99.00, 'BDT', 'pending', NULL, '2026-08-08 12:00:00')");
        $this->connection->exec("INSERT INTO attendance VALUES (1, 1, 'present', '2026-08-05 13:00:00'), (2, 2, 'present', '2026-08-05 13:01:00')");
        $this->connection->exec("INSERT INTO reviews VALUES
            (1, 1, 200, 5, 'published', '2026-08-06 09:00:00'),
            (2, 1, 201, 4, 'published', '2026-08-06 10:00:00'),
            (3, 1, 202, 3, 'published', '2026-08-06 11:00:00'),
            (4, 1, 200, 1, 'hidden', '2026-08-06 12:00:00')");
        $this->connection->exec("INSERT INTO favorites VALUES (200, 1, '2026-08-05 09:00:00'), (201, 1, '2026-08-05 09:01:00'), (202, 1, '2026-08-05 09:02:00')");
    }
}
