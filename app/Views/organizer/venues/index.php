<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-buildings" aria-hidden="true"></i><span>Venue management</span></p>
        <h1>Your venues</h1>
        <p>Keep reusable location details ready for event drafts.</p>
    </div>
    <a class="button button--primary" href="/organizer/venues/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create venue</span></a>
</div>

<section class="dashboard-panel organizer-list-panel mt-8" aria-labelledby="venue-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-map-pin-line" aria-hidden="true"></i></span><div><h2 id="venue-list-heading">Venue list</h2><p>Only locations owned by this organizer account appear here.</p></div></div>
    <?php if ($venues === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-buildings" aria-hidden="true"></i></span><strong>No venues yet</strong><p>Add a location once, then reuse it across event drafts.</p><a class="button button--primary" href="/organizer/venues/create">Create venue</a></div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="organizer-table">
                <caption class="sr-only">Organizer venues</caption>
                <thead><tr><th scope="col">Venue</th><th scope="col">Location</th><th scope="col">Capacity</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody><?php foreach ($venues as $venue): ?><tr><td data-label="Venue"><strong><?= e($venue['name']) ?></strong><small><?= e($venue['address_line'] ?? '') ?></small></td><td data-label="Location"><?= e($venue['city'] ?? '') ?>, <?= e($venue['country'] ?? '') ?></td><td data-label="Capacity"><?= $venue['capacity'] === null ? 'Not set' : e($venue['capacity']) ?></td><td class="organizer-table__action"><a class="text-link" href="/organizer/venues/<?= e($venue['id']) ?>/edit">Edit <i class="ph ph-pencil-simple" aria-hidden="true"></i></a></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
