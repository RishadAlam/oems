<?php
$timeline = is_array($charts['timeline'] ?? null) ? $charts['timeline'] : [];
$categorySeries = is_array($charts['categories'] ?? null) ? $charts['categories'] : [];
$chartLabels = is_array($timeline['labels'] ?? null) ? $timeline['labels'] : [];
$chartPayments = is_array($timeline['payments'] ?? null) ? $timeline['payments'] : [];
$categoryLabels = is_array($categorySeries['labels'] ?? null) ? $categorySeries['labels'] : [];
$categoryCounts = is_array($categorySeries['registrations'] ?? null) ? $categorySeries['registrations'] : [];
?>
<section class="mt-6" aria-labelledby="analytics-trends-heading">
    <div class="dashboard-panel__heading mb-4"><span class="dashboard-panel__icon"><i class="ph ph-chart-line" aria-hidden="true"></i></span><div><h2 id="analytics-trends-heading">Activity trends</h2><p>Charts enhance the same aggregate values shown in the tables below.</p></div></div>
    <p class="mb-4 text-sm text-[var(--ink-muted)]" data-analytics-chart-status role="status" aria-live="polite"></p>
    <div class="grid gap-6 xl:grid-cols-2">
        <article class="dashboard-panel min-w-0" aria-labelledby="timeline-chart-heading">
            <h3 id="timeline-chart-heading" class="text-lg font-bold">Events, registrations, attendance, and payments</h3>
            <?php if ($chartLabels === []): ?>
                <div class="empty-state mt-5"><strong>No timeline activity</strong><p>Adjust the reporting range to compare activity.</p></div>
            <?php else: ?>
                <div class="relative mt-5 h-72 min-w-0"><canvas data-analytics-chart="timeline" aria-hidden="true"></canvas></div>
                <div class="organizer-table-wrap mt-5"><table class="operations-table organizer-table"><caption class="sr-only">Timeline chart data</caption><thead><tr><th>Period</th><th>Events</th><th>Registrations</th><th>Attendance</th><?php foreach (array_keys($chartPayments) as $currency): ?><th>Paid <?= e($currency) ?></th><?php endforeach; ?></tr></thead><tbody>
                    <?php foreach ($chartLabels as $index => $label): ?><tr><td data-label="Period"><strong><?= e($label) ?></strong></td><td data-label="Events"><?= e((int) ($timeline['events'][$index] ?? 0)) ?></td><td data-label="Registrations"><?= e((int) ($timeline['registrations'][$index] ?? 0)) ?></td><td data-label="Attendance"><?= e((int) ($timeline['attendance'][$index] ?? 0)) ?></td><?php foreach ($chartPayments as $currency => $values): ?><td data-label="Paid <?= e($currency) ?>"><?= e($values[$index] ?? '0.00') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </article>
        <article class="dashboard-panel min-w-0" aria-labelledby="category-chart-heading">
            <h3 id="category-chart-heading" class="text-lg font-bold">Registrations by category</h3>
            <?php if ($categoryLabels === []): ?>
                <div class="empty-state mt-5"><strong>No category activity</strong><p>Category totals appear after registrations are recorded.</p></div>
            <?php else: ?>
                <div class="relative mt-5 h-72 min-w-0"><canvas data-analytics-chart="categories" aria-hidden="true"></canvas></div>
                <div class="organizer-table-wrap mt-5"><table class="operations-table organizer-table"><caption class="sr-only">Category chart data</caption><thead><tr><th>Category</th><th>Registrations</th></tr></thead><tbody><?php foreach ($categoryLabels as $index => $label): ?><tr><td data-label="Category"><strong><?= e($label) ?></strong></td><td data-label="Registrations"><?= e((int) ($categoryCounts[$index] ?? 0)) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </article>
    </div>
</section>
<script type="application/json" id="analytics-chart-data"><?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
