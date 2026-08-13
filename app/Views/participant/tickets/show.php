<?php
$status = (string) ($ticket['ticket_status'] ?? '');
$statusCopy = match ($status) {
    'used' => 'This ticket has been checked in.',
    'cancelled' => 'This ticket is cancelled and cannot be used for entry.',
    default => 'Present this code at event check-in.',
};
$statusLabel = match ($status) {
    'used' => 'Checked in',
    'cancelled' => 'Cancelled',
    'valid' => 'Valid',
    default => oems_status_label($status),
};
$canUseArtifacts = $status !== 'cancelled';
$hasQr = $canUseArtifacts && !empty($ticket['has_qr_artifact']);
$hasPdf = $canUseArtifacts && !empty($ticket['has_pdf_artifact']);
?>
<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span><?= e($ticket['ticket_number']) ?></span></p><h1><?= e($ticket['event_title']) ?></h1><p><?= e($statusCopy) ?></p></div>
    <a class="button button--quiet" href="/participant/tickets"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>All tickets</span></a>
</header>

<div class="mt-8 grid min-w-0 gap-6 lg:grid-cols-[minmax(280px,420px)_minmax(0,1fr)] lg:items-start">
    <section class="ticket-panel dashboard-panel min-w-0 text-center" aria-labelledby="ticket-code-heading">
        <h2 id="ticket-code-heading" class="text-xl font-bold">Check-in code</h2>
        <?php if ($hasQr): ?>
            <div class="qr-frame"><img src="/participant/tickets/<?= e($ticket['id']) ?>/qr" alt="QR code for ticket <?= e($ticket['ticket_number']) ?>" width="280" height="280"></div>
        <?php elseif ($canUseArtifacts): ?>
            <div class="ticket-artifact-unavailable mt-5" role="status" aria-label="QR code unavailable"><i class="ph ph-qr-code" aria-hidden="true"></i><p>The QR code is not available for this ticket. Use the printed ticket number at check-in.</p></div>
        <?php endif; ?>
        <p class="mt-4 break-all text-sm font-semibold"><?= e($ticket['ticket_number']) ?></p>
        <?php if ($hasPdf): ?>
            <a class="button button--primary mt-5 w-full" href="/participant/tickets/<?= e($ticket['id']) ?>/pdf"><i class="ph ph-download-simple" aria-hidden="true"></i><span>Download PDF ticket</span></a>
        <?php elseif ($canUseArtifacts): ?>
            <p class="mt-5 text-sm text-muted" role="status" aria-label="PDF ticket unavailable">The PDF download is not available. Keep the ticket number above for check-in.</p>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel min-w-0" aria-labelledby="ticket-details-heading">
        <h2 id="ticket-details-heading" class="text-xl font-bold">Ticket details</h2>
        <dl class="status-list mt-5">
            <div><dt>Status</dt><dd><span class="status-chip status-chip--<?= e(status_modifier($status, 'ticket')) ?>" aria-label="Ticket status: <?= e($statusLabel) ?>"><?= e($statusLabel) ?></span></dd></div>
            <div><dt>Event</dt><dd><a class="text-link" href="/events/<?= e($ticket['event_slug']) ?>"><?= e($ticket['event_title']) ?></a></dd></div>
            <div><dt>Schedule</dt><dd><?= e($ticket['event_start_display']) ?></dd></div>
            <div><dt>Registration</dt><dd><a class="text-link break-all" href="/participant/registrations/<?= e($ticket['registration_id']) ?>"><?= e($ticket['registration_number']) ?></a></dd></div>
            <div><dt>Issued</dt><dd><?= e($ticket['issued_display']) ?></dd></div>
        </dl>
        <?php if ($canUseArtifacts): ?>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a class="button button--primary" href="/participant/registrations/<?= e($ticket['registration_id']) ?>/calendar.ics"><i class="ph ph-calendar-plus" aria-hidden="true"></i><span>Download calendar</span></a>
                <a class="button button--quiet" href="/participant/registrations/<?= e($ticket['registration_id']) ?>/google-calendar" target="_blank" rel="noopener noreferrer"><i class="ph ph-arrow-square-out" aria-hidden="true"></i><span>Google Calendar</span></a>
            </div>
        <?php endif; ?>
    </section>
</div>
