<section class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Participant workspace</span></p>
        <h1>Saved events</h1>
        <p>Keep upcoming ideas in one place and revisit events that are no longer available.</p>
    </div>
    <a class="button button--quiet" href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
</section>

<?php if ($favorites === []): ?>
    <section class="event-empty-state mt-8" aria-labelledby="saved-events-empty-heading">
        <span><i class="ph ph-bookmark-simple" aria-hidden="true"></i></span>
        <h2 id="saved-events-empty-heading">No saved events yet</h2>
        <p>Save an event from its details page to find it here later.</p>
        <a class="button button--primary button--compact" href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
    </section>
<?php else: ?>
    <div class="mt-8 grid gap-5 lg:grid-cols-2">
        <?php foreach ($favorites as $favorite): ?>
            <article class="dashboard-panel favorite-history<?= empty($favorite['is_available']) ? ' favorite-history--unavailable' : '' ?>">
                <div class="favorite-history__heading">
                    <div>
                        <p class="event-card__category"><?= e($favorite['category_name'] ?? 'Saved event') ?></p>
                        <h2><?= e($favorite['title']) ?></h2>
                    </div>
                    <?php if (empty($favorite['is_available'])): ?><span class="status-badge status-badge--muted">Unavailable</span><?php endif; ?>
                </div>
                <dl class="favorite-history__details">
                    <div><dt>Date</dt><dd><?= e($favorite['start_display']) ?></dd></div>
                    <div><dt>Price</dt><dd><?= e($favorite['price_display']) ?></dd></div>
                    <?php if (empty($favorite['is_available'])): ?><?php $eventStatus = (string) ($favorite['event_status'] ?? ''); ?><div><dt>Status</dt><dd><span class="status-chip status-chip--<?= e(status_modifier($eventStatus, 'event')) ?>"><?= e(oems_status_label($eventStatus)) ?></span></dd></div><?php endif; ?>
                </dl>
                <div class="favorite-history__actions">
                    <?php if (!empty($favorite['is_available'])): ?><a class="text-link" href="/events/<?= e($favorite['slug']) ?>"><span>View event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
                    <form action="/participant/favorites/<?= e($favorite['event_id']) ?>/remove" method="post" data-form-kind="action">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="return_to" value="/participant/favorites<?= (int) $pagination['page'] > 1 ? '?page=' . (int) $pagination['page'] : '' ?>">
                        <button class="button button--quiet button--compact" type="submit" aria-label="Remove <?= e($favorite['title']) ?> from saved events" data-submit-label="Removing…"><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span data-submit-text>Remove</span></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ((int) $pagination['last_page'] > 1): ?>
        <nav class="favorite-pagination" aria-label="Saved event pages">
            <span>Page <?= e($pagination['page']) ?> of <?= e($pagination['last_page']) ?></span>
            <div>
                <?php if ((int) $pagination['page'] > 1): ?><a class="button button--quiet button--compact" href="/participant/favorites?page=<?= (int) $pagination['page'] - 1 ?>">Previous</a><?php endif; ?>
                <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?><a class="button button--quiet button--compact" href="/participant/favorites?page=<?= (int) $pagination['page'] + 1 ?>">Next</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>
