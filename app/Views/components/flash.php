<?php foreach (['success', 'error', 'info'] as $type): ?>
    <?php if (!empty($flash[$type])): ?>
        <?php $flashIcon = ['success' => 'ph-check-circle', 'error' => 'ph-warning-circle', 'info' => 'ph-info'][$type]; ?>
        <div
            class="flash-message flash-message--<?= e($type) ?>"
            role="<?= $type === 'error' ? 'alert' : 'status' ?>"
            data-flash-message
        >
            <div class="flash-message__content">
                <i class="ph <?= e($flashIcon) ?>" aria-hidden="true"></i>
                <p><?= e($flash[$type]) ?></p>
            </div>
            <button type="button" class="flash-message__close" data-dismiss-flash aria-label="Dismiss message" title="Dismiss message">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if (!empty($flash['development_link'])): ?>
    <div class="flash-message flash-message--info" role="status" data-flash-message>
        <div class="flash-message__content">
            <i class="ph ph-info" aria-hidden="true"></i>
            <p>
                Development link:
                <a class="font-semibold underline underline-offset-4" href="<?= e($flash['development_link']) ?>">continue securely</a>
            </p>
        </div>
        <button type="button" class="flash-message__close" data-dismiss-flash aria-label="Dismiss message" title="Dismiss message">
            <i class="ph ph-x" aria-hidden="true"></i>
        </button>
    </div>
<?php endif; ?>
