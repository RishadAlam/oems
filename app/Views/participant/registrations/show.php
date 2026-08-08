<?php
$registrationStatus = (string) $registration['registration_status'];
$paymentStatus = (string) $registration['payment_status'];
$statusCopy = match (true) {
    $paymentStatus === 'failed' => 'The payment reference was rejected and your place was released.',
    $registrationStatus === 'confirmed' => 'Your place is confirmed.',
    $registrationStatus === 'cancelled' => 'This registration is cancelled and no longer holds a place.',
    $registrationStatus === 'refunded' => 'This registration was refunded and no longer holds a place.',
    default => 'Your payment reference is awaiting review. Your ticket is issued after approval.',
};
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
    <section class="dashboard-panel" aria-labelledby="payment-status-heading">
        <h2 id="payment-status-heading" class="text-xl font-bold">Payment and ticket</h2>
        <dl class="status-list mt-5">
            <div><dt>Total</dt><dd><?= e($registration['amount_display']) ?> <?= e($registration['currency']) ?></dd></div>
            <div><dt>Payment</dt><dd><?= e($paymentStatus === 'pending' ? 'Payment review pending' : ucfirst(str_replace('_', ' ', $paymentStatus))) ?></dd></div>
            <div><dt>Ticket</dt><dd><?php if (is_array($registration['ticket'])): ?><a class="text-link" href="/participant/tickets/<?= e($registration['ticket']['id']) ?>">View ticket <?= e($registration['ticket']['ticket_number']) ?></a><?php else: ?>Not issued<?php endif; ?></dd></div>
        </dl>
    </section>
</div>

<section class="dashboard-panel mt-6" aria-labelledby="status-timeline-heading">
    <h2 id="status-timeline-heading" class="text-lg font-bold">Status timeline</h2>
    <ol class="mt-5 grid gap-4 sm:grid-cols-3">
        <li class="rounded-[18px] border border-[var(--line)] p-4"><i class="ph ph-check-circle text-[var(--accent)]" aria-hidden="true"></i><strong class="mt-2 block">Registered</strong><span class="mt-1 block text-sm text-[var(--ink-muted)]"><?= e($registration['registered_display']) ?></span></li>
        <li class="rounded-[18px] border border-[var(--line)] p-4"><i class="ph ph-credit-card text-[var(--accent)]" aria-hidden="true"></i><strong class="mt-2 block">Payment</strong><span class="mt-1 block text-sm text-[var(--ink-muted)]"><?= e($paymentStatus === 'pending' ? 'Review pending' : ucfirst(str_replace('_', ' ', $paymentStatus))) ?></span></li>
        <li class="rounded-[18px] border border-[var(--line)] p-4"><i class="ph ph-ticket text-[var(--accent)]" aria-hidden="true"></i><strong class="mt-2 block">Ticket</strong><span class="mt-1 block text-sm text-[var(--ink-muted)]"><?= is_array($registration['ticket']) ? e(ucfirst((string) ($registration['ticket']['ticket_status'] ?? 'issued'))) : 'Not issued' ?></span></li>
    </ol>
</section>

<?php if ($registrationStatus === 'cancelled' && !empty($registration['cancellation_reason'])): ?>
    <section class="dashboard-panel mt-6"><h2 class="text-lg font-bold">Cancellation</h2><p class="mt-2 text-sm text-[var(--ink-muted)]"><?= e($registration['cancellation_reason']) ?></p></section>
<?php elseif ($registration['can_cancel']): ?>
    <section class="dashboard-panel mt-6" aria-labelledby="cancel-registration-heading">
        <h2 id="cancel-registration-heading" class="text-lg font-bold">Cancel registration</h2><p class="mt-2 text-sm text-[var(--ink-muted)]">Cancellation releases your place and invalidates related payment or ticket state.</p>
        <form class="form-stack mt-5" action="/participant/registrations/<?= e($registration['id']) ?>/cancel" method="post" novalidate>
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="field-group"><label for="reason">Cancellation reason</label><textarea id="reason" name="reason" rows="3" maxlength="500" required<?= field_error($errors, 'reason') ? ' aria-invalid="true" aria-describedby="reason-error"' : '' ?>></textarea><?php if ($error = field_error($errors, 'reason') ?? field_error($errors, 'registration')): ?><p id="reason-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <button class="button button--danger w-full sm:w-auto" type="submit"><i class="ph ph-x-circle" aria-hidden="true"></i><span>Cancel registration</span></button>
        </form>
    </section>
<?php endif; ?>
