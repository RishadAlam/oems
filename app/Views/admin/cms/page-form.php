<?php
$value = static fn (string $key): string => old_value($old, $key, (string) ($page[$key] ?? ''));
$invalid = static fn (string $key): string => field_error($errors, $key)
    ? ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"'
    : '';
?>
<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-file-text" aria-hidden="true"></i><span>Fixed public page</span></p><h1>Edit <?= e($page['title']) ?></h1><p>The route <code>/<?= e($page['slug']) ?></code> is fixed. Content is plain text and rendered as paragraphs.</p></div>
    <a class="button button--quiet" href="/admin/cms"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to content</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="/admin/cms/pages/<?= e($page['slug']) ?>" method="post" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php
    $fieldTargets = ['page' => 'page-content'];
    $fieldLabels = ['page' => 'Page', 'title' => 'Title', 'content' => 'Content', 'meta_title' => 'Meta title', 'meta_description' => 'Meta description'];
    $formErrorSummaryId = 'page-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>
    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>Title and content are required.</span></p>

    <section class="organizer-form__section" aria-labelledby="page-content">
        <div class="organizer-form__heading"><span><i class="ph ph-text-aa" aria-hidden="true"></i></span><div><h2 id="page-content">Page content</h2><p>Separate paragraphs with a blank line. HTML is not accepted.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group sm:col-span-2"><label for="title">Title</label><input id="title" name="title" maxlength="180" value="<?= $value('title') ?>" data-form-label="Title" required<?= $invalid('title') ?>><?php if ($message = field_error($errors, 'title')): ?><p id="title-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group sm:col-span-2"><label for="content">Content</label><textarea id="content" name="content" rows="14" maxlength="20000" data-form-label="Content" required<?= $invalid('content') ?>><?= $value('content') ?></textarea><?php if ($message = field_error($errors, 'content')): ?><p id="content-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="meta_title">Meta title <span class="field-label-note">Optional</span></label><input id="meta_title" name="meta_title" maxlength="190" value="<?= $value('meta_title') ?>" data-form-label="Meta title"<?= $invalid('meta_title') ?>><?php if ($message = field_error($errors, 'meta_title')): ?><p id="meta-title-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="meta_description">Meta description <span class="field-label-note">Optional</span></label><textarea id="meta_description" name="meta_description" rows="3" maxlength="320" data-form-label="Meta description"<?= $invalid('meta_description') ?>><?= $value('meta_description') ?></textarea><?php if ($message = field_error($errors, 'meta_description')): ?><p id="meta-description-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
        </div>
    </section>

    <div class="organizer-form__actions"><p><i class="ph ph-info" aria-hidden="true"></i><span>Saving content does not change publication status.</span></p><button class="button button--primary" type="submit" data-submit-label="Saving page…"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span data-submit-text>Save page</span></button></div>
</form>
