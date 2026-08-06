<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker">Participant</p>
        <h1>Good to see you, <?= e(explode(' ', $currentUser['name'])[0] ?? 'there') ?>.</h1>
        <p>Your event life will come together here as you register.</p>
    </div>
    <a class="button button--primary" href="/events">Find an event</a>
</div>

<div class="dashboard-metric-grid mt-8">
    <article><span>Upcoming events</span><strong>0</strong><small>No registrations yet</small></article>
    <article><span>Available tickets</span><strong>0</strong><small>Tickets appear after registration</small></article>
    <article><span>Unread updates</span><strong>0</strong><small>You are all caught up</small></article>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading">
            <div><h2>Upcoming events</h2><p>Your next registrations will appear here.</p></div>
        </div>
        <div class="empty-state">
            <strong>Your calendar is open</strong>
            <p>Browse local events and choose one worth showing up for.</p>
            <a class="text-link" href="/events">Explore events</a>
        </div>
    </section>
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading"><div><h2>Quick actions</h2><p>Keep your account ready.</p></div></div>
        <div class="action-list">
            <a href="/events"><span>Browse events</span><small>Find something new</small></a>
            <a href="/settings/password"><span>Account security</span><small>Update your password</small></a>
        </div>
    </section>
</div>

