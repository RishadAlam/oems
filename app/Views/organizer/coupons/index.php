<header class="dashboard-page-header">
    <div><p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>Pricing tools</span></p><h1>Coupons</h1><p>Create controlled discounts for one event or your full event catalog.</p></div>
    <a class="button button--primary" href="/organizer/coupons/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create coupon</span></a>
</header>

<section class="dashboard-panel mt-8" aria-labelledby="coupon-list-heading">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="coupon-list-heading" class="text-xl font-bold">Coupon library</h2><p class="mt-1 text-sm text-[var(--ink-muted)]">Usage counts are updated only after an atomic registration redemption.</p></div><span class="status-badge status-badge--neutral"><?= e(count($coupons)) ?> total</span></div>
    <?php if ($coupons === []): ?>
        <div class="empty-state mt-6"><i class="ph ph-ticket" aria-hidden="true"></i><h3>No coupons yet</h3><p>Create a percentage or fixed discount when you are ready to run a promotion.</p><a class="button button--primary" href="/organizer/coupons/create">Create first coupon</a></div>
    <?php else: ?>
        <div class="table-shell mt-6"><table class="operations-table"><thead><tr><th>Code</th><th>Scope</th><th>Discount</th><th>Usage</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($coupons as $coupon): ?>
            <tr>
                <td data-label="Code"><strong><?= e($coupon['code']) ?></strong></td>
                <td data-label="Scope"><?= e($coupon['event_title'] ?? 'All owned events') ?></td>
                <td data-label="Discount"><?= e($coupon['discount_type'] === 'percentage' ? rtrim(rtrim((string) $coupon['discount_value'], '0'), '.') . '%' : (string) $coupon['discount_value'] . ($coupon['event_currency'] === null ? ' event currency' : ' ' . (string) $coupon['event_currency'])) ?></td>
                <td data-label="Usage"><?= e((int) $coupon['used_count']) ?><?= $coupon['usage_limit'] === null ? '' : ' of ' . e((int) $coupon['usage_limit']) ?></td>
                <td data-label="Status"><span class="status-badge <?= !empty($coupon['is_active']) ? 'status-badge--success' : 'status-badge--neutral' ?>"><?= !empty($coupon['is_active']) ? 'Active' : 'Inactive' ?></span></td>
                <td data-label="Actions"><div class="table-actions"><a class="button button--compact button--quiet" href="/organizer/coupons/<?= e($coupon['id']) ?>/edit">Edit</a><form action="/organizer/coupons/<?= e($coupon['id']) ?>/status" method="post" data-form-kind="action"<?= !empty($coupon['is_active']) ? ' data-confirm="Deactivate this coupon? New registrations will no longer be able to use it."' : '' ?>><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="is_active" value="<?= !empty($coupon['is_active']) ? '0' : '1' ?>"><button class="button button--compact <?= !empty($coupon['is_active']) ? 'button--danger' : 'button--quiet' ?>" type="submit" data-submit-label="<?= !empty($coupon['is_active']) ? 'Deactivating coupon…' : 'Activating coupon…' ?>"><span data-submit-text><?= !empty($coupon['is_active']) ? 'Deactivate' : 'Activate' ?></span></button></form></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
