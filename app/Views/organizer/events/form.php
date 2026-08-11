<?php
$isEdit = is_array($event);
$action = $isEdit ? '/organizer/events/' . (int) $event['id'] : '/organizer/events';
$eventValue = static function (string $key, string $default = '') use ($old, $event): string {
    $value = $old[$key] ?? ($event[$key] ?? $default);

    if ($key === 'tags' && is_array($value)) {
        $value = implode(', ', array_filter($value, 'is_scalar'));
    }

    if (in_array($key, ['start_date', 'end_date', 'registration_deadline'], true) && is_string($value)) {
        $value = substr(str_replace(' ', 'T', $value), 0, 16);
    }

    return e(is_scalar($value) ? $value : '');
};
$selected = static fn (string $key): string => (string) ($old[$key] ?? $event[$key] ?? '');
$waitlistValue = array_key_exists('waitlist_enabled', $old)
    ? $old['waitlist_enabled']
    : ($event['waitlist_enabled'] ?? 1);
$waitlistChecked = is_scalar($waitlistValue)
    && in_array(strtolower((string) $waitlistValue), ['1', 'true', 'on'], true);
$locationVisibility = $selected('location_visibility') ?: 'public';
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"';
$described = static function (string $key) use ($errors): string {
    $id = str_replace('_', '-', $key);
    $hasError = field_error($errors, $key) !== null;

    return ($hasError ? ' aria-invalid="true"' : '')
        . ' aria-describedby="' . $id . '-help' . ($hasError ? ' ' . $id . '-error' : '') . '"';
};
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-note-pencil" aria-hidden="true"></i><span>Event editor</span></p>
        <h1><?= $isEdit ? 'Edit event' : 'Create event' ?></h1>
        <p>Save complete event details as a draft before sending it for review.</p>
    </div>
    <a class="button button--quiet" href="<?= $isEdit ? '/organizer/events/' . (int) $event['id'] : '/organizer/events' ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <?php if (($generalError = field_error($errors, 'event')) !== null): ?>
        <div class="form-alert" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($generalError) ?></span></div>
    <?php endif; ?>

    <section class="organizer-form__section" aria-labelledby="event-basics-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-text-t" aria-hidden="true"></i></span><div><h2 id="event-basics-heading">Event basics</h2><p>Name the event and explain what attendees can expect.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group sm:col-span-2"><label for="title">Event title</label><input id="title" name="title" type="text" maxlength="180" value="<?= $eventValue('title') ?>" required<?= $invalid('title') ?>><?php if ($error = field_error($errors, 'title')): ?><p id="title-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="category_id">Category</label><select id="category_id" name="category_id" required<?= $invalid('category_id') ?>><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= e($category['id']) ?>" <?= $selected('category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select><?php if ($error = field_error($errors, 'category_id')): ?><p id="category-id-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="venue_id">Venue <span class="field-label-note">Optional</span></label><select id="venue_id" name="venue_id"<?= $invalid('venue_id') ?>><option value="">No venue selected</option><?php foreach ($venues as $venueOption): ?><option value="<?= e($venueOption['id']) ?>" <?= $selected('venue_id') === (string) $venueOption['id'] ? 'selected' : '' ?>><?= e($venueOption['name']) ?></option><?php endforeach; ?></select><?php if ($error = field_error($errors, 'venue_id')): ?><p id="venue-id-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
        <div class="field-group"><label for="description">Description</label><textarea id="description" name="description" rows="8" maxlength="20000" required<?= $described('description') ?>><?= $eventValue('description') ?></textarea><p id="description-help" class="field-help">Use at least 30 characters.</p><?php if ($error = field_error($errors, 'description')): ?><p id="description-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group"><label for="speaker">Speaker <span class="field-label-note">Optional</span></label><input id="speaker" name="speaker" type="text" maxlength="190" value="<?= $eventValue('speaker') ?>"<?= $invalid('speaker') ?>><?php if ($error = field_error($errors, 'speaker')): ?><p id="speaker-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="tags">Tags <span class="field-label-note">Optional</span></label><input id="tags" name="tags" type="text" maxlength="500" value="<?= $eventValue('tags') ?>" placeholder="design, community"<?= $described('tags') ?>><p id="tags-help" class="field-help">Separate up to 12 tags with commas.</p><?php if ($error = field_error($errors, 'tags')): ?><p id="tags-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
    </section>

    <section class="organizer-form__section" aria-labelledby="event-location-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-map-pin-line" aria-hidden="true"></i></span><div><h2 id="event-location-heading">Location access</h2><p>Choose who can see exact venue details and add concise arrival guidance.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <fieldset class="field-group">
                <legend>Exact location visibility</legend>
                <div class="location-visibility-options">
                    <label>
                        <input id="location_visibility_public" name="location_visibility" type="radio" value="public"<?= $locationVisibility === 'public' ? ' checked' : '' ?><?= $described('location_visibility') ?>>
                        <span><i class="ph ph-globe-hemisphere-west" aria-hidden="true"></i><strong>Public exact location</strong><small>Guests can see the venue details.</small><i class="ph ph-check-circle" aria-hidden="true"></i></span>
                    </label>
                    <label>
                        <input id="location_visibility_registered" name="location_visibility" type="radio" value="registered"<?= $locationVisibility === 'registered' ? ' checked' : '' ?><?= $described('location_visibility') ?>>
                        <span><i class="ph ph-users-three" aria-hidden="true"></i><strong>Confirmed participants only</strong><small>Reveal details after registration.</small><i class="ph ph-check-circle" aria-hidden="true"></i></span>
                    </label>
                </div>
                <p id="location-visibility-help" class="field-help">Restricted mode hides the exact address, pin, directions, and arrival notes until registration is confirmed.</p>
                <?php if ($error = field_error($errors, 'location_visibility')): ?><p id="location-visibility-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
            </fieldset>
            <div class="field-group">
                <label for="arrival_notes">Arrival notes <span class="field-label-note">Optional</span></label>
                <textarea id="arrival_notes" name="arrival_notes" rows="4" maxlength="500"<?= $described('arrival_notes') ?>><?= $eventValue('arrival_notes') ?></textarea>
                <p id="arrival-notes-help" class="field-help">Add an entrance, floor, landmark, or accessibility instruction. A venue is required.</p>
                <?php if ($error = field_error($errors, 'arrival_notes')): ?><p id="arrival-notes-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="organizer-form__section" aria-labelledby="event-schedule-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-clock" aria-hidden="true"></i></span><div><h2 id="event-schedule-heading">Schedule and capacity</h2><p>Set local dates, seat limits, and the ticket price in BDT.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div class="field-group"><label for="start_date">Starts</label><input id="start_date" name="start_date" type="datetime-local" value="<?= $eventValue('start_date') ?>" required<?= $invalid('start_date') ?>><?php if ($error = field_error($errors, 'start_date')): ?><p id="start-date-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="end_date">Ends</label><input id="end_date" name="end_date" type="datetime-local" value="<?= $eventValue('end_date') ?>" required<?= $invalid('end_date') ?>><?php if ($error = field_error($errors, 'end_date')): ?><p id="end-date-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="registration_deadline">Response deadline</label><input id="registration_deadline" name="registration_deadline" type="datetime-local" value="<?= $eventValue('registration_deadline') ?>" required<?= $invalid('registration_deadline') ?>><?php if ($error = field_error($errors, 'registration_deadline')): ?><p id="registration-deadline-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="capacity">Capacity</label><input id="capacity" name="capacity" type="number" min="1" max="100000" step="1" value="<?= $eventValue('capacity') ?>" required<?= $invalid('capacity') ?>><?php if ($error = field_error($errors, 'capacity')): ?><p id="capacity-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="ticket_price">Ticket price</label><input id="ticket_price" name="ticket_price" type="number" min="0" max="9999999.99" step="0.01" value="<?= $eventValue('ticket_price', '0') ?>" required<?= $invalid('ticket_price') ?>><?php if ($error = field_error($errors, 'ticket_price')): ?><p id="ticket-price-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group sm:col-span-2 xl:col-span-1">
                <input type="hidden" name="waitlist_enabled" value="0">
                <label class="flex min-h-11 items-start gap-3" for="waitlist_enabled"><input id="waitlist_enabled" name="waitlist_enabled" type="checkbox" value="1"<?= $waitlistChecked ? ' checked' : '' ?>><span><strong>Enable a waitlist when seats sell out</strong><small class="mt-1 block text-[var(--ink-muted)]">Participants join without payment and are promoted oldest-first when a seat opens.</small></span></label>
            </div>
            <div class="field-group"><label for="map_url">Map URL <span class="field-label-note">Optional</span></label><input id="map_url" name="map_url" type="url" maxlength="500" placeholder="https://www.google.com/maps/..." value="<?= $eventValue('map_url') ?>"<?= $invalid('map_url') ?>><p class="field-help">Use an HTTPS link from an approved map provider.</p><?php if ($error = field_error($errors, 'map_url')): ?><p id="map-url-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
    </section>

    <section class="organizer-form__section" aria-labelledby="event-media-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-image" aria-hidden="true"></i></span><div><h2 id="event-media-heading">Event media</h2><p>JPEG, PNG, and WebP images are accepted up to 5 MB each.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group"><label for="banner">Banner image <span class="field-label-note">Optional</span></label><input id="banner" name="banner" type="file" accept="image/jpeg,image/png,image/webp"<?= $invalid('banner') ?>><?php if ($error = field_error($errors, 'banner')): ?><p id="banner-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="gallery">Gallery images <span class="field-label-note">Optional</span></label><input id="gallery" name="gallery[]" type="file" accept="image/jpeg,image/png,image/webp" multiple<?= $described('gallery') ?>><p id="gallery-help" class="field-help">Choose up to six images.<?php if ($isEdit && $gallery !== []): ?> New gallery images replace the current gallery.<?php endif; ?></p><?php if ($error = field_error($errors, 'gallery')): ?><p id="gallery-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
        <?php if ($isEdit && (!empty($event['banner']) || $gallery !== [])): ?>
            <div class="grid gap-5 sm:grid-cols-2" aria-label="Existing event media">
                <?php if (!empty($event['banner'])): ?>
                    <section aria-labelledby="current-banner-heading"><h3 id="current-banner-heading">Current banner</h3><figure class="admin-evidence-banner"><img src="<?= e($event['banner']) ?>" alt="Current banner for <?= e($event['title']) ?>"></figure></section>
                <?php endif; ?>
                <?php if ($gallery !== []): ?>
                    <section aria-labelledby="current-gallery-heading"><h3 id="current-gallery-heading">Current gallery</h3><div class="admin-evidence-gallery"><?php foreach ($gallery as $image): ?><figure><img src="<?= e($image['image_path'] ?? '') ?>" alt="<?= e($image['alt_text'] ?? ('Gallery image for ' . (string) $event['title'])) ?>"></figure><?php endforeach; ?></div></section>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="organizer-form__actions"><p><i class="ph ph-info" aria-hidden="true"></i><span><?= $isEdit ? 'Only draft and returned events can be updated.' : 'New events are saved as drafts.' ?></span></p><button class="button button--primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span><?= $isEdit ? 'Save changes' : 'Create draft' ?></span></button></div>
</form>
