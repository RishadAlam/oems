<?php
$status = (string) $ticket['ticket_status'];
$statusCopy = match ($status) {
    'used' => 'This ticket has been checked in.',
    'cancelled' => 'This ticket is cancelled and cannot be used for entry.',
    default => 'Present this code at event check-in.',
};
?>
<header class="dashboard-page-header">
    <div><p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span><?= e($ticket['ticket_number']) ?></span></p><h1><?= e($ticket['event_title']) ?></h1><p><?= e($statusCopy) ?></p></div>
    <a class="button button--quiet" href="/participant/tickets"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>All tickets</span></a>
</header>

<div class="mt-8 grid gap-6 lg:grid-cols-[minmax(280px,420px)_minmax(0,1fr)] lg:items-start">
    <section class="dashboard-panel text-center" aria-labelledby="ticket-code-heading">
        <h2 id="ticket-code-heading" class="text-xl font-bold">Check-in code</h2>
        <?php if ($status !== 'cancelled'): ?><img class="mx-auto mt-5 w-full max-w-[280px] rounded-[18px] border border-[var(--line)] bg-white p-4" src="/participant/tickets/<?= e($ticket['id']) ?>/qr" alt="QR code for ticket <?= e($ticket['ticket_number']) ?>" width="280" height="280"><?php endif; ?>
        <p class="mt-4 break-all text-sm font-semibold"><?= e($ticket['ticket_number']) ?></p>
        <?php if ($status !== 'cancelled'): ?><a class="button button--primary mt-5 w-full" href="/participant/tickets/<?= e($ticket['id']) ?>/pdf"><i class="ph ph-download-simple" aria-hidden="true"></i><span>Download PDF ticket</span></a><?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="ticket-details-heading">
        <h2 id="ticket-details-heading" class="text-xl font-bold">Ticket details</h2>
        <dl class="status-list mt-5">
            <div><dt>Status</dt><dd><span class="status-chip status-chip--<?= e($status) ?>"><?= e(ucfirst($status)) ?></span></dd></div>
            <div><dt>Event</dt><dd><a class="text-link" href="/events/<?= e($ticket['event_slug']) ?>"><?= e($ticket['event_title']) ?></a></dd></div>
            <div><dt>Schedule</dt><dd><?= e($ticket['event_start_display']) ?></dd></div>
            <div><dt>Registration</dt><dd><a class="text-link" href="/participant/registrations/<?= e($ticket['registration_id']) ?>"><?= e($ticket['registration_number']) ?></a></dd></div>
            <div><dt>Issued</dt><dd><?= e($ticket['issued_display']) ?></dd></div>
        </dl>
    </section>
</div>
