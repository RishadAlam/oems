<div class="auth-heading">
    <h1>Welcome back</h1>
    <p>Sign in to reach your events, tickets, and workspace.</p>
</div>

<form class="form-stack mt-9" action="/login" method="post" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

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
            aria-describedby="email-help<?= field_error($errors, 'email') ? ' email-error' : '' ?>"
            <?= field_error($errors, 'email') ? 'aria-invalid="true"' : '' ?>
        >
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
        <div class="password-field">
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                <?= field_error($errors, 'password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>
            >
            <button type="button" data-password-toggle aria-controls="password">Show</button>
        </div>
        <?php if ($error = field_error($errors, 'password')): ?>
            <p id="password-error" class="field-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
    </div>

    <label class="check-row">
        <input name="remember" type="checkbox" value="1">
        <span>Keep me signed in on this device</span>
    </label>

    <button class="button button--primary w-full" type="submit">Sign in</button>
</form>

<p class="auth-switch">New to OEMS? <a href="/register">Create an account</a></p>

