<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\ReportService;
use OEMS\Tests\Support\FakeAnalyticsRepository;
use OEMS\Tests\Support\TestCase;

final class AnalyticsChartTest extends TestCase
{
    public function testReportServiceAddsBoundedAggregateOnlyChartPayloads(): void
    {
        $repository = new FakeAnalyticsRepository();
        $repository->organizerSummary = ['lifecycle' => [], 'registrations' => []];
        $repository->organizerRows = [];
        $repository->organizerSeries = $this->series();
        $repository->adminSummary = ['lifecycle' => [], 'registrations' => []];
        $repository->adminSeries = $this->series();
        $service = new ReportService($repository, new DateTimeImmutable('2026-08-10 10:00:00+06:00'));

        $organizer = $service->organizerData(9, '2026-08-01', '2026-08-10', null);
        $admin = $service->adminData('2026-08-01', '2026-08-10', null, null);

        $this->assertTrue($organizer['success']);
        $this->assertTrue($admin['success']);
        $this->assertSame(['2026-08-01', '2026-08-02'], $organizer['data']['charts']['timeline']['labels']);
        $this->assertSame(['12.30', '0.00'], $organizer['data']['charts']['timeline']['payments']['BDT']);
        $this->assertSame(['Technology'], $admin['data']['charts']['categories']['labels']);
        $encoded = json_encode($admin['data']['charts'], JSON_THROW_ON_ERROR);
        foreach (['user_id', 'name', 'email', 'reference', 'location', 'gateway'] as $forbidden) {
            $this->assertFalse(str_contains($encoded, $forbidden));
        }
    }

    public function testAnalyticsViewsUseLocalProgressiveChartsWithVisibleTableFallbacks(): void
    {
        $layout = file_get_contents(base_path('app/Views/layouts/dashboard.php')) ?: '';
        $organizer = file_get_contents(base_path('app/Views/organizer/analytics/index.php')) ?: '';
        $admin = file_get_contents(base_path('app/Views/admin/analytics/index.php')) ?: '';
        $component = file_get_contents(base_path('app/Views/components/analytics-charts.php')) ?: '';
        $assets = file_get_contents(base_path('scripts/copy-fonts.mjs')) ?: '';

        $this->assertTrue(str_contains($layout, '/assets/vendor/chartjs/chart.umd.min.js'));
        $this->assertTrue(str_contains($layout, '/assets/js/analytics-charts.js'));
        $this->assertTrue(str_contains($organizer, 'analytics-charts.php'));
        $this->assertTrue(str_contains($admin, 'analytics-charts.php'));
        $this->assertTrue(str_contains($component, 'data-analytics-chart'));
        $this->assertTrue(str_contains($component, '<table'));
        $this->assertTrue(str_contains($organizer, '<table'));
        $this->assertTrue(str_contains($admin, '<table'));
        $this->assertTrue(str_contains($assets, 'chart.umd.min.js'));
        $this->assertFalse(str_contains($layout, 'cdn.'));
    }

    private function series(): array
    {
        return [
            'granularity' => 'day',
            'periods' => ['2026-08-01', '2026-08-02'],
            'events' => ['2026-08-01' => 1, '2026-08-02' => 0],
            'registrations' => ['2026-08-01' => 3, '2026-08-02' => 2],
            'attendance' => ['2026-08-01' => 1, '2026-08-02' => 1],
            'payments' => ['BDT' => ['2026-08-01' => '12.30', '2026-08-02' => '0.00']],
            'categories' => [['label' => 'Technology', 'count' => 5]],
        ];
    }
}
