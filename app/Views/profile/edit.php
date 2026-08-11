<?php
$profileValue = static fn (string $key): string => old_value($old, $key, (string) ($profile[$key] ?? ''));
$selectedValue = static fn (string $key): string => (string) ($old[$key] ?? $profile[$key] ?? '');
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true"';
$describedBy = static function (
    string $key,
    ?string $helpId = null,
    ?string $errorId = null,
) use ($errors): string {
    $ids = $helpId === null ? [] : [$helpId];

    if (field_error($errors, $key) !== null) {
        $ids[] = $errorId ?? str_replace('_', '-', $key) . '-error';
    }

    return $ids === [] ? '' : ' aria-describedby="' . implode(' ', $ids) . '"';
};
$profileNameParts = preg_split('/\s+/', trim((string) $profile['name'])) ?: [];
$profileInitials = implode('', array_map(
    static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
    array_slice(array_filter($profileNameParts), 0, 2),
));
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-user-circle" aria-hidden="true"></i><span>Account settings</span></p>
        <h1>Your profile</h1>
        <p>Keep your contact details and preferences current.</p>
    </div>
</div>

<div class="profile-layout mt-8">
    <aside class="profile-identity" aria-label="<?= e($profile['name']) ?> profile summary">
        <span class="profile-identity__avatar" aria-hidden="true"><?= e($profileInitials !== '' ? $profileInitials : 'O') ?></span>
        <div><h2><?= e($profile['name']) ?></h2><p><?= e($profile['email']) ?></p></div>
        <span class="role-badge"><?= e($profile['role_name']) ?></span>
        <dl>
            <div><dt>Account</dt><dd><i class="ph ph-check-circle" aria-hidden="true"></i>Active</dd></div>
            <div><dt>Email</dt><dd><i class="ph ph-seal-check" aria-hidden="true"></i>Verified</dd></div>
        </dl>
    </aside>

<section class="dashboard-panel profile-form-panel">
    <form class="form-stack" action="/profile" method="post" data-form-kind="entry">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

        <?php
        $fieldLabels = [
            'name' => 'Full name',
            'phone' => 'Phone',
            'bio' => 'Bio',
            'date_of_birth' => 'Date of birth',
            'gender' => 'Gender',
            'address_line' => 'Street address',
            'city' => 'City',
            'country' => 'Country',
            'postal_code' => 'Postal code',
            'website' => 'Website',
            'locale' => 'Language',
            'timezone' => 'Timezone',
        ];
        $formErrorSummaryId = 'profile-error-summary';
        require base_path('app/Views/components/form-errors.php');
        ?>

        <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>Full name, language, and timezone are required.</span></p>

        <div class="profile-form-section" aria-labelledby="profile-account-heading">
            <div class="profile-form-section__heading">
                <span><i class="ph ph-identification-card" aria-hidden="true"></i></span>
                <div><h2 id="profile-account-heading">Account details</h2><p>Your email and role are managed by the platform.</p></div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="<?= $profileValue('name') ?>" autocomplete="name" minlength="2" maxlength="100" data-form-label="Full name" required<?= $invalid('name') ?><?= $describedBy('name') ?>>
                    <?php if ($error = field_error($errors, 'name')): ?>
                        <p id="name-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="phone">Phone <span class="field-label-note">Optional</span></label>
                    <input id="phone" name="phone" type="tel" value="<?= $profileValue('phone') ?>" autocomplete="tel" maxlength="30" data-form-label="Phone"<?= $invalid('phone') ?><?= $describedBy('phone', 'phone-help') ?>>
                    <p id="phone-help" class="field-help">Include the country code when possible.</p>
                    <?php if ($error = field_error($errors, 'phone')): ?>
                        <p id="phone-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="email">Email address</label>
                    <input id="email" type="email" value="<?= e($profile['email']) ?>" autocomplete="email" readonly aria-describedby="email-help">
                    <p id="email-help" class="field-help">Contact support if this address needs to change.</p>
                </div>
                <div class="field-group">
                    <label for="role">Account role</label>
                    <input id="role" type="text" value="<?= e($profile['role_name']) ?>" readonly aria-describedby="role-help">
                    <p id="role-help" class="field-help">Your role controls dashboard access.</p>
                </div>
            </div>
        </div>

        <div class="profile-form-section" aria-labelledby="profile-personal-heading">
            <div class="profile-form-section__heading">
                <span><i class="ph ph-user" aria-hidden="true"></i></span>
                <div><h2 id="profile-personal-heading">Personal details</h2><p>Share only the information you want associated with your account.</p></div>
            </div>
            <div class="field-group">
                <label for="bio">Bio <span class="field-label-note">Optional</span></label>
                <textarea id="bio" name="bio" maxlength="2000" rows="5" data-form-label="Bio"<?= $invalid('bio') ?><?= $describedBy('bio', 'bio-help') ?>><?= $profileValue('bio') ?></textarea>
                <p id="bio-help" class="field-help">A short introduction for event activity.</p>
                <?php if ($error = field_error($errors, 'bio')): ?>
                    <p id="bio-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group">
                    <label for="date_of_birth">Date of birth <span class="field-label-note">Optional</span></label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="<?= $profileValue('date_of_birth') ?>" max="<?= e(date('Y-m-d')) ?>" data-form-label="Date of birth"<?= $invalid('date_of_birth') ?><?= $describedBy('date_of_birth') ?>>
                    <?php if ($error = field_error($errors, 'date_of_birth')): ?>
                        <p id="date-of-birth-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="gender">Gender <span class="field-label-note">Optional</span></label>
                    <select id="gender" name="gender" data-form-label="Gender"<?= $invalid('gender') ?><?= $describedBy('gender') ?>>
                        <option value="">Choose an option</option>
                        <option value="female" <?= $selectedValue('gender') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="male" <?= $selectedValue('gender') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="non-binary" <?= $selectedValue('gender') === 'non-binary' ? 'selected' : '' ?>>Non-binary</option>
                        <option value="prefer-not-to-say" <?= $selectedValue('gender') === 'prefer-not-to-say' ? 'selected' : '' ?>>Prefer not to say</option>
                    </select>
                    <?php if ($error = field_error($errors, 'gender')): ?>
                        <p id="gender-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-form-section" aria-labelledby="profile-address-heading">
            <div class="profile-form-section__heading">
                <span><i class="ph ph-map-pin" aria-hidden="true"></i></span>
                <div><h2 id="profile-address-heading">Address</h2><p>These fields are optional.</p></div>
            </div>
            <div class="field-group">
                <label for="address_line">Street address</label>
                <input id="address_line" name="address_line" type="text" value="<?= $profileValue('address_line') ?>" autocomplete="street-address" maxlength="190" data-form-label="Street address"<?= $invalid('address_line') ?><?= $describedBy('address_line') ?>>
                <?php if ($error = field_error($errors, 'address_line')): ?>
                    <p id="address-line-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="field-group">
                    <label for="city">City</label>
                    <input id="city" name="city" type="text" value="<?= $profileValue('city') ?>" autocomplete="address-level2" maxlength="100" data-form-label="City"<?= $invalid('city') ?><?= $describedBy('city') ?>>
                    <?php if ($error = field_error($errors, 'city')): ?>
                        <p id="city-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="country">Country</label>
                    <input id="country" name="country" type="text" value="<?= $profileValue('country') ?>" autocomplete="country-name" maxlength="100" data-form-label="Country"<?= $invalid('country') ?><?= $describedBy('country') ?>>
                    <?php if ($error = field_error($errors, 'country')): ?>
                        <p id="country-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="postal_code">Postal code</label>
                    <input id="postal_code" name="postal_code" type="text" value="<?= $profileValue('postal_code') ?>" autocomplete="postal-code" maxlength="30" data-form-label="Postal code"<?= $invalid('postal_code') ?><?= $describedBy('postal_code') ?>>
                    <?php if ($error = field_error($errors, 'postal_code')): ?>
                        <p id="postal-code-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-form-section" aria-labelledby="profile-regional-heading">
            <div class="profile-form-section__heading">
                <span><i class="ph ph-globe-hemisphere-east" aria-hidden="true"></i></span>
                <div><h2 id="profile-regional-heading">Regional preferences</h2><p>Choose how dates and times should be presented.</p></div>
            </div>
            <div class="field-group">
                <label for="website">Website <span class="field-label-note">Optional</span></label>
                <input id="website" name="website" type="url" value="<?= $profileValue('website') ?>" autocomplete="url" maxlength="255" placeholder="https://example.com" data-form-label="Website"<?= $invalid('website') ?><?= $describedBy('website') ?>>
                <?php if ($error = field_error($errors, 'website')): ?>
                    <p id="website-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group">
                    <label for="locale">Language</label>
                    <select id="locale" name="locale" data-form-label="Language" required<?= $invalid('locale') ?><?= $describedBy('locale') ?>>
                        <option value="en" <?= $selectedValue('locale') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="bn" <?= $selectedValue('locale') === 'bn' ? 'selected' : '' ?>>Bangla</option>
                    </select>
                    <?php if ($error = field_error($errors, 'locale')): ?>
                        <p id="locale-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" data-form-label="Timezone" required<?= $invalid('timezone') ?><?= $describedBy('timezone') ?>>
                        <option value="Asia/Dhaka" <?= $selectedValue('timezone') === 'Asia/Dhaka' ? 'selected' : '' ?>>Asia/Dhaka</option>
                        <option value="UTC" <?= $selectedValue('timezone') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                    </select>
                    <?php if ($error = field_error($errors, 'timezone')): ?>
                        <p id="timezone-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-form-actions">
            <p><i class="ph ph-info" aria-hidden="true"></i><span>Your changes apply to this account only.</span></p>
            <button class="button button--primary w-full sm:w-auto" type="submit" data-submit-label="Saving profile…"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span data-submit-text>Save profile</span></button>
        </div>
    </form>
</section>
</div>
