<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span>Participant communication</span></p>
        <h1>Send announcement</h1>
        <p>Notify eligible confirmed participants for <strong><?= e($event['title'] ?? 'Event') ?></strong>.</p>
    </div>
    <a class="button button--quiet" href="/organizer/events/<?= e($event['id']) ?>/announcements"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Announcement history</span></a>
</div>

<?php if (is_string($actionError ?? null) && $actionError !== ''): ?><div class="form-alert mt-6" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($actionError) ?></span></div><?php endif; ?>

<div class="admin-moderation-layout mt-8">
    <section class="dashboard-panel" aria-labelledby="announcement-compose-heading">
        <?php if (is_array($confirmation ?? null)): ?>
            <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span><div><h2 id="announcement-compose-heading">Confirm announcement send</h2><p>Review the final message before participant notifications are created.</p></div></div>
            <div class="form-alert mt-6" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span>This will notify every currently eligible participant. Sent announcements cannot be edited or recalled.</span></div>
            <dl class="organizer-detail-list mt-6">
                <div><dt>Event</dt><dd><?= e($event['title'] ?? 'Event') ?></dd></div>
                <div><dt>Audience</dt><dd>Active, verified participants with a confirmed registration</dd></div>
                <div><dt>Subject</dt><dd><?= e($confirmation['subject'] ?? '') ?></dd></div>
                <div><dt>Message</dt><dd><?= nl2br(e($confirmation['message'] ?? '')) ?></dd></div>
            </dl>
            <form class="mt-6" action="/organizer/events/<?= e($event['id']) ?>/announcements" method="post" data-form-kind="action">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="confirm_send" value="1">
                <input type="hidden" name="request_key" value="<?= e($confirmation['request_key'] ?? '') ?>">
                <button class="button button--primary w-full" type="submit" data-submit-label="Sending announcement…"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span data-submit-text>Confirm send</span></button>
            </form>
            <a class="button button--quiet w-full mt-3" href="/organizer/events/<?= e($event['id']) ?>/announcements/create">Cancel and edit message</a>
        <?php else: ?>
            <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-note-pencil" aria-hidden="true"></i></span><div><h2 id="announcement-compose-heading">Compose message</h2><p>Use plain text and include only information participants need to act on.</p></div></div>
            <?php if ($canSend): ?>
                <form class="mt-6 grid gap-5" action="/organizer/events/<?= e($event['id']) ?>/announcements" method="post" data-form-kind="entry">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <?php
                    $fieldTargets = ['subject' => 'announcement-subject', 'message' => 'announcement-message', 'announcement' => 'announcement-compose-heading'];
                    $fieldLabels = ['subject' => 'Subject', 'message' => 'Message', 'announcement' => 'Announcement'];
                    $formErrorSummaryId = 'announcement-error-summary';
                    require base_path('app/Views/components/form-errors.php');
                    ?>
                    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>Subject and message are required.</span></p>
                    <div class="field-group">
                        <label for="announcement-subject">Subject</label>
                        <input id="announcement-subject" name="subject" type="text" maxlength="180" value="<?= old_value($old, 'subject') ?>" autocomplete="off" data-form-label="Subject" required<?= field_error($errors, 'subject') === null ? '' : ' aria-invalid="true" aria-describedby="announcement-subject-error"' ?>>
                        <?php if ($error = field_error($errors, 'subject')): ?><p id="announcement-subject-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
                    </div>
                    <div class="field-group">
                        <label for="announcement-message">Message</label>
                        <textarea id="announcement-message" name="message" rows="9" maxlength="1000" aria-describedby="announcement-message-help<?= field_error($errors, 'message') === null ? '' : ' announcement-message-error' ?>" data-form-label="Message" required><?= old_value($old, 'message') ?></textarea>
                        <p id="announcement-message-help" class="field-help">Plain text only, up to 1,000 characters. Do not include passwords, payment details, or private account data.</p>
                        <?php if ($error = field_error($errors, 'message')): ?><p id="announcement-message-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?>
                    </div>
                    <button class="button button--primary" type="submit" data-submit-label="Reviewing announcement…"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span data-submit-text>Review announcement</span></button>
                </form>
            <?php else: ?>
                <div class="empty-state mt-6"><span class="empty-state__icon"><i class="ph ph-lock-key" aria-hidden="true"></i></span><strong>Sending is unavailable</strong><p>This event or organizer account is not currently eligible to send participant announcements.</p><a class="button button--quiet" href="/organizer/events/<?= e($event['id']) ?>">Return to event</a></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <aside class="dashboard-panel organizer-actions-panel" aria-labelledby="announcement-delivery-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-users-three" aria-hidden="true"></i></span><div><h2 id="announcement-delivery-heading">Delivery rules</h2><p>Audience eligibility is checked again when you confirm.</p></div></div>
        <ul class="grid gap-4 mt-6">
            <li class="organizer-action-note"><i class="ph ph-check-circle" aria-hidden="true"></i><span>Registration must still be confirmed and not cancelled.</span></li>
            <li class="organizer-action-note"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Participant account must be active and email verified.</span></li>
            <li class="organizer-action-note"><i class="ph ph-bell" aria-hidden="true"></i><span>Each eligible participant receives one in-app notification.</span></li>
        </ul>
    </aside>
</div>
