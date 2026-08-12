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

            <section class="public-event__location" aria-labelledby="event-location-heading">
                <h2 id="event-location-heading">Location</h2>
                <?php if (!empty($event['exact_location_visible'])): ?>
                    <address><?= e($event['address']) ?></address>
                    <?php if (!empty($event['arrival_notes'])): ?><p><?= nl2br(e($event['arrival_notes'])) ?></p><?php endif; ?>
                    <?php if (!empty($event['directions_url'])): ?>
                        <a class="text-link" href="<?= e($event['directions_url']) ?>" target="_blank" rel="noopener noreferrer"><span>Get directions</span><i class="ph ph-arrow-square-out" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php if (is_array($mapPayload)): ?>
                        <div class="event-map event-map--detail" data-event-map role="region" aria-label="Map showing the event location">
                            <p data-map-fallback>Map is unavailable. Use the address or directions link instead.</p>
                        </div>
                        <script type="application/json" id="event-detail-map-data"><?= json_encode($mapPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
                    <?php endif; ?>
                <?php else: ?>
                    <address><?= e($event['address']) ?></address>
                    <p class="public-event__location-notice"><i class="ph ph-lock-key" aria-hidden="true"></i><span>Exact location shared after confirmation</span></p>
                <?php endif; ?>
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

            <section aria-labelledby="event-reviews-heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="eyebrow"><i class="ph ph-star" aria-hidden="true"></i><span>Participant feedback</span></p>
                        <h2 id="event-reviews-heading">Event reviews</h2>
                    </div>
                    <?php if ((int) ($reviewSummary['count'] ?? 0) > 0): ?>
                        <p class="text-sm font-semibold text-[var(--ink-muted)]"><?= e(number_format((float) $reviewSummary['average'], 1)) ?> average rating, <?= e($reviewSummary['count']) ?> published <?= (int) $reviewSummary['count'] === 1 ? 'review' : 'reviews' ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($reviews === []): ?>
                    <div class="event-empty-state mt-6"><span><i class="ph ph-chat-centered-text" aria-hidden="true"></i></span><h3>No published reviews yet</h3><p>Participant reviews will appear here after moderation.</p></div>
                <?php else: ?>
                    <div class="mt-6 grid gap-4">
                        <?php foreach ($reviews as $review): ?>
                            <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface-raised)] p-5 sm:p-6">
                                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-2"><strong><?= e($review['participant_name'] ?? 'Participant') ?></strong><?php if (!empty($review['verified_attendee'])): ?><span class="status-badge status-badge--info">Verified attendee</span><?php endif; ?></div>
                                    <p class="text-sm font-bold text-[var(--accent)]" aria-label="<?= e($review['rating'] ?? 0) ?> out of 5 stars"><i class="ph ph-star-fill" aria-hidden="true"></i> <?= e($review['rating'] ?? 0) ?>/5</p>
                                </header>
                                <p class="mt-4 text-sm leading-7 text-[var(--ink-muted)]"><?= e($review['review'] ?? '') ?></p>
                                <?php if (!empty($review['organizer_reply'])): ?>
                                    <div class="mt-5 rounded-[12px] bg-[var(--surface)] p-4"><strong class="text-sm">Organizer reply</strong><p class="mt-2 text-sm leading-6 text-[var(--ink-muted)]"><?= e($review['organizer_reply']) ?></p></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="public-event__sidebar" aria-label="Event essentials">
            <section>
                <h2>Event essentials</h2>
                <dl>
                    <div><dt><i class="ph ph-calendar-blank" aria-hidden="true"></i>Starts</dt><dd><time datetime="<?= e($event['start_iso']) ?>"><?= e($event['start_date_display']) ?> at <?= e($event['start_time_display']) ?></time></dd></div>
                    <div><dt><i class="ph ph-clock" aria-hidden="true"></i>Ends</dt><dd><time datetime="<?= e($event['end_iso']) ?>"><?= e($event['end_display']) ?></time></dd></div>
                    <div><dt><i class="ph ph-map-pin" aria-hidden="true"></i>Venue</dt><dd><address><?= e($event['address']) ?></address><?php if (empty($event['exact_location_visible'])): ?><small>Exact location shared after confirmation</small><?php endif; ?></dd></div>
                    <div><dt><i class="ph ph-ticket" aria-hidden="true"></i>Price</dt><dd><?= e($event['price_display']) ?><?php if (empty($event['is_free'])): ?> <?= e($event['currency'] ?? 'BDT') ?><?php endif; ?></dd></div>
                    <div><dt><i class="ph ph-users" aria-hidden="true"></i>Capacity</dt><dd><?= e($event['capacity']) ?> total places, <?= e($event['available_seats']) ?> currently available</dd></div>
                    <?php if (!empty($event['speaker'])): ?><div><dt><i class="ph ph-microphone-stage" aria-hidden="true"></i>Speaker</dt><dd><?= e($event['speaker']) ?></dd></div><?php endif; ?>
                </dl>
            </section>
            <section class="public-event__organizer">
                <span><i class="ph ph-buildings" aria-hidden="true"></i></span>
                <div><small>Organized by</small><strong><?= e($event['organization_name'] ?? 'OEMS organizer') ?></strong></div>
            </section>
            <section class="public-event__registration" aria-labelledby="calendar-heading">
                <span><i class="ph ph-calendar-plus" aria-hidden="true"></i></span>
                <div>
                    <h2 id="calendar-heading">Add to calendar</h2>
                    <p>Save the public event schedule. Restricted venues remain coarse until your registration is confirmed.</p>
                    <div class="mt-4 grid gap-3">
                        <a class="button button--quiet w-full" href="/events/<?= e($event['slug']) ?>/calendar.ics"><i class="ph ph-download-simple" aria-hidden="true"></i><span>Download calendar file</span></a>
                        <a class="text-link justify-center" href="/events/<?= e($event['slug']) ?>/google-calendar" target="_blank" rel="noopener noreferrer"><span>Open Google Calendar</span><i class="ph ph-arrow-square-out" aria-hidden="true"></i></a>
                    </div>
                </div>
            </section>
            <section class="public-event__registration" aria-labelledby="registration-heading">
                <span><i class="ph ph-ticket" aria-hidden="true"></i></span>
                <div>
                    <h2 id="registration-heading"><?= e($registrationAction['label']) ?></h2>
                    <p><?= e($registrationAction['description']) ?></p>
                    <?php if (is_string($registrationAction['href'])): ?>
                        <a class="button button--primary mt-4 w-full" href="<?= e($registrationAction['href']) ?>"><?= e($registrationAction['label']) ?></a>
                    <?php elseif (is_string($registrationAction['post_url'] ?? null)): ?>
                        <form class="mt-4" action="<?= e($registrationAction['post_url']) ?>" method="post" data-form-kind="action">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <button class="button button--primary w-full" type="submit" data-submit-label="Updating registration…"><i class="ph ph-hourglass-medium" aria-hidden="true"></i><span data-submit-text><?= e($registrationAction['label']) ?></span></button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            <?php if (!empty($event['favorite']['is_participant'])): ?>
                <section class="public-event__registration" aria-labelledby="favorite-heading">
                    <span><i class="ph <?= !empty($event['favorite']['is_saved']) ? 'ph-bookmark-simple-fill' : 'ph-bookmark-simple' ?>" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="favorite-heading"><?= !empty($event['favorite']['is_saved']) ? 'Saved event' : 'Save this event' ?></h2>
                        <p><?= !empty($event['favorite']['is_saved']) ? 'This event is in your saved list.' : 'Keep this event in your workspace for later.' ?></p>
                        <form class="mt-4" action="/participant/favorites/<?= e($event['id']) ?><?= !empty($event['favorite']['is_saved']) ? '/remove' : '' ?>" method="post" data-form-kind="action">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="return_to" value="/events/<?= e($event['slug']) ?>">
                            <button class="favorite-control w-full" type="submit" data-submit-label="Updating…" aria-label="<?= !empty($event['favorite']['is_saved']) ? 'Remove ' : 'Save ' ?><?= e($event['title']) ?><?= !empty($event['favorite']['is_saved']) ? ' from saved events' : '' ?>"><i class="ph <?= !empty($event['favorite']['is_saved']) ? 'ph-bookmark-simple-fill' : 'ph-bookmark-simple' ?>" aria-hidden="true"></i><span data-submit-text><?= !empty($event['favorite']['is_saved']) ? 'Remove from saved' : 'Save event' ?></span></button>
                        </form>
                    </div>
                </section>
            <?php elseif (!empty($event['favorite']['is_guest'])): ?>
                <section class="public-event__registration" aria-labelledby="favorite-heading">
                    <span><i class="ph ph-bookmark-simple" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="favorite-heading">Save this event</h2>
                        <p>Sign in to keep this event in your saved list.</p>
                        <a class="favorite-guest-link" href="/login?return_to=<?= e(rawurlencode('/events/' . (string) $event['slug'])) ?>" aria-label="Sign in to save <?= e($event['title']) ?>"><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Sign in to save</span></a>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</article>
