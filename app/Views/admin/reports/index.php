<?php
$reportTypes = ['events' => 'Events', 'registrations' => 'Registrations', 'payments' => 'Payments', 'attendance' => 'Attendance', 'organizers' => 'Organizers'];
$eventStatuses = ['' => 'All lifecycle states', 'draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'published' => 'Published', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$statusDomains = [
    'event_status' => 'event',
    'registration_status' => 'registration',
    'payment_status' => 'payment',
    'attendance_status' => 'attendance',
    'approval_status' => 'organizer_approval',
];
$query = http_build_query(array_filter([
    'type' => $reportType,
    'start' => $range['start'] ?? null,
    'end' => $range['end'] ?? null,
    'event_status' => $filters['event_status'] ?? null,
    'currency' => $filters['currency'] ?? null,
], static fn (mixed $value): bool => $value !== null && $value !== ''), '', '&', PHP_QUERY_RFC3986);
?>
<div class="dashboard-page-heading organizer-page-heading"><div><p class="dashboard-kicker"><i class="ph ph-files" aria-hidden="true"></i><span>Safe operational exports</span></p><h1>Operational reports</h1><p>Aggregate, non-PII reports for events, registrations, payments, attendance, and organizers.</p></div><div class="flex flex-wrap gap-2"><a class="button button--quiet" href="/admin/reports.csv?<?= e($query) ?>"><i class="ph ph-download-simple" aria-hidden="true"></i><span>CSV</span></a><a class="button button--quiet" href="/admin/reports.pdf?<?= e($query) ?>"><i class="ph ph-file-pdf" aria-hidden="true"></i><span>PDF</span></a><a class="button button--quiet" href="/admin/reports.xml?<?= e($query) ?>"><i class="ph ph-file-xls" aria-hidden="true"></i><span>Excel XML</span></a></div></div>

<?php if ($filterError !== null): ?><div class="form-alert mt-6" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($filterError) ?></span></div><?php endif; ?>

<section class="dashboard-panel mt-8" aria-labelledby="report-filters"><div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-funnel" aria-hidden="true"></i></span><div><h2 id="report-filters">Report filters</h2><p>Exports use the same bounded filters as this preview.</p></div></div>
<form class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,0.55fr)_auto]" method="get" action="/admin/reports" data-form-kind="filter">
<label class="form-field" for="report-type"><span>Report</span><select id="report-type" name="type"><?php foreach ($reportTypes as $value => $label): ?><option value="<?= e($value) ?>"<?= $reportType === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label class="form-field" for="report-start"><span>Start date</span><input id="report-start" name="start" type="date" value="<?= e($range['start'] ?? '') ?>"></label>
<label class="form-field" for="report-end"><span>End date</span><input id="report-end" name="end" type="date" value="<?= e($range['end'] ?? '') ?>"></label>
<label class="form-field" for="report-status"><span>Event status</span><select id="report-status" name="event_status"><?php foreach ($eventStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= ($filters['event_status'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label class="form-field" for="report-currency"><span>Currency</span><input id="report-currency" name="currency" maxlength="3" value="<?= e($filters['currency'] ?? '') ?>" placeholder="BDT" autocapitalize="characters"></label>
<div class="flex items-end gap-2"><button class="button button--primary" type="submit">Apply</button><a class="button button--quiet" href="/admin/reports">Reset</a></div>
</form></section>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="report-preview"><div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-table" aria-hidden="true"></i></span><div><h2 id="report-preview"><?= e($reportTypes[$reportType] ?? 'Report') ?> preview</h2><p>The preview shows the first 100 aggregate rows. CSV streams all matching rows; PDF and Excel XML are bounded to 200 rows.</p></div></div>
<?php if ($rows === []): ?><div class="empty-state"><span class="empty-state__icon"><i class="ph ph-files" aria-hidden="true"></i></span><strong>No report rows</strong><p>Adjust the date range or filters to find operational activity.</p></div><?php else: ?><div class="organizer-table-wrap mt-6"><table class="operations-table organizer-table"><caption class="sr-only"><?= e($reportTypes[$reportType] ?? 'Operational') ?> report preview</caption><thead><tr><?php foreach ($columns as $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($columns as $key => $label): $value = is_scalar($row[$key] ?? null) ? (string) $row[$key] : ''; ?><td data-label="<?= e($label) ?>"><?php if (isset($statusDomains[$key])): ?><span class="status-chip status-chip--<?= e(status_modifier($value, $statusDomains[$key])) ?>"><?= e(oems_status_label($value, [], false)) ?></span><?php else: ?><?= e($value) ?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
