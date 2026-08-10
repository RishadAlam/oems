<?php
$isEdit = is_array($coupon);
$action = $isEdit ? '/organizer/coupons/' . (int) $coupon['id'] : '/organizer/coupons';
$value = static fn (string $key, string $default = ''): string => old_value($old, $key, (string) ($coupon[$key] ?? $default));
$dateValue = static function (string $key) use ($old, $coupon): string {
    $raw = old_value($old, $key, (string) ($coupon[$key] ?? ''));
    return $raw === '' ? '' : str_replace(' ', 'T', substr($raw, 0, 16));
};
$described = static function (string $key, string $help) use ($errors): string {
    $ids = [$help]; if (field_error($errors, $key) !== null) $ids[] = str_replace('_', '-', $key) . '-error';
    return ' aria-describedby="' . e(implode(' ', $ids)) . '"' . (count($ids) > 1 ? ' aria-invalid="true"' : '');
};
?>
<header class="dashboard-page-header"><div><p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>Pricing tools</span></p><h1><?= $isEdit ? 'Edit coupon' : 'Create coupon' ?></h1><p>Coupon pricing is rechecked inside the registration transaction.</p></div><a class="button button--quiet" href="/organizer/coupons">Back to coupons</a></header>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php if ($error = field_error($errors, 'coupon')): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <section class="organizer-form__section" aria-labelledby="coupon-details-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-percent" aria-hidden="true"></i></span><div><h2 id="coupon-details-heading">Coupon details</h2><p>Use a memorable code without spaces. Codes are stored in uppercase.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group"><label for="code">Coupon code</label><input id="code" name="code" maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,79}" value="<?= $value('code') ?>" required<?= $described('code', 'code-help') ?>><p id="code-help" class="field-help">3 to 80 letters, numbers, underscores, or hyphens.</p><?php if ($error = field_error($errors, 'code')): ?><p id="code-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="event_id">Event scope</label><select id="event_id" name="event_id"<?= $described('event_id', 'event-id-help') ?>><option value="">All my events</option><?php foreach ($events as $event): ?><option value="<?= e($event['id']) ?>"<?= $value('event_id') === (string) $event['id'] ? ' selected' : '' ?>><?= e($event['title']) ?></option><?php endforeach; ?></select><p id="event-id-help" class="field-help">A global coupon still works only on events you own.</p><?php if ($error = field_error($errors, 'event_id')): ?><p id="event-id-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="discount_type">Discount type</label><select id="discount_type" name="discount_type" required<?= $described('discount_type', 'discount-type-help') ?>><option value="fixed"<?= $value('discount_type', 'fixed') === 'fixed' ? ' selected' : '' ?>>Fixed amount</option><option value="percentage"<?= $value('discount_type') === 'percentage' ? ' selected' : '' ?>>Percentage</option></select><p id="discount-type-help" class="field-help">Fixed discounts never reduce the total below zero.</p><?php if ($error = field_error($errors, 'discount_type')): ?><p id="discount-type-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="discount_value">Discount value</label><input id="discount_value" name="discount_value" type="number" min="0.01" max="9999999999.99" step="0.01" value="<?= $value('discount_value') ?>" required<?= $described('discount_value', 'discount-value-help') ?>><p id="discount-value-help" class="field-help">Percentage discounts must be 100.00 or less.</p><?php if ($error = field_error($errors, 'discount_value')): ?><p id="discount-value-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="usage_limit">Usage limit <span class="field-label-note">Optional</span></label><input id="usage_limit" name="usage_limit" type="number" min="1" max="1000000" step="1" value="<?= $value('usage_limit') ?>"<?= $described('usage_limit', 'usage-limit-help') ?>><p id="usage-limit-help" class="field-help">Leave blank for no total redemption limit. Each participant may use a coupon once.</p><?php if ($error = field_error($errors, 'usage_limit')): ?><p id="usage-limit-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="starts_at">Starts at <span class="field-label-note">Optional</span></label><input id="starts_at" name="starts_at" type="datetime-local" value="<?= e($dateValue('starts_at')) ?>"<?= $described('starts_at', 'starts-at-help') ?>><p id="starts-at-help" class="field-help">Uses the configured OEMS timezone.</p><?php if ($error = field_error($errors, 'starts_at')): ?><p id="starts-at-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="expires_at">Expires at <span class="field-label-note">Optional</span></label><input id="expires_at" name="expires_at" type="datetime-local" value="<?= e($dateValue('expires_at')) ?>"<?= $described('expires_at', 'expires-at-help') ?>><p id="expires-at-help" class="field-help">The coupon is valid through this exact time.</p><?php if ($error = field_error($errors, 'expires_at')): ?><p id="expires-at-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
    </section>
    <div class="organizer-form__actions"><p><i class="ph ph-lock-key" aria-hidden="true"></i><span>Activation remains a separate explicit action.</span></p><button class="button button--primary" type="submit"><?= $isEdit ? 'Save coupon' : 'Create coupon' ?></button></div>
</form>
