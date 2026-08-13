<?php
$status = (string) ($payment['payment_status'] ?? 'pending');
$statusLabels = ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'];
$queueQuery = http_build_query(array_filter($returnFilters, static fn (mixed $value): bool => $value !== '' && $value !== null), '', '&', PHP_QUERY_RFC3986);
$preserved = array_filter($returnFilters, static fn (mixed $value): bool => $value !== '' && $value !== null);
$impact = match (true) {
    $status === 'paid' && in_array((string) ($payment['ticket_status'] ?? 'none'), ['valid', 'used'], true) => 'Registration confirmed and ticket issued',
    $status === 'failed' && (string) ($payment['registration_status'] ?? '') === 'cancelled' => 'Registration cancelled and seat released',
    $status === 'refunded' => 'Payment refunded and registration no longer active',
    default => 'Seat held while payment is pending',
};
?>

<div class="dashboard-page-heading organizer-page-heading"><div><p class="dashboard-kicker"><i class="ph ph-file-magnifying-glass" aria-hidden="true"></i><span>Settlement evidence</span></p><h1>Payment <?= e($payment['transaction_reference'] ?? ('#' . $payment['id'])) ?></h1><p>Submitted <?= e($paymentAge) ?>. Current status: <span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span></p></div><a class="button button--quiet" href="/admin/payments<?= $queueQuery === '' ? '' : '?' . e($queueQuery) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to payments</span></a></div>

<?php if (is_string($actionError ?? null) && $actionError !== ''): ?><div class="form-alert mt-6" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($actionError) ?></span></div><?php endif; ?>

<div class="admin-moderation-layout mt-8">
    <article class="dashboard-panel admin-evidence-panel" aria-labelledby="payment-evidence-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-receipt" aria-hidden="true"></i></span><div><h2 id="payment-evidence-heading">Payment evidence</h2><p>Check the participant, event, method, reference, and amount before acting.</p></div></div>
        <div class="admin-evidence-summary"><span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span><span><?= e($payment['currency'] ?? 'BDT') ?> <?= e($payment['amount'] ?? '0.00') ?></span><span><?= e($payment['payment_method_name'] ?? 'Manual payment') ?></span></div>
        <dl class="organizer-detail-list">
            <div><dt><i class="ph ph-user" aria-hidden="true"></i>Participant</dt><dd><?= e($payment['participant_name'] ?? 'Participant') ?><small><?= e($payment['participant_email'] ?? '') ?></small></dd></div>
            <div><dt><i class="ph ph-calendar-dots" aria-hidden="true"></i>Event</dt><dd><?= e($payment['event_title'] ?? 'Event') ?><small><?= e($payment['organizer_name'] ?? 'Organizer') ?></small></dd></div>
            <div><dt><i class="ph ph-identification-card" aria-hidden="true"></i>Registration</dt><dd><?= e($payment['registration_number'] ?? '') ?><small><span class="status-chip status-chip--<?= e((string) ($payment['registration_status'] ?? 'unknown')) ?>"><?= e(ucfirst((string) ($payment['registration_status'] ?? 'unknown'))) ?></span></small></dd></div>
            <div><dt><i class="ph ph-hash" aria-hidden="true"></i>Reference</dt><dd><?= e($payment['transaction_reference'] ?? 'Not supplied') ?></dd></div>
            <div><dt><i class="ph ph-bank" aria-hidden="true"></i>Channel</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) ($payment['payment_channel'] ?? 'Not supplied')))) ?></dd></div>
            <div><dt><i class="ph ph-clock" aria-hidden="true"></i>Submitted</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) ($payment['created_at'] ?? ''))) ?>"><?= e($payment['created_at'] ?? '') ?></time><small><?= e($paymentAge) ?></small></dd></div>
            <?php if (!empty($payment['reviewed_at'])): ?><div><dt><i class="ph ph-shield-check" aria-hidden="true"></i>Reviewed</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) $payment['reviewed_at'])) ?>"><?= e($payment['reviewed_at']) ?></time><small><?= e($payment['reviewer_name'] ?? 'Administrator') ?></small></dd></div><?php endif; ?>
            <?php if (!empty($payment['review_note'])): ?><div><dt><i class="ph ph-note" aria-hidden="true"></i>Review note</dt><dd><?= e($payment['review_note']) ?></dd></div><?php endif; ?>
        </dl>
        <div class="form-alert mt-6" role="status"><i class="ph ph-info" aria-hidden="true"></i><span><strong>Fulfillment impact:</strong> <?= e($impact) ?>.</span></div>
    </article>

    <aside class="dashboard-panel organizer-actions-panel" aria-labelledby="payment-actions-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-gavel" aria-hidden="true"></i></span><div><h2 id="payment-actions-heading">Settlement actions</h2><p>Each action opens a final evidence confirmation before saving.</p></div></div>
        <?php if ($status === 'pending'): ?>
            <?php if (is_array($confirmation ?? null)): ?>
                <?php $confirmingPaid = ($confirmation['target'] ?? null) === 'paid'; ?>
                <section class="organizer-action-stack" aria-labelledby="payment-confirmation-heading">
                    <div class="form-alert" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span>This settlement decision is irreversible. Confirm only after checking the evidence again.</span></div>
                    <div>
                        <h3 id="payment-confirmation-heading" class="font-extrabold">Confirm payment <?= $confirmingPaid ? 'verification' : 'rejection' ?></h3>
                        <dl class="organizer-detail-list mt-4">
                            <div><dt>Participant</dt><dd><?= e($payment['participant_name'] ?? 'Participant') ?></dd></div>
                            <div><dt>Event</dt><dd><?= e($payment['event_title'] ?? 'Event') ?></dd></div>
                            <div><dt>Amount</dt><dd><?= e($payment['currency'] ?? 'BDT') ?> <?= e($payment['amount'] ?? '0.00') ?></dd></div>
                            <div><dt>Reference</dt><dd><?= e($payment['transaction_reference'] ?? 'Not supplied') ?></dd></div>
                            <div><dt>Action</dt><dd><?= $confirmingPaid ? 'Verify payment, confirm registration, and issue ticket' : 'Reject payment, cancel registration, and release seat' ?></dd></div>
                            <?php if (is_string($confirmation['note'] ?? null) && $confirmation['note'] !== ''): ?><div><dt>Review note</dt><dd><?= e($confirmation['note']) ?></dd></div><?php endif; ?>
                        </dl>
                    </div>
                    <form action="/admin/payments/<?= e($payment['id']) ?>/<?= $confirmingPaid ? 'verify' : 'reject' ?>" method="post" data-form-kind="action">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="confirm_review" value="1">
                        <input type="hidden" name="review_intent" value="<?= e($confirmation['token'] ?? '') ?>">
                        <button class="button <?= $confirmingPaid ? 'button--primary' : 'button--danger' ?> w-full" type="submit" data-submit-label="Saving decision…"><i class="ph <?= $confirmingPaid ? 'ph-check-circle' : 'ph-x-circle' ?>" aria-hidden="true"></i><span data-submit-text>Confirm <?= $confirmingPaid ? 'verification' : 'rejection' ?></span></button>
                    </form>
                    <a class="button button--quiet w-full" href="<?= e($confirmation['cancelUrl'] ?? ('/admin/payments/' . $payment['id'])) ?>">Cancel and review again</a>
                </section>
            <?php else: ?><div class="organizer-action-stack">
                <form action="/admin/payments/<?= e($payment['id']) ?>/verify" method="post" data-form-kind="entry"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><?php foreach ($preserved as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach; ?><?php $fieldTargets = ['note' => 'verify-note', 'payment' => 'verify-note']; $fieldLabels = ['note' => 'Verification note', 'payment' => 'Payment review']; $formErrorSummaryId = 'payment-verification-error-summary'; require base_path('app/Views/components/form-errors.php'); ?><div class="field-group"><label for="verify-note">Verification note <span class="text-[var(--ink-muted)]">(optional)</span></label><textarea id="verify-note" name="note" rows="4" maxlength="500" data-form-label="Verification note" aria-describedby="verify-note-help<?= field_error($errors, 'note') === null ? '' : ' verify-note-error' ?>"<?= field_error($errors, 'note') === null ? '' : ' aria-invalid="true"' ?>><?= old_value($old, 'note') ?></textarea><p id="verify-note-help" class="field-help">Add only settlement context. Do not include account credentials.</p><?php if ($error = field_error($errors, 'note')): ?><p id="verify-note-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div><button class="button button--primary w-full" type="submit" data-submit-label="Reviewing verification…"><i class="ph ph-check-circle" aria-hidden="true"></i><span data-submit-text>Review verification</span></button></form>
                <form action="/admin/payments/<?= e($payment['id']) ?>/reject" method="post" data-form-kind="entry"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><?php foreach ($preserved as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach; ?><?php $fieldTargets = ['note' => 'reject-note', 'payment' => 'reject-note']; $fieldLabels = ['note' => 'Rejection note', 'payment' => 'Payment review']; $formErrorSummaryId = 'payment-rejection-error-summary'; require base_path('app/Views/components/form-errors.php'); ?><div class="field-group"><label for="reject-note">Rejection note <span class="text-[var(--ink-muted)]">(optional)</span></label><textarea id="reject-note" name="note" rows="4" maxlength="500" data-form-label="Rejection note" aria-describedby="reject-note-help<?= field_error($errors, 'note') === null ? '' : ' reject-note-error' ?>"<?= field_error($errors, 'note') === null ? '' : ' aria-invalid="true"' ?>><?= old_value($old, 'note') ?></textarea><p id="reject-note-help" class="field-help">Explain the mismatch without copying private account details.</p><?php if ($error = field_error($errors, 'note')): ?><p id="reject-note-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div><button class="button button--danger w-full" type="submit" data-submit-label="Reviewing rejection…"><i class="ph ph-x-circle" aria-hidden="true"></i><span data-submit-text>Review rejection</span></button></form>
            </div><?php endif; ?>
        <?php else: ?><p class="organizer-action-note"><i class="ph ph-info" aria-hidden="true"></i><span>This payment has a terminal settlement state. No new action is available.</span></p><?php endif; ?>
    </aside>
</div>
