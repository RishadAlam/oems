<?php
$isEdit = is_array($category);
$action = $isEdit ? '/admin/categories/' . (int) $category['id'] : '/admin/categories';
$categoryValue = static fn (string $key, string $default = ''): string => old_value($old, $key, (string) ($category[$key] ?? $default));
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"';
$selectedParent = (string) ($old['parent_id'] ?? $category['parent_id'] ?? '');
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-tag" aria-hidden="true"></i><span>Category editor</span></p><h1><?= $isEdit ? 'Edit category' : 'Create category' ?></h1><p>Use a stable slug and a concise public label.</p></div>
    <a class="button button--quiet" href="/admin/categories"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to categories</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php if ($error = field_error($errors, 'category')): ?><div class="form-alert" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($error) ?></span></div><?php endif; ?>
    <section class="organizer-form__section" aria-labelledby="category-details-heading">
        <div class="organizer-form__heading"><span><i class="ph ph-identification-card" aria-hidden="true"></i></span><div><h2 id="category-details-heading">Category details</h2><p>The slug is normalized to lowercase words joined by hyphens.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group"><label for="name">Category name</label><input id="name" name="name" type="text" maxlength="100" value="<?= $categoryValue('name') ?>" required<?= $invalid('name') ?>><?php if ($error = field_error($errors, 'name')): ?><p id="name-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="slug">Slug</label><input id="slug" name="slug" type="text" maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= $categoryValue('slug') ?>" required<?= $invalid('slug') ?>><?php if ($error = field_error($errors, 'slug')): ?><p id="slug-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="parent_id">Parent category <span class="field-label-note">Optional</span></label><select id="parent_id" name="parent_id"<?= $invalid('parent_id') ?>><option value="">No parent</option><?php foreach ($categories as $parent): ?><?php if (!$isEdit || (int) $parent['id'] !== (int) $category['id']): ?><option value="<?= e($parent['id']) ?>"<?= $selectedParent === (string) $parent['id'] ? ' selected' : '' ?>><?= e($parent['name']) ?></option><?php endif; ?><?php endforeach; ?></select><?php if ($error = field_error($errors, 'parent_id')): ?><p id="parent-id-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="sort_order">Sort order</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" step="1" value="<?= $categoryValue('sort_order', '0') ?>" required<?= $invalid('sort_order') ?>><?php if ($error = field_error($errors, 'sort_order')): ?><p id="sort-order-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="icon">Phosphor icon name <span class="field-label-note">Optional</span></label><input id="icon" name="icon" type="text" maxlength="100" placeholder="calendar-dots" value="<?= $categoryValue('icon') ?>"<?= $invalid('icon') ?>><?php if ($error = field_error($errors, 'icon')): ?><p id="icon-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
        </div>
        <div class="field-group"><label for="description">Description <span class="field-label-note">Optional</span></label><textarea id="description" name="description" rows="5" maxlength="500"<?= $invalid('description') ?>><?= $categoryValue('description') ?></textarea><?php if ($error = field_error($errors, 'description')): ?><p id="description-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
    </section>
    <div class="organizer-form__actions"><p><i class="ph ph-info" aria-hidden="true"></i><span>New categories start active. Status changes remain separate from editing.</span></p><button class="button button--primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span><?= $isEdit ? 'Save category' : 'Create category' ?></span></button></div>
</form>
