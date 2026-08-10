<?php
$query = http_build_query(array_filter([
    'start' => $range['start'] ?? null,
    'end' => $range['end'] ?? null,
    'event' => $eventId,
], static fn (mixed $value): bool => $value !== null && $value !== ''), '', '&', PHP_QUERY_RFC3986);
$lifecycle = (array) ($summary['lifecycle'] ?? []);
$registrations = (array) ($summary['registrations'] ?? []);
$payments = (array) ($summary['verified_payments'] ?? []);
$lifecycleLabels = ['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'published' => 'Published', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$registrationLabels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'waitlisted' => 'Waitlisted', 'refunded' => 'Refunded'];
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-chart-line-up" aria-hidden="true"></i><span>Event performance</span></p><h1>Organizer analytics</h1><p>Operational activity and event-level outcomes for the selected date range.</p></div>
    <a class="button button--quiet" href="/organizer/analytics.csv?<?= e($query) ?>"><i class="ph ph-download-simple" aria-hidden="true"></i><span>Export CSV</span></a>
</div>

<?php if ($filterError !== null): ?><div class="form-alert mt-6" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($filterError) ?></span></div><?php endif; ?>

<section class="dashboard-panel mt-8" aria-labelledby="organizer-analytics-filters">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-funnel" aria-hidden="true"></i></span><div><h2 id="organizer-analytics-filters">Analytics range</h2><p>Choose up to 366 inclusive calendar days.</p></div></div>
    <form class="mt-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_auto]" method="get" action="/organizer/analytics">
        <label class="form-field" for="organizer-analytics-start"><span>Start date</span><input id="organizer-analytics-start" name="start" type="date" value="<?= e($range['start'] ?? '') ?>"></label>
        <label class="form-field" for="organizer-analytics-end"><span>End date</span><input id="organizer-analytics-end" name="end" type="date" value="<?= e($range['end'] ?? '') ?>"></label>
        <label class="form-field" for="organizer-analytics-event"><span>Event ID <small>(optional)</small></span><input id="organizer-analytics-event" name="event" type="number" min="1" step="1" inputmode="numeric" value="<?= e($eventId ?? '') ?>"></label>
        <div class="flex items-end gap-2"><button class="button button--primary" type="submit">Apply</button><a class="button button--quiet" href="/organizer/analytics">Reset</a></div>
    </form>
</section>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Organizer analytics summary">
    <div class="dashboard-panel"><span class="text-sm text-[var(--ink-muted)]">Events</span><strong class="mt-2 block text-3xl" aria-label="Events: <?= e((int) ($lifecycle['total'] ?? 0)) ?>"><?= e((int) ($lifecycle['total'] ?? 0)) ?></strong><small><?= e((int) ($lifecycle['published'] ?? 0)) ?> published, <?= e((int) ($lifecycle['completed'] ?? 0)) ?> completed</small></div>
    <div class="dashboard-panel"><span class="text-sm text-[var(--ink-muted)]">Registrations</span><strong class="mt-2 block text-3xl" aria-label="Confirmed registrations: <?= e((int) ($registrations['confirmed'] ?? 0)) ?>"><?= e((int) ($registrations['confirmed'] ?? 0)) ?></strong><small><?= e((int) ($registrations['pending'] ?? 0)) ?> pending, <?= e((int) ($registrations['cancelled'] ?? 0)) ?> cancelled</small></div>
    <div class="dashboard-panel"><span class="text-sm text-[var(--ink-muted)]">Capacity utilization</span><strong class="mt-2 block text-3xl" aria-label="Capacity utilization: <?= e($summary['capacity_utilization_rate'] ?? '0.0') ?> percent"><?= e($summary['capacity_utilization_rate'] ?? '0.0') ?>%</strong><small><?= e((int) ($summary['capacity_total'] ?? 0)) ?> total places</small></div>
    <div class="dashboard-panel"><span class="text-sm text-[var(--ink-muted)]">Attendance rate</span><strong class="mt-2 block text-3xl" aria-label="Attendance rate: <?= e($summary['attendance_rate'] ?? '0.0') ?> percent"><?= e($summary['attendance_rate'] ?? '0.0') ?>%</strong><small><?= e((int) ($summary['attendance_count'] ?? 0)) ?> checked in</small></div>
</section>

<?php require base_path('app/Views/components/analytics-charts.php'); ?>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="dashboard-panel" aria-labelledby="organizer-lifecycle-breakdown"><div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-flow-arrow" aria-hidden="true"></i></span><div><h2 id="organizer-lifecycle-breakdown">Lifecycle breakdown</h2><p>Every event state in the selected range.</p></div></div><dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4"><?php foreach ($lifecycleLabels as $status => $label): ?><div class="rounded-xl border border-[var(--line)] p-3" data-lifecycle-status="<?= e($status) ?>"><dt class="text-sm text-[var(--ink-muted)]"><?= e($label) ?></dt><dd class="mt-1 text-xl font-bold"><?= e((int) ($lifecycle[$status] ?? 0)) ?></dd></div><?php endforeach; ?></dl></section>
    <section class="dashboard-panel" aria-labelledby="organizer-registration-breakdown"><div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-users-three" aria-hidden="true"></i></span><div><h2 id="organizer-registration-breakdown">Registration breakdown</h2><p>Every registration state recorded in the range.</p></div></div><dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3"><?php foreach ($registrationLabels as $status => $label): ?><div class="rounded-xl border border-[var(--line)] p-3" data-registration-status="<?= e($status) ?>"><dt class="text-sm text-[var(--ink-muted)]"><?= e($label) ?></dt><dd class="mt-1 text-xl font-bold"><?= e((int) ($registrations[$status] ?? 0)) ?></dd></div><?php endforeach; ?></dl></section>
</div>

<section class="dashboard-panel mt-6" aria-labelledby="organizer-payment-summary">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-currency-circle-dollar" aria-hidden="true"></i></span><div><h2 id="organizer-payment-summary">Verified payment totals</h2><p>Exact verified amounts remain separated by currency.</p></div></div>
    <?php if ($payments === []): ?><div class="empty-state mt-6"><span class="empty-state__icon"><i class="ph ph-receipt" aria-hidden="true"></i></span><strong>No verified payments</strong><p>No paid transactions match this range.</p></div><?php else: ?><dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><?php foreach ($payments as $currency => $amount): ?><div class="rounded-xl border border-[var(--line)] p-4"><dt class="text-sm text-[var(--ink-muted)]"><?= e($currency) ?></dt><dd class="mt-1 text-xl font-bold"><?= e($currency) ?> <?= e($amount) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    <?php if ((int) ($summary['refund_attention_count'] ?? 0) > 0): ?><p class="form-alert mt-4" role="note"><i class="ph ph-warning" aria-hidden="true"></i><span><?= e((int) $summary['refund_attention_count']) ?> paid cancellation<?= (int) $summary['refund_attention_count'] === 1 ? '' : 's' ?> require refund attention.</span></p><?php endif; ?>
</section>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="event-analytics-breakdown">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-table" aria-hidden="true"></i></span><div><h2 id="event-analytics-breakdown">Event breakdown</h2><p>Canonical event-level metrics with archived history retained.</p></div></div>
    <?php if ($rows === []): ?><div class="empty-state"><span class="empty-state__icon"><i class="ph ph-chart-line" aria-hidden="true"></i></span><strong>No event activity</strong><p>Adjust the dates or remove the event filter.</p></div><?php else: ?>
        <div class="organizer-table-wrap mt-6"><table class="operations-table organizer-table"><caption class="sr-only">Organizer event analytics breakdown</caption><thead><tr><th>Event</th><th>Lifecycle</th><th>Registrations</th><th>Attendance</th><th>Payments</th><th>Engagement</th></tr></thead><tbody>
        <?php foreach ($rows as $row): $counts = (array) ($row['registration_counts'] ?? []); ?>
            <tr>
                <td data-label="Event"><strong><?= e($row['event_title'] ?? 'Event') ?></strong><small>#<?= e((int) ($row['event_id'] ?? 0)) ?>, <?= e($row['start_date'] ?? '') ?><?= !empty($row['archived']) ? ', archived' : '' ?></small></td>
                <td data-label="Lifecycle"><span class="status-chip status-chip--<?= e($row['event_status'] ?? 'draft') ?>"><?= e(ucfirst((string) ($row['event_status'] ?? 'draft'))) ?></span><small>Capacity <?= e((int) ($row['capacity'] ?? 0)) ?></small></td>
                <td data-label="Registrations"><strong><?= e((int) ($counts['confirmed'] ?? 0)) ?> confirmed</strong><small><?= e((int) ($counts['pending'] ?? 0)) ?> pending, <?= e((int) ($counts['cancelled'] ?? 0)) ?> cancelled</small></td>
                <td data-label="Attendance"><strong><?= e((int) ($row['attendance_count'] ?? 0)) ?> checked in</strong></td>
                <td data-label="Payments"><?php $rowPayments = (array) ($row['verified_payments'] ?? []); ?><?php if ($rowPayments === []): ?><span>None verified</span><?php else: ?><?php foreach ($rowPayments as $currency => $amount): ?><strong class="block"><?= e($currency) ?> <?= e($amount) ?></strong><?php endforeach; ?><?php endif; ?><?php if ((int) ($row['refund_attention_count'] ?? 0) > 0): ?><small><?= e((int) $row['refund_attention_count']) ?> refund attention</small><?php endif; ?></td>
                <td data-label="Engagement"><strong><?= e((int) ($row['favorites_count'] ?? 0)) ?> favorites</strong><small><?= e((int) ($row['review_count'] ?? 0)) ?> reviews, <?= e($row['review_average'] ?? '0.00') ?> average</small></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
