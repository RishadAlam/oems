<section class="hero-section">
    <div class="page-shell grid min-h-[calc(100dvh-72px)] items-center gap-10 py-12 lg:grid-cols-[0.96fr_1.04fr] lg:py-16">
        <div class="max-w-xl" data-reveal>
            <h1 class="hero-title">Find your next room full of ideas.</h1>
            <p class="hero-copy">
                Discover workshops, talks, and gatherings across your city, or give your own audience a better event experience.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a class="button button--primary" href="/events">Explore events</a>
                <a class="button button--quiet" href="/register?role=organizer">Host an event</a>
            </div>

            <form class="hero-search" action="/events" method="get" role="search">
                <label class="sr-only" for="hero-search">Search events</label>
                <input id="hero-search" name="search" type="search" placeholder="Search talks, workshops, communities" autocomplete="off">
                <button type="submit">Search</button>
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
            <div class="hero-media__note">
                <span>Built for every side of the room</span>
                <strong>Discover. Host. Attend.</strong>
            </div>
        </div>
    </div>
</section>

<section class="category-rail" aria-label="Popular event categories">
    <div class="page-shell grid gap-5 py-7 sm:grid-cols-2 lg:grid-cols-4">
        <a href="/events?category=technology"><span>Technology</span><small>Talks and meetups</small></a>
        <a href="/events?category=arts-culture"><span>Arts and culture</span><small>Live and local</small></a>
        <a href="/events?category=business"><span>Business</span><small>Build your network</small></a>
        <a href="/events?category=community"><span>Community</span><small>Meet your people</small></a>
    </div>
</section>

<section class="section-space">
    <div class="page-shell">
        <div class="max-w-2xl">
            <h2 class="section-title">A better plan for your weekend</h2>
            <p class="section-copy">Two fresh ways to learn, listen, and meet people in Dhaka.</p>
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
                        <dl class="event-card__details">
                            <div><dt>Date</dt><dd><?= e($event['date']) ?> at <?= e($event['time']) ?></dd></div>
                            <div><dt>Place</dt><dd><?= e($event['venue']) ?></dd></div>
                        </dl>
                        <div class="mt-6 flex items-center justify-between border-t border-[var(--line)] pt-4">
                            <strong class="text-sm"><?= e($event['price']) ?></strong>
                            <a class="text-link" href="/events">View event</a>
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
                <h2 class="section-title">From idea to full room</h2>
                <p class="section-copy">A clear path for organizers and participants, without the busywork.</p>
            </div>
            <div class="how-grid__steps">
                <article>
                    <h3>Publish with confidence</h3>
                    <p>Create the event, share the essentials, and send it for approval.</p>
                </article>
                <article>
                    <h3>Keep every guest informed</h3>
                    <p>Registrations, tickets, and updates stay connected to one account.</p>
                </article>
                <article>
                    <h3>Welcome people quickly</h3>
                    <p>QR check-in keeps the door moving and attendance accurate.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="organizer-callout">
    <div class="page-shell grid items-end gap-8 py-16 md:grid-cols-[1fr_auto] md:py-20">
        <div class="max-w-2xl">
            <h2 class="section-title text-white">Your audience is out there.</h2>
            <p class="mt-4 max-w-xl text-base leading-7 text-white/72">
                Give them a thoughtful event page, a smooth registration, and a reason to arrive excited.
            </p>
        </div>
        <a class="button button--light" href="/register?role=organizer">Create organizer account</a>
    </div>
</section>
