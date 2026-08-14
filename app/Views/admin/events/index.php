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

<div class="filter-toolbar mt-8">
    <p class="result-summary filter-toolbar__summary" role="status" aria-live="polite" aria-atomic="true">
        <strong class="result-summary__count" aria-hidden="true"><?= e(count($events)) ?></strong>
        <span class="result-summary__copy" aria-hidden="true">
            <span class="result-summary__context">In queue</span>
            <span class="result-summary__subject">Events</span>
        </span>
        <span class="sr-only"><?= e(count($events)) ?> <?= count($events) === 1 ? 'event' : 'events' ?> in this queue</span>
    </p>
    <form class="filter-toolbar__form filter-toolbar__form--compact" action="/admin/events" method="get" role="search" aria-label="Filter events" data-form-kind="filter">
        <div class="filter-toolbar__field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="all"<?= $status === null ? ' selected' : '' ?>>All statuses</option>
                <?php foreach ($statuses as $availableStatus): ?><option value="<?= e($availableStatus) ?>"<?= $status === $availableStatus ? ' selected' : '' ?>><?= e($statusLabels[$availableStatus] ?? ucfirst($availableStatus)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="filter-toolbar__actions"><button class="button button--quiet button--compact" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i><span>Filter</span></button></div>
    </form>
</div>

<section class="dashboard-panel organizer-list-panel mt-6" aria-labelledby="moderation-list-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-clipboard-text" aria-hidden="true"></i></span><div><h2 id="moderation-list-heading">Moderation queue</h2><p>Open an event to review its evidence before choosing an action.</p></div></div>
    <?php if ($events === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span><strong>No events in this queue</strong><p>Choose another status or return later for new submissions.</p></div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="operations-table organizer-table">
                <caption class="sr-only">Administrator event moderation queue</caption>
                <thead><tr><th scope="col">Event</th><th scope="col">Organizer</th><th scope="col">Schedule</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php $eventStatus = (string) ($event['status'] ?? ''); ?>
                    <tr>
                        <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><small><?= e($event['category_name'] ?? 'Uncategorized') ?><?php if (!empty($event['venue_name'])): ?>, <?= e($event['venue_name']) ?><?php endif; ?></small></td>
                        <td data-label="Organizer"><?= e($event['organization_name'] ?? 'Unknown organizer') ?></td>
                        <td data-label="Schedule"><time datetime="<?= e(str_replace(' ', 'T', (string) ($event['start_date'] ?? ''))) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) ($event['start_date'] ?? 'now')))) ?></time></td>
                        <td data-label="Status"><span class="status-chip status-chip--<?= e(status_modifier($eventStatus, 'event')) ?>"><?= e(oems_status_label($eventStatus, $statusLabels)) ?></span></td>
                        <td class="organizer-table__action" data-label="Action"><a class="text-link" href="/admin/events/<?= e($event['id']) ?>">Review <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
