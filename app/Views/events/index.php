<section class="section-space pt-12 lg:pt-16">
    <div class="page-shell">
        <div class="max-w-3xl">
            <h1 class="hero-title text-[clamp(2.8rem,7vw,5.6rem)]">Events that move the week forward.</h1>
            <p class="hero-copy max-w-2xl">Browse the first OEMS event previews. Full search and filters arrive with the Week 2 event module.</p>
        </div>

        <form class="event-search-panel" action="/events" method="get" role="search">
            <div>
                <label for="event-search">What are you looking for?</label>
                <input
                    id="event-search"
                    name="search"
                    type="search"
                    value="<?= e($search) ?>"
                    placeholder="Try design, music, or technology"
                >
            </div>
            <button class="button button--primary" type="submit">Search events</button>
        </form>

        <?php if ($search !== ''): ?>
            <p class="mt-6 text-sm text-[var(--ink-muted)]">Preview results for “<?= e($search) ?>”</p>
        <?php endif; ?>

        <div class="mt-10 grid gap-6 md:grid-cols-2">
            <?php foreach ($featuredEvents as $event): ?>
                <article class="event-card" data-reveal>
                    <div class="event-card__media">
                        <img src="<?= e($event['image']) ?>" alt="<?= e($event['alt']) ?>" width="1400" height="1086">
                    </div>
                    <div class="event-card__body">
                        <p class="event-card__category"><?= e($event['category']) ?></p>
                        <h2><?= e($event['title']) ?></h2>
                        <p class="mt-3 text-sm leading-6 text-[var(--ink-muted)]">
                            <?= e($event['date']) ?> at <?= e($event['time']) ?><br>
                            <?= e($event['venue']) ?>
                        </p>
                        <div class="mt-6 border-t border-[var(--line)] pt-4 text-sm font-semibold"><?= e($event['price']) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

