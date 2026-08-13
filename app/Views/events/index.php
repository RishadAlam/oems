<?php
$filters = $filters ?? ['search' => '', 'category' => '', 'city' => '', 'date' => 'upcoming', 'price' => '', 'sort' => 'soonest'];
$activeFilters = array_filter($filters, static fn (string $value, string $key): bool => $value !== '' && !in_array([$key, $value], [['date', 'upcoming'], ['sort', 'soonest']], true), ARRAY_FILTER_USE_BOTH);
$mapMarkerCount = count($mapPayload['markers'] ?? []);
?>
<section class="events-index">
    <div class="page-shell">
        <div class="events-index__heading">
            <div class="max-w-3xl">
                <p class="eyebrow"><i class="ph ph-compass" aria-hidden="true"></i><span>Discover what is nearby</span></p>
                <h1 class="section-title">Events that move the week forward.</h1>
                <p class="section-copy">Browse published workshops, talks, and gatherings, then narrow the list around your plans.</p>
            </div>
            <div class="events-index__count" aria-live="polite"><strong><?= count($events) ?></strong><span><?= count($events) === 1 ? 'event' : 'events' ?></span></div>
        </div>

        <form class="event-filter-panel" action="/events" method="get" role="search" aria-label="Search and filter events" data-form-kind="filter">
            <div class="event-filter-panel__search">
                <label for="event-search">Search events</label>
                <div><i class="ph ph-magnifying-glass" aria-hidden="true"></i><input id="event-search" name="search" type="search" maxlength="120" value="<?= e($filters['search']) ?>" placeholder="Title, organizer, speaker, or place" data-form-label="Search events"></div>
            </div>
            <div class="event-filter-panel__field">
                <label for="event-category">Category</label>
                <select id="event-category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?><option value="<?= e($category['slug']) ?>" <?= $filters['category'] === (string) $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="event-filter-panel__field">
                <label for="event-city">City</label>
                <select id="event-city" name="city">
                    <option value="">All cities</option>
                    <?php foreach ($cities as $city): ?><option value="<?= e($city) ?>" <?= $filters['city'] === (string) $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="event-filter-panel__field">
                <label for="event-date">Date</label>
                <select id="event-date" name="date">
                    <option value="upcoming" <?= $filters['date'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="today" <?= $filters['date'] === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="this_week" <?= $filters['date'] === 'this_week' ? 'selected' : '' ?>>This week</option>
                    <option value="this_month" <?= $filters['date'] === 'this_month' ? 'selected' : '' ?>>This month</option>
                </select>
            </div>
            <div class="event-filter-panel__field">
                <label for="event-price">Price</label>
                <select id="event-price" name="price">
                    <option value="" <?= $filters['price'] === '' ? 'selected' : '' ?>>Any price</option>
                    <option value="free" <?= $filters['price'] === 'free' ? 'selected' : '' ?>>Free</option>
                    <option value="paid" <?= $filters['price'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div class="event-filter-panel__field">
                <label for="event-sort">Sort by</label>
                <select id="event-sort" name="sort">
                    <option value="soonest" <?= $filters['sort'] === 'soonest' ? 'selected' : '' ?>>Soonest</option>
                    <option value="latest" <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>>Latest</option>
                    <option value="price_low" <?= $filters['sort'] === 'price_low' ? 'selected' : '' ?>>Price, low to high</option>
                    <option value="price_high" <?= $filters['sort'] === 'price_high' ? 'selected' : '' ?>>Price, high to low</option>
                    <?php if (!empty($distanceSortAvailable)): ?><option value="distance" <?= $filters['sort'] === 'distance' ? 'selected' : '' ?>>Nearest</option><?php endif; ?>
                </select>
            </div>
            <?php if ($location !== null): ?>
                <div class="event-filter-panel__field">
                    <label for="event-radius">Distance</label>
                    <select id="event-radius" name="radius">
                        <?php foreach ($radii as $radius): ?><option value="<?= e($radius) ?>" <?= (int) $location['radius'] === (int) $radius ? 'selected' : '' ?>>Within <?= e($radius) ?> km</option><?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="event-filter-panel__actions">
                <button class="button button--primary" type="submit"><span>Apply filters</span><i class="ph ph-funnel" aria-hidden="true"></i></button>
                <a class="button button--quiet" href="/events"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i><span>Clear all</span></a>
            </div>
        </form>

        <div class="event-location-controls" aria-label="Location and result view controls">
            <div class="event-location-controls__preference">
                <form action="/events/location" method="post" data-location-form data-form-kind="special">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="latitude" value="">
                    <input type="hidden" name="longitude" value="">
                    <input type="hidden" name="radius" value="<?= e($location['radius'] ?? 25) ?>">
                    <button class="button button--quiet button--compact" type="button" data-location-use>
                        <i class="ph ph-crosshair" aria-hidden="true"></i>
                        <span><?= $location === null ? 'Use my location' : 'Change location' ?></span>
                    </button>
                </form>
                <?php if ($location !== null): ?>
                    <span class="event-location-controls__active"><i class="ph ph-map-pin" aria-hidden="true"></i><?= e($location['label']) ?></span>
                    <form action="/events/location/clear" method="post" data-form-kind="action">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <button class="button button--quiet button--compact" type="submit" data-submit-label="Clearing…"><span data-submit-text>Clear location</span></button>
                    </form>
                <?php endif; ?>
                <p class="event-location-controls__status" data-location-status role="status" aria-live="polite"></p>
            </div>
            <div class="event-view-control">
                <span class="event-view-control__label" id="event-view-label">View</span>
                <div class="event-view-switch" role="group" aria-labelledby="event-view-label">
                    <button type="button" value="list" data-event-view data-view="list" aria-pressed="true"><i class="ph ph-list" aria-hidden="true"></i><span>List</span></button>
                    <button type="button" value="map" data-event-view data-view="map" aria-pressed="false"><i class="ph ph-map-trifold" aria-hidden="true"></i><span>Map</span></button>
                </div>
            </div>
            <p class="sr-only" data-event-view-status role="status" aria-live="polite"></p>
        </div>

        <?php if ($activeFilters !== []): ?>
            <p class="search-preview"><i class="ph ph-check-circle" aria-hidden="true"></i><span>Showing <?= count($events) ?> <?= count($events) === 1 ? 'match' : 'matches' ?> for your selected filters.</span></p>
        <?php endif; ?>

        <div class="event-discovery-layout<?= $events === [] ? ' event-discovery-layout--empty' : '' ?>" data-event-discovery data-event-discovery-view="list">
        <?php if ($events === []): ?>
            <div class="event-empty-state">
                <span><i class="ph ph-calendar-x" aria-hidden="true"></i></span>
                <h2>No published events match these filters.</h2>
                <p>Try a broader search, another city, or a different date range.</p>
                <a class="button button--quiet button--compact" href="/events"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i><span>Clear search and filters</span></a>
            </div>
        <?php else: ?>
            <div class="event-results-grid" data-event-results>
                <?php foreach ($events as $event): ?>
                    <article class="event-card" data-reveal data-event-id="<?= e($event['id']) ?>" tabindex="-1">
                        <a class="event-card__media" href="/events/<?= e($event['slug']) ?>" aria-label="View <?= e($event['title']) ?>">
                            <img src="<?= e($event['banner_display']) ?>" alt="<?= e($event['banner_alt']) ?>" width="1400" height="1050" loading="lazy">
                        </a>
                        <div class="event-card__body">
                            <p class="event-card__category"><?= e($event['category_name'] ?? 'Event') ?></p>
                            <h2><a href="/events/<?= e($event['slug']) ?>"><?= e($event['title']) ?></a></h2>
                            <div class="event-card__details">
                                <div><i class="ph ph-calendar-blank" aria-hidden="true"></i><span><small>Date</small><time datetime="<?= e($event['start_iso']) ?>"><?= e($event['start_date_display']) ?> at <?= e($event['start_time_display']) ?></time></span></div>
                                <div><i class="ph ph-map-pin" aria-hidden="true"></i><span><small>Place</small><address><?= e($event['address']) ?></address><?php if (!empty($event['distance_label'])): ?><small><?= e($event['distance_label']) ?></small><?php endif; ?></span></div>
                            </div>
                            <div class="event-card__footer">
                                <strong><?= e($event['price_display']) ?></strong>
                                <div class="event-card__actions">
                                    <?php if (!empty($event['favorite']['is_participant'])): ?>
                                        <form action="/participant/favorites/<?= e($event['id']) ?><?= !empty($event['favorite']['is_saved']) ? '/remove' : '' ?>" method="post" data-form-kind="action">
                                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="return_to" value="/events">
                                            <button class="favorite-control" type="submit" data-submit-label="Updating…" aria-label="<?= !empty($event['favorite']['is_saved']) ? 'Remove ' : 'Save ' ?><?= e($event['title']) ?><?= !empty($event['favorite']['is_saved']) ? ' from saved events' : '' ?>"><i class="ph <?= !empty($event['favorite']['is_saved']) ? 'ph-bookmark-simple-fill' : 'ph-bookmark-simple' ?>" aria-hidden="true"></i><span data-submit-text><?= !empty($event['favorite']['is_saved']) ? 'Saved' : 'Save' ?></span></button>
                                        </form>
                                    <?php elseif (!empty($event['favorite']['is_guest'])): ?>
                                        <a class="favorite-guest-link" href="/login?return_to=%2Fevents" aria-label="Sign in to save <?= e($event['title']) ?>"><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Sign in to save</span></a>
                                    <?php endif; ?>
                                    <a class="text-link" href="/events/<?= e($event['slug']) ?>"><span>View details</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="event-map-panel" data-event-map-panel hidden aria-labelledby="event-map-heading">
            <div class="event-map-panel__heading">
                <div><h2 id="event-map-heading">Event map</h2><p><?= $mapMarkerCount ?> public event <?= $mapMarkerCount === 1 ? 'location' : 'locations' ?>. Only exact locations shared publicly appear here.</p></div>
            </div>
            <div class="event-map" data-event-map role="region" aria-label="Map of published public events">
                <p data-map-fallback>Map is unavailable. Browse the complete event list instead.</p>
            </div>
        </section>
        </div>

        <script type="application/json" id="event-map-data"><?= json_encode($mapPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
    </div>
</section>
