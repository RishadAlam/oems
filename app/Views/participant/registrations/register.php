<?php
$channel = old_value($old, 'channel');
$described = static function (string $field, string $help, array $errors): string {
    $ids = [$help];
    if (field_error($errors, $field) !== null) {
        $ids[] = $field . '-error';
    }

    return ' aria-describedby="' . e(implode(' ', $ids)) . '"' . (count($ids) > 1 ? ' aria-invalid="true"' : '');
};
?>
<header class="dashboard-page-header">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>One place</span></p>
        <h1>Register for <?= e($event['title']) ?></h1>
        <p>Review the event and total before confirming.</p>
    </div>
    <a class="button button--quiet" href="/events/<?= e($event['slug']) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Event details</span></a>
</header>

<div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)] lg:items-start">
    <section class="dashboard-panel order-2 lg:order-1" aria-labelledby="checkout-action-heading">
        <div class="profile-form-section__heading">
            <span><i class="ph ph-check-circle" aria-hidden="true"></i></span>
            <div><h2 id="checkout-action-heading"><?= $isFree ? 'Confirm your place' : 'Submit payment reference' ?></h2><p><?= $isFree ? 'No payment is required for this event.' : 'OEMS will review the reference before confirming your place.' ?></p></div>
        </div>

        <?php if ($error = field_error($errors, 'event') ?? field_error($errors, 'registration') ?? field_error($errors, 'account')): ?>
            <div class="form-alert mt-5" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form class="form-stack mt-6" action="/participant/events/<?= e($event['slug']) ?>/register" method="post" novalidate>
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <?php if (!$isFree): ?>
                <div class="field-group">
                    <label for="channel">Payment channel</label>
                    <select id="channel" name="channel" required<?= $described('channel', 'channel-help', $errors) ?>>
                        <option value="">Select a channel</option>
                        <?php foreach (['bank' => 'Bank', 'mobile' => 'Mobile banking', 'cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_banking' => 'Mobile banking transfer', 'cash_deposit' => 'Cash deposit'] as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $channel === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="channel-help" class="field-help">Choose the channel used for your payment.</p>
                    <?php if ($error = field_error($errors, 'channel')): ?><p id="channel-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="field-group">
                    <label for="transaction_reference">Transaction reference</label>
                    <input id="transaction_reference" name="transaction_reference" type="text" minlength="6" maxlength="190" autocomplete="off" required<?= $described('transaction_reference', 'transaction-reference-help', $errors) ?>>
                    <p id="transaction-reference-help" class="field-help">Enter the reference issued by your payment provider. Do not enter card or account secrets.</p>
                    <?php if ($error = field_error($errors, 'transaction_reference')): ?><p id="transaction_reference-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <button class="button button--primary w-full sm:w-auto" type="submit"><i class="ph ph-check" aria-hidden="true"></i><span><?= $isFree ? 'Confirm free registration' : 'Submit for review' ?></span></button>
        </form>
    </section>

    <aside class="dashboard-panel order-1 lg:order-2" aria-labelledby="order-summary-heading">
        <p class="dashboard-kicker"><i class="ph ph-receipt" aria-hidden="true"></i><span>Registration summary</span></p>
        <h2 id="order-summary-heading" class="mt-3 text-xl font-bold">One seat</h2>
        <dl class="status-list mt-5">
            <div><dt>Event</dt><dd><?= e($event['title']) ?></dd></div>
            <div><dt>Date</dt><dd><?= e($event['start_display']) ?></dd></div>
            <div><dt>Venue</dt><dd><?= e($event['venue_name'] ?? 'Venue to be announced') ?></dd></div>
            <div><dt>Total</dt><dd><strong><?= e($event['total_display']) ?></strong> <?= e($event['currency']) ?></dd></div>
        </dl>
    </aside>
</div>
