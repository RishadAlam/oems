<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-tag" aria-hidden="true"></i><span>Event taxonomy</span></p>
        <h1>Categories</h1>
        <p>Maintain the category choices available to event organizers.</p>
    </div>
    <a class="button button--primary" href="/admin/categories/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create category</span></a>
</div>

<section class="dashboard-panel organizer-list-panel mt-8" aria-labelledby="category-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-tag-chevron" aria-hidden="true"></i></span><div><h2 id="category-list-heading">Category list</h2><p>Inactive categories remain visible here but are unavailable for new event drafts.</p></div></div>
    <?php if ($categories === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-tag" aria-hidden="true"></i></span><strong>No categories yet</strong><p>Create the first category for event organizers.</p><a class="button button--primary" href="/admin/categories/create">Create category</a></div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="operations-table organizer-table">
                <caption class="sr-only">Administrator categories</caption>
                <thead><tr><th scope="col">Category</th><th scope="col">Slug</th><th scope="col">Order</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <?php $isActive = !empty($category['is_active']); ?>
                    <tr>
                        <td data-label="Category"><div class="organizer-table__primary organizer-table__value"><strong><?= e($category['name'] ?? 'Unnamed category') ?></strong><small><?= e($category['description'] ?? 'No description') ?></small></div></td>
                        <td class="organizer-table__value" data-label="Slug"><code><?= e($category['slug'] ?? '') ?></code></td>
                        <td data-label="Order"><?= e($category['sort_order'] ?? 0) ?></td>
                        <td data-label="Status"><span class="status-chip <?= $isActive ? 'status-chip--active' : 'status-chip--inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span></td>
                        <td class="organizer-table__action" data-label="Actions">
                            <div class="admin-table-actions">
                                <a class="button button--quiet button--compact" href="/admin/categories/<?= e($category['id']) ?>/edit"><i class="ph ph-pencil-simple" aria-hidden="true"></i><span>Edit</span></a>
                                <form action="/admin/categories/<?= e($category['id']) ?>/status" method="post" data-form-kind="action"<?= $isActive ? ' data-confirm="Deactivate this category? It will no longer be available for new event drafts."' : '' ?>>
                                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
                                    <button class="button button--compact <?= $isActive ? 'button--danger' : 'button--quiet' ?>" type="submit" data-submit-label="Updating category…"><i class="ph <?= $isActive ? 'ph-eye-slash' : 'ph-eye' ?>" aria-hidden="true"></i><span data-submit-text><?= $isActive ? 'Deactivate' : 'Activate' ?></span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
