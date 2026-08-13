<div class="auth-heading verification-recovery">
    <p class="auth-kicker"><i class="ph ph-envelope-simple-open" aria-hidden="true"></i><span>Email verification</span></p>
    <h1>Resend verification email</h1>
    <p>Enter your account email. If it still needs verification, we will send a new single-use link.</p>
</div>

<form class="form-stack mt-9" action="/verify-email/resend" method="post" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php
    $fieldTargets = ['email' => 'verification-email'];
    $fieldLabels = ['email' => 'Email address'];
    $formErrorSummaryId = 'verification-recovery-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>
    <div class="field-group">
        <label for="verification-email">Email address</label>
        <div class="input-with-icon">
            <i class="ph ph-envelope-simple" aria-hidden="true"></i>
            <input
                id="verification-email"
                name="email"
                type="email"
                value="<?= old_value($old, 'email', (string) ($currentUser['email'] ?? '')) ?>"
                autocomplete="email"
                inputmode="email"
                maxlength="190"
                required
                data-form-label="Email address"
                <?= ltrim(form_control_attributes($errors, 'email', ['verification-email-help'])) ?>
            >
        </div>
        <p id="verification-email-help" class="field-help">Only the newest verification link will work. Delivery may take a few minutes.</p>
        <?php if ($error = field_error($errors, 'email')): ?>
            <p id="verification-email-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <button class="button button--primary w-full" type="submit" data-submit-label="Sending verification email…"><span data-submit-text>Send verification email</span><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i></button>
</form>

<p class="auth-switch"><a href="/login">Return to sign in</a></p>
