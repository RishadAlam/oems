<div class="auth-heading">
    <p class="auth-kicker"><i class="ph ph-key" aria-hidden="true"></i><span>Account recovery</span></p>
    <h1>Reset your password</h1>
    <p>Enter your email and we will prepare a secure reset link.</p>
</div>

<form class="form-stack mt-9" action="/forgot-password" method="post" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="field-group">
        <label for="email">Email address</label>
        <div class="input-with-icon">
            <i class="ph ph-envelope-simple" aria-hidden="true"></i>
            <input id="email" name="email" type="email" value="<?= old_value($old, 'email') ?>" autocomplete="email" required <?= field_error($errors, 'email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
        </div>
        <?php if ($error = field_error($errors, 'email')): ?>
            <p id="email-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <button class="button button--primary w-full" type="submit"><span>Prepare reset link</span><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
</form>

<p class="auth-switch"><a href="/login">Return to sign in</a></p>
