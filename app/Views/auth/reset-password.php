<div class="auth-heading">
    <p class="auth-kicker"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Secure your account</span></p>
    <h1>Choose a new password</h1>
    <p>Use a password that is unique to your OEMS account.</p>
</div>

<form class="form-stack mt-9" action="/reset-password/<?= e(rawurlencode($token)) ?>" method="post" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="field-group">
        <label for="password">New password</label>
        <div class="password-field input-with-icon">
            <i class="ph ph-lock-key" aria-hidden="true"></i>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
            <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password"><i class="ph ph-eye" aria-hidden="true"></i></button>
        </div>
        <?php if ($error = field_error($errors, 'password')): ?>
            <p class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <div class="field-group">
        <label for="password_confirmation">Confirm new password</label>
        <div class="password-field input-with-icon">
            <i class="ph ph-lock-key" aria-hidden="true"></i>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required <?= field_error($errors, 'password_confirmation') ? 'aria-invalid="true" aria-describedby="password-confirmation-error"' : '' ?>>
            <button type="button" data-password-toggle data-password-label="password confirmation" aria-controls="password_confirmation" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation"><i class="ph ph-eye" aria-hidden="true"></i></button>
        </div>
        <?php if ($error = field_error($errors, 'password_confirmation')): ?>
            <p id="password-confirmation-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <button class="button button--primary w-full" type="submit"><span>Update password</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
</form>
