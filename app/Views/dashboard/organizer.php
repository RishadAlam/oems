<?php
$summary = is_array($summary ?? null) ? $summary : [];
$events = is_array($events ?? null) ? $events : [];
$transactionMetrics = is_array($transactionMetrics ?? null) ? $transactionMetrics : [];
$registrationMetrics = is_array($transactionMetrics['registration'] ?? null) ? $transactionMetrics['registration'] : [];
$paymentMetrics = is_array($transactionMetrics['payment'] ?? null) ? $transactionMetrics['payment'] : [];
$ticketMetrics = is_array($transactionMetrics['ticket'] ?? null) ? $transactionMetrics['ticket'] : [];
$reviewMetrics = is_array($transactionMetrics['reviews'] ?? null) ? $transactionMetrics['reviews'] : [];
$statusLabels = [
    'draft' => 'Draft',
    'pending' => 'Pending review',
    'approved' => 'Approved',
    'rejected' => 'Needs changes',
    'published' => 'Published',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>Organizer workspace</span></p>
        <h1>Build the room people remember.</h1>
        <p>Track your event lifecycle and open the next action from one workspace.</p>
    </div>
    <a class="button button--primary" href="/organizer/events/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create event</span></a>
</div>

<div class="dashboard-metric-grid mt-6">
    <article aria-label="Active registrations: <?= e((int) ($registrationMetrics['active'] ?? 0)) ?>"><div class="metric-card__top"><span>Active registrations</span><i class="ph ph-users-three" aria-hidden="true"></i></div><strong><?= e((int) ($registrationMetrics['active'] ?? 0)) ?></strong><small><?= e((int) ($registrationMetrics['pending'] ?? 0)) ?> pending, <?= e((int) ($registrationMetrics['confirmed'] ?? 0)) ?> confirmed</small></article>
    <article aria-label="Pending payments: <?= e((int) ($paymentMetrics['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending payments</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($paymentMetrics['pending'] ?? 0)) ?></strong><small>Awaiting administrator review</small></article>
    <article aria-label="Paid total: BDT <?= e($paymentMetrics['paid_total'] ?? '0.00') ?>"><div class="metric-card__top"><span>Paid total</span><i class="ph ph-bank" aria-hidden="true"></i></div><strong>BDT <?= e($paymentMetrics['paid_total'] ?? '0.00') ?></strong><small><?= e((int) ($paymentMetrics['paid'] ?? 0)) ?> verified payments</small></article>
    <article aria-label="Issued tickets: <?= e((int) ($ticketMetrics['issued'] ?? 0)) ?>"><div class="metric-card__top"><span>Issued tickets</span><i class="ph ph-ticket" aria-hidden="true"></i></div><strong><?= e((int) ($ticketMetrics['issued'] ?? 0)) ?></strong><small>Valid or used tickets</small></article>
    <article aria-label="Checked in: <?= e((int) ($ticketMetrics['checked_in'] ?? 0)) ?>"><div class="metric-card__top"><span>Checked in</span><i class="ph ph-scan" aria-hidden="true"></i></div><strong><?= e((int) ($ticketMetrics['checked_in'] ?? 0)) ?></strong><small>Recorded attendance</small></article>
    <article aria-label="Reviews awaiting reply: <?= e((int) ($reviewMetrics['awaiting_reply'] ?? 0)) ?>"><div class="metric-card__top"><span>Reviews awaiting reply</span><i class="ph ph-chat-centered-text" aria-hidden="true"></i></div><strong><?= e((int) ($reviewMetrics['awaiting_reply'] ?? 0)) ?></strong><small><?= e((int) ($reviewMetrics['published'] ?? 0)) ?> published reviews</small></article>
</div>

<div class="dashboard-metric-grid mt-8">
    <article aria-label="Total events: <?= e((int) ($summary['total'] ?? 0)) ?>"><div class="metric-card__top"><span>Total events</span><i class="ph ph-calendar-dots" aria-hidden="true"></i></div><strong><?= e((int) ($summary['total'] ?? 0)) ?></strong><small>Active event records</small></article>
    <article aria-label="Pending review: <?= e((int) ($summary['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending review</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($summary['pending'] ?? 0)) ?></strong><small>Waiting for administrator review</small></article>
    <article aria-label="Published events: <?= e((int) ($summary['published'] ?? 0)) ?>"><div class="metric-card__top"><span>Published events</span><i class="ph ph-calendar-check" aria-hidden="true"></i></div><strong><?= e((int) ($summary['published'] ?? 0)) ?></strong><small>Visible in public discovery</small></article>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
    <section class="dashboard-panel organizer-list-panel" aria-labelledby="recent-events-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-calendar-blank" aria-hidden="true"></i></span><div><h2 id="recent-events-heading">Recent events</h2><p>Your five most recent event records and their next actions.</p></div></div>
        <?php if ($events === []): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><i class="ph ph-note-pencil" aria-hidden="true"></i></span>
                <strong>Your first event starts with a clear idea</strong>
                <p>Create a draft, add its venue and media, then submit it for review.</p>
                <a class="button button--primary" href="/organizer/events/create">Create event</a>
            </div>
        <?php else: ?>
            <div class="organizer-table-wrap mt-6">
                <table class="organizer-table">
                    <caption class="sr-only">Recent organizer events and next actions</caption>
                    <thead><tr><th scope="col">Event</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Next action</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $eventId = (int) ($event['id'] ?? 0);
                        $status = (string) ($event['status'] ?? 'draft');
                        $editNext = in_array($status, ['draft', 'rejected'], true);
                        $publicNext = $status === 'published' && !empty($event['slug']);
                        $nextUrl = $editNext
                            ? '/organizer/events/' . $eventId . '/edit'
                            : ($publicNext
                                ? '/events/' . rawurlencode((string) $event['slug'])
                                : '/organizer/events/' . $eventId);
                        $nextLabel = $editNext ? 'Continue editing' : ($publicNext ? 'View public page' : 'View details');
                        ?>
                        <tr>
                            <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><?php if (!empty($event['start_date'])): ?><small><time datetime="<?= e(str_replace(' ', 'T', (string) $event['start_date'])) ?>"><?= e(date('M j, Y', strtotime((string) $event['start_date']))) ?></time></small><?php endif; ?></td>
                            <td data-label="Status"><span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span></td>
                            <td class="organizer-table__action"><a class="text-link" href="<?= e($nextUrl) ?>"><?= e($nextLabel) ?> <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="mt-5"><a class="text-link" href="/organizer/events">Manage all events <i class="ph ph-arrow-right" aria-hidden="true"></i></a></p>
    </section>
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-chart-line-up" aria-hidden="true"></i></span><div><h2>Fulfillment overview</h2><p>Current participant and revenue operations</p></div></div>
        <dl class="status-list">
            <div><dt><i class="ph ph-list-checks" aria-hidden="true"></i>Confirmed registrations</dt><dd><?= e((int) ($registrationMetrics['confirmed'] ?? 0)) ?></dd></div>
            <div><dt><i class="ph ph-receipt" aria-hidden="true"></i>Paid payments</dt><dd><?= e((int) ($paymentMetrics['paid'] ?? 0)) ?></dd></div>
            <div><dt><i class="ph ph-chat-centered-text" aria-hidden="true"></i>Published reviews</dt><dd><a class="text-link" href="/organizer/reviews"><?= e((int) ($reviewMetrics['published'] ?? 0)) ?></a></dd></div>
        </dl>
        <p class="mt-5 text-sm leading-6 text-[var(--ink-muted)]">Metrics come from owner-scoped registration, payment, ticket, attendance, and review aggregates.</p>
    </section>
</div>
