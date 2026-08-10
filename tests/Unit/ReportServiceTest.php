<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Services\ReportService;
use OEMS\Tests\Support\FakeAnalyticsRepository;
use OEMS\Tests\Support\TestCase;

final class ReportServiceTest extends TestCase
{
    public function testDateRangesAreScalarStrictInclusiveAndBounded(): void
    {
        $service = $this->service();

        $this->assertSame([
            'valid' => true,
            'start' => '2026-07-12',
            'end' => '2026-08-10',
            'start_at' => '2026-07-12 00:00:00',
            'end_exclusive' => '2026-08-11 00:00:00',
        ], $service->dateRange(null, null));
        $this->assertTrue($service->dateRange('2025-08-11', '2026-08-10')['valid']);

        foreach ([
            [['2026-08-01'], '2026-08-10'],
            ['2026-02-30', '2026-08-10'],
            ['2026-08-10', '2026-08-01'],
            ['2025-08-09', '2026-08-10'],
            ['08/01/2026', '2026-08-10'],
        ] as [$start, $end]) {
            $result = $service->dateRange($start, $end);
            $this->assertFalse($result['valid']);
            $this->assertTrue(isset($result['error']) && mb_strlen($result['error']) <= 160);
        }
    }

    public function testFilterAndReportAllowlistsRejectNestedOrUnknownValues(): void
    {
        $service = $this->service();

        $this->assertSame(['valid' => true, 'filters' => ['event_status' => 'published', 'currency' => 'USD']], $service->adminFilters('published', 'usd'));
        $this->assertFalse($service->adminFilters('private', 'USD')['valid']);
        $this->assertFalse($service->adminFilters(['published'], 'USD')['valid']);
        $this->assertFalse($service->adminFilters('', '=BD')['valid']);
        $this->assertSame('events', $service->reportType(null));
        foreach (['events', 'registrations', 'payments', 'attendance', 'organizers'] as $type) {
            $this->assertSame($type, $service->reportType($type));
        }
        $this->assertNull($service->reportType('users'));
        $this->assertNull($service->reportType(['events']));
    }

    public function testOrganizerDataReturnsNotFoundForForeignEventAndPassesExclusiveRange(): void
    {
        $repository = new FakeAnalyticsRepository();
        $repository->foreignEventIds = [99];
        $service = $this->service($repository);

        $result = $service->organizerData(10, '2026-08-01', '2026-08-10', '99');
        $this->assertSame('not_found', $result['code']);
        $this->assertSame(['organizerSummary', 10, '2026-08-01 00:00:00', '2026-08-11 00:00:00', 99], $repository->calls[0]);
        $this->assertSame([], $service->organizerData(10, '2026-08-01', '2026-08-10', 'event')['data'] ?? []);
    }

    public function testCsvStreamsBatchesWithBomAndNeutralizesFormulasAndControls(): void
    {
        $repository = new FakeAnalyticsRepository();
        for ($index = 1; $index <= 205; $index++) {
            $repository->reportRows['events'][] = [
                'event_id' => $index,
                'event_status' => 'published',
                'start_date' => '2026-08-05 10:00:00',
                'capacity' => 10,
                'confirmed_registrations' => 2,
                'attendance_count' => 1,
                'favorites_count' => 3,
                'published_review_count' => 2,
                'published_review_average' => '4.50',
                'verified_payments' => $index === 1 ? "=2+2\r\nInjected" : 'BDT 25.00',
                'refund_attention_count' => 0,
                'archived' => 0,
            ];
        }
        $service = $this->service($repository);
        $chunks = [];
        $result = $service->streamAdminCsv(
            'events',
            '2026-08-01',
            '2026-08-10',
            [],
            static function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );
        $csv = implode('', $chunks);

        $this->assertSame(['success' => true], $result);
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertTrue(str_contains($csv, "'=2+2 Injected"));
        $this->assertFalse(str_contains(substr($csv, 3), "\r"));
        $requests = array_values(array_filter($repository->calls, static fn (array $call): bool => $call[0] === 'adminReportRows'));
        $this->assertSame([100, 100, 100], array_column($requests, 5));
        $this->assertSame([0, 100, 200], array_column($requests, 6));
        foreach (['email', 'transaction_reference', 'gateway_response', 'token', 'location'] as $forbidden) {
            $this->assertFalse(str_contains(strtolower($csv), $forbidden));
        }
    }

    private function service(?FakeAnalyticsRepository $repository = null): ReportService
    {
        return new ReportService(
            $repository ?? new FakeAnalyticsRepository(),
            new DateTimeImmutable('2026-08-10 15:00:00', new DateTimeZone('Asia/Dhaka')),
        );
    }
}
