<div class="auth-heading">
    <h1>Reset your password</h1>
    <p>Enter your email and we will prepare a secure reset link.</p>
</div>

<form class="form-stack mt-9" action="/forgot-password" method="post" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="field-group">
        <label for="email">Email address</label>
        <input
            id="email"
            name="email"
            type="email"
            value="<?= old_value($old, 'email') ?>"
            autocomplete="email"
            required
            <?= field_error($errors, 'email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
        >
        <?php if ($error = field_error($errors, 'email')): ?>
            <p id="email-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <button class="button button--primary w-full" type="submit">Prepare reset link</button>
</form>

<p class="auth-switch"><a href="/login">Return to sign in</a></p>

