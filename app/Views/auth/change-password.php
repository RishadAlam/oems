<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Account security</span></p>
        <h1>Change password</h1>
        <p>Choose a unique password and keep your account protected.</p>
    </div>
</div>

<div class="security-layout mt-8">
<aside class="security-note">
    <span><i class="ph ph-lock-key" aria-hidden="true"></i></span>
    <h2>A stronger password protects every ticket and workspace.</h2>
    <p>Use at least 8 characters and avoid a password you use on another service.</p>
</aside>
<section class="dashboard-panel">
    <form class="form-stack" action="/settings/password" method="post" data-form-kind="entry">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <?php
        $fieldTargets = ['current_password' => 'current_password', 'password' => 'password', 'password_confirmation' => 'password_confirmation'];
        $fieldLabels = ['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm password'];
        $formErrorSummaryId = 'change-password-error-summary';
        require base_path('app/Views/components/form-errors.php');
        ?>
        <div class="field-group">
            <label for="current_password">Current password</label>
            <div class="password-field input-with-icon">
                <i class="ph ph-lock-key" aria-hidden="true"></i>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" maxlength="1024" required data-form-label="Current password"<?= form_control_attributes($errors, 'current_password') ?>>
                <button type="button" data-password-toggle data-password-label="current password" aria-controls="current_password" aria-pressed="false" aria-label="Show current password" title="Show current password"><i class="ph ph-eye" aria-hidden="true"></i></button>
            </div>
            <?php if ($error = field_error($errors, 'current_password')): ?>
                <p id="current-password-error" class="field-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group">
                <label for="password">New password</label>
                <div class="password-field input-with-icon">
                    <i class="ph ph-key" aria-hidden="true"></i>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required data-form-label="New password"<?= form_control_attributes($errors, 'password') ?>>
                    <button type="button" data-password-toggle data-password-label="new password" aria-controls="password" aria-pressed="false" aria-label="Show new password" title="Show new password"><i class="ph ph-eye" aria-hidden="true"></i></button>
                </div>
                <?php if ($error = field_error($errors, 'password')): ?>
                    <p id="password-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
            <div class="field-group">
                <label for="password_confirmation">Confirm password</label>
                <div class="password-field input-with-icon">
                    <i class="ph ph-key" aria-hidden="true"></i>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="128" required data-form-label="Password confirmation" data-match-field="password"<?= form_control_attributes($errors, 'password_confirmation') ?>>
                    <button type="button" data-password-toggle data-password-label="password confirmation" aria-controls="password_confirmation" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation"><i class="ph ph-eye" aria-hidden="true"></i></button>
                </div>
                <?php if ($error = field_error($errors, 'password_confirmation')): ?>
                    <p id="password-confirmation-error" class="field-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <button class="button button--primary" type="submit" data-submit-label="Saving password…"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span data-submit-text>Save password</span></button>
        </div>
    </form>
</section>
</div>
