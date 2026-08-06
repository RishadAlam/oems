<?php
$profileValue = static fn (string $key): string => old_value($old, $key, (string) ($profile[$key] ?? ''));
$selectedValue = static fn (string $key): string => (string) ($old[$key] ?? $profile[$key] ?? '');
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true"';
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker">Account settings</p>
        <h1>Your profile</h1>
        <p>Keep your contact details and preferences current.</p>
    </div>
</div>

<section class="dashboard-panel mt-8 max-w-5xl">
    <form class="form-stack" action="/profile" method="post" novalidate>
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

        <div class="profile-form-section">
            <div class="profile-form-section__heading">
                <h2>Account details</h2>
                <p>Your email and role are managed by the platform.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="<?= $profileValue('name') ?>" autocomplete="name" maxlength="100" required<?= $invalid('name') ?> aria-describedby="name-error">
                    <?php if ($error = field_error($errors, 'name')): ?>
                        <p id="name-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="phone">Phone <span class="field-label-note">Optional</span></label>
                    <input id="phone" name="phone" type="tel" value="<?= $profileValue('phone') ?>" autocomplete="tel" maxlength="30"<?= $invalid('phone') ?> aria-describedby="phone-help phone-error">
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

        <div class="profile-form-section">
            <div class="profile-form-section__heading">
                <h2>Personal details</h2>
                <p>Share only the information you want associated with your account.</p>
            </div>
            <div class="field-group">
                <label for="bio">Bio <span class="field-label-note">Optional</span></label>
                <textarea id="bio" name="bio" maxlength="2000" rows="5"<?= $invalid('bio') ?> aria-describedby="bio-help bio-error"><?= $profileValue('bio') ?></textarea>
                <p id="bio-help" class="field-help">A short introduction for event activity.</p>
                <?php if ($error = field_error($errors, 'bio')): ?>
                    <p id="bio-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group">
                    <label for="date_of_birth">Date of birth <span class="field-label-note">Optional</span></label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="<?= $profileValue('date_of_birth') ?>" max="<?= e(date('Y-m-d')) ?>"<?= $invalid('date_of_birth') ?> aria-describedby="date-of-birth-error">
                    <?php if ($error = field_error($errors, 'date_of_birth')): ?>
                        <p id="date-of-birth-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="gender">Gender <span class="field-label-note">Optional</span></label>
                    <select id="gender" name="gender"<?= $invalid('gender') ?> aria-describedby="gender-error">
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

        <div class="profile-form-section">
            <div class="profile-form-section__heading">
                <h2>Address</h2>
                <p>These fields are optional.</p>
            </div>
            <div class="field-group">
                <label for="address_line">Street address</label>
                <input id="address_line" name="address_line" type="text" value="<?= $profileValue('address_line') ?>" autocomplete="street-address" maxlength="190"<?= $invalid('address_line') ?> aria-describedby="address-line-error">
                <?php if ($error = field_error($errors, 'address_line')): ?>
                    <p id="address-line-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="field-group">
                    <label for="city">City</label>
                    <input id="city" name="city" type="text" value="<?= $profileValue('city') ?>" autocomplete="address-level2" maxlength="100"<?= $invalid('city') ?> aria-describedby="city-error">
                    <?php if ($error = field_error($errors, 'city')): ?>
                        <p id="city-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="country">Country</label>
                    <input id="country" name="country" type="text" value="<?= $profileValue('country') ?>" autocomplete="country-name" maxlength="100"<?= $invalid('country') ?> aria-describedby="country-error">
                    <?php if ($error = field_error($errors, 'country')): ?>
                        <p id="country-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="postal_code">Postal code</label>
                    <input id="postal_code" name="postal_code" type="text" value="<?= $profileValue('postal_code') ?>" autocomplete="postal-code" maxlength="30"<?= $invalid('postal_code') ?> aria-describedby="postal-code-error">
                    <?php if ($error = field_error($errors, 'postal_code')): ?>
                        <p id="postal-code-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-form-section">
            <div class="profile-form-section__heading">
                <h2>Regional preferences</h2>
                <p>Choose how dates and times should be presented.</p>
            </div>
            <div class="field-group">
                <label for="website">Website <span class="field-label-note">Optional</span></label>
                <input id="website" name="website" type="url" value="<?= $profileValue('website') ?>" autocomplete="url" maxlength="255" placeholder="https://example.com"<?= $invalid('website') ?> aria-describedby="website-error">
                <?php if ($error = field_error($errors, 'website')): ?>
                    <p id="website-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field-group">
                    <label for="locale">Language</label>
                    <select id="locale" name="locale" required<?= $invalid('locale') ?> aria-describedby="locale-error">
                        <option value="en" <?= $selectedValue('locale') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="bn" <?= $selectedValue('locale') === 'bn' ? 'selected' : '' ?>>Bangla</option>
                    </select>
                    <?php if ($error = field_error($errors, 'locale')): ?>
                        <p id="locale-error" class="field-error" role="alert"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" required<?= $invalid('timezone') ?> aria-describedby="timezone-error">
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
            <button class="button button--primary w-full sm:w-auto" type="submit">Save profile</button>
        </div>
    </form>
</section>
