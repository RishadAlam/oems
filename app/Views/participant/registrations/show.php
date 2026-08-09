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
<header class="dashboard-page-header">
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
            <div><dt>Venue</dt><dd><?= e($registration['venue_name'] ?? 'Venue to be announced') ?></dd></div>
        </dl>
    </section>
    <section class="money-summary dashboard-panel" aria-labelledby="payment-status-heading">
        <h2 id="payment-status-heading" class="text-xl font-bold">Payment and ticket</h2>
        <dl class="status-list mt-5">
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

<?php if ($registration['can_cancel']): ?>
    <section class="dashboard-panel mt-6" aria-labelledby="cancel-registration-heading">
        <h2 id="cancel-registration-heading" class="text-lg font-bold">Cancel registration</h2><p class="mt-2 text-sm text-[var(--ink-muted)]">Cancellation releases your place and invalidates related payment or ticket state.</p>
        <form class="form-stack mt-5" action="/participant/registrations/<?= e($registration['id']) ?>/cancel" method="post" novalidate>
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="field-group"><label for="reason">Cancellation reason</label><textarea id="reason" name="reason" rows="3" maxlength="500" required aria-describedby="reason-help<?= $reasonError !== null ? ' reason-error' : '' ?>"<?= $reasonError !== null ? ' aria-invalid="true"' : '' ?>></textarea><p id="reason-help" class="field-help">Explain the cancellation without including private payment information.</p><?php if ($reasonError !== null): ?><p id="reason-error" class="field-error" role="alert"><?= e($reasonError) ?></p><?php endif; ?></div>
            <button class="button button--danger w-full sm:w-auto" type="submit"><i class="ph ph-x-circle" aria-hidden="true"></i><span>Cancel registration</span></button>
        </form>
    </section>
<?php elseif ($cancellationReason !== null): ?>
    <section class="dashboard-panel mt-6" aria-labelledby="cancellation-unavailable-heading">
        <h2 id="cancellation-unavailable-heading" class="text-lg font-bold">Cancellation unavailable</h2>
        <p class="mt-2 text-sm text-[var(--ink-muted)]"><?= e($cancellationReason) ?></p>
        <?php if (!empty($registration['cancellation_reason'])): ?><p class="mt-2 text-sm text-[var(--ink-muted)]">Recorded reason: <?= e($registration['cancellation_reason']) ?></p><?php endif; ?>
    </section>
<?php endif; ?>
