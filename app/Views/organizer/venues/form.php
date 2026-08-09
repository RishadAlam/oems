<?php
$isEdit = is_array($venue);
$action = $isEdit ? '/organizer/venues/' . (int) $venue['id'] : '/organizer/venues';
$venueValue = static fn (string $key, string $default = ''): string => old_value($old, $key, (string) ($venue[$key] ?? $default));
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"';
$mapConfig = is_array($mapConfig ?? null) ? $mapConfig : [];
$searchValue = trim(implode(', ', array_filter([
    $venueValue('address_line'),
    $venueValue('city'),
    $venueValue('country', 'Bangladesh'),
], static fn (string $value): bool => $value !== '')));
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-map-pin-line" aria-hidden="true"></i><span>Venue editor</span></p><h1><?= $isEdit ? 'Edit venue' : 'Create venue' ?></h1><p>Store accurate location and capacity details for event planning.</p></div>
    <a class="button button--quiet" href="/organizer/venues"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to venues</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post" novalidate data-venue-map-form data-geocode-url="/organizer/venues/geocode" data-csrf="<?= e($csrfToken) ?>">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <section class="organizer-form__section" aria-labelledby="venue-details-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-buildings" aria-hidden="true"></i></span><div><h2 id="venue-details-heading">Venue details</h2><p>Use the public name and complete street address.</p></div></div>
        <div class="field-group"><label for="name">Venue name</label><input id="name" name="name" type="text" maxlength="160" value="<?= $venueValue('name') ?>" required<?= $invalid('name') ?>><?php if ($error = field_error($errors, 'name')): ?><p id="name-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        <div class="field-group"><label for="address_line">Street address</label><input id="address_line" name="address_line" type="text" autocomplete="street-address" maxlength="190" value="<?= $venueValue('address_line') ?>" required<?= $invalid('address_line') ?>><?php if ($error = field_error($errors, 'address_line')): ?><p id="address-line-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        <div class="grid gap-5 sm:grid-cols-3">
            <div class="field-group"><label for="city">City</label><input id="city" name="city" type="text" autocomplete="address-level2" maxlength="100" value="<?= $venueValue('city') ?>" required<?= $invalid('city') ?>><?php if ($error = field_error($errors, 'city')): ?><p id="city-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="country">Country</label><input id="country" name="country" type="text" autocomplete="country-name" maxlength="100" value="<?= $venueValue('country', 'Bangladesh') ?>" required<?= $invalid('country') ?>><?php if ($error = field_error($errors, 'country')): ?><p id="country-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="postal_code">Postal code <span class="field-label-note">Optional</span></label><input id="postal_code" name="postal_code" type="text" autocomplete="postal-code" maxlength="30" value="<?= $venueValue('postal_code') ?>"<?= $invalid('postal_code') ?>><?php if ($error = field_error($errors, 'postal_code')): ?><p id="postal-code-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
    </section>

    <section class="organizer-form__section" aria-labelledby="venue-location-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-map-trifold" aria-hidden="true"></i></span><div><h2 id="venue-location-heading">Place the venue pin</h2><p>Search once, choose a result, or place the pin directly. The written address stays unchanged.</p></div></div>

        <div class="venue-search-control">
            <div class="field-group">
                <label for="venue-address-search">Address search</label>
                <input id="venue-address-search" type="search" maxlength="160" value="<?= e($searchValue) ?>" data-venue-search aria-describedby="venue-address-search-help">
                <p id="venue-address-search-help" class="field-help">Search runs only when you select Find address. It does not change the address fields above.</p>
            </div>
            <button class="button button--quiet" type="button" data-venue-find><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span>Find address</span></button>
        </div>

        <div class="venue-search-results" data-venue-results aria-label="Address search results"></div>

        <div class="venue-map" data-venue-map aria-label="Venue pin map"
             data-tile-url="<?= e($mapConfig['tile_url'] ?? '') ?>"
             data-tile-attribution="<?= e($mapConfig['tile_attribution'] ?? '') ?>"
             data-default-lat="<?= e($mapConfig['default_lat'] ?? 23.8103) ?>"
             data-default-lng="<?= e($mapConfig['default_lng'] ?? 90.4125) ?>"
             data-default-zoom="<?= e($mapConfig['default_zoom'] ?? 11) ?>">
            <p class="venue-map__fallback">If the map is unavailable, enter both coordinates below.</p>
        </div>

        <div class="venue-map-actions">
            <button class="button button--quiet" type="button" data-venue-use-location><i class="ph ph-crosshair" aria-hidden="true"></i><span>Use current position</span></button>
            <button class="button button--quiet" type="button" data-venue-clear-pin><i class="ph ph-eraser" aria-hidden="true"></i><span>Clear pin</span></button>
        </div>
        <p class="venue-map-status" data-venue-status aria-live="polite">Choose an address result, click the map, or enter exact coordinates.</p>

        <details class="venue-coordinate-details"<?= field_error($errors, 'latitude') !== null || field_error($errors, 'longitude') !== null ? ' open' : '' ?>>
            <summary>Advanced coordinates</summary>
            <p class="field-help">Latitude and longitude must be saved together. Seven decimal places are supported.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group"><label for="latitude">Latitude</label><input id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?= $venueValue('latitude') ?>"<?= $invalid('latitude') ?>><?php if ($error = field_error($errors, 'latitude')): ?><p id="latitude-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
                <div class="field-group"><label for="longitude">Longitude</label><input id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?= $venueValue('longitude') ?>"<?= $invalid('longitude') ?>><?php if ($error = field_error($errors, 'longitude')): ?><p id="longitude-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            </div>
        </details>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group"><label for="map_url">Map URL <span class="field-label-note">Optional</span></label><input id="map_url" name="map_url" type="url" maxlength="500" placeholder="https://maps.example.com" value="<?= $venueValue('map_url') ?>"<?= $invalid('map_url') ?>><p class="field-help">Use an HTTPS directions or venue page.</p><?php if ($error = field_error($errors, 'map_url')): ?><p id="map-url-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="capacity">Maximum capacity <span class="field-label-note">Optional</span></label><input id="capacity" name="capacity" type="number" min="1" max="100000" step="1" value="<?= $venueValue('capacity') ?>"<?= $invalid('capacity') ?>><?php if ($error = field_error($errors, 'capacity')): ?><p id="capacity-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
    </section>
    <div class="organizer-form__actions"><p><i class="ph ph-info" aria-hidden="true"></i><span>Venue ownership stays with this organizer account.</span></p><button class="button button--primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span><?= $isEdit ? 'Save venue' : 'Create venue' ?></span></button></div>
</form>

<?php if ($isEdit): ?>
    <section class="organizer-danger-zone mt-6" aria-labelledby="venue-delete-heading">
        <div class="organizer-danger-zone__copy"><h2 id="venue-delete-heading">Delete venue</h2><p>This is available only when no active event uses this venue.</p></div>
        <form action="/organizer/venues/<?= e($venue['id']) ?>/delete" method="post">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <button class="button button--danger" type="submit"><i class="ph ph-trash" aria-hidden="true"></i><span>Delete venue</span></button>
        </form>
    </section>
<?php endif; ?>
