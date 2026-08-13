<?php
$eventCount = count($events);
$statusLabels = [
    'draft' => 'Draft',
    'pending' => 'Pending review',
    'approved' => 'Approved',
    'rejected' => 'Needs changes',
    'published' => 'Published',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>

<div class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Event management</span></p>
        <h1>Your events</h1>
        <p>Create, review, and manage the events owned by your organizer account.</p>
    </div>
    <a class="button button--primary" href="/organizer/events/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create event</span></a>
</div>

<div class="filter-toolbar mt-8">
    <p class="result-summary filter-toolbar__summary" role="status" aria-live="polite" aria-atomic="true">
        <strong class="result-summary__count" aria-hidden="true"><?= e($eventCount) ?></strong>
        <span class="result-summary__copy" aria-hidden="true">
            <span class="result-summary__context">Matching</span>
            <span class="result-summary__subject">Events</span>
        </span>
        <span class="sr-only"><?= e($eventCount) ?> matching <?= $eventCount === 1 ? 'event' : 'events' ?></span>
    </p>
    <form class="filter-toolbar__form filter-toolbar__form--compact" action="/organizer/events" method="get" role="search" aria-label="Filter events" data-form-kind="filter">
        <div class="filter-toolbar__field">
            <label for="event-status">Filter by status</label>
            <select id="event-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($statusLabels[$option] ?? ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-toolbar__actions"><button class="button button--quiet button--compact" type="submit" data-auto-submit-fallback>Apply</button></div>
    </form>
</div>

<section class="dashboard-panel organizer-list-panel mt-5" aria-labelledby="event-list-heading">
    <div class="dashboard-panel__heading">
        <span class="dashboard-panel__icon"><i class="ph ph-list-bullets" aria-hidden="true"></i></span>
        <div><h2 id="event-list-heading">Event list</h2><p>Open an event to review its details and available actions.</p></div>
    </div>

    <?php if ($events === []): ?>
        <div class="empty-state">
            <span class="empty-state__icon"><i class="ph ph-calendar-plus" aria-hidden="true"></i></span>
            <strong>No events found</strong>
            <p><?= $status === null ? 'Create your first draft to begin.' : 'No events match this status filter.' ?></p>
            <?php if ($status === null): ?><a class="button button--primary" href="/organizer/events/create">Create event</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="organizer-table-wrap">
            <table class="organizer-table">
                <caption class="sr-only">Organizer events</caption>
                <thead><tr><th scope="col">Event</th><th scope="col">Schedule</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php $eventStatus = (string) ($event['status'] ?? 'draft'); ?>
                    <tr>
                        <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><small><?= e($event['category_name'] ?? 'Uncategorized') ?><?php if (!empty($event['venue_name'])): ?> · <?= e($event['venue_name']) ?><?php endif; ?></small></td>
                        <td data-label="Schedule"><time datetime="<?= e(str_replace(' ', 'T', (string) ($event['start_date'] ?? ''))) ?>"><?= e(date('M j, Y, g:i A', strtotime((string) ($event['start_date'] ?? 'now')))) ?></time></td>
                        <td data-label="Status"><span class="status-chip status-chip--<?= e($eventStatus) ?>"><?= e($statusLabels[$eventStatus] ?? ucfirst($eventStatus)) ?></span></td>
                        <td class="organizer-table__action" data-label="Action"><a class="text-link" href="/organizer/events/<?= e($event['id']) ?>">View <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
