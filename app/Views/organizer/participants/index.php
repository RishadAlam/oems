<?php
$eventId = (int) ($event['event_id'] ?? 0);
$statusLabels = [
    'not_checked_in' => 'Not checked in',
    'partially_refunded' => 'Partially refunded',
    'none' => 'None',
];
$query = static fn (array $changes = []): string => http_build_query(array_filter(
    array_merge($filters, $changes),
    static fn (mixed $value): bool => $value !== '' && $value !== null,
));
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-users-three" aria-hidden="true"></i><span>Event operations</span></p><h1>Participants</h1><p>Manage registrations and fulfillment for <?= e($event['event_title'] ?? 'this event') ?>.</p></div>
    <div class="organizer-heading-actions"><a class="button button--quiet" href="/organizer/events/<?= e($eventId) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Event details</span></a><a class="button button--quiet" href="/organizer/events/<?= e($eventId) ?>/check-in"><i class="ph ph-scan" aria-hidden="true"></i><span>Check-in</span></a><a class="button button--primary" href="/organizer/events/<?= e($eventId) ?>/participants.csv?<?= e($query()) ?>"><i class="ph ph-download-simple" aria-hidden="true"></i><span>Export CSV</span></a></div>
</div>

<section class="dashboard-panel mt-8" aria-labelledby="participant-filters-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-funnel" aria-hidden="true"></i></span><div><h2 id="participant-filters-heading">Find participants</h2><p>Filter by fulfillment state or search safe participant fields.</p></div></div>
    <form class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3" method="get" action="/organizer/events/<?= e($eventId) ?>/participants">
        <label class="form-field xl:col-span-2" for="participant-search"><span>Search</span><input id="participant-search" name="search" type="search" maxlength="120" value="<?= e($filters['search'] ?? '') ?>" placeholder="Name, email, registration, or ticket"></label>
        <label class="form-field" for="registration-status"><span>Registration</span><select id="registration-status" name="registration_status"><option value="">All</option><?php foreach (['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['registration_status'] ?? '') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <label class="form-field" for="payment-status"><span>Payment</span><select id="payment-status" name="payment_status"><option value="">All</option><?php foreach (['none', 'pending', 'paid', 'failed', 'refunded', 'partially_refunded'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['payment_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($statusLabels[$value] ?? ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <label class="form-field" for="ticket-status"><span>Ticket</span><select id="ticket-status" name="ticket_status"><option value="">All</option><?php foreach (['none', 'valid', 'used', 'cancelled'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['ticket_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($statusLabels[$value] ?? ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <label class="form-field" for="attendance-status"><span>Attendance</span><select id="attendance-status" name="attendance_status"><option value="">All</option><?php foreach (['not_checked_in', 'present', 'absent'] as $value): ?><option value="<?= e($value) ?>" <?= ($filters['attendance_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($statusLabels[$value] ?? ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <div class="flex items-end gap-2"><button class="button button--primary" type="submit">Apply</button><a class="button button--quiet" href="/organizer/events/<?= e($eventId) ?>/participants">Reset</a></div>
    </form>
</section>

<section class="dashboard-panel mt-6" aria-labelledby="participant-table-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-identification-card" aria-hidden="true"></i></span><div><h2 id="participant-table-heading">Participant records</h2><p><?= e($total) ?> matching registration<?= $total === 1 ? '' : 's' ?>.</p></div></div>
    <?php if ($participants === []): ?>
        <div class="empty-state mt-6"><span class="empty-state__icon"><i class="ph ph-user-list" aria-hidden="true"></i></span><strong>No participants found</strong><p>Adjust the filters or check again after registrations arrive.</p></div>
    <?php else: ?>
        <div class="organizer-table-wrap mt-6"><table class="organizer-table"><thead><tr><th>Participant</th><th>Registration</th><th>Payment</th><th>Ticket</th><th>Attendance</th></tr></thead><tbody><?php foreach ($participants as $participant): ?><tr><td id="participant-name-<?= e($participant['id']) ?>"><strong><?= e($participant['participant_name'] ?? '') ?></strong><small><?= e($participant['participant_email'] ?? '') ?></small></td><td><strong><?= e($participant['registration_number'] ?? '') ?></strong><small><?= e($statusLabels[$participant['registration_status'] ?? ''] ?? ucfirst((string) ($participant['registration_status'] ?? ''))) ?></small></td><td><?= e($statusLabels[$participant['payment_status'] ?? 'none'] ?? ucfirst((string) ($participant['payment_status'] ?? 'none'))) ?></td><td><strong><?= e($participant['ticket_number'] ?? 'Not issued') ?></strong><small><?= e($statusLabels[$participant['ticket_status'] ?? 'none'] ?? ucfirst((string) ($participant['ticket_status'] ?? 'none'))) ?></small></td><td><strong><?= e($statusLabels[$participant['attendance_status'] ?? 'not_checked_in'] ?? ucfirst((string) ($participant['attendance_status'] ?? 'not_checked_in'))) ?></strong><?php if (!empty($participant['scanned_at'])): ?><small><?= e($participant['scanned_at']) ?></small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php if ($lastPage > 1): ?><nav class="mt-6 flex items-center justify-between gap-4" aria-label="Participant pages"><span>Page <?= e($page) ?> of <?= e($lastPage) ?></span><div class="flex gap-2"><?php if ($page > 1): ?><a class="button button--quiet button--compact" href="?<?= e($query(['page' => $page - 1])) ?>">Previous</a><?php endif; ?><?php if ($page < $lastPage): ?><a class="button button--quiet button--compact" href="?<?= e($query(['page' => $page + 1])) ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
    <?php endif; ?>
</section>
