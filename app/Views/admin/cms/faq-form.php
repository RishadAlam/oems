<?php
$edit = is_array($faq);
$action = $edit ? '/admin/cms/faqs/' . (int) $faq['id'] : '/admin/cms/faqs';
$value = static fn (string $key, string $default = ''): string => old_value($old, $key, (string) ($faq[$key] ?? $default));
$invalid = static fn (string $key): string => field_error($errors, $key)
    ? ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"'
    : '';
?>
<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-question" aria-hidden="true"></i><span>Public help</span></p><h1><?= $edit ? 'Edit FAQ' : 'Create FAQ' ?></h1><p>Write a direct question and a concise plain-text answer.</p></div>
    <a class="button button--quiet" href="/admin/cms"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to content</span></a>
</div>

<form class="dashboard-panel organizer-form mt-8" action="<?= e($action) ?>" method="post" data-form-kind="entry">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <?php
    $fieldTargets = ['faq' => 'faq-details'];
    $fieldLabels = ['faq' => 'FAQ', 'question' => 'Question', 'answer' => 'Answer', 'category' => 'Category', 'sort_order' => 'Sort order'];
    $formErrorSummaryId = 'faq-error-summary';
    require base_path('app/Views/components/form-errors.php');
    ?>
    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>Question, answer, and sort order are required.</span></p>

    <section class="organizer-form__section" aria-labelledby="faq-details">
        <div class="organizer-form__heading"><span><i class="ph ph-chat-centered-text" aria-hidden="true"></i></span><div><h2 id="faq-details">FAQ details</h2><p>Activation is controlled separately after the answer is saved.</p></div></div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field-group sm:col-span-2"><label for="question">Question</label><input id="question" name="question" maxlength="255" value="<?= $value('question') ?>" data-form-label="Question" required<?= $invalid('question') ?>><?php if ($message = field_error($errors, 'question')): ?><p id="question-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group sm:col-span-2"><label for="answer">Answer</label><textarea id="answer" name="answer" rows="7" maxlength="5000" data-form-label="Answer" required<?= $invalid('answer') ?>><?= $value('answer') ?></textarea><?php if ($message = field_error($errors, 'answer')): ?><p id="answer-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="category">Category <span class="field-label-note">Optional</span></label><input id="category" name="category" maxlength="100" value="<?= $value('category') ?>" data-form-label="Category"<?= $invalid('category') ?>><?php if ($message = field_error($errors, 'category')): ?><p id="category-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
            <div class="field-group"><label for="sort_order">Sort order</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" step="1" value="<?= $value('sort_order', '0') ?>" data-form-label="Sort order" required<?= $invalid('sort_order') ?>><?php if ($message = field_error($errors, 'sort_order')): ?><p id="sort-order-error" class="field-error" role="alert"><?= e($message) ?></p><?php endif; ?></div>
        </div>
    </section>

    <div class="organizer-form__actions"><span></span><button class="button button--primary" type="submit" data-submit-label="<?= $edit ? 'Saving FAQ…' : 'Creating FAQ…' ?>"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span data-submit-text><?= $edit ? 'Save FAQ' : 'Create FAQ' ?></span></button></div>
</form>
