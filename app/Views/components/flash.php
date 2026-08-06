<?php foreach (['success', 'error', 'info'] as $type): ?>
    <?php if (!empty($flash[$type])): ?>
        <div
            class="flash-message flash-message--<?= e($type) ?>"
            role="<?= $type === 'error' ? 'alert' : 'status' ?>"
            data-flash-message
        >
            <p><?= e($flash[$type]) ?></p>
            <button type="button" class="flash-message__close" data-dismiss-flash aria-label="Dismiss message">Close</button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!empty($flash['development_link'])): ?>
    <div class="flash-message flash-message--info" role="status" data-flash-message>
        <p>
            Development link:
            <a class="font-semibold underline underline-offset-4" href="<?= e($flash['development_link']) ?>">continue securely</a>
        </p>
        <button type="button" class="flash-message__close" data-dismiss-flash aria-label="Dismiss message">Close</button>
    </div>
<?php endif; ?>

