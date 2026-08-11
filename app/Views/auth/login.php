<div class="auth-heading">
    <p class="auth-kicker"><i class="ph ph-sign-in" aria-hidden="true"></i><span>Account access</span></p>
    <h1>Welcome back</h1>
    <p>Sign in to reach your events, tickets, and workspace.</p>
</div>

<form class="form-stack mt-9" action="/login" method="post" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php if (is_string($returnTo ?? null)): ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><?php endif; ?>

    <?php
    $fieldTargets = ['email' => 'email', 'password' => 'password'];
    $fieldLabels = ['email' => 'Email address', 'password' => 'Password'];
    $formErrorSummaryId = 'login-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>

    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>All fields marked required must be completed.</span></p>

    <div class="field-group">
        <label for="email">Email address</label>
        <div class="input-with-icon">
            <i class="ph ph-envelope-simple" aria-hidden="true"></i>
            <input id="email" name="email" type="email" value="<?= old_value($old, 'email') ?>" autocomplete="email" inputmode="email" maxlength="190" required data-form-label="Email address"<?= form_control_attributes($errors, 'email', ['email-help']) ?>>
        </div>
        <p id="email-help" class="field-help">Use the email connected to your OEMS account.</p>
        <?php if ($error = field_error($errors, 'email')): ?>
            <p id="email-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <div class="field-group">
        <div class="flex items-center justify-between gap-4">
            <label for="password">Password</label>
            <a class="text-link text-xs" href="/forgot-password">Forgot password?</a>
        </div>
        <div class="password-field input-with-icon">
            <i class="ph ph-lock-key" aria-hidden="true"></i>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                maxlength="1024"
                required
                data-form-label="Password"
                <?= ltrim(form_control_attributes($errors, 'password')) ?>
            >
            <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button>
        </div>
        <?php if ($error = field_error($errors, 'password')): ?>
            <p id="password-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <label class="check-row">
        <input name="remember" type="checkbox" value="1">
        <span>Keep me signed in on this device</span>
    </label>

    <button class="button button--primary w-full" type="submit" data-submit-label="Signing in…"><span data-submit-text>Sign in</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
</form>

<p class="auth-switch">New to OEMS? <a href="/register">Create an account</a></p>
