<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-shield-star" aria-hidden="true"></i><span>Super Admin workspace</span></p>
        <h1>Platform overview</h1>
        <p>Monitor platform activity across users, organizers, and events.</p>
    </div>
    <a class="button button--quiet" href="/settings/password"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Security settings</span></a>
</div>

<div class="dashboard-metric-grid mt-8">
    <article aria-label="Users: <?= e($metrics['users']) ?>"><div class="metric-card__top"><span>Users</span><i class="ph ph-users-three" aria-hidden="true"></i></div><strong><?= e($metrics['users']) ?></strong><small>Registered accounts</small></article>
    <article aria-label="Organizers: <?= e($metrics['organizers']) ?>"><div class="metric-card__top"><span>Organizers</span><i class="ph ph-microphone-stage" aria-hidden="true"></i></div><strong><?= e($metrics['organizers']) ?></strong><small>Organizer profiles</small></article>
    <article aria-label="Events: <?= e($metrics['events']) ?>"><div class="metric-card__top"><span>Events</span><i class="ph ph-calendar-dots" aria-hidden="true"></i></div><strong><?= e($metrics['events']) ?></strong><small>Event records</small></article>
    <article aria-label="Pending review: <?= e((int) ($metrics['pending_reviews'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending review</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($metrics['pending_reviews'] ?? 0)) ?></strong><small>Awaiting moderation</small></article>
</div>

<section class="dashboard-panel mt-8" aria-labelledby="review-queue-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-shield-chevron" aria-hidden="true"></i></span><div><h2 id="review-queue-heading">Event review queue</h2><p>Review organizer submissions and move approved events toward publication.</p></div></div>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm leading-6 text-[var(--ink-muted)]"><strong class="text-[var(--ink)]"><?= e((int) ($metrics['pending_reviews'] ?? 0)) ?></strong> <?= (int) ($metrics['pending_reviews'] ?? 0) === 1 ? 'event is' : 'events are' ?> waiting for review.</p>
        <a class="button button--primary" href="/admin/events?status=pending"><i class="ph ph-clipboard-text" aria-hidden="true"></i><span>Review events</span></a>
    </div>
</section>
