<?php
$statusLabels = [
    'draft' => 'Draft',
    'pending' => 'Pending review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'published' => 'Published',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-shield-chevron" aria-hidden="true"></i><span>Administrator review</span></p>
        <h1>Event moderation</h1>
        <p>Review pending submissions first, then manage approved and published events.</p>
    </div>
</div>

<div class="organizer-toolbar mt-8">
    <p><strong><?= e(count($events)) ?></strong> <?= count($events) === 1 ? 'event' : 'events' ?> in this queue</p>
    <form action="/admin/events" method="get">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="all"<?= $status === null ? ' selected' : '' ?>>All statuses</option>
            <?php foreach ($statuses as $availableStatus): ?><option value="<?= e($availableStatus) ?>"<?= $status === $availableStatus ? ' selected' : '' ?>><?= e($statusLabels[$availableStatus] ?? ucfirst($availableStatus)) ?></option><?php endforeach; ?>
        </select>
        <button class="button button--quiet button--compact" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i><span>Filter</span></button>
    </form>
</div>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="moderation-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-clipboard-text" aria-hidden="true"></i></span><div><h2 id="moderation-list-heading">Moderation queue</h2><p>Open an event to review its evidence before choosing an action.</p></div></div>
    <?php if ($events === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span><strong>No events in this queue</strong><p>Choose another status or return later for new submissions.</p></div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="organizer-table">
                <caption class="sr-only">Administrator event moderation queue</caption>
                <thead><tr><th scope="col">Event</th><th scope="col">Organizer</th><th scope="col">Schedule</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php $eventStatus = (string) ($event['status'] ?? 'draft'); ?>
                    <tr>
                        <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><small><?= e($event['category_name'] ?? 'Uncategorized') ?><?php if (!empty($event['venue_name'])): ?>, <?= e($event['venue_name']) ?><?php endif; ?></small></td>
                        <td data-label="Organizer"><?= e($event['organization_name'] ?? 'Unknown organizer') ?></td>
                        <td data-label="Schedule"><time datetime="<?= e(str_replace(' ', 'T', (string) ($event['start_date'] ?? ''))) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) ($event['start_date'] ?? 'now')))) ?></time></td>
                        <td data-label="Status"><span class="status-chip status-chip--<?= e($eventStatus) ?>"><?= e($statusLabels[$eventStatus] ?? ucfirst($eventStatus)) ?></span></td>
                        <td class="organizer-table__action" data-label="Action"><a class="text-link" href="/admin/events/<?= e($event['id']) ?>">Review <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
