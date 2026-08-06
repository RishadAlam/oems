<?php $selectedRole = old_value($old, 'role', ($_GET['role'] ?? 'participant') === 'organizer' ? 'organizer' : 'participant'); ?>
<div class="auth-heading">
    <h1>Create your account</h1>
    <p>Start as an attendee, or open a workspace for your events.</p>
</div>

<form class="form-stack mt-9" action="/register" method="post" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

    <fieldset class="field-group">
        <legend>How will you use OEMS?</legend>
        <div class="account-type-grid">
            <label>
                <input type="radio" name="role" value="participant" <?= $selectedRole === 'participant' ? 'checked' : '' ?>>
                <span><strong>Attend events</strong><small>Discover, register, and manage tickets.</small></span>
            </label>
            <label>
                <input type="radio" name="role" value="organizer" <?= $selectedRole === 'organizer' ? 'checked' : '' ?>>
                <span><strong>Host events</strong><small>Create events and manage participants.</small></span>
            </label>
        </div>
        <?php if ($error = field_error($errors, 'role')): ?>
            <p class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </fieldset>

    <div class="field-group">
        <label for="name">Full name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="<?= old_value($old, 'name') ?>"
            autocomplete="name"
            maxlength="100"
            required
            <?= field_error($errors, 'name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>
        >
        <?php if ($error = field_error($errors, 'name')): ?>
            <p id="name-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="field-group">
        <label for="email">Email address</label>
        <input
            id="email"
            name="email"
            type="email"
            value="<?= old_value($old, 'email') ?>"
            autocomplete="email"
            inputmode="email"
            required
            <?= field_error($errors, 'email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
        >
        <?php if ($error = field_error($errors, 'email')): ?>
            <p id="email-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field-group">
            <label for="password">Password</label>
            <div class="password-field">
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button>
            </div>
            <p class="field-help">Use at least 8 characters.</p>
            <?php if ($error = field_error($errors, 'password')): ?>
                <p class="field-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </div>
        <div class="field-group">
            <label for="password_confirmation">Confirm password</label>
            <div class="password-field">
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                <button type="button" data-password-toggle data-password-label="password confirmation" aria-controls="password_confirmation" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation"><i class="ph ph-eye" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>

    <label class="check-row items-start">
        <input name="terms" type="checkbox" value="1" required>
        <span>I agree to the platform terms and privacy policy.</span>
    </label>
    <?php if ($error = field_error($errors, 'terms')): ?>
        <p class="field-error -mt-3" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <button class="button button--primary w-full" type="submit">Create account</button>
</form>

<p class="auth-switch">Already have an account? <a href="/login">Sign in</a></p>
