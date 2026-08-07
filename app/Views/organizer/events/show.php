<?php
$status = (string) ($event['status'] ?? 'draft');
$statusLabels = ['draft' => 'Draft', 'pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Needs changes', 'published' => 'Published', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$canEdit = in_array($status, ['draft', 'rejected'], true);
$canCancel = in_array($status, ['approved', 'published'], true);
$canDelete = in_array($status, ['draft', 'rejected', 'cancelled'], true);
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-calendar-check" aria-hidden="true"></i><span>Event details</span></p>
        <h1><?= e($event['title']) ?></h1>
        <p>Review the current event record and manage its lifecycle.</p>
    </div>
    <div class="organizer-heading-actions"><a class="button button--quiet" href="/organizer/events"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>All events</span></a><?php if ($canEdit): ?><a class="button button--primary" href="/organizer/events/<?= e($event['id']) ?>/edit"><i class="ph ph-pencil-simple" aria-hidden="true"></i><span>Edit event</span></a><?php endif; ?></div>
</div>

<div class="organizer-detail-grid mt-8">
    <article class="dashboard-panel organizer-event-detail">
        <div class="organizer-detail-lead"><span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span><span><?= e($event['category_name'] ?? 'Uncategorized') ?></span></div>
        <p class="organizer-event-description"><?= nl2br(e($event['description'] ?? '')) ?></p>
        <?php if ($status === 'rejected' && !empty($event['rejection_reason'])): ?><div class="form-alert" role="note"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><strong>Review note:</strong> <?= e($event['rejection_reason']) ?></span></div><?php endif; ?>
        <dl class="organizer-detail-list">
            <div><dt><i class="ph ph-calendar" aria-hidden="true"></i>Starts</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) $event['start_date'])) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) $event['start_date']))) ?></time></dd></div>
            <div><dt><i class="ph ph-clock" aria-hidden="true"></i>Ends</dt><dd><time datetime="<?= e(str_replace(' ', 'T', (string) $event['end_date'])) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) $event['end_date']))) ?></time></dd></div>
            <div><dt><i class="ph ph-map-pin" aria-hidden="true"></i>Venue</dt><dd><?= e($event['venue_name'] ?? 'No venue selected') ?></dd></div>
            <div><dt><i class="ph ph-users" aria-hidden="true"></i>Capacity</dt><dd><?= e($event['capacity']) ?></dd></div>
            <div><dt><i class="ph ph-ticket" aria-hidden="true"></i>Ticket price</dt><dd>৳<?= e(number_format((float) ($event['ticket_price'] ?? 0), 2)) ?></dd></div>
            <div><dt><i class="ph ph-microphone-stage" aria-hidden="true"></i>Speaker</dt><dd><?= e($event['speaker'] ?? 'Not specified') ?></dd></div>
        </dl>
    </article>

    <aside class="dashboard-panel organizer-actions-panel" aria-labelledby="event-actions-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-lightning" aria-hidden="true"></i></span><div><h2 id="event-actions-heading">Available actions</h2><p>Actions depend on the current status.</p></div></div>
        <div class="organizer-action-stack">
            <?php if ($canEdit): ?><form action="/organizer/events/<?= e($event['id']) ?>/submit" method="post"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--primary w-full" type="submit"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span>Submit for review</span></button></form><?php endif; ?>
            <?php if ($canCancel): ?><form action="/organizer/events/<?= e($event['id']) ?>/cancel" method="post"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--quiet w-full" type="submit"><i class="ph ph-x-circle" aria-hidden="true"></i><span>Cancel event</span></button></form><?php endif; ?>
            <?php if ($canDelete): ?><form action="/organizer/events/<?= e($event['id']) ?>/delete" method="post"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--danger w-full" type="submit"><i class="ph ph-trash" aria-hidden="true"></i><span>Delete event</span></button></form><?php endif; ?>
            <?php if (!$canEdit && !$canCancel && !$canDelete): ?><p class="organizer-action-note"><i class="ph ph-info" aria-hidden="true"></i><span>No organizer actions are available for this status.</span></p><?php endif; ?>
        </div>
    </aside>
</div>
