<?php

declare(strict_types=1);

$formErrorEntries = form_error_entries(
    is_array($errors ?? null) ? $errors : [],
    is_array($fieldTargets ?? null) ? $fieldTargets : [],
    is_array($fieldLabels ?? null) ? $fieldLabels : [],
);
$formErrorSummaryId = preg_replace(
    '/[^a-zA-Z0-9_-]+/',
    '-',
    is_scalar($formErrorSummaryId ?? null) ? (string) $formErrorSummaryId : 'form-error-summary',
);
$formErrorSummaryTitleId = $formErrorSummaryId . '-title';
?>
<?php if ($formErrorEntries !== []): ?>
    <section id="<?= e($formErrorSummaryId) ?>" class="form-error-summary" data-form-error-summary tabindex="-1" role="alert" aria-labelledby="<?= e($formErrorSummaryTitleId) ?>">
        <div class="form-error-summary__heading">
            <i class="ph ph-warning-circle" aria-hidden="true"></i>
            <div>
                <h2 id="<?= e($formErrorSummaryTitleId) ?>">Check the highlighted fields</h2>
                <p>Review the details below, then submit the form again.</p>
            </div>
        </div>
        <ul>
            <?php foreach ($formErrorEntries as $entry): ?>
                <li><a href="#<?= e($entry['target']) ?>"><strong><?= e($entry['label']) ?>:</strong> <?= e($entry['message']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
