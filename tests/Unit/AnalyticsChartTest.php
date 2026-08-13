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
        $styles = file_get_contents(base_path('resources/css/app.css')) ?: '';

        $this->assertTrue(str_contains($layout, '/assets/vendor/chartjs/chart.umd.min.js'));
        $this->assertTrue(str_contains($layout, '/assets/js/analytics-charts.js?v=20260814-analytics-dashboard-v1'));
        $this->assertTrue(str_contains($organizer, 'analytics-charts.php'));
        $this->assertTrue(str_contains($admin, 'analytics-charts.php'));
        $this->assertTrue(str_contains($component, 'data-analytics-chart'));
        $this->assertTrue(str_contains($component, '<table'));
        $this->assertTrue(str_contains($organizer, '<table'));
        $this->assertTrue(str_contains($admin, '<table'));
        $this->assertTrue(str_contains($assets, 'chart.umd.min.js'));
        $this->assertTrue(str_contains($styles, '.form-field {'));
        $this->assertTrue(str_contains($styles, '.form-field > span {'));
        $this->assertFalse(str_contains($component, '<?= e($currency) ?> <?= e($values[$index]'));
        $this->assertFalse(str_contains($layout, 'cdn.'));
    }

    public function testSharedAnalyticsComponentUsesInsightCardsAndClosedNativeDataDisclosures(): void
    {
        $html = $this->renderCharts([
            'timeline' => [
                'labels' => ['2026-08-01', '2026-08-02'],
                'events' => [1, 0],
                'registrations' => [3, 2],
                'attendance' => [1, 1],
                'payments' => ['BDT' => ['12.30', '0.00']],
            ],
            'categories' => ['labels' => ['Technology'], 'registrations' => [5]],
        ]);
        [$document, $xpath] = $this->document($html);

        $this->assertSame(1, $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " analytics-performance ")]')?->length);
        $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " analytics-chart-grid ")]')?->length);
        $this->assertSame(2, $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " analytics-chart-card ")]')?->length);
        $this->assertSame(2, $xpath->query('//details[contains(concat(" ", normalize-space(@class), " "), " analytics-data-disclosure ") and not(@open)]')?->length);
        $this->assertSame(1, $xpath->query('//details/summary[contains(normalize-space(.), "View timeline data (2 periods)")]')?->length);
        $this->assertSame(1, $xpath->query('//details/summary[contains(normalize-space(.), "View category data (1 category)")]')?->length);
        $this->assertSame(1, $xpath->query('//*[@data-analytics-insight="active-periods" and normalize-space(.) = "2 active periods"]')?->length);
        $this->assertSame(1, $xpath->query('//*[@data-analytics-insight="busiest-period" and contains(normalize-space(.), "2026-08-01")]')?->length);
        $this->assertSame(1, $xpath->query('//*[@data-analytics-insight="leading-category" and contains(normalize-space(.), "Technology")]')?->length);
        $this->assertSame(1, $xpath->query('//*[@data-analytics-chart="categories" and @data-category-count="1"]')?->length);
        $this->assertSame(1, $xpath->query('//th[normalize-space(.) = "Paid BDT"]')?->length);
        $this->assertSame(1, $xpath->query('//td[@data-label="Paid BDT" and normalize-space(.) = "12.30"]')?->length);
        $this->assertSame(1, $xpath->query('//*[@data-analytics-chart-status and @role="status" and @aria-atomic="true"]')?->length);
        $this->assertNotSame('', $document->textContent);
    }

    public function testAllZeroTimelineAvoidsMeaninglessCanvasAndKeepsExactSourceData(): void
    {
        $html = $this->renderCharts([
            'timeline' => [
                'labels' => ['2026-08-01', '2026-08-02'],
                'events' => [0, 0],
                'registrations' => [0, 0],
                'attendance' => [0, 0],
                'payments' => ['BDT' => ['0.00', '0.00']],
            ],
            'categories' => ['labels' => [], 'registrations' => []],
        ]);
        [, $xpath] = $this->document($html);

        $this->assertSame(0, $xpath->query('//*[@data-analytics-chart="timeline"]')?->length);
        $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " analytics-chart-empty ") and contains(normalize-space(.), "No recorded activity")]')?->length);
        $this->assertSame(2, $xpath->query('//table[caption[contains(normalize-space(.), "Timeline")]]/tbody/tr')?->length);
        $this->assertSame(1, $xpath->query('//details/summary[contains(normalize-space(.), "View timeline data (2 periods)")]')?->length);
    }

    private function renderCharts(array $charts): string
    {
        ob_start();
        require base_path('app/Views/components/analytics-charts.php');

        return (string) ob_get_clean();
    }

    /** @return array{0: \DOMDocument, 1: \DOMXPath} */
    private function document(string $html): array
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $this->assertTrue($loaded);

        return [$document, new \DOMXPath($document)];
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
