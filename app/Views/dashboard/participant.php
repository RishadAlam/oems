<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>Participant workspace</span></p>
        <h1>Good to see you, <?= e(explode(' ', $currentUser['name'])[0] ?? 'there') ?>.</h1>
        <p>Your event life will come together here as you register.</p>
    </div>
    <a class="button button--primary" href="/events"><span>Find an event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
</div>

<div class="dashboard-metric-grid mt-8">
    <article aria-label="Upcoming events: 0"><div class="metric-card__top"><span>Upcoming events</span><i class="ph ph-calendar-check" aria-hidden="true"></i></div><strong>0</strong><small>No registrations yet</small></article>
    <article aria-label="Available tickets: 0"><div class="metric-card__top"><span>Available tickets</span><i class="ph ph-ticket" aria-hidden="true"></i></div><strong>0</strong><small>Tickets appear after registration</small></article>
    <article aria-label="Unread updates: 0"><div class="metric-card__top"><span>Unread updates</span><i class="ph ph-bell" aria-hidden="true"></i></div><strong>0</strong><small>You are all caught up</small></article>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading">
            <span class="dashboard-panel__icon"><i class="ph ph-calendar-dots" aria-hidden="true"></i></span><div><h2>Upcoming events</h2><p>Your next registrations will appear here.</p></div>
        </div>
        <div class="empty-state">
            <span class="empty-state__icon"><i class="ph ph-calendar-plus" aria-hidden="true"></i></span>
            <strong>Your calendar is open</strong>
            <p>Browse local events and choose one worth showing up for.</p>
            <a class="button button--quiet button--compact" href="/events"><span>Explore events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
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
