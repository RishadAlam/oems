<?php
$contactFields = [
    ['name', 'Name', 'text', 2, 100, 'name'],
    ['email', 'Email address', 'email', null, 190, 'email'],
    ['subject', 'Subject', 'text', 3, 180, 'off'],
];
?>
<section class="section-space">
    <div class="page-shell grid gap-8 lg:grid-cols-[.8fr_1.2fr]">
        <div>
            <p class="eyebrow"><i class="ph ph-chats" aria-hidden="true"></i><span>Support</span></p>
            <h1 class="section-title mt-4"><?= e($page['title'] ?? 'Contact OEMS') ?></h1>
            <p class="mt-5 text-[var(--ink-muted)]"><?= e($copy !== '' ? $copy : 'Send the team a message. We reply by email when a response is needed.') ?></p>
        </div>

        <form class="dashboard-panel form-stack" action="/contact/submit" method="post" data-form-kind="entry">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="sr-only" aria-hidden="true"><label for="contact-website">Website</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>

            <?php
            $fieldTargets = ['name' => 'contact-name', 'email' => 'contact-email', 'subject' => 'contact-subject', 'message' => 'contact-message'];
            $fieldLabels = ['name' => 'Name', 'email' => 'Email address', 'subject' => 'Subject', 'message' => 'Message'];
            $formErrorSummaryId = 'contact-error-summary';
            require base_path('app/Views/components/form-errors.php');
            ?>

            <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>All fields are required.</span></p>

            <?php foreach ($contactFields as [$key, $label, $type, $min, $max, $autocomplete]): ?>
                <div class="field-group">
                    <label for="contact-<?= e($key) ?>"><?= e($label) ?></label>
                    <input
                        id="contact-<?= e($key) ?>"
                        name="<?= e($key) ?>"
                        type="<?= e($type) ?>"
                        <?= $min === null ? '' : 'minlength="' . e($min) . '" ' ?>maxlength="<?= e($max) ?>"
                        value="<?= old_value($old, $key) ?>"
                        autocomplete="<?= e($autocomplete) ?>"
                        <?= $type === 'email' ? 'inputmode="email" ' : '' ?>required
                        data-form-label="<?= e($label) ?>"
                        <?= ltrim(form_control_attributes($errors, $key, ['contact-' . $key . '-help'], 'contact-' . $key . '-error')) ?>
                    >
                    <p id="contact-<?= e($key) ?>-help" class="field-help"><?= $key === 'email' ? 'Replies are sent only to this address.' : ($min === null ? 'Required.' : e($min) . ' to ' . e($max) . ' characters.') ?></p>
                    <?php if ($error = field_error($errors, $key)): ?><p id="contact-<?= e($key) ?>-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="field-group">
                <label for="contact-message">Message</label>
                <textarea id="contact-message" name="message" minlength="10" maxlength="4000" rows="7" required data-form-label="Message"<?= form_control_attributes($errors, 'message', ['contact-message-help'], 'contact-message-error') ?>><?= old_value($old, 'message') ?></textarea>
                <p id="contact-message-help" class="field-help">10 to 4000 characters. Do not include passwords or payment secrets.</p>
                <?php if ($error = field_error($errors, 'message')): ?><p id="contact-message-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
            </div>

            <button class="button button--primary" type="submit" data-submit-label="Sending message…"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span data-submit-text>Send message</span></button>
        </form>
    </div>
</section>
