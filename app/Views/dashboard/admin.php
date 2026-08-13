<?php
$registration = is_array($metrics['registration'] ?? null) ? $metrics['registration'] : [];
$payment = is_array($metrics['payment'] ?? null) ? $metrics['payment'] : [];
$ticket = is_array($metrics['ticket'] ?? null) ? $metrics['ticket'] : [];
$reviews = is_array($metrics['reviews'] ?? null) ? $metrics['reviews'] : [];
$pendingOrganizers = is_array($pendingOrganizers ?? null) ? $pendingOrganizers : [];
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-shield-star" aria-hidden="true"></i><span>Super Admin workspace</span></p>
        <h1>Platform overview</h1>
        <p>Monitor platform activity across users, organizers, and events.</p>
    </div>
    <a class="button button--quiet" href="/settings/password"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Security settings</span></a>
</div>

<div class="dashboard-metric-grid mt-6">
    <article aria-label="Active registrations: <?= e((int) ($registration['active'] ?? 0)) ?>"><div class="metric-card__top"><span>Active registrations</span><i class="ph ph-list-checks" aria-hidden="true"></i></div><strong><?= e((int) ($registration['active'] ?? 0)) ?></strong><small><?= e((int) ($registration['pending'] ?? 0)) ?> pending, <?= e((int) ($registration['confirmed'] ?? 0)) ?> confirmed</small></article>
    <article aria-label="Pending payments: <?= e((int) ($payment['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending payments</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($payment['pending'] ?? 0)) ?></strong><small>Awaiting settlement review</small></article>
    <article aria-label="Paid total: BDT <?= e($payment['paid_total'] ?? '0.00') ?>"><div class="metric-card__top"><span>Paid total</span><i class="ph ph-bank" aria-hidden="true"></i></div><strong>BDT <?= e($payment['paid_total'] ?? '0.00') ?></strong><small><?= e((int) ($payment['paid'] ?? 0)) ?> verified payments</small></article>
    <article aria-label="Issued tickets: <?= e((int) ($ticket['issued'] ?? 0)) ?>"><div class="metric-card__top"><span>Issued tickets</span><i class="ph ph-ticket" aria-hidden="true"></i></div><strong><?= e((int) ($ticket['issued'] ?? 0)) ?></strong><small>Valid or used tickets</small></article>
    <article aria-label="Checked in: <?= e((int) ($ticket['checked_in'] ?? 0)) ?>"><div class="metric-card__top"><span>Checked in</span><i class="ph ph-scan" aria-hidden="true"></i></div><strong><?= e((int) ($ticket['checked_in'] ?? 0)) ?></strong><small>Recorded attendance</small></article>
    <article aria-label="Pending participant reviews: <?= e((int) ($reviews['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending participant reviews</span><i class="ph ph-chat-centered-text" aria-hidden="true"></i></div><strong><?= e((int) ($reviews['pending'] ?? 0)) ?></strong><small><?= e((int) ($reviews['published'] ?? 0)) ?> published</small></article>
</div>

<div class="dashboard-metric-grid mt-8">
    <article aria-label="Users: <?= e($metrics['users']) ?>"><div class="metric-card__top"><span>Users</span><i class="ph ph-users-three" aria-hidden="true"></i></div><strong><?= e($metrics['users']) ?></strong><small>Registered accounts</small></article>
    <article aria-label="Organizers: <?= e($metrics['organizers']) ?>"><div class="metric-card__top"><span>Organizers</span><i class="ph ph-microphone-stage" aria-hidden="true"></i></div><strong><?= e($metrics['organizers']) ?></strong><small>Organizer profiles</small></article>
    <article aria-label="Events: <?= e($metrics['events']) ?>"><div class="metric-card__top"><span>Events</span><i class="ph ph-calendar-dots" aria-hidden="true"></i></div><strong><?= e($metrics['events']) ?></strong><small>Event records</small></article>
    <article aria-label="Pending review: <?= e((int) ($metrics['pending_reviews'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending review</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($metrics['pending_reviews'] ?? 0)) ?></strong><small>Awaiting moderation</small></article>
    <article aria-label="Pending organizers: <?= e((int) ($metrics['pending_organizers'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending organizers</span><i class="ph ph-buildings" aria-hidden="true"></i></div><strong><?= e((int) ($metrics['pending_organizers'] ?? 0)) ?></strong><small>Awaiting organization review</small></article>
</div>

<section class="dashboard-panel mt-8" aria-labelledby="organizer-review-queue-heading">
    <div class="dashboard-panel__heading">
        <span class="dashboard-panel__icon"><i class="ph ph-buildings" aria-hidden="true"></i></span>
        <div><h2 id="organizer-review-queue-heading">Organizer approval queue</h2><p>Check account readiness and organization evidence before deciding.</p></div>
    </div>
    <?php if ($pendingOrganizers === []): ?>
        <div class="organizer-action-note mt-6"><i class="ph ph-check-circle" aria-hidden="true"></i><span>No organizer applications are waiting for review.</span></div>
    <?php else: ?>
        <ul class="approval-queue mt-6">
            <?php foreach ($pendingOrganizers as $pendingOrganizer): ?>
                <?php
                $pendingOrganizerId = (int) ($pendingOrganizer['id'] ?? 0);
                $organizationName = (string) ($pendingOrganizer['organization_name'] ?? 'Organizer application');
                $applicationReady = ($pendingOrganizer['user_status'] ?? null) === 'active' && !empty($pendingOrganizer['email_verified_at']);
                ?>
                <li>
                    <div class="approval-queue__identity">
                        <span class="approval-queue__icon" aria-hidden="true"><i class="ph ph-buildings"></i></span>
                        <div><strong><?= e($organizationName) ?></strong><span><?= e($pendingOrganizer['contact_name'] ?? 'Unknown contact') ?> · <?= e($pendingOrganizer['created_at'] ?? 'Application date unavailable') ?></span></div>
                    </div>
                    <div class="approval-queue__action">
                        <span class="status-badge status-badge--<?= e(status_modifier($applicationReady ? 'info' : 'warning', 'tone')) ?>"><i class="ph <?= $applicationReady ? 'ph-check-circle' : 'ph-warning-circle' ?>" aria-hidden="true"></i><?= $applicationReady ? 'Ready to review' : 'Email not verified' ?></span>
                        <a class="button button--quiet button--compact" href="/admin/organizers/<?= e($pendingOrganizerId) ?>" aria-label="Review <?= e($organizationName) ?>">Review <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="mt-5"><a class="text-link" href="/admin/organizers?approval_status=pending">View all pending organizers <i class="ph ph-arrow-right" aria-hidden="true"></i></a></p>
</section>

<section class="dashboard-panel mt-6" aria-labelledby="review-queue-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-shield-chevron" aria-hidden="true"></i></span><div><h2 id="review-queue-heading">Event review queue</h2><p>Review organizer submissions and move approved events toward publication.</p></div></div>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm leading-6 text-[var(--ink-muted)]"><strong class="text-[var(--ink)]"><?= e((int) ($metrics['pending_reviews'] ?? 0)) ?></strong> <?= (int) ($metrics['pending_reviews'] ?? 0) === 1 ? 'event is' : 'events are' ?> waiting for review.</p>
        <a class="button button--primary" href="/admin/events?status=pending"><i class="ph ph-clipboard-text" aria-hidden="true"></i><span>Review events</span></a>
    </div>
</section>

<section class="dashboard-panel mt-6" aria-labelledby="payment-queue-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-receipt" aria-hidden="true"></i></span><div><h2 id="payment-queue-heading">Payment review queue</h2><p>Verify submitted references or reject them and release the reserved seat.</p></div></div>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-4"><p class="text-sm leading-6 text-[var(--ink-muted)]"><strong class="text-[var(--ink)]"><?= e((int) ($payment['pending'] ?? 0)) ?></strong> <?= (int) ($payment['pending'] ?? 0) === 1 ? 'payment is' : 'payments are' ?> waiting for review.</p><a class="button button--primary" href="/admin/payments?status=pending"><i class="ph ph-receipt" aria-hidden="true"></i><span>Review payments</span></a></div>
</section>
