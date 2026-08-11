<?php $selectedRole = old_value($old, 'role', ($_GET['role'] ?? 'participant') === 'organizer' ? 'organizer' : 'participant'); ?>
<div class="auth-heading">
    <p class="auth-kicker"><i class="ph ph-user-plus" aria-hidden="true"></i><span>Join the community</span></p>
    <h1>Create your account</h1>
    <p>Start as an attendee, or open a workspace for your events.</p>
</div>

<form class="form-stack mt-9" action="/register" method="post" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <?php
    $fieldTargets = [
        'role' => 'role_participant',
        'name' => 'name',
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'password_confirmation',
        'terms' => 'terms',
    ];
    $fieldLabels = [
        'role' => 'Account type',
        'name' => 'Full name',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'terms' => 'Terms and privacy',
    ];
    $formErrorSummaryId = 'registration-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>

    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>All fields are required.</span></p>

    <fieldset class="field-group">
        <legend>How will you use OEMS?</legend>
        <div class="account-type-grid">
            <label>
                <input id="role_participant" type="radio" name="role" value="participant" required data-form-label="Account type"<?= form_control_attributes($errors, 'role', ['participant-role-description'], 'role-error') ?> <?= $selectedRole === 'participant' ? 'checked' : '' ?>>
                <span><i class="ph ph-ticket" aria-hidden="true"></i><strong>Attend events</strong><small id="participant-role-description">Discover, register, and manage tickets.</small><i class="ph ph-check-circle account-type-grid__check" aria-hidden="true"></i></span>
            </label>
            <label>
                <input id="role_organizer" type="radio" name="role" value="organizer" data-form-label="Account type"<?= form_control_attributes($errors, 'role', ['organizer-role-description'], 'role-error') ?> <?= $selectedRole === 'organizer' ? 'checked' : '' ?>>
                <span><i class="ph ph-microphone-stage" aria-hidden="true"></i><strong>Host events</strong><small id="organizer-role-description">Create events and manage participants.</small><i class="ph ph-check-circle account-type-grid__check" aria-hidden="true"></i></span>
            </label>
        </div>
        <?php if ($error = field_error($errors, 'role')): ?>
            <p id="role-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </fieldset>

    <div class="field-group">
        <label for="name">Full name</label>
        <div class="input-with-icon">
            <i class="ph ph-user" aria-hidden="true"></i>
            <input id="name" name="name" type="text" value="<?= old_value($old, 'name') ?>" autocomplete="name" minlength="2" maxlength="100" required data-form-label="Full name"<?= form_control_attributes($errors, 'name') ?>>
        </div>
        <?php if ($error = field_error($errors, 'name')): ?>
            <p id="name-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="field-group">
        <label for="email">Email address</label>
        <div class="input-with-icon">
            <i class="ph ph-envelope-simple" aria-hidden="true"></i>
            <input id="email" name="email" type="email" value="<?= old_value($old, 'email') ?>" autocomplete="email" inputmode="email" maxlength="190" required data-form-label="Email address"<?= form_control_attributes($errors, 'email') ?>>
        </div>
        <?php if ($error = field_error($errors, 'email')): ?>
            <p id="email-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field-group">
            <label for="password">Password</label>
            <div class="password-field input-with-icon">
                <i class="ph ph-lock-key" aria-hidden="true"></i>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required data-form-label="Password"<?= form_control_attributes($errors, 'password', ['password-help']) ?>>
                <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button>
            </div>
            <p id="password-help" class="field-help">Use 8 to 128 characters and avoid a password used elsewhere.</p>
            <?php if ($error = field_error($errors, 'password')): ?>
                <p id="password-error" class="field-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </div>
        <div class="field-group">
            <label for="password_confirmation">Confirm password</label>
            <div class="password-field input-with-icon">
                <i class="ph ph-lock-key" aria-hidden="true"></i>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="128" required data-form-label="Password confirmation" data-match-field="password"<?= form_control_attributes($errors, 'password_confirmation') ?>>
                <button type="button" data-password-toggle data-password-label="password confirmation" aria-controls="password_confirmation" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation"><i class="ph ph-eye" aria-hidden="true"></i></button>
            </div>
            <?php if ($error = field_error($errors, 'password_confirmation')): ?>
                <p id="password-confirmation-error" class="field-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <label class="check-row items-start">
        <input id="terms" name="terms" type="checkbox" value="1" required data-form-label="Terms and privacy"<?= form_control_attributes($errors, 'terms') ?>>
        <span>I agree to the platform terms and privacy policy.</span>
    </label>
    <?php if ($error = field_error($errors, 'terms')): ?>
        <p id="terms-error" class="field-error -mt-3" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <button class="button button--primary w-full" type="submit" data-submit-label="Creating account…"><span data-submit-text>Create account</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
</form>

<p class="auth-switch">Already have an account? <a href="/login">Sign in</a></p>
