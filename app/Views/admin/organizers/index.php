<?php
$items = is_array($result['items'] ?? null) ? $result['items'] : [];
$pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
$total = (int) ($pagination['total'] ?? 0);
$search = is_scalar($filters['search'] ?? null) ? (string) $filters['search'] : '';
$approval = is_scalar($filters['approval_status'] ?? null) ? (string) $filters['approval_status'] : '';
$page = max(1, (int) ($pagination['page'] ?? 1));
$lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$query = static function (int $targetPage) use ($search, $approval, $pagination): string {
    return http_build_query(array_filter([
        'search' => $search,
        'approval_status' => $approval,
        'per_page' => (int) ($pagination['per_page'] ?? 10) === 10 ? null : (int) $pagination['per_page'],
        'page' => $targetPage === 1 ? null : $targetPage,
    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
};
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-buildings" aria-hidden="true"></i><span>Organizer review</span></p>
        <h1>Organizers</h1>
        <p>Review organization identity, account eligibility, and approval history.</p>
    </div>
</div>

<div class="filter-toolbar mt-8">
    <p class="result-summary filter-toolbar__summary" role="status" aria-live="polite" aria-atomic="true">
        <strong class="result-summary__count" aria-hidden="true"><?= e($total) ?></strong>
        <span class="result-summary__copy" aria-hidden="true">
            <span class="result-summary__context">Matching</span>
            <span class="result-summary__subject">Organizers</span>
        </span>
        <span class="sr-only"><?= e($total) ?> matching <?= $total === 1 ? 'organizer' : 'organizers' ?></span>
    </p>
    <form class="filter-toolbar__form" action="/admin/organizers" method="get" role="search" aria-label="Filter organizers" data-form-kind="filter">
        <div class="filter-toolbar__field filter-toolbar__field--search"><label for="organizer-search">Search</label><input id="organizer-search" name="search" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="Organization, contact, or email"></div>
        <div class="filter-toolbar__field"><label for="approval-status">Approval</label><select id="approval-status" name="approval_status"><option value="">All states</option><?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label): ?><option value="<?= e($value) ?>"<?= $approval === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="filter-toolbar__actions"><button class="button button--quiet button--compact" type="submit"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span>Apply filters</span></button></div>
    </form>
</div>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="organizer-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-buildings" aria-hidden="true"></i></span><div><h2 id="organizer-list-heading">Organizer directory</h2><p>Open an application to review its evidence before deciding.</p></div></div>
    <?php if ($items === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-buildings" aria-hidden="true"></i></span><strong>No organizers match these filters</strong><p>Clear one or more filters to widen the organizer search.</p><a class="button button--quiet" href="/admin/organizers">Clear filters</a></div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="operations-table organizer-table">
                <caption class="sr-only">Administrator organizer directory</caption>
                <thead><tr><th scope="col">Organization</th><th scope="col">Contact</th><th scope="col">Events</th><th scope="col">Approval</th><th scope="col">Action</th></tr></thead>
                <tbody><?php foreach ($items as $organizer): ?><?php $state = (string) ($organizer['approval_status'] ?? ''); $accountStatus = (string) ($organizer['user_status'] ?? ''); ?><tr>
                    <td data-label="Organization"><strong><?= e($organizer['organization_name'] ?? 'Unnamed organization') ?></strong><small>Account: <span class="status-chip status-chip--<?= e(status_modifier($accountStatus, 'account')) ?>"><?= e(oems_status_label($accountStatus)) ?></span></small></td>
                    <td data-label="Contact"><strong><?= e($organizer['name'] ?? 'Unknown contact') ?></strong><small class="organizer-table__value"><?= e($organizer['email'] ?? '') ?></small></td>
                    <td data-label="Events"><?= e((int) ($organizer['event_count'] ?? 0)) ?></td>
                    <td data-label="Approval"><span class="status-chip status-chip--<?= e(status_modifier($state, 'organizer_approval')) ?>"><?= e(oems_status_label($state)) ?></span></td>
                    <td class="organizer-table__action" data-label="Action"><a class="text-link" href="/admin/organizers/<?= e($organizer['id']) ?>">Review <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php endif; ?>
    <nav class="mt-6 flex items-center justify-between gap-4" aria-label="Organizer directory pages"><span>Page <?= e($page) ?> of <?= e($lastPage) ?></span><div class="flex gap-2"><?php if ($page > 1): ?><a class="button button--quiet button--compact" href="/admin/organizers?<?= e($query($page - 1)) ?>">Previous</a><?php endif; ?><?php if ($page < $lastPage): ?><a class="button button--quiet button--compact" href="/admin/organizers?<?= e($query($page + 1)) ?>">Next</a><?php endif; ?></div></nav>
</section>
