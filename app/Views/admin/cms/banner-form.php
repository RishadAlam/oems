<?php
$edit = is_array($banner);
$action = $edit ? '/admin/cms/banners/' . (int) $banner['id'] : '/admin/cms/banners';
$value = static fn (string $key, string $default = ''): string => old_value($old, $key, (string) ($banner[$key] ?? $default));
$date = static fn (string $key): string => str_replace(' ', 'T', substr((string) ($banner[$key] ?? ''), 0, 16));
$invalid = static fn (string $key): string => field_error($errors, $key)
    ? ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"'
    : '';
?>
<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-image" aria-hidden="true"></i><span>Home message</span></p><h1><?= $edit ? 'Edit banner' : 'Create banner' ?></h1><p>Use one image, a visible title, and an optional same-site destination.</p></div>
    <a class="button button--quiet" href="/admin/cms"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to content</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post" enctype="multipart/form-data" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php
    $fieldTargets = ['banner' => 'banner-details'];
    $fieldLabels = ['banner' => 'Banner', 'title' => 'Visible title', 'subtitle' => 'Subtitle', 'image' => 'Banner image', 'link_url' => 'Same-site link', 'starts_at' => 'Start', 'ends_at' => 'End', 'sort_order' => 'Sort order'];
    $formErrorSummaryId = 'banner-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>
    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>Visible title, an image for new banners, and sort order are required.</span></p>

    <section class="organizer-form__section" aria-labelledby="banner-details">
        <div class="organizer-form__heading"><span><i class="ph ph-image-square" aria-hidden="true"></i></span><div><h2 id="banner-details">Banner details</h2><p>JPEG, PNG, or WebP up to 5 MB and 16 million pixels.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group sm:col-span-2"><label for="title">Visible title</label><input id="title" name="title" maxlength="180" value="<?= $value('title') ?>" data-form-label="Visible title" required<?= $invalid('title') ?>><?php if ($message = field_error($errors, 'title')): ?><p id="title-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group sm:col-span-2"><label for="subtitle">Subtitle <span class="field-label-note">Optional</span></label><input id="subtitle" name="subtitle" maxlength="255" value="<?= $value('subtitle') ?>" data-form-label="Subtitle"<?= $invalid('subtitle') ?>><?php if ($message = field_error($errors, 'subtitle')): ?><p id="subtitle-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group sm:col-span-2"><label for="image">Banner image<?= $edit ? ' (leave blank to keep current image)' : '' ?></label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-form-label="Banner image" data-max-bytes="5242880"<?= $edit ? '' : ' required' ?><?= $invalid('image') ?>><?php if ($message = field_error($errors, 'image')): ?><p id="image-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group sm:col-span-2"><label for="link_url">Same-site link <span class="field-label-note">Optional</span></label><input id="link_url" name="link_url" maxlength="500" pattern="/(?!/).*" placeholder="/events" value="<?= $value('link_url') ?>" data-form-label="Same-site link"<?= $invalid('link_url') ?>><?php if ($message = field_error($errors, 'link_url')): ?><p id="link-url-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="starts_at">Start <span class="field-label-note">Optional</span></label><input id="starts_at" name="starts_at" type="datetime-local" value="<?= old_value($old, 'starts_at', $date('starts_at')) ?>" data-form-label="Start"<?= $invalid('starts_at') ?>><?php if ($message = field_error($errors, 'starts_at')): ?><p id="starts-at-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="ends_at">End <span class="field-label-note">Optional</span></label><input id="ends_at" name="ends_at" type="datetime-local" value="<?= old_value($old, 'ends_at', $date('ends_at')) ?>" data-form-label="End" data-after-field="starts_at"<?= $invalid('ends_at') ?>><?php if ($message = field_error($errors, 'ends_at')): ?><p id="ends-at-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="sort_order">Sort order</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" step="1" value="<?= $value('sort_order', '0') ?>" data-form-label="Sort order" required<?= $invalid('sort_order') ?>><?php if ($message = field_error($errors, 'sort_order')): ?><p id="sort-order-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
        </div>
    </section>

    <div class="organizer-form__actions"><p><i class="ph ph-info" aria-hidden="true"></i><span>New banners start active. Status changes remain separate.</span></p><button class="button button--primary" type="submit" data-submit-label="<?= $edit ? 'Saving banner…' : 'Creating banner…' ?>"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span data-submit-text><?= $edit ? 'Save banner' : 'Create banner' ?></span></button></div>
</form>
