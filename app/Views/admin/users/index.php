<?php
$items = is_array($result['items'] ?? null) ? $result['items'] : [];
$pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
$total = (int) ($pagination['total'] ?? 0);
$search = is_scalar($filters['search'] ?? null) ? (string) $filters['search'] : '';
$role = is_scalar($filters['role'] ?? null) ? (string) $filters['role'] : '';
$status = is_scalar($filters['status'] ?? null) ? (string) $filters['status'] : '';
$page = max(1, (int) ($pagination['page'] ?? 1));
$lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$query = static function (int $targetPage) use ($search, $role, $status, $pagination): string {
    return http_build_query(array_filter([
        'search' => $search,
        'role' => $role,
        'status' => $status,
        'per_page' => (int) ($pagination['per_page'] ?? 10) === 10 ? null : (int) $pagination['per_page'],
        'page' => $targetPage === 1 ? null : $targetPage,
    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
};
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-users" aria-hidden="true"></i><span>Account operations</span></p>
        <h1>Users</h1>
        <p>Find participant and organizer accounts, inspect their access state, and manage sign-in eligibility.</p>
    </div>
</div>

<div class="organizer-toolbar mt-8">
    <p><strong><?= e($total) ?></strong> matching <?= $total === 1 ? 'user' : 'users' ?></p>
    <form action="/admin/users" method="get">
        <div class="field-group"><label for="user-search">Search</label><input id="user-search" name="search" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="Name or email"></div>
        <div class="field-group"><label for="user-role">Role</label><select id="user-role" name="role"><option value="">All roles</option><option value="participant"<?= $role === 'participant' ? ' selected' : '' ?>>Participant</option><option value="organizer"<?= $role === 'organizer' ? ' selected' : '' ?>>Organizer</option><option value="super-admin"<?= $role === 'super-admin' ? ' selected' : '' ?>>Super administrator</option></select></div>
        <div class="field-group"><label for="user-status">Status</label><select id="user-status" name="status"><option value="">All statuses</option><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label): ?><option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <button class="button button--quiet button--compact" type="submit"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span>Apply filters</span></button>
    </form>
</div>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="user-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-identification-card" aria-hidden="true"></i></span><div><h2 id="user-list-heading">Account directory</h2><p>Open an account before changing its access.</p></div></div>
    <?php if ($items === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-user-focus" aria-hidden="true"></i></span><strong>No users match these filters</strong><p>Clear one or more filters to widen the account search.</p><a class="button button--quiet" href="/admin/users">Clear filters</a></div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="organizer-table">
                <caption class="sr-only">Administrator user directory</caption>
                <thead><tr><th scope="col">Account</th><th scope="col">Role</th><th scope="col">Verification</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $user): ?>
                    <?php $userStatus = (string) ($user['status'] ?? 'inactive'); ?>
                    <tr>
                        <td data-label="Account"><strong><?= e($user['name'] ?? 'Unnamed user') ?></strong><small><?= e($user['email'] ?? '') ?></small></td>
                        <td data-label="Role"><?= e($user['role_name'] ?? ucfirst((string) ($user['role_slug'] ?? 'member'))) ?></td>
                        <td data-label="Verification"><?= !empty($user['email_verified_at']) ? 'Email verified' : 'Email unverified' ?></td>
                        <td data-label="Status"><span class="status-chip <?= $userStatus === 'active' ? 'status-chip--approved' : 'status-chip--cancelled' ?>"><?= e(ucfirst($userStatus)) ?></span></td>
                        <td class="organizer-table__action" data-label="Action"><a class="text-link" href="/admin/users/<?= e($user['id']) ?>">Review <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <nav class="mt-6 flex items-center justify-between gap-4" aria-label="User directory pages"><span>Page <?= e($page) ?> of <?= e($lastPage) ?></span><div class="flex gap-2"><?php if ($page > 1): ?><a class="button button--quiet button--compact" href="/admin/users?<?= e($query($page - 1)) ?>">Previous</a><?php endif; ?><?php if ($page < $lastPage): ?><a class="button button--quiet button--compact" href="/admin/users?<?= e($query($page + 1)) ?>">Next</a><?php endif; ?></div></nav>
</section>
