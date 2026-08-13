<?php
$timeline = is_array($charts['timeline'] ?? null) ? $charts['timeline'] : [];
$categorySeries = is_array($charts['categories'] ?? null) ? $charts['categories'] : [];
$chartLabels = is_array($timeline['labels'] ?? null) ? array_values($timeline['labels']) : [];
$eventCounts = is_array($timeline['events'] ?? null) ? array_values($timeline['events']) : [];
$registrationCounts = is_array($timeline['registrations'] ?? null) ? array_values($timeline['registrations']) : [];
$attendanceCounts = is_array($timeline['attendance'] ?? null) ? array_values($timeline['attendance']) : [];
$chartPayments = is_array($timeline['payments'] ?? null) ? $timeline['payments'] : [];
$categoryLabels = is_array($categorySeries['labels'] ?? null) ? array_values($categorySeries['labels']) : [];
$categoryCounts = is_array($categorySeries['registrations'] ?? null) ? array_values($categorySeries['registrations']) : [];
$periodCount = count($chartLabels);
$categoryCount = count($categoryLabels);
$activePeriodCount = 0;
$busiestPeriod = null;
$busiestPeriodTotal = 0;

foreach ($chartLabels as $index => $label) {
    $periodTotal = (int) ($eventCounts[$index] ?? 0)
        + (int) ($registrationCounts[$index] ?? 0)
        + (int) ($attendanceCounts[$index] ?? 0);
    if ($periodTotal > 0) {
        $activePeriodCount++;
    }
    if ($periodTotal > $busiestPeriodTotal) {
        $busiestPeriodTotal = $periodTotal;
        $busiestPeriod = (string) $label;
    }
}

$leadingCategory = null;
$leadingCategoryCount = 0;
foreach ($categoryLabels as $index => $label) {
    $count = (int) ($categoryCounts[$index] ?? 0);
    if ($leadingCategory === null || $count > $leadingCategoryCount) {
        $leadingCategory = (string) $label;
        $leadingCategoryCount = $count;
    }
}

$periodNoun = $periodCount === 1 ? 'period' : 'periods';
$activePeriodNoun = $activePeriodCount === 1 ? 'active period' : 'active periods';
$categoryNoun = $categoryCount === 1 ? 'category' : 'categories';
?>
<section class="analytics-performance mt-8" aria-labelledby="analytics-trends-heading">
    <header class="analytics-section-heading">
        <div class="dashboard-panel__heading-main">
            <span class="dashboard-panel__icon"><i class="ph ph-chart-line" aria-hidden="true"></i></span>
            <div>
                <h2 id="analytics-trends-heading">Performance overview</h2>
                <p>Compare activity over time, then inspect the exact aggregate source values when needed.</p>
            </div>
        </div>
        <p class="analytics-chart-status" data-analytics-chart-status role="status" aria-live="polite" aria-atomic="true"></p>
    </header>

    <div class="analytics-chart-grid">
        <article class="analytics-chart-card analytics-chart-card--timeline" aria-labelledby="timeline-chart-heading">
            <header class="analytics-chart-card__heading">
                <div>
                    <p class="analytics-chart-card__eyebrow">Count activity</p>
                    <h3 id="timeline-chart-heading">Activity over time</h3>
                    <p>Events, registrations, and attendance share one comparable scale.</p>
                </div>
                <i class="ph ph-chart-line-up" aria-hidden="true"></i>
            </header>

            <dl class="analytics-insights" aria-label="Timeline insights">
                <div><dt>Range</dt><dd><?= e($periodCount) ?> <?= e($periodNoun) ?></dd></div>
                <div><dt>Activity</dt><dd data-analytics-insight="active-periods"><?= e($activePeriodCount) ?> <?= e($activePeriodNoun) ?></dd></div>
                <div><dt>Peak period</dt><dd data-analytics-insight="busiest-period"><?= $busiestPeriod === null ? 'No activity' : e($busiestPeriod) ?></dd></div>
            </dl>

            <?php if ($chartLabels === []): ?>
                <div class="analytics-chart-empty empty-state"><strong>No timeline data</strong><p>Adjust the reporting range to compare activity.</p></div>
            <?php elseif ($activePeriodCount === 0): ?>
                <div class="analytics-chart-empty empty-state"><strong>No recorded activity</strong><p>The selected periods contain no events, registrations, or attendance.</p></div>
            <?php else: ?>
                <div class="analytics-chart-frame analytics-chart-frame--timeline"><canvas data-analytics-chart="timeline" aria-hidden="true"></canvas></div>
            <?php endif; ?>

            <?php if ($chartLabels !== []): ?>
                <details class="analytics-data-disclosure">
                    <summary><span><i class="ph ph-table" aria-hidden="true"></i>View timeline data (<?= e($periodCount) ?> <?= e($periodNoun) ?>)</span><i class="ph ph-caret-down" aria-hidden="true"></i></summary>
                    <div class="analytics-data-disclosure__content organizer-table-wrap">
                        <table class="operations-table organizer-table analytics-data-table">
                            <caption class="sr-only">Timeline chart source data</caption>
                            <thead><tr><th>Period</th><th>Events</th><th>Registrations</th><th>Attendance</th><?php foreach (array_keys($chartPayments) as $currency): ?><th>Paid <?= e($currency) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                            <?php foreach ($chartLabels as $index => $label): ?>
                                <tr>
                                    <td data-label="Period"><strong><?= e($label) ?></strong></td>
                                    <td data-label="Events"><?= e((int) ($eventCounts[$index] ?? 0)) ?></td>
                                    <td data-label="Registrations"><?= e((int) ($registrationCounts[$index] ?? 0)) ?></td>
                                    <td data-label="Attendance"><?= e((int) ($attendanceCounts[$index] ?? 0)) ?></td>
                                    <?php foreach ($chartPayments as $currency => $values): ?><td data-label="Paid <?= e($currency) ?>"><?= e($values[$index] ?? '0.00') ?></td><?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </article>

        <article class="analytics-chart-card analytics-chart-card--categories" aria-labelledby="category-chart-heading">
            <header class="analytics-chart-card__heading">
                <div>
                    <p class="analytics-chart-card__eyebrow">Demand mix</p>
                    <h3 id="category-chart-heading">Registrations by category</h3>
                    <p>See which event categories attracted registrations.</p>
                </div>
                <i class="ph ph-chart-bar" aria-hidden="true"></i>
            </header>

            <dl class="analytics-insights analytics-insights--compact" aria-label="Category insights">
                <div><dt>Coverage</dt><dd><?= e($categoryCount) ?> <?= e($categoryNoun) ?></dd></div>
                <div><dt>Leading category</dt><dd data-analytics-insight="leading-category"><?= $leadingCategory === null ? 'No activity' : e($leadingCategory) ?></dd></div>
            </dl>

            <?php if ($categoryLabels === []): ?>
                <div class="analytics-chart-empty empty-state"><strong>No category activity</strong><p>Category totals appear after registrations are recorded.</p></div>
            <?php else: ?>
                <div class="analytics-chart-frame analytics-chart-frame--categories" style="--analytics-category-count: <?= e(max(1, min(8, $categoryCount))) ?>">
                    <canvas data-analytics-chart="categories" data-category-count="<?= e($categoryCount) ?>" aria-hidden="true"></canvas>
                </div>
                <details class="analytics-data-disclosure">
                    <summary><span><i class="ph ph-table" aria-hidden="true"></i>View category data (<?= e($categoryCount) ?> <?= e($categoryNoun) ?>)</span><i class="ph ph-caret-down" aria-hidden="true"></i></summary>
                    <div class="analytics-data-disclosure__content organizer-table-wrap">
                        <table class="operations-table organizer-table analytics-data-table">
                            <caption class="sr-only">Category chart source data</caption>
                            <thead><tr><th>Category</th><th>Registrations</th></tr></thead>
                            <tbody><?php foreach ($categoryLabels as $index => $label): ?><tr><td data-label="Category"><strong><?= e($label) ?></strong></td><td data-label="Registrations"><?= e((int) ($categoryCounts[$index] ?? 0)) ?></td></tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </article>
    </div>
</section>
<script type="application/json" id="analytics-chart-data"><?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
