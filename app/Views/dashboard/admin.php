<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker">Super Admin</p>
        <h1>Platform overview</h1>
        <p>Monitor platform activity across users, organizers, and events.</p>
    </div>
    <a class="button button--quiet" href="/settings/password">Security settings</a>
</div>

<div class="dashboard-metric-grid mt-8">
    <article><span>Users</span><strong><?= e($metrics['users']) ?></strong><small>Registered accounts</small></article>
    <article><span>Organizers</span><strong><?= e($metrics['organizers']) ?></strong><small>Organizer profiles</small></article>
    <article><span>Events</span><strong><?= e($metrics['events']) ?></strong><small>Event records</small></article>
</div>
