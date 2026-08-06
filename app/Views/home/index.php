<section class="hero-section">
    <div class="page-shell hero-layout">
        <div class="hero-content" data-reveal>
            <p class="eyebrow"><i class="ph ph-sparkle" aria-hidden="true"></i><span>Events made for showing up</span></p>
            <h1 class="hero-title">Find your next <span>standout event.</span></h1>
            <p class="hero-copy">
                Discover workshops, talks, and gatherings across Dhaka, or give your own audience an event experience that feels effortless.
            </p>
            <div class="hero-actions">
                <a href="/events" class="button button--primary"><span>Explore events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <a href="/register?role=organizer" class="button button--quiet"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>Host an event</span></a>
            </div>

            <form class="hero-search" action="/events" method="get" role="search" aria-label="Search events">
                <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                <label class="sr-only" for="hero-search">Search events</label>
                <input id="hero-search" name="search" type="search" placeholder="Search events" autocomplete="off">
                <button class="button button--primary button--compact" type="submit"><span>Search</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
            </form>
        </div>

        <div class="hero-media" data-reveal>
            <img
                src="/assets/images/hero-events.webp"
                alt="A community listening to speakers at a contemporary event in Dhaka"
                width="1694"
                height="929"
                fetchpriority="high"
            >
            <div class="hero-media__badge"><i class="ph ph-map-pin" aria-hidden="true"></i><span>Curated in Dhaka</span></div>
            <div class="hero-media__note">
                <span>One place for every side of the room</span>
                <strong>Discover, host, and attend with confidence.</strong>
            </div>
        </div>
    </div>
</section>

<section class="category-rail" aria-label="Popular event categories">
    <div class="page-shell category-rail__grid">
        <a href="/events?category=technology"><i class="ph ph-cpu" aria-hidden="true"></i><span><strong>Technology</strong><small>Talks and meetups</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
        <a href="/events?category=arts-culture"><i class="ph ph-palette" aria-hidden="true"></i><span><strong>Arts and culture</strong><small>Live and local</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
        <a href="/events?category=business"><i class="ph ph-handshake" aria-hidden="true"></i><span><strong>Business</strong><small>Build your network</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
        <a href="/events?category=community"><i class="ph ph-users-three" aria-hidden="true"></i><span><strong>Community</strong><small>Meet your people</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
    </div>
</section>

<section class="section-space">
    <div class="page-shell">
        <div class="section-heading-row">
            <div class="max-w-2xl">
                <p class="eyebrow"><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>This week in Dhaka</span></p>
                <h2 class="section-title">A better plan for your weekend</h2>
                <p class="section-copy">Two fresh ways to learn, listen, and meet people nearby.</p>
            </div>
            <a class="text-link section-heading-row__link" href="/events"><span>Browse all events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
            <?php foreach ($featuredEvents as $index => $event): ?>
                <article class="event-card <?= $index === 0 ? 'event-card--wide' : '' ?>" data-reveal>
                    <a href="/events" class="event-card__media" aria-label="View <?= e($event['title']) ?>">
                        <img
                            src="<?= e($event['image']) ?>"
                            alt="<?= e($event['alt']) ?>"
                            width="1400"
                            height="1086"
                            loading="lazy"
                        >
                    </a>
                    <div class="event-card__body">
                        <p class="event-card__category"><?= e($event['category']) ?></p>
                        <h3><a href="/events"><?= e($event['title']) ?></a></h3>
                        <div class="event-card__details">
                            <div><i class="ph ph-calendar-blank" aria-hidden="true"></i><span><small>Date</small><time datetime="<?= e($event['datetime']) ?>"><?= e($event['date']) ?> at <?= e($event['time']) ?></time></span></div>
                            <div><i class="ph ph-map-pin" aria-hidden="true"></i><span><small>Place</small><address><?= e($event['venue']) ?></address></span></div>
                        </div>
                        <div class="event-card__footer">
                            <strong class="text-sm"><?= e($event['price']) ?></strong>
                            <a class="text-link" href="/events"><span>View event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="how-it-works" class="section-space pt-0">
    <div class="page-shell">
        <div class="how-grid">
            <div class="how-grid__intro">
                <p class="eyebrow eyebrow--inverse"><i class="ph ph-path" aria-hidden="true"></i><span>How OEMS works</span></p>
                <h2 class="section-title">From idea to full room</h2>
                <p class="section-copy">A clear path for organizers and participants, without the busywork.</p>
            </div>
            <div class="how-grid__steps">
                <article>
                    <span class="how-grid__icon"><i class="ph ph-note-pencil" aria-hidden="true"></i></span>
                    <div><h3>Publish with confidence</h3><p>Create the event, share the essentials, and send it for approval.</p></div>
                </article>
                <article>
                    <span class="how-grid__icon"><i class="ph ph-ticket" aria-hidden="true"></i></span>
                    <div><h3>Keep every guest informed</h3><p>Registrations, tickets, and updates stay connected to one account.</p></div>
                </article>
                <article>
                    <span class="how-grid__icon"><i class="ph ph-qr-code" aria-hidden="true"></i></span>
                    <div><h3>Welcome people quickly</h3><p>QR check-in keeps the door moving and attendance accurate.</p></div>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="organizer-callout">
    <div class="page-shell organizer-callout__inner">
        <div class="max-w-2xl">
            <p class="eyebrow eyebrow--inverse"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>For organizers</span></p>
            <h2 class="section-title text-white">Your audience is out there.</h2>
            <p class="mt-4 max-w-xl text-base leading-7 text-white/72">
                Give them a thoughtful event page, a smooth registration, and a reason to arrive excited.
            </p>
        </div>
        <a class="button button--light" href="/register?role=organizer"><span>Create organizer account</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
    </div>
</section>
