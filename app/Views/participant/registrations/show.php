<?php
$registrationStatus = (string) $registration['registration_status'];
$paymentStatus = (string) $registration['payment_status'];
$cancellationState = is_array($registration['cancellation_state'] ?? null)
    ? $registration['cancellation_state']
    : ['allowed' => false, 'reason' => null];
$cancellationReason = is_string($cancellationState['reason'] ?? null)
    ? $cancellationState['reason']
    : null;
$reasonError = field_error($errors, 'reason') ?? field_error($errors, 'registration');
$eventCancelled = (string) ($registration['event_status'] ?? '') === 'cancelled'
    || (string) ($registration['cancellation_reason'] ?? '') === 'Event cancelled';
$statusCopy = match (true) {
    $eventCancelled => 'The event was cancelled. Your registration and ticket are no longer active.',
    $paymentStatus === 'failed' => 'The payment reference was rejected and your place was released.',
    !empty($registration['promoted_claim_active']) => 'Waitlist seat ready. Submit payment before the claim deadline to keep your place.',
    $registrationStatus === 'confirmed' => 'Your place is confirmed.',
    $registrationStatus === 'cancelled' => 'This registration is cancelled and no longer holds a place.',
    $registrationStatus === 'refunded' => 'This registration was refunded and no longer holds a place.',
    default => 'Your payment reference is awaiting review. Your ticket is issued after approval.',
};
$registrationTerminal = in_array($registrationStatus, ['cancelled', 'refunded'], true);
$paymentComplete = in_array($paymentStatus, ['paid', 'not_required'], true);
$ticketIssued = is_array($registration['ticket']);
$ticketStepStatus = (string) ($registration['ticket']['ticket_status'] ?? 'issued');
$ticketStepLabel = match ($ticketStepStatus) {
    'used' => 'Checked in',
    'cancelled' => 'Cancelled',
    'valid' => 'Ready for check-in',
    default => 'Issued',
};
$paymentStepLabel = match (true) {
    $eventCancelled => 'Event cancelled',
    $paymentStatus === 'failed' => 'Payment rejected',
    $paymentStatus === 'refunded' => 'Refunded',
    $paymentStatus === 'paid' => 'Paid',
    $paymentStatus === 'not_required' => 'Not required',
    default => 'Review pending',
};
$paymentStepState = match (true) {
    $eventCancelled => 'terminal',
    $paymentStatus === 'failed' => 'failed',
    $paymentStatus === 'refunded' || $registrationTerminal => 'terminal',
    $paymentStatus === 'pending' => 'current',
    $paymentComplete => 'complete',
    default => 'terminal',
};
$ticketStepState = match (true) {
    $eventCancelled => 'terminal',
    !$ticketIssued && $registrationTerminal => 'unavailable',
    !$ticketIssued && $paymentComplete => 'current',
    !$ticketIssued => 'upcoming',
    $ticketStepStatus === 'cancelled' => 'failed',
    $ticketStepStatus === 'used' => 'complete',
    default => 'current',
};
$ticketStepLabel = !$ticketIssued
    ? ($eventCancelled ? 'Event cancelled' : ($registrationTerminal ? 'Not issued' : ($paymentComplete ? 'Issuance pending' : 'Issued after approval')))
    : ($eventCancelled ? 'Event cancelled' : $ticketStepLabel);
$paymentDetailLabel = $eventCancelled
    ? 'Event cancelled'
    : ($paymentStatus === 'pending' ? 'Payment review pending' : ucfirst(str_replace('_', ' ', $paymentStatus)));
?>
<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-identification-card" aria-hidden="true"></i><span><?= e($registration['registration_number']) ?></span></p><h1><?= e($registration['event_title']) ?></h1><p><?= e($statusCopy) ?></p></div>
    <a class="button button--quiet" href="/participant/registrations"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>All registrations</span></a>
</header>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="dashboard-panel" aria-labelledby="registration-status-heading">
        <h2 id="registration-status-heading" class="text-xl font-bold">Registration and event</h2>
        <dl class="status-list mt-5">
            <div><dt>Status</dt><dd><span class="status-chip status-chip--<?= e($registrationStatus) ?>"><?= e(ucfirst($registrationStatus)) ?></span></dd></div>
            <div><dt>Registered</dt><dd><?= e($registration['registered_display']) ?></dd></div>
            <div><dt>Starts</dt><dd><?= e($registration['event_start_display']) ?></dd></div>
            <div><dt>Venue</dt><dd><?= e($registration['venue_display'] ?? 'Venue to be announced') ?></dd></div>
        </dl>
        <?php if ($registrationStatus === 'confirmed' && in_array((string) ($registration['event_status'] ?? ''), ['published', 'completed'], true)): ?>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a class="button button--primary" href="/participant/registrations/<?= e($registration['id']) ?>/calendar.ics"><i class="ph ph-calendar-plus" aria-hidden="true"></i><span>Download calendar</span></a>
                <a class="button button--quiet" href="/participant/registrations/<?= e($registration['id']) ?>/google-calendar" target="_blank" rel="noopener noreferrer"><i class="ph ph-arrow-square-out" aria-hidden="true"></i><span>Google Calendar</span></a>
            </div>
        <?php endif; ?>
        <?php if ($registrationStatus === 'confirmed' && (string) ($registration['event_status'] ?? '') === 'completed' && (string) ($registration['ticket']['ticket_status'] ?? '') === 'used'): ?>
            <form class="mt-4" action="/participant/registrations/<?= e($registration['id']) ?>/certificate" method="post" data-form-kind="action">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <button class="button button--primary" type="submit" data-submit-label="Preparing certificate…"><i class="ph ph-seal-check" aria-hidden="true"></i><span data-submit-text>Get attendance certificate</span></button>
            </form>
        <?php endif; ?>
    </section>
    <section class="money-summary dashboard-panel" aria-labelledby="payment-status-heading">
        <h2 id="payment-status-heading" class="text-xl font-bold">Payment and ticket</h2>
        <dl class="status-list mt-5">
            <?php if (!empty($registration['coupon_applied'])): ?><div><dt>Original price</dt><dd><?= e($registration['base_amount_display']) ?> <?= e($registration['currency']) ?></dd></div><div><dt>Discount</dt><dd><?= e($registration['discount_amount_display']) ?> <?= e($registration['currency']) ?> <span class="status-badge status-badge--success">Coupon applied</span></dd></div><?php endif; ?>
            <div><dt>Total</dt><dd><?= e($registration['amount_display']) ?> <?= e($registration['currency']) ?></dd></div>
            <div><dt>Payment</dt><dd><?= e($paymentDetailLabel) ?></dd></div>
            <div><dt>Ticket</dt><dd><?php if (is_array($registration['ticket'])): ?><a class="text-link" href="/participant/tickets/<?= e($registration['ticket']['id']) ?>">View ticket <?= e($registration['ticket']['ticket_number']) ?></a><?php else: ?>Not issued<?php endif; ?></dd></div>
        </dl>
    </section>
</div>

<section class="dashboard-panel mt-6" aria-labelledby="status-timeline-heading">
    <h2 id="status-timeline-heading" class="text-lg font-bold">Status timeline</h2>
    <ol class="transaction-steps">
        <li class="transaction-step transaction-step--complete"><i class="ph ph-check-circle" aria-hidden="true"></i><strong>Registered</strong><span><?= e($registration['registered_display']) ?></span></li>
        <li class="transaction-step transaction-step--<?= e($paymentStepState) ?>"<?= $paymentStepState === 'current' ? ' aria-current="step"' : '' ?>><i class="ph ph-credit-card" aria-hidden="true"></i><strong>Payment</strong><span><?= e($paymentStepLabel) ?></span></li>
        <li class="transaction-step transaction-step--<?= e($ticketStepState) ?>"<?= $ticketStepState === 'current' ? ' aria-current="step"' : '' ?>><i class="ph ph-ticket" aria-hidden="true"></i><strong>Ticket</strong><span><?= e($ticketStepLabel) ?></span></li>
    </ol>
</section>

<?php if (!empty($registration['promoted_claim_active'])):
    $channelError = field_error($errors, 'channel');
    $referenceError = field_error($errors, 'transaction_reference') ?? field_error($errors, 'payment') ?? field_error($errors, 'registration');
?>
    <section class="dashboard-panel mt-6" aria-labelledby="waitlist-payment-heading">
        <p class="dashboard-kicker"><i class="ph ph-hourglass-high" aria-hidden="true"></i><span>Promoted from waitlist</span></p>
        <h2 id="waitlist-payment-heading" class="mt-2 text-xl font-bold">Waitlist seat ready</h2>
        <p class="mt-2 text-sm text-[var(--ink-muted)]">Submit payment by <?= e($registration['waitlist_claim_expires_display'] ?? 'the claim deadline') ?>. The seat returns to the queue if the window expires.</p>
        <?php if (is_array($manualPayment) && $manualPayment !== []): ?>
            <div class="callout mt-5" aria-labelledby="waitlist-payment-guidance-heading">
                <h3 id="waitlist-payment-guidance-heading" class="font-bold"><?= e($manualPayment['name'] ?? 'Manual payment') ?></h3>
                <?php if (!empty($manualPayment['account_title'])): ?><p class="mt-2"><strong>Account:</strong> <?= e($manualPayment['account_title']) ?></p><?php endif; ?>
                <?php if (!empty($manualPayment['account_identifier'])): ?><p><strong>Identifier:</strong> <?= e($manualPayment['account_identifier']) ?></p><?php endif; ?>
                <?php if (!empty($manualPayment['instructions'])): ?><p class="mt-2 text-sm"><?= e($manualPayment['instructions']) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
        <form class="form-stack mt-5" action="/participant/registrations/<?= e($registration['id']) ?>/payment" method="post" data-form-kind="entry">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <?php
            $fieldTargets = ['channel' => 'channel', 'transaction_reference' => 'transaction_reference', 'payment' => 'transaction_reference', 'registration' => 'waitlist-payment-heading'];
            $fieldLabels = ['channel' => 'Payment channel', 'transaction_reference' => 'Transaction reference', 'payment' => 'Payment', 'registration' => 'Registration'];
            $formErrorSummaryId = 'promoted-payment-error-summary';
            require base_path('app/Views/components/form-errors.php');
            ?>
            <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>Payment channel and transaction reference are required.</span></p>
            <div class="field-group">
                <label for="channel">Payment channel</label>
                <select id="channel" name="channel" required aria-describedby="channel-help<?= $channelError !== null ? ' channel-error' : '' ?>"<?= $channelError !== null ? ' aria-invalid="true"' : '' ?> data-form-label="Payment channel">
                    <option value="">Choose a channel</option>
                    <?php foreach (['bank_transfer' => 'Bank transfer', 'mobile_banking' => 'Mobile banking', 'cash_deposit' => 'Cash deposit'] as $value => $label): ?><option value="<?= e($value) ?>"<?= ($old['channel'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <p id="channel-help" class="field-help">Choose how you sent the payment.</p>
                <?php if ($channelError !== null): ?><p id="channel-error" class="field-error" role="alert"><?= e($channelError) ?></p><?php endif; ?>
            </div>
            <div class="field-group">
                <label for="transaction_reference">Transaction reference</label>
                <input id="transaction_reference" name="transaction_reference" type="text" minlength="6" maxlength="190" required autocomplete="off" aria-describedby="transaction-reference-help<?= $referenceError !== null ? ' transaction-reference-error' : '' ?>"<?= $referenceError !== null ? ' aria-invalid="true"' : '' ?> data-form-label="Transaction reference">
                <p id="transaction-reference-help" class="field-help">Enter the exact reference from your payment receipt. It is never repopulated after an error.</p>
                <?php if ($referenceError !== null): ?><p id="transaction-reference-error" class="field-error" role="alert"><?= e($referenceError) ?></p><?php endif; ?>
            </div>
            <button class="button button--primary w-full sm:w-auto" type="submit" data-submit-label="Submitting payment…"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span data-submit-text>Submit payment</span></button>
        </form>
    </section>
<?php endif; ?>

<?php if ($registration['can_cancel']): ?>
    <section class="dashboard-panel mt-6" aria-labelledby="cancel-registration-heading">
        <h2 id="cancel-registration-heading" class="text-lg font-bold">Cancel registration</h2><p class="mt-2 text-sm text-[var(--ink-muted)]">Cancellation releases your place and invalidates related payment or ticket state.</p>
        <form class="form-stack mt-5" action="/participant/registrations/<?= e($registration['id']) ?>/cancel" method="post" data-form-kind="entry">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <?php
            $fieldTargets = ['reason' => 'reason', 'registration' => 'reason'];
            $fieldLabels = ['reason' => 'Cancellation reason', 'registration' => 'Cancellation'];
            $formErrorSummaryId = 'cancellation-error-summary';
            require base_path('app/Views/components/form-errors.php');
            ?>
            <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>A cancellation reason is required.</span></p>
            <div class="field-group"><label for="reason">Cancellation reason</label><textarea id="reason" name="reason" rows="3" maxlength="500" required aria-describedby="reason-help<?= $reasonError !== null ? ' reason-error' : '' ?>"<?= $reasonError !== null ? ' aria-invalid="true"' : '' ?> data-form-label="Cancellation reason"></textarea><p id="reason-help" class="field-help">Explain the cancellation without including private payment information.</p><?php if ($reasonError !== null): ?><p id="reason-error" class="field-error" role="alert"><?= e($reasonError) ?></p><?php endif; ?></div>
            <button class="button button--danger w-full sm:w-auto" type="submit" data-submit-label="Cancelling registration…"><i class="ph ph-x-circle" aria-hidden="true"></i><span data-submit-text>Cancel registration</span></button>
        </form>
    </section>
<?php elseif ($cancellationReason !== null): ?>
    <section class="dashboard-panel mt-6" aria-labelledby="cancellation-unavailable-heading">
        <h2 id="cancellation-unavailable-heading" class="text-lg font-bold">Cancellation unavailable</h2>
        <p class="mt-2 text-sm text-[var(--ink-muted)]"><?= e($cancellationReason) ?></p>
        <?php if (!empty($registration['cancellation_reason'])): ?><p class="mt-2 text-sm text-[var(--ink-muted)]">Recorded reason: <?= e($registration['cancellation_reason']) ?></p><?php endif; ?>
    </section>
<?php endif; ?>
