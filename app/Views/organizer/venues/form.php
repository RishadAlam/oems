<?php
$isEdit = is_array($venue);
$action = $isEdit ? '/organizer/venues/' . (int) $venue['id'] : '/organizer/venues';
$venueValue = static fn (string $key, string $default = ''): string => old_value($old, $key, (string) ($venue[$key] ?? $default));
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"';
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-map-pin-line" aria-hidden="true"></i><span>Venue editor</span></p><h1><?= $isEdit ? 'Edit venue' : 'Create venue' ?></h1><p>Store accurate location and capacity details for event planning.</p></div>
    <a class="button button--quiet" href="/organizer/venues"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to venues</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post" novalidate>
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

    <section class="organizer-form__section" aria-labelledby="venue-planning-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-compass-tool" aria-hidden="true"></i></span><div><h2 id="venue-planning-heading">Planning details</h2><p>Coordinates, map URL, and capacity are optional.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group"><label for="latitude">Latitude</label><input id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?= $venueValue('latitude') ?>"<?= $invalid('latitude') ?>><?php if ($error = field_error($errors, 'latitude')): ?><p id="latitude-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="longitude">Longitude</label><input id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?= $venueValue('longitude') ?>"<?= $invalid('longitude') ?>><?php if ($error = field_error($errors, 'longitude')): ?><p id="longitude-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="map_url">Map URL</label><input id="map_url" name="map_url" type="url" maxlength="500" placeholder="https://maps.example.com" value="<?= $venueValue('map_url') ?>"<?= $invalid('map_url') ?>><?php if ($error = field_error($errors, 'map_url')): ?><p id="map-url-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="capacity">Maximum capacity</label><input id="capacity" name="capacity" type="number" min="1" max="100000" step="1" value="<?= $venueValue('capacity') ?>"<?= $invalid('capacity') ?>><?php if ($error = field_error($errors, 'capacity')): ?><p id="capacity-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
    </section>
    <div class="organizer-form__actions"><p><i class="ph ph-info" aria-hidden="true"></i><span>Venue ownership stays with this organizer account.</span></p><div class="organizer-heading-actions"><?php if ($isEdit): ?><button class="button button--danger" type="submit" formaction="/organizer/venues/<?= e($venue['id']) ?>/delete"><i class="ph ph-trash" aria-hidden="true"></i><span>Delete venue</span></button><?php endif; ?><button class="button button--primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span><?= $isEdit ? 'Save venue' : 'Create venue' ?></span></button></div></div>
</form>
