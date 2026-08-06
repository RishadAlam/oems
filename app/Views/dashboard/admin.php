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
</div>
