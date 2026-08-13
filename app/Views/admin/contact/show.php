<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-envelope-open" aria-hidden="true"></i><span>Message evidence</span></p><h1><?= e($message['subject']) ?></h1><p>Received from <?= e($message['name']) ?> at <?= e($message['email']) ?>.</p></div>
    <a class="button button--quiet" href="/admin/contact">Back to inbox</a>
</header>

<section class="dashboard-panel mt-8">
    <dl class="grid gap-4 sm:grid-cols-2"><div><dt>Status</dt><dd><span class="status-chip status-chip--<?= e($message['status']) ?>"><?= e(ucfirst($message['status'])) ?></span></dd></div><div><dt>Received</dt><dd><?= e($message['created_at']) ?></dd></div></dl>
    <div class="mt-6 whitespace-pre-wrap rounded-[var(--radius-card)] border border-[var(--line)] bg-[var(--surface-soft)] p-5"><?= e($message['message']) ?></div>
</section>

<section class="dashboard-panel mt-6" aria-labelledby="contact-reply-heading">
    <h2 id="contact-reply-heading" class="text-xl font-bold">Reply by email</h2>
    <p class="mt-1 text-sm text-[var(--ink-muted)]">The reply is queued. SMTP delivery never blocks this page.</p>
    <form class="mt-5 grid gap-4" method="post" action="/admin/contact/<?= e($message['id']) ?>/reply" data-form-kind="entry">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <?php $fieldTargets=['reply'=>'contact-reply']; $fieldLabels=['reply'=>'Reply']; $formErrorSummaryId='contact-reply-error-summary'; require base_path('app/Views/components/form-errors.php'); ?>
        <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>A reply is required.</span></p>
        <label for="contact-reply">Reply</label>
        <textarea id="contact-reply" name="reply" rows="7" minlength="2" maxlength="4000" data-form-label="Reply" required aria-describedby="contact-reply-help<?= field_error($errors, 'reply') ? ' contact-reply-error' : '' ?>"<?= field_error($errors, 'reply') ? ' aria-invalid="true"' : '' ?>></textarea>
        <p id="contact-reply-help" class="field-help">2 to 4000 characters. Do not include secrets.</p>
        <?php if ($error = field_error($errors, 'reply')): ?><p id="contact-reply-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <button class="button button--primary" type="submit" data-submit-label="Queueing reply…"><span data-submit-text>Queue reply</span></button>
    </form>
</section>

<section class="dashboard-panel mt-6">
    <h2 class="text-xl font-bold">Update status</h2>
    <div class="mt-4 flex flex-wrap gap-3">
        <?php foreach (['new', 'read', 'archived'] as $state): ?>
            <?php if ($state !== $message['status']): ?>
                <form method="post" action="/admin/contact/<?= e($message['id']) ?>/status" data-form-kind="action">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="from" value="<?= e($message['status']) ?>">
                    <input type="hidden" name="status" value="<?= e($state) ?>">
                    <button class="button button--quiet" type="submit" data-submit-label="Updating status…"><span data-submit-text>Mark <?= e($state) ?></span></button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
