<?php
$registration = is_array($metrics['registration'] ?? null) ? $metrics['registration'] : [];
$payment = is_array($metrics['payment'] ?? null) ? $metrics['payment'] : [];
$ticket = is_array($metrics['ticket'] ?? null) ? $metrics['ticket'] : [];
$reviews = is_array($metrics['reviews'] ?? null) ? $metrics['reviews'] : [];
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>Participant workspace</span></p>
        <h1>Good to see you, <?= e(explode(' ', $currentUser['name'])[0] ?? 'there') ?>.</h1>
        <p>Track registration, payment, ticket, attendance, and review activity.</p>
    </div>
    <a class="button button--primary" href="/events"><span>Find an event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
</div>

<div class="dashboard-metric-grid mt-8">
    <article aria-label="Active registrations: <?= e((int) ($registration['active'] ?? 0)) ?>"><div class="metric-card__top"><span>Active registrations</span><i class="ph ph-list-checks" aria-hidden="true"></i></div><strong><?= e((int) ($registration['active'] ?? 0)) ?></strong><small>Pending and confirmed</small></article>
    <article aria-label="Pending registrations: <?= e((int) ($registration['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending registrations</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($registration['pending'] ?? 0)) ?></strong><small>Awaiting settlement</small></article>
    <article aria-label="Confirmed registrations: <?= e((int) ($registration['confirmed'] ?? 0)) ?>"><div class="metric-card__top"><span>Confirmed registrations</span><i class="ph ph-check-circle" aria-hidden="true"></i></div><strong><?= e((int) ($registration['confirmed'] ?? 0)) ?></strong><small>Ready for attendance</small></article>
    <article aria-label="Pending payments: <?= e((int) ($payment['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending payments</span><i class="ph ph-receipt" aria-hidden="true"></i></div><strong><?= e((int) ($payment['pending'] ?? 0)) ?></strong><small>Awaiting verification</small></article>
    <article aria-label="Paid payments: <?= e((int) ($payment['paid'] ?? 0)) ?>"><div class="metric-card__top"><span>Paid payments</span><i class="ph ph-bank" aria-hidden="true"></i></div><strong><?= e((int) ($payment['paid'] ?? 0)) ?></strong><small>Verified settlements</small></article>
    <article aria-label="Paid total: BDT <?= e($payment['paid_total'] ?? '0.00') ?>"><div class="metric-card__top"><span>Paid total</span><i class="ph ph-currency-circle-dollar" aria-hidden="true"></i></div><strong>BDT <?= e($payment['paid_total'] ?? '0.00') ?></strong><small>Verified payment value</small></article>
    <article aria-label="Issued tickets: <?= e((int) ($ticket['issued'] ?? 0)) ?>"><div class="metric-card__top"><span>Issued tickets</span><i class="ph ph-ticket" aria-hidden="true"></i></div><strong><?= e((int) ($ticket['issued'] ?? 0)) ?></strong><small>Valid or used</small></article>
    <article aria-label="Checked in: <?= e((int) ($ticket['checked_in'] ?? 0)) ?>"><div class="metric-card__top"><span>Checked in</span><i class="ph ph-scan" aria-hidden="true"></i></div><strong><?= e((int) ($ticket['checked_in'] ?? 0)) ?></strong><small>Attendance recorded</small></article>
    <article aria-label="Submitted reviews: <?= e((int) ($reviews['submitted'] ?? 0)) ?>"><div class="metric-card__top"><span>Submitted reviews</span><i class="ph ph-star" aria-hidden="true"></i></div><strong><?= e((int) ($reviews['submitted'] ?? 0)) ?></strong><small><?= e((int) ($reviews['pending'] ?? 0)) ?> awaiting moderation</small></article>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading">
            <span class="dashboard-panel__icon"><i class="ph ph-calendar-dots" aria-hidden="true"></i></span><div><h2>Registration workspace</h2><p>Open your registration history for current fulfillment status.</p></div>
        </div>
        <div class="empty-state">
            <span class="empty-state__icon"><i class="ph ph-calendar-plus" aria-hidden="true"></i></span>
            <strong><?= (int) ($registration['active'] ?? 0) === 0 ? 'Your calendar is open' : 'Your registrations are ready to review' ?></strong>
            <p><?= (int) ($registration['active'] ?? 0) === 0 ? 'Browse local events and choose one worth showing up for.' : 'Check payment, ticket, and cancellation status in one place.' ?></p>
            <a class="button button--quiet button--compact" href="/participant/registrations"><span>View registrations</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-lightning" aria-hidden="true"></i></span><div><h2>Quick actions</h2><p>Keep your account ready.</p></div></div>
        <div class="action-list">
            <a href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span><strong>Browse events</strong><small>Find something new</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            <a href="/settings/password"><i class="ph ph-shield-check" aria-hidden="true"></i><span><strong>Account security</strong><small>Update your password</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
</div>
