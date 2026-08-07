<?php
$summary = is_array($summary ?? null) ? $summary : [];
$events = is_array($events ?? null) ? $events : [];
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

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>Organizer workspace</span></p>
        <h1>Build the room people remember.</h1>
        <p>Track your event lifecycle and open the next action from one workspace.</p>
    </div>
    <a class="button button--primary" href="/organizer/events/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create event</span></a>
</div>

<div class="dashboard-metric-grid mt-8">
    <article aria-label="Total events: <?= e((int) ($summary['total'] ?? 0)) ?>"><div class="metric-card__top"><span>Total events</span><i class="ph ph-calendar-dots" aria-hidden="true"></i></div><strong><?= e((int) ($summary['total'] ?? 0)) ?></strong><small>Active event records</small></article>
    <article aria-label="Pending review: <?= e((int) ($summary['pending'] ?? 0)) ?>"><div class="metric-card__top"><span>Pending review</span><i class="ph ph-hourglass-medium" aria-hidden="true"></i></div><strong><?= e((int) ($summary['pending'] ?? 0)) ?></strong><small>Waiting for administrator review</small></article>
    <article aria-label="Published events: <?= e((int) ($summary['published'] ?? 0)) ?>"><div class="metric-card__top"><span>Published events</span><i class="ph ph-calendar-check" aria-hidden="true"></i></div><strong><?= e((int) ($summary['published'] ?? 0)) ?></strong><small>Visible in public discovery</small></article>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
    <section class="dashboard-panel organizer-list-panel" aria-labelledby="recent-events-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-calendar-blank" aria-hidden="true"></i></span><div><h2 id="recent-events-heading">Recent events</h2><p>Your five most recent event records and their next actions.</p></div></div>
        <?php if ($events === []): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><i class="ph ph-note-pencil" aria-hidden="true"></i></span>
                <strong>Your first event starts with a clear idea</strong>
                <p>Create a draft, add its venue and media, then submit it for review.</p>
                <a class="button button--primary" href="/organizer/events/create">Create event</a>
            </div>
        <?php else: ?>
            <div class="organizer-table-wrap mt-6">
                <table class="organizer-table">
                    <caption class="sr-only">Recent organizer events and next actions</caption>
                    <thead><tr><th scope="col">Event</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Next action</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $eventId = (int) ($event['id'] ?? 0);
                        $status = (string) ($event['status'] ?? 'draft');
                        $editNext = in_array($status, ['draft', 'rejected'], true);
                        $publicNext = $status === 'published' && !empty($event['slug']);
                        $nextUrl = $editNext
                            ? '/organizer/events/' . $eventId . '/edit'
                            : ($publicNext
                                ? '/events/' . rawurlencode((string) $event['slug'])
                                : '/organizer/events/' . $eventId);
                        $nextLabel = $editNext ? 'Continue editing' : ($publicNext ? 'View public page' : 'View details');
                        ?>
                        <tr>
                            <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><?php if (!empty($event['start_date'])): ?><small><time datetime="<?= e(str_replace(' ', 'T', (string) $event['start_date'])) ?>"><?= e(date('M j, Y', strtotime((string) $event['start_date']))) ?></time></small><?php endif; ?></td>
                            <td data-label="Status"><span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span></td>
                            <td class="organizer-table__action"><a class="text-link" href="<?= e($nextUrl) ?>"><?= e($nextLabel) ?> <i class="ph ph-arrow-right" aria-hidden="true"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="mt-5"><a class="text-link" href="/organizer/events">Manage all events <i class="ph ph-arrow-right" aria-hidden="true"></i></a></p>
    </section>
    <section class="dashboard-panel">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-road-horizon" aria-hidden="true"></i></span><div><h2>Week 2 scope</h2><p>Event management is ready</p></div></div>
        <dl class="status-list">
            <div><dt><i class="ph ph-buildings" aria-hidden="true"></i>Venues</dt><dd><a class="text-link" href="/organizer/venues">Manage</a></dd></div>
            <div><dt><i class="ph ph-image" aria-hidden="true"></i>Event media</dt><dd>Available</dd></div>
            <div><dt><i class="ph ph-ticket" aria-hidden="true"></i>Registration</dt><dd class="status-list__pending">Week 3</dd></div>
        </dl>
        <p class="mt-5 text-sm leading-6 text-[var(--ink-muted)]">Registration and ticket revenue begin in Week 3. No participant or revenue totals are inferred before that workflow exists.</p>
    </section>
</div>
