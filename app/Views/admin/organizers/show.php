<?php
$approval = (string) ($organizer['approval_status'] ?? '');
$accountStatus = (string) ($organizer['user_status'] ?? '');
$organizerIdentity = ($organizer['role_slug'] ?? null) === 'organizer';
$accountActive = ($organizer['user_status'] ?? null) === 'active';
$emailVerified = !empty($organizer['email_verified_at']);
$eligibleForApproval = $organizerIdentity && $accountActive && $emailVerified;
$canApprove = $eligibleForApproval && in_array($approval, ['pending', 'rejected'], true);
$canReject = $organizerIdentity && in_array($approval, ['pending', 'approved'], true);
$approvalDecisionAvailable = in_array($approval, ['pending', 'rejected'], true);
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-buildings" aria-hidden="true"></i><span>Application evidence</span></p>
        <h1><?= e($organizer['organization_name'] ?? 'Organizer') ?></h1>
        <p>Primary contact: <?= e($organizer['name'] ?? 'Unknown') ?>, <?= e($organizer['email'] ?? '') ?></p>
    </div>
    <a class="button button--quiet" href="/admin/organizers"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to organizers</span></a>
</div>

<div class="admin-moderation-layout mt-8">
    <section class="dashboard-panel admin-evidence-panel" aria-labelledby="organizer-evidence-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-file-magnifying-glass" aria-hidden="true"></i></span><div><h2 id="organizer-evidence-heading">Organizer evidence</h2><p>Confirm account and organization details before deciding.</p></div></div>
        <?php if (!empty($organizer['description'])): ?><p class="organizer-event-description"><?= nl2br(e($organizer['description'])) ?></p><?php endif; ?>
        <?php if (!empty($organizer['rejection_reason'])): ?><div class="form-alert" role="note"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><strong>Previous feedback:</strong> <?= e($organizer['rejection_reason']) ?></span></div><?php endif; ?>
        <dl class="organizer-detail-list">
            <div><dt>Approval</dt><dd><span class="status-chip status-chip--<?= e(status_modifier($approval, 'organizer_approval')) ?>"><?= e(oems_status_label($approval)) ?></span></dd></div>
            <div><dt>Account status</dt><dd><span class="status-chip status-chip--<?= e(status_modifier($accountStatus, 'account')) ?>"><?= e(oems_status_label($accountStatus)) ?></span></dd></div>
            <div><dt>Email verification</dt><dd><span class="status-chip <?= $emailVerified ? 'status-chip--success' : 'status-chip--warning' ?>"><?= $emailVerified ? 'Verified' : 'Not verified' ?></span></dd></div>
            <div><dt>Tax identifier</dt><dd><?= e($organizer['tax_identifier'] ?? 'Not provided') ?></dd></div>
            <div><dt>Active events</dt><dd><?= e((int) ($organizer['event_count'] ?? 0)) ?></dd></div>
            <div><dt>Location</dt><dd><?= e(implode(', ', array_filter([$organizer['city'] ?? null, $organizer['country'] ?? null]))) ?: 'Not provided' ?></dd></div>
            <div><dt>Applied</dt><dd><?= e($organizer['created_at'] ?? 'Unknown') ?></dd></div>
            <div><dt>Last sign-in</dt><dd><?= e($organizer['last_login_at'] ?? 'No recorded sign-in') ?></dd></div>
        </dl>
    </section>

    <aside class="dashboard-panel organizer-actions-panel" aria-labelledby="organizer-actions-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-gavel" aria-hidden="true"></i></span><div><h2 id="organizer-actions-heading">Application actions</h2><p>Each action checks the latest application state.</p></div></div>
        <div class="organizer-action-stack">
            <?php if ($approvalDecisionAvailable): ?>
                <section id="organizer-approval-readiness" class="approval-readiness-panel approval-readiness-panel--<?= $canApprove ? 'ready' : 'blocked' ?>" aria-labelledby="organizer-approval-readiness-heading">
                    <div class="approval-readiness-panel__heading">
                        <i class="ph <?= $canApprove ? 'ph-check-circle' : 'ph-warning-circle' ?>" aria-hidden="true"></i>
                        <div>
                            <h3 id="organizer-approval-readiness-heading"><?= $canApprove ? 'Ready to approve' : 'Approval blocked' ?></h3>
                            <p><?= $canApprove ? 'All identity and account requirements are complete.' : 'Approval becomes available when every trust requirement is complete.' ?></p>
                        </div>
                    </div>
                    <ul class="approval-readiness-list approval-readiness-list--stacked" aria-label="Approval readiness requirements">
                        <?php foreach ([
                            ['label' => 'Organizer account role', 'complete' => $organizerIdentity],
                            ['label' => 'Account active', 'complete' => $accountActive],
                            ['label' => 'Email address verified', 'complete' => $emailVerified],
                        ] as $requirement): ?>
                            <li>
                                <span><i class="ph <?= $requirement['complete'] ? 'ph-check-circle' : 'ph-x-circle' ?>" aria-hidden="true"></i><?= e($requirement['label']) ?></span>
                                <strong><span class="status-chip <?= $requirement['complete'] ? 'status-chip--success' : 'status-chip--danger' ?>"><?= $requirement['complete'] ? 'Completed' : 'Not completed' ?></span></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php if ($canApprove): ?>
                    <form action="/admin/organizers/<?= e($organizer['id']) ?>/approve" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="expected_status" value="<?= e($approval) ?>"><button class="button button--primary w-full" type="submit" aria-describedby="organizer-approval-readiness" data-submit-label="Approving organizer…"><i class="ph ph-check-circle" aria-hidden="true"></i><span data-submit-text>Approve organizer</span></button></form>
                <?php else: ?>
                    <button class="button button--primary w-full" type="button" disabled aria-describedby="organizer-approval-readiness"><i class="ph ph-lock-key" aria-hidden="true"></i><span>Approve organizer</span></button>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($canReject): ?>
                <form action="/admin/organizers/<?= e($organizer['id']) ?>/reject" method="post" data-form-kind="entry">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="expected_status" value="<?= e($approval) ?>">
                    <?php $fieldTargets = ['reason' => 'reason']; $fieldLabels = ['reason' => 'Reason for rejection']; $formErrorSummaryId = 'organizer-rejection-error-summary'; require base_path('app/Views/components/form-errors.php'); ?>
                    <div class="field-group"><label for="reason">Reason for rejection</label><textarea id="reason" name="reason" rows="5" maxlength="500" required aria-describedby="<?= field_error($errors, 'reason') === null ? 'reason-help' : 'reason-help reason-error' ?>"<?= field_error($errors, 'reason') === null ? '' : ' aria-invalid="true"' ?>><?= old_value($old, 'reason') ?></textarea><p id="reason-help" class="field-help">Explain the exact change needed. Maximum 500 characters.</p><?php if ($error = field_error($errors, 'reason')): ?><p id="reason-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
                    <button class="button button--danger w-full" type="submit" data-submit-label="Rejecting organizer…"><i class="ph ph-x-circle" aria-hidden="true"></i><span data-submit-text>Reject organizer</span></button>
                </form>
            <?php endif; ?>
            <?php if (!$approvalDecisionAvailable && !$canReject): ?><p class="organizer-action-note"><i class="ph ph-info" aria-hidden="true"></i><span>No organizer action is available for the current account and approval state.</span></p><?php endif; ?>
        </div>
    </aside>
</div>
