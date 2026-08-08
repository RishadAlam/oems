<?php
$statusLabels = ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded', 'all' => 'All statuses'];
$query = static fn (array $changes = []): string => http_build_query(array_filter(
    array_merge($filters, ['per_page' => $perPage], $changes),
    static fn (mixed $value): bool => $value !== '' && $value !== null,
), '', '&', PHP_QUERY_RFC3986);
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-receipt" aria-hidden="true"></i><span>Administrator settlement</span></p><h1>Payment review</h1><p>Pending payments appear oldest first. Completed history appears newest first.</p></div>
</div>

<section class="dashboard-panel mt-8" aria-labelledby="payment-filters-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-funnel" aria-hidden="true"></i></span><div><h2 id="payment-filters-heading">Find payments</h2><p>Search participant, event, or transaction reference.</p></div></div>
    <form class="mt-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(12rem,0.35fr)_auto]" method="get" action="/admin/payments">
        <label class="form-field" for="payment-search"><span>Search</span><input id="payment-search" name="search" type="search" maxlength="120" value="<?= e($filters['search'] ?? '') ?>" placeholder="Participant, event, or reference"></label>
        <label class="form-field" for="payment-status"><span>Status</span><select id="payment-status" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"<?= ($filters['status'] ?? 'pending') === $status ? ' selected' : '' ?>><?= e($statusLabels[$status] ?? ucfirst($status)) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
        <div class="flex items-end gap-2"><button class="button button--primary" type="submit">Apply</button><a class="button button--quiet" href="/admin/payments">Reset</a></div>
    </form>
</section>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="payment-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-list-magnifying-glass" aria-hidden="true"></i></span><div><h2 id="payment-list-heading">Payment records</h2><p><?= e($total) ?> matching payment<?= $total === 1 ? '' : 's' ?>.</p></div></div>
    <?php if ($payments === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span><strong>No payments found</strong><p><?= ($filters['status'] ?? 'pending') === 'pending' ? 'There are no payments awaiting review.' : 'Adjust the filters to review another part of payment history.' ?></p></div>
    <?php else: ?>
        <div class="organizer-table-wrap mt-6"><table class="organizer-table"><caption class="sr-only">Administrator payment review records</caption><thead><tr><th>Participant</th><th>Event</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
        <?php foreach ($payments as $payment): ?><?php $paymentStatus = (string) ($payment['payment_status'] ?? 'pending'); ?><tr><td data-label="Participant"><strong><?= e($payment['participant_name'] ?? 'Participant') ?></strong><small><?= e($payment['participant_email'] ?? '') ?></small></td><td data-label="Event"><strong><?= e($payment['event_title'] ?? 'Event') ?></strong><small><?= e($payment['organizer_name'] ?? 'Organizer') ?></small></td><td data-label="Amount"><strong><?= e($payment['currency'] ?? 'BDT') ?> <?= e($payment['amount'] ?? '0.00') ?></strong></td><td data-label="Method"><strong><?= e($payment['payment_method_name'] ?? 'Manual payment') ?></strong><small><?= e(ucwords(str_replace('_', ' ', (string) ($payment['payment_channel'] ?? 'Not supplied')))) ?></small></td><td data-label="Reference"><strong><?= e($payment['transaction_reference'] ?? 'Not supplied') ?></strong><small><time datetime="<?= e(str_replace(' ', 'T', (string) ($payment['created_at'] ?? ''))) ?>"><?= e($payment['created_at'] ?? '') ?></time></small></td><td data-label="Status"><span class="status-chip status-chip--<?= e($paymentStatus) ?>"><?= e($statusLabels[$paymentStatus] ?? ucfirst($paymentStatus)) ?></span></td><td class="organizer-table__action"><a class="text-link" href="/admin/payments/<?= e($payment['id']) ?>?<?= e($query(['page' => $page])) ?>">Review <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <nav class="mt-6 flex items-center justify-between gap-4" aria-label="Payment pages"><span>Page <?= e($page) ?> of <?= e($lastPage) ?></span><div class="flex gap-2"><?php if ($page > 1): ?><a class="button button--quiet button--compact" href="?<?= e($query(['page' => $page - 1])) ?>">Previous</a><?php endif; ?><?php if ($page < $lastPage): ?><a class="button button--quiet button--compact" href="?<?= e($query(['page' => $page + 1])) ?>">Next</a><?php endif; ?></div></nav>
    <?php endif; ?>
</section>
