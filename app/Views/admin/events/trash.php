<header class="dashboard-page-heading"><div><p class="dashboard-kicker"><i class="ph ph-archive" aria-hidden="true"></i><span>Platform recovery</span></p><h1>Deleted events</h1><p>Review deleted event history and restore only lifecycle-safe, registration-free records.</p></div><a class="button button--quiet" href="/admin/events"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Moderation queue</span></a></header>

<section class="dashboard-panel mt-8" aria-labelledby="admin-trash-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span><div><h2 id="admin-trash-heading">Recovery queue</h2><p>Restore keeps the stored lifecycle and never publishes the event.</p></div></div>
    <?php if ($events === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span><strong>No deleted events</strong><p>Soft-deleted event records will appear here.</p><a class="button button--primary" href="/admin/events">Open moderation</a></div>
    <?php else: ?>
        <div class="organizer-table-wrap mt-6"><table class="operations-table organizer-table"><caption class="sr-only">Administrator deleted events</caption><thead><tr><th>Event</th><th>Organizer</th><th>Lifecycle</th><th>Deleted</th><th>Registration history</th><th><span class="sr-only">Recovery action</span></th></tr></thead><tbody>
        <?php foreach ($events as $event): ?><?php $eventStatus = (string) ($event['status'] ?? ''); ?><tr>
            <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><small><?= e($event['slug'] ?? '') ?></small></td>
            <td data-label="Organizer"><?= e($event['organization_name'] ?? 'Unknown organizer') ?></td>
            <td data-label="Lifecycle"><span class="status-chip status-chip--<?= e(status_modifier($eventStatus, 'event')) ?>"><?= e(oems_status_label($eventStatus)) ?></span></td>
            <td data-label="Deleted"><time datetime="<?= e(str_replace(' ', 'T', (string) ($event['deleted_at'] ?? ''))) ?>"><?= e($event['deleted_at'] ?? '') ?></time></td>
            <td data-label="Registration history"><?= e((int) ($event['registration_count'] ?? 0)) ?></td>
            <td class="organizer-table__action" data-label="Action"><?php if (!empty($event['restorable'])): ?><form action="/admin/events/trash/<?= e($event['id']) ?>/restore" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="deleted_at" value="<?= e($event['deleted_at']) ?>"><button class="button button--primary button--compact" type="submit" data-submit-label="Restoring event…"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i><span data-submit-text>Restore</span></button></form><?php else: ?><small>Recovery unavailable</small><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<aside class="organizer-action-note mt-6"><i class="ph ph-shield-check" aria-hidden="true"></i><p>Registration history is retained. A web restore is unavailable when history exists or the retained lifecycle is outside draft, rejected, or cancelled. Permanent purge and database restore stay operator-owned.</p></aside>
