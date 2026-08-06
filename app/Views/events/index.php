<section class="events-index">
    <div class="page-shell">
        <div class="events-index__heading">
            <div class="max-w-3xl">
                <p class="eyebrow"><i class="ph ph-compass" aria-hidden="true"></i><span>Discover what is nearby</span></p>
                <h1 class="section-title">Events that move the week forward.</h1>
                <p class="section-copy">Browse curated previews for workshops, music, culture, and community gatherings across Dhaka.</p>
            </div>
            <div class="events-index__count"><strong><?= count($featuredEvents) ?></strong><span>curated events</span></div>
        </div>

        <form class="event-search-panel" action="/events" method="get" role="search" aria-label="Search events">
            <?php if ($category !== ''): ?>
                <input type="hidden" name="category" value="<?= e($category) ?>">
            <?php endif; ?>
            <div class="event-search-panel__field">
                <label for="event-search">What are you looking for?</label>
                <div><i class="ph ph-magnifying-glass" aria-hidden="true"></i><input id="event-search" name="search" type="search" value="<?= e($search) ?>" placeholder="Try design or music"></div>
            </div>
            <button class="button button--primary" type="submit"><span>Search events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
        </form>

        <?php if (($search !== '' || $category !== '') && $featuredEvents !== []): ?>
            <p class="search-preview"><i class="ph ph-check-circle" aria-hidden="true"></i><span>
                <?php if ($search !== '' && $category !== ''): ?>
                    Found <?= count($featuredEvents) ?> curated <?= count($featuredEvents) === 1 ? 'preview' : 'previews' ?> for “<?= e($search) ?>” in <strong><?= e($categoryLabel) ?> events</strong>.
                <?php elseif ($search !== ''): ?>
                    Found <?= count($featuredEvents) ?> curated <?= count($featuredEvents) === 1 ? 'preview' : 'previews' ?> for “<?= e($search) ?>”.
                <?php else: ?>
                    Showing <?= count($featuredEvents) ?> curated <?= count($featuredEvents) === 1 ? 'preview' : 'previews' ?> in <strong><?= e($categoryLabel) ?> events</strong>.
                <?php endif; ?>
            </span></p>
        <?php endif; ?>

        <?php if ($featuredEvents === []): ?>
            <div class="event-empty-state">
                <span><i class="ph ph-calendar-x" aria-hidden="true"></i></span>
                <h2>No preview events match<?= $search !== '' ? ' “' . e($search) . '”' : '' ?><?= $category !== '' ? ' in ' . e($categoryLabel) : '' ?>.</h2>
                <p>Try a broader topic, category, or Dhaka location.</p>
                <a class="button button--quiet button--compact" href="/events"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i><span>Clear search and filters</span></a>
            </div>
        <?php else: ?>
            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <?php foreach ($featuredEvents as $event): ?>
                    <article class="event-card" data-reveal>
                        <div class="event-card__media">
                            <img src="<?= e($event['image']) ?>" alt="<?= e($event['alt']) ?>" width="1400" height="1086">
                        </div>
                        <div class="event-card__body">
                            <p class="event-card__category"><?= e($event['category']) ?></p>
                            <h2><?= e($event['title']) ?></h2>
                            <div class="event-card__details">
                                <div><i class="ph ph-calendar-blank" aria-hidden="true"></i><span><small>Date</small><time datetime="<?= e($event['datetime']) ?>"><?= e($event['date']) ?> at <?= e($event['time']) ?></time></span></div>
                                <div><i class="ph ph-map-pin" aria-hidden="true"></i><span><small>Place</small><address><?= e($event['venue']) ?></address></span></div>
                            </div>
                            <div class="event-card__footer"><strong><?= e($event['price']) ?></strong><span class="preview-badge">Preview</span></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
