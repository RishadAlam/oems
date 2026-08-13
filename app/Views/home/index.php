<?php $heroEvent = $featuredEvents[0] ?? null; ?>
<section class="hero-section" aria-labelledby="home-hero-title">
    <div class="page-shell">
    <div class="hero-layout">
        <div class="hero-content" data-reveal>
            <p class="eyebrow"><i class="ph ph-sparkle" aria-hidden="true"></i><span><?= e($siteSettings['home_hero_kicker'] ?? 'Events made for showing up') ?></span></p>
            <h1 id="home-hero-title" class="hero-title"><?= e($siteSettings['home_hero_title'] ?? 'Find your next standout event.') ?></h1>
            <p class="hero-copy">
                <?= e($siteSettings['home_hero_copy'] ?? 'Discover workshops, talks, and gatherings across Dhaka, or host an event experience that feels effortless.') ?>
            </p>

            <form class="hero-search" action="/events" method="get" role="search" aria-label="Search events" data-form-kind="filter">
                <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                <label class="sr-only" for="hero-search">Search events</label>
                <input id="hero-search" name="search" type="search" maxlength="120" placeholder="Search events" autocomplete="off" data-form-label="Search events">
                <button class="button button--primary button--compact" type="submit"><span>Search</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
            </form>

            <div class="hero-actions" aria-label="Homepage actions">
                <a href="/events" class="button button--primary"><span>Explore events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <a href="/register?role=organizer" class="button button--quiet"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>Host an event</span></a>
            </div>
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
                <?php if ($heroEvent !== null): ?>
                    <span>Next on OEMS · <?= e($heroEvent['date']) ?> at <?= e($heroEvent['time']) ?></span>
                    <strong><a href="/events/<?= e($heroEvent['slug']) ?>"><?= e($heroEvent['title']) ?></a></strong>
                <?php else: ?>
                    <span>Made for every side of the room</span>
                    <strong>Discover, host, and arrive ready.</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</section>

<?php if (($homeBanners ?? []) !== []): ?>
<section class="home-announcements" aria-label="Platform announcements">
    <div class="page-shell home-announcements__list">
        <?php foreach ($homeBanners as $index => $banner): ?>
            <article class="home-announcement" aria-labelledby="home-announcement-title-<?= e($index) ?>" data-reveal>
                <div class="home-announcement__media">
                    <img
                        src="<?= e($banner['image_path']) ?>"
                        alt="Promotion: <?= e($banner['title']) ?>"
                        width="1200"
                        height="525"
                        loading="lazy"
                    >
                </div>
                <div class="home-announcement__body">
                    <p class="home-announcement__eyebrow"><i class="ph ph-megaphone" aria-hidden="true"></i><span>OEMS update</span></p>
                    <h2 id="home-announcement-title-<?= e($index) ?>"><?= e($banner['title']) ?></h2>
                    <?php if (!empty($banner['subtitle'])): ?>
                        <p><?= e($banner['subtitle']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($banner['link_url'])): ?>
                        <a class="text-link" href="<?= e($banner['link_url']) ?>">
                            <span>Learn more</span>
                            <i class="ph ph-arrow-right" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section id="browse-categories" class="home-categories" aria-labelledby="home-categories-title">
    <div class="page-shell">
    <div class="home-categories__layout">
        <div class="home-categories__heading">
            <p class="section-kicker">Explore by interest</p>
            <h2 id="home-categories-title">Start with what you are into</h2>
            <p>Four quick paths to events that match your day.</p>
        </div>
        <div class="home-categories__grid">
            <a href="/events?category=technology"><i class="ph ph-cpu" aria-hidden="true"></i><span><strong>Technology</strong><small>Talks and meetups</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
            <a href="/events?category=arts-culture"><i class="ph ph-palette" aria-hidden="true"></i><span><strong>Arts and culture</strong><small>Live and local</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
            <a href="/events?category=business"><i class="ph ph-handshake" aria-hidden="true"></i><span><strong>Business</strong><small>Build your network</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
            <a href="/events?category=community"><i class="ph ph-users-three" aria-hidden="true"></i><span><strong>Community</strong><small>Meet your people</small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
        </div>
    </div>
    </div>
</section>

<section class="home-featured section-space" aria-labelledby="home-featured-title">
    <div class="page-shell">
        <div class="section-heading-row">
            <div class="max-w-2xl">
                <p class="eyebrow"><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Featured on OEMS</span></p>
                <h2 id="home-featured-title" class="section-title">Worth adding to your calendar</h2>
                <p class="section-copy">A considered mix of upcoming ways to learn, listen, and meet people.</p>
            </div>
            <a class="text-link section-heading-row__link" href="/events"><span>Browse all events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>

        <?php if ($featuredEvents === []): ?>
            <div class="event-empty-state">
                <span><i class="ph ph-calendar-dots" aria-hidden="true"></i></span>
                <h3>No featured events yet</h3>
                <p>Published events will appear here as organizers share what is next.</p>
                <a class="button button--quiet button--compact" href="/events"><span>Browse all events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            </div>
        <?php else: ?>
        <div class="home-featured__grid">
            <?php foreach ($featuredEvents as $index => $event): ?>
                <article class="event-card <?= $index === 0 ? 'event-card--wide' : '' ?>" data-reveal>
                    <a href="/events/<?= e($event['slug']) ?>" class="event-card__media" aria-label="View <?= e($event['title']) ?>">
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
                        <h3><a href="/events/<?= e($event['slug']) ?>"><?= e($event['title']) ?></a></h3>
                        <div class="event-card__details">
                            <div><i class="ph ph-calendar-blank" aria-hidden="true"></i><span><small>Date</small><time datetime="<?= e($event['datetime']) ?>"><?= e($event['date']) ?> at <?= e($event['time']) ?></time></span></div>
                            <div><i class="ph ph-map-pin" aria-hidden="true"></i><span><small>Place</small><address><?= e($event['venue']) ?></address></span></div>
                        </div>
                        <div class="event-card__footer">
                            <strong class="text-sm"><?= e($event['price']) ?></strong>
                            <div class="event-card__actions">
                                <?php if (!empty($event['favorite']['is_participant'])): ?>
                                    <form action="/participant/favorites/<?= e($event['id']) ?><?= !empty($event['favorite']['is_saved']) ? '/remove' : '' ?>" method="post" data-form-kind="action">
                                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="return_to" value="/">
                                        <button class="favorite-control" type="submit" data-submit-label="Updating…" aria-label="<?= !empty($event['favorite']['is_saved']) ? 'Remove ' : 'Save ' ?><?= e($event['title']) ?><?= !empty($event['favorite']['is_saved']) ? ' from saved events' : '' ?>"><i class="ph <?= !empty($event['favorite']['is_saved']) ? 'ph-bookmark-simple-fill' : 'ph-bookmark-simple' ?>" aria-hidden="true"></i><span data-submit-text><?= !empty($event['favorite']['is_saved']) ? 'Saved' : 'Save' ?></span></button>
                                    </form>
                                <?php elseif (!empty($event['favorite']['is_guest'])): ?>
                                    <a class="favorite-guest-link" href="/login?return_to=%2F" aria-label="Sign in to save <?= e($event['title']) ?>"><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Sign in to save</span></a>
                                <?php endif; ?>
                                <a class="text-link" href="/events/<?= e($event['slug']) ?>"><span>View event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section id="how-it-works" class="home-journeys section-space pt-0" aria-labelledby="home-journeys-title">
    <div class="page-shell">
        <div class="home-journeys__surface">
            <div class="home-journeys__intro">
                <p class="eyebrow eyebrow--inverse"><i class="ph ph-path" aria-hidden="true"></i><span>How OEMS works</span></p>
                <div>
                    <h2 id="home-journeys-title" class="section-title">One platform, two clear paths</h2>
                    <p class="section-copy">Whether you are finding a seat or filling a room, the next step stays obvious.</p>
                </div>
            </div>

            <div class="home-journeys__grid">
                <article class="home-journey home-journey--participant">
                    <header>
                        <span class="home-journey__icon"><i class="ph ph-ticket" aria-hidden="true"></i></span>
                        <div><p>For participants</p><h3>Find a reason to show up</h3></div>
                    </header>
                    <ol>
                        <li><span>01</span><div><strong>Discover</strong><p>Search by interest, date, or place.</p></div></li>
                        <li><span>02</span><div><strong>Register</strong><p>Save your place and keep ticket details together.</p></div></li>
                        <li><span>03</span><div><strong>Arrive ready</strong><p>Bring your QR ticket for a faster check-in.</p></div></li>
                    </ol>
                    <a class="text-link text-link--inverse" href="/events"><span>Explore upcoming events</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                </article>

                <article class="home-journey home-journey--organizer">
                    <header>
                        <span class="home-journey__icon"><i class="ph ph-microphone-stage" aria-hidden="true"></i></span>
                        <div><p>For organizers</p><h3>Turn an idea into a full room</h3></div>
                    </header>
                    <ol>
                        <li><span>01</span><div><strong>Create</strong><p>Build a clear page with venue and schedule details.</p></div></li>
                        <li><span>02</span><div><strong>Submit</strong><p>Send the event for review before it goes live.</p></div></li>
                        <li><span>03</span><div><strong>Run the room</strong><p>Manage guests and scan tickets at the door.</p></div></li>
                    </ol>
                    <a class="text-link text-link--inverse" href="/register?role=organizer"><span>Start as an organizer</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="organizer-callout section-space pt-0" aria-labelledby="organizer-callout-title">
    <div class="page-shell organizer-callout__inner">
        <div class="organizer-callout__content">
            <p class="eyebrow eyebrow--inverse"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>For organizers</span></p>
            <h2 id="organizer-callout-title" class="section-title text-white">Your audience is out there.</h2>
            <p>Give them a thoughtful event page, a smooth registration, and a reason to arrive excited.</p>
            <ul class="organizer-callout__points" aria-label="Organizer capabilities">
                <li><i class="ph ph-check-circle" aria-hidden="true"></i><span>Build detailed event pages</span></li>
                <li><i class="ph ph-check-circle" aria-hidden="true"></i><span>Submit for approval</span></li>
                <li><i class="ph ph-check-circle" aria-hidden="true"></i><span>Manage guests and check-ins</span></li>
            </ul>
        </div>
        <a class="button button--light" href="/register?role=organizer"><span>Create organizer account</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
    </div>
</section>
