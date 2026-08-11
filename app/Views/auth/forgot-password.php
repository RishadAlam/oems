<div class="auth-heading">
    <p class="auth-kicker"><i class="ph ph-key" aria-hidden="true"></i><span>Account recovery</span></p>
    <h1>Reset your password</h1>
    <p>Enter your email and we will send you a secure reset link.</p>
</div>

<form class="form-stack mt-9" action="/forgot-password" method="post" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php
    $fieldTargets = ['email' => 'email'];
    $fieldLabels = ['email' => 'Email address'];
    $formErrorSummaryId = 'password-recovery-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>
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
    <button class="button button--primary w-full" type="submit" data-submit-label="Sending reset link…"><span data-submit-text>Send reset link</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
</form>

<p class="auth-switch"><a href="/login">Return to sign in</a></p>
