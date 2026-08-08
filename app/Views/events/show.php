<article class="public-event">
    <header class="public-event__hero">
        <div class="page-shell public-event__hero-grid">
            <div class="public-event__heading" data-reveal>
                <a class="text-link" href="/events"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>All events</span></a>
                <p class="event-card__category"><?= e($event['category_name'] ?? 'Event') ?></p>
                <h1><?= e($event['title']) ?></h1>
                <p><?= e($event['description']) ?></p>
                <div class="public-event__quick-facts">
                    <span><i class="ph ph-calendar-blank" aria-hidden="true"></i><time datetime="<?= e($event['start_iso']) ?>"><?= e($event['start_date_display']) ?> at <?= e($event['start_time_display']) ?></time></span>
                    <span><i class="ph ph-map-pin" aria-hidden="true"></i><?= e($event['address']) ?></span>
                </div>
            </div>
            <figure class="public-event__banner" data-reveal>
                <img src="<?= e($event['banner_display']) ?>" alt="<?= e($event['banner_alt']) ?>" width="1600" height="1050" fetchpriority="high">
            </figure>
        </div>
    </header>

    <div class="page-shell public-event__layout">
        <div class="public-event__story">
            <section aria-labelledby="event-about-heading">
                <p class="eyebrow"><i class="ph ph-note" aria-hidden="true"></i><span>About this event</span></p>
                <h2 id="event-about-heading">What to expect</h2>
                <p class="public-event__description"><?= nl2br(e($event['description'])) ?></p>
            </section>

            <?php if ($gallery !== []): ?>
                <section class="public-event__gallery" aria-labelledby="event-gallery-heading">
                    <h2 id="event-gallery-heading">Event gallery</h2>
                    <div>
                        <?php foreach ($gallery as $image): ?>
                            <figure><img src="<?= e($image['path']) ?>" alt="<?= e($image['alt']) ?>" width="1200" height="900" loading="lazy"></figure>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($event['tags']) && is_array($event['tags'])): ?>
                <section class="public-event__tags" aria-labelledby="event-tags-heading">
                    <h2 id="event-tags-heading">Topics</h2>
                    <div><?php foreach ($event['tags'] as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?></div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="public-event__sidebar" aria-label="Event essentials">
            <section>
                <h2>Event essentials</h2>
                <dl>
                    <div><dt><i class="ph ph-calendar-blank" aria-hidden="true"></i>Starts</dt><dd><time datetime="<?= e($event['start_iso']) ?>"><?= e($event['start_date_display']) ?> at <?= e($event['start_time_display']) ?></time></dd></div>
                    <div><dt><i class="ph ph-clock" aria-hidden="true"></i>Ends</dt><dd><time datetime="<?= e($event['end_iso']) ?>"><?= e($event['end_display']) ?></time></dd></div>
                    <div><dt><i class="ph ph-map-pin" aria-hidden="true"></i>Venue</dt><dd><address><?= e($event['address']) ?></address></dd></div>
                    <div><dt><i class="ph ph-ticket" aria-hidden="true"></i>Price</dt><dd><?= e($event['price_display']) ?><?php if ((float) $event['ticket_price'] > 0): ?> <?= e($event['currency'] ?? 'BDT') ?><?php endif; ?></dd></div>
                    <div><dt><i class="ph ph-users" aria-hidden="true"></i>Capacity</dt><dd><?= e($event['capacity']) ?> total places, <?= e($event['available_seats']) ?> currently available</dd></div>
                    <?php if (!empty($event['speaker'])): ?><div><dt><i class="ph ph-microphone-stage" aria-hidden="true"></i>Speaker</dt><dd><?= e($event['speaker']) ?></dd></div><?php endif; ?>
                </dl>
            </section>
            <section class="public-event__organizer">
                <span><i class="ph ph-buildings" aria-hidden="true"></i></span>
                <div><small>Organized by</small><strong><?= e($event['organization_name'] ?? 'OEMS organizer') ?></strong></div>
            </section>
            <section class="public-event__registration" aria-labelledby="registration-heading">
                <span><i class="ph ph-ticket" aria-hidden="true"></i></span>
                <div>
                    <h2 id="registration-heading"><?= e($registrationAction['label']) ?></h2>
                    <p><?= e($registrationAction['description']) ?></p>
                    <?php if (is_string($registrationAction['href'])): ?>
                        <a class="button button--primary mt-4 w-full" href="<?= e($registrationAction['href']) ?>"><?= e($registrationAction['label']) ?></a>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</article>
