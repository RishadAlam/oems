<?php
$registration = is_array($metrics['registration'] ?? null) ? $metrics['registration'] : [];
$payment = is_array($metrics['payment'] ?? null) ? $metrics['payment'] : [];
$ticket = is_array($metrics['ticket'] ?? null) ? $metrics['ticket'] : [];
$reviews = is_array($metrics['reviews'] ?? null) ? $metrics['reviews'] : [];
$workspace = is_array($workspace ?? null) ? $workspace : [];
$upcoming = is_array($workspace['upcoming'] ?? null) ? $workspace['upcoming'] : [];
$recentTickets = is_array($workspace['tickets'] ?? null) ? $workspace['tickets'] : [];
$recentNotifications = is_array($workspace['recent_notifications'] ?? null) ? $workspace['recent_notifications'] : [];
$favoriteCount = (int) ($workspace['favorite_count'] ?? 0);
$reviewActions = (int) ($workspace['review_actions'] ?? 0);
$unreadNotifications = (int) ($unreadNotifications ?? 0);
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>Participant workspace</span></p>
        <h1>Good to see you, <?= e(explode(' ', $currentUser['name'])[0] ?? 'there') ?>.</h1>
        <p>Track registration, payment, ticket, attendance, and review activity.</p>
    </div>
    <a class="button button--primary" href="/events"><span>Find an event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading">
            <span class="dashboard-panel__icon"><i class="ph ph-ticket" aria-hidden="true"></i></span><div><h2>Recent tickets</h2><p>Your latest issued tickets.</p></div>
        </div>
        <?php if ($recentTickets === []): ?>
            <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-ticket" aria-hidden="true"></i></span><strong>No tickets yet</strong><p>Your confirmed registrations will appear here.</p><a class="button button--quiet button--compact" href="/participant/registrations"><span>View registrations</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
        <?php else: ?>
            <div class="grid gap-3">
                <?php foreach ($recentTickets as $item): ?>
                    <?php $ticketStatus = (string) ($item['ticket_status'] ?? 'valid'); ?>
                    <a class="flex items-center justify-between gap-4 rounded-[18px] border border-[var(--line)] p-4 transition hover:border-[var(--accent)]" href="/participant/tickets/<?= e((int) ($item['id'] ?? 0)) ?>"><span><strong class="block"><?= e($item['event_title'] ?? '') ?></strong><small class="text-[var(--ink-muted)]"><?= e($item['ticket_number'] ?? '') ?></small></span><span class="status-badge status-badge--<?= e($ticketStatus) ?>"><?= e(ucfirst(str_replace('_', ' ', $ticketStatus))) ?></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <?php endforeach; ?>
            </div>
            <a class="button button--quiet button--compact mt-5" href="/participant/tickets"><span>View tickets</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </section>
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading">
            <span class="dashboard-panel__icon"><i class="ph ph-bell" aria-hidden="true"></i></span><div><h2>Recent updates</h2><p>Registration, ticket, and review news.</p></div>
        </div>
        <?php if ($recentNotifications === []): ?>
            <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-bell" aria-hidden="true"></i></span><strong>You are up to date</strong><p>New account and event activity will appear here.</p><a class="button button--quiet button--compact" href="/participant/notifications"><span>View notifications</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
        <?php else: ?>
            <div class="grid gap-3">
                <?php foreach ($recentNotifications as $item): ?>
                    <?php
                    $actionUrl = (string) ($item['action_url'] ?? '');
                    if (preg_match('#^/participant/(?:registrations|tickets|reviews)(?:/[1-9][0-9]*)?$#', $actionUrl) !== 1) {
                        $actionUrl = '/participant/notifications';
                    }
                    ?>
                    <a class="flex items-center justify-between gap-4 rounded-[18px] border border-[var(--line)] p-4 transition hover:border-[var(--accent)]" href="<?= e($actionUrl) ?>"><span><strong class="block"><?= e($item['title'] ?? '') ?></strong><small class="text-[var(--ink-muted)]"><?= e($item['message'] ?? '') ?></small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <?php endforeach; ?>
            </div>
            <a class="button button--quiet button--compact mt-5" href="/participant/notifications"><span>View notifications</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </section>
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
            <span class="dashboard-panel__icon"><i class="ph ph-calendar-dots" aria-hidden="true"></i></span><div><h2>Upcoming registrations</h2><p>Your next confirmed and pending event activity.</p></div>
        </div>
        <?php if ($upcoming === []): ?>
            <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-calendar-plus" aria-hidden="true"></i></span><strong>Your calendar is open</strong><p>Browse local events and choose one worth showing up for.</p><a class="button button--quiet button--compact" href="/events"><span>Find an event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
        <?php else: ?>
            <div class="grid gap-3">
                <?php foreach ($upcoming as $item): ?>
                    <?php $paymentStatus = (string) ($item['payment_status'] ?? 'not_required'); ?>
                    <a class="flex items-center justify-between gap-4 rounded-[18px] border border-[var(--line)] p-4 transition hover:border-[var(--accent)]" href="/participant/registrations/<?= e((int) $item['id']) ?>"><span><strong class="block"><?= e($item['event_title'] ?? '') ?></strong><small class="text-[var(--ink-muted)]"><?= e($item['event_start_date'] ?? '') ?></small></span><span class="status-badge status-badge--<?= e($paymentStatus) ?>"><?= e(ucfirst(str_replace('_', ' ', $paymentStatus))) ?></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <?php endforeach; ?>
            </div>
            <a class="button button--quiet button--compact mt-5" href="/participant/registrations"><span>View registrations</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        <?php endif; ?>
    </section>
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-lightning" aria-hidden="true"></i></span><div><h2>Quick actions</h2><p>Keep your account ready.</p></div></div>
        <div class="action-list">
            <a href="/participant/favorites"><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span><strong><?= e($favoriteCount) ?> saved <?= $favoriteCount === 1 ? 'event' : 'events' ?></strong><small>Review your favorites</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            <a href="/participant/reviews"><i class="ph ph-star" aria-hidden="true"></i><span><strong><?= e($reviewActions) ?> <?= $reviewActions === 1 ? 'review' : 'reviews' ?> ready</strong><small>Share completed event feedback</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            <a href="/participant/notifications"><i class="ph ph-bell" aria-hidden="true"></i><span><strong><?= e($unreadNotifications) ?> unread updates</strong><small>Registration, ticket, and review news</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            <a href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span><strong>Browse events</strong><small>Find something new</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            <a href="/settings/password"><i class="ph ph-shield-check" aria-hidden="true"></i><span><strong>Account security</strong><small>Update your password</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
</div>
