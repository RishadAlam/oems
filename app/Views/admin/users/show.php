<?php
$status = (string) ($managedUser['status'] ?? 'inactive');
$role = (string) ($managedUser['role_slug'] ?? '');
$canSuspend = in_array($role, ['participant', 'organizer'], true) && $status === 'active';
$canDeactivate = $canSuspend;
$canReactivate = in_array($role, ['participant', 'organizer'], true)
    && in_array($status, ['inactive', 'suspended'], true);
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-identification-badge" aria-hidden="true"></i><span>Account review</span></p>
        <h1><?= e($managedUser['name'] ?? 'User') ?></h1>
        <p><?= e($managedUser['email'] ?? '') ?></p>
    </div>
    <a class="button button--quiet" href="/admin/users"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to users</span></a>
</div>

<div class="admin-moderation-layout mt-8">
    <section class="dashboard-panel admin-evidence-panel" aria-labelledby="account-evidence-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-user-list" aria-hidden="true"></i></span><div><h2 id="account-evidence-heading">Account evidence</h2><p>Review account and access history before taking action.</p></div></div>
        <dl class="organizer-detail-list">
            <div><dt>Role</dt><dd><?= e($managedUser['role_name'] ?? ucfirst($role)) ?></dd></div>
            <div><dt>Status</dt><dd><span class="status-chip status-chip--<?= e($status) ?>"><?= e(ucfirst($status)) ?></span></dd></div>
            <div><dt>Email verification</dt><dd><span class="status-chip <?= !empty($managedUser['email_verified_at']) ? 'status-chip--success' : 'status-chip--warning' ?>"><?= !empty($managedUser['email_verified_at']) ? 'Verified' : 'Not verified' ?></span></dd></div>
            <div><dt>Active remembered sessions</dt><dd><?= e((int) ($managedUser['session_count'] ?? 0)) ?></dd></div>
            <div><dt>Registrations</dt><dd><?= e((int) ($managedUser['registration_count'] ?? 0)) ?></dd></div>
            <div><dt>Last sign-in</dt><dd><?= e($managedUser['last_login_at'] ?? 'No recorded sign-in') ?></dd></div>
            <div><dt>Location</dt><dd><?= e(implode(', ', array_filter([$managedUser['city'] ?? null, $managedUser['country'] ?? null]))) ?: 'Not provided' ?></dd></div>
            <div><dt>Joined</dt><dd><?= e($managedUser['created_at'] ?? 'Unknown') ?></dd></div>
        </dl>
    </section>

    <aside class="dashboard-panel organizer-actions-panel" aria-labelledby="account-actions-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-shield-check" aria-hidden="true"></i></span><div><h2 id="account-actions-heading">Access action</h2><p>Changes are checked against the latest account state.</p></div></div>
        <div class="organizer-action-stack">
            <?php if ($canSuspend): ?>
                <div class="form-alert" role="note"><i class="ph ph-warning" aria-hidden="true"></i><span>Suspending signs the user out of remembered sessions and invalidates pending reset links.</span></div>
                <form action="/admin/users/<?= e($managedUser['id']) ?>/suspend" method="post" data-form-kind="action" data-confirm="Suspend this account and revoke its active sessions?">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="expected_status" value="active">
                    <button class="button button--danger w-full" type="submit" data-submit-label="Suspending account…"><i class="ph ph-user-minus" aria-hidden="true"></i><span data-submit-text>Suspend account</span></button>
                </form>
                <?php if ($canDeactivate): ?>
                    <form action="/admin/users/<?= e($managedUser['id']) ?>/deactivate" method="post" data-form-kind="action" data-confirm="Deactivate this account and revoke its active sessions?">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="expected_status" value="active">
                        <button class="button button--quiet w-full" type="submit" data-submit-label="Deactivating account…"><i class="ph ph-user-circle-minus" aria-hidden="true"></i><span data-submit-text>Deactivate account</span></button>
                    </form>
                <?php endif; ?>
            <?php elseif ($canReactivate): ?>
                <p class="organizer-action-note"><i class="ph ph-info" aria-hidden="true"></i><span>Reactivation restores sign-in eligibility. Previous sessions remain revoked.</span></p>
                <form action="/admin/users/<?= e($managedUser['id']) ?>/reactivate" method="post" data-form-kind="action">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="expected_status" value="<?= e($status) ?>">
                    <button class="button button--primary w-full" type="submit" data-submit-label="Reactivating account…"><i class="ph ph-user-check" aria-hidden="true"></i><span data-submit-text>Reactivate account</span></button>
                </form>
            <?php else: ?>
                <p class="organizer-action-note"><i class="ph ph-lock-key" aria-hidden="true"></i><span>This account cannot be changed from this workspace.</span></p>
            <?php endif; ?>
        </div>
    </aside>
</div>
