<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-megaphone" aria-hidden="true"></i><span>Participant communication</span></p>
        <h1>Announcements</h1>
        <p>Messages sent for <strong><?= e($event['title'] ?? 'Event') ?></strong> to eligible confirmed participants.</p>
    </div>
    <div class="organizer-heading-actions">
        <a class="button button--quiet" href="/organizer/events/<?= e($event['id']) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Event details</span></a>
        <?php if ($canSend): ?><a class="button button--primary" href="/organizer/events/<?= e($event['id']) ?>/announcements/create"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span>New announcement</span></a><?php endif; ?>
    </div>
</div>

<?php if (!$canSend): ?>
    <div class="form-alert mt-6" role="note"><i class="ph ph-info" aria-hidden="true"></i><span>New announcements are available only while this event is published or completed and the organizer remains approved.</span></div>
<?php endif; ?>

<section class="dashboard-panel mt-8" aria-labelledby="announcement-history-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span><div><h2 id="announcement-history-heading">Delivery history</h2><p>Most recent first. Messages cannot be edited after delivery.</p></div></div>
    <?php if ($announcements === []): ?>
        <div class="empty-state mt-6"><span class="empty-state__icon"><i class="ph ph-megaphone-simple" aria-hidden="true"></i></span><strong>No announcements sent</strong><p>Use a concise update when confirmed participants need operational event information.</p><?php if ($canSend): ?><a class="button button--quiet" href="/organizer/events/<?= e($event['id']) ?>/announcements/create">Create announcement</a><?php endif; ?></div>
    <?php else: ?>
        <div class="organizer-table-wrap mt-6">
            <table class="operations-table organizer-table">
                <caption class="sr-only">Announcement delivery history for <?= e($event['title'] ?? 'this event') ?></caption>
                <thead><tr><th>Announcement</th><th>Audience</th><th>Sent by</th><th>Sent</th></tr></thead>
                <tbody>
                <?php foreach ($announcements as $announcement): ?>
                    <?php $count = (int) ($announcement['recipient_count'] ?? 0); ?>
                    <tr>
                        <td data-label="Announcement"><strong><?= e($announcement['subject'] ?? '') ?></strong><small><?= nl2br(e($announcement['message'] ?? '')) ?></small></td>
                        <td data-label="Audience"><strong><?= e($count) ?> recipient<?= $count === 1 ? '' : 's' ?></strong><small>Confirmed participants</small></td>
                        <td data-label="Sent by"><?= e($announcement['author_name'] ?? 'Former organizer user') ?></td>
                        <td data-label="Sent"><time datetime="<?= e(str_replace(' ', 'T', (string) ($announcement['sent_at'] ?? ''))) ?>"><?= e($announcement['sent_at'] ?? '') ?></time></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
