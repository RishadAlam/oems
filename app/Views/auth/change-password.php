<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker">Account security</p>
        <h1>Change password</h1>
        <p>Choose a unique password and keep your account protected.</p>
    </div>
</div>

<section class="dashboard-panel mt-8 max-w-2xl">
    <form class="form-stack" action="/settings/password" method="post" novalidate>
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <div class="field-group">
            <label for="current_password">Current password</label>
            <div class="password-field">
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                <button type="button" data-password-toggle data-password-label="current password" aria-controls="current_password" aria-pressed="false" aria-label="Show current password" title="Show current password"><i class="ph ph-eye" aria-hidden="true"></i></button>
            </div>
            <?php if ($error = field_error($errors, 'current_password')): ?>
                <p class="field-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group">
                <label for="password">New password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    <button type="button" data-password-toggle data-password-label="new password" aria-controls="password" aria-pressed="false" aria-label="Show new password" title="Show new password"><i class="ph ph-eye" aria-hidden="true"></i></button>
                </div>
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
        <div>
            <button class="button button--primary" type="submit">Save password</button>
        </div>
    </form>
</section>
