<?php
$status = (string) ($event['status'] ?? 'draft');
$statusLabels = ['draft' => 'Draft', 'pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'published' => 'Published', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$canCancel = in_array($status, ['approved', 'published'], true);
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Moderation evidence</span></p>
        <h1><?= e($event['title']) ?></h1>
        <p>Current status: <span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span></p>
    </div>
    <a class="button button--quiet" href="/admin/events"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Back to queue</span></a>
</div>

<div class="admin-moderation-layout mt-8">
    <article class="dashboard-panel admin-evidence-panel" aria-labelledby="event-evidence-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-file-magnifying-glass" aria-hidden="true"></i></span><div><h2 id="event-evidence-heading">Event evidence</h2><p>Verify the submitted record before using a moderation action.</p></div></div>
        <?php if (!empty($event['banner'])): ?><figure class="admin-evidence-banner"><img src="<?= e($event['banner']) ?>" alt="Banner submitted for <?= e($event['title']) ?>"></figure><?php endif; ?>
        <div class="admin-evidence-summary"><span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span><span><?= e($event['category_name'] ?? 'Uncategorized') ?></span><span><?= e($event['organization_name'] ?? 'Unknown organizer') ?></span></div>
        <p class="organizer-event-description"><?= nl2br(e($event['description'] ?? '')) ?></p>
        <?php if (!empty($event['rejection_reason'])): ?><div class="form-alert" role="note"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><strong>Previous rejection reason:</strong> <?= e($event['rejection_reason']) ?></span></div><?php endif; ?>
        <dl class="organizer-detail-list">
            <div><dt><i class="ph ph-calendar" aria-hidden="true"></i>Starts</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) $event['start_date'])) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) $event['start_date']))) ?></time></dd></div>
            <div><dt><i class="ph ph-clock" aria-hidden="true"></i>Ends</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) $event['end_date'])) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) $event['end_date']))) ?></time></dd></div>
            <div><dt><i class="ph ph-hourglass" aria-hidden="true"></i>Registration closes</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) $event['registration_deadline'])) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) $event['registration_deadline']))) ?></time></dd></div>
            <div><dt><i class="ph ph-map-pin" aria-hidden="true"></i>Venue</dt><dd><?= e($event['venue_name'] ?? 'No venue selected') ?><?php if (!empty($event['venue_city'])): ?>, <?= e($event['venue_city']) ?><?php endif; ?></dd></div>
            <div><dt><i class="ph ph-users" aria-hidden="true"></i>Capacity</dt><dd><?= e($event['capacity']) ?> total, <?= e($event['available_seats']) ?> available</dd></div>
            <div><dt><i class="ph ph-ticket" aria-hidden="true"></i>Ticket price</dt><dd><?= e(\OEMS\App\Support\Money::format($event['ticket_price'] ?? null, (string) ($event['currency'] ?? 'BDT'))) ?></dd></div>
            <div><dt><i class="ph ph-microphone-stage" aria-hidden="true"></i>Speaker</dt><dd><?= e($event['speaker'] ?? 'Not specified') ?></dd></div>
            <div><dt><i class="ph ph-link" aria-hidden="true"></i>Map</dt><dd><?php if (!empty($event['map_url'])): ?><a class="text-link" href="<?= e($event['map_url']) ?>" target="_blank" rel="noopener noreferrer">Open submitted map <i class="ph ph-arrow-square-out" aria-hidden="true"></i></a><?php else: ?>Not provided<?php endif; ?></dd></div>
        </dl>
        <?php if (!empty($event['tags']) && is_array($event['tags'])): ?><div class="admin-evidence-tags" aria-label="Event tags"><?php foreach ($event['tags'] as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
        <?php if (($gallery ?? []) !== []): ?>
            <section class="public-event__gallery" aria-labelledby="admin-event-gallery-heading">
                <h2 id="admin-event-gallery-heading">Submitted gallery</h2>
                <div>
                    <?php foreach ($gallery as $image): ?>
                        <figure><img src="<?= e($image['image_path']) ?>" alt="<?= e($image['alt_text'] ?? ('Gallery image for ' . $event['title'])) ?>"></figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>

    <aside class="dashboard-panel organizer-actions-panel" aria-labelledby="moderation-actions-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-gavel" aria-hidden="true"></i></span><div><h2 id="moderation-actions-heading">Moderation actions</h2><p>Each action checks the current state again before saving.</p></div></div>
        <p class="admin-current-state">Current status: <span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span></p>
        <div class="organizer-action-stack">
            <?php if ($status === 'pending'): ?>
                <form action="/admin/events/<?= e($event['id']) ?>/approve" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--primary w-full" type="submit" data-submit-label="Approving event…"><i class="ph ph-check-circle" aria-hidden="true"></i><span data-submit-text>Approve event</span></button></form>
                <form class="admin-rejection-form" action="/admin/events/<?= e($event['id']) ?>/reject" method="post" data-form-kind="entry">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <?php $fieldTargets = ['reason' => 'reason']; $fieldLabels = ['reason' => 'Reason for rejection']; $formErrorSummaryId = 'event-rejection-error-summary'; require base_path('app/Views/components/form-errors.php'); ?>
                    <div class="field-group"><label for="reason">Reason for rejection</label><textarea id="reason" name="reason" rows="5" maxlength="500" required<?= field_error($errors, 'reason') === null ? '' : ' aria-invalid="true" aria-describedby="reason-error"' ?>><?= old_value($old, 'reason') ?></textarea><?php if ($error = field_error($errors, 'reason')): ?><p id="reason-error" class="field-error" role="alert"><?= e($error) ?></p><?php endif; ?></div>
                    <button class="button button--danger w-full" type="submit" data-submit-label="Rejecting event…"><i class="ph ph-x-circle" aria-hidden="true"></i><span data-submit-text>Reject event</span></button>
                </form>
            <?php endif; ?>
            <?php if ($status === 'approved'): ?><form action="/admin/events/<?= e($event['id']) ?>/publish" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--primary w-full" type="submit" data-submit-label="Publishing event…"><i class="ph ph-broadcast" aria-hidden="true"></i><span data-submit-text>Publish event</span></button></form><?php endif; ?>
            <?php if ($status === 'published'): ?><form action="/admin/events/<?= e($event['id']) ?>/complete" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--primary w-full" type="submit" data-submit-label="Completing event…"><i class="ph ph-flag-checkered" aria-hidden="true"></i><span data-submit-text>Mark complete</span></button></form><?php endif; ?>
            <?php if ($canCancel): ?><form action="/admin/events/<?= e($event['id']) ?>/cancel" method="post" data-form-kind="action" data-confirm="Cancel this event? Active participant and ticket state may be affected."><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--danger w-full" type="submit" data-submit-label="Cancelling event…"><i class="ph ph-prohibit" aria-hidden="true"></i><span data-submit-text>Cancel event</span></button></form><?php endif; ?>
            <?php if (in_array($status, ['draft', 'rejected', 'cancelled'], true)): ?>
                <div class="form-alert" role="note"><i class="ph ph-warning" aria-hidden="true"></i><span>Deletion succeeds only when no participant registration history exists. The audit record is retained.</span></div>
                <form action="/admin/events/<?= e($event['id']) ?>/delete" method="post" data-form-kind="action" data-confirm="Delete this event? The audit record remains and restoration may be restricted."><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--danger w-full" type="submit" data-submit-label="Deleting event…"><i class="ph ph-trash" aria-hidden="true"></i><span data-submit-text>Delete event</span></button></form>
            <?php elseif (!in_array($status, ['pending', 'approved', 'published'], true)): ?><p class="organizer-action-note"><i class="ph ph-info" aria-hidden="true"></i><span>No administrator action is available for this status.</span></p><?php endif; ?>
        </div>
    </aside>
</div>
