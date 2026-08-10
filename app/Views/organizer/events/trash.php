<header class="dashboard-page-header"><div><p class="dashboard-kicker"><i class="ph ph-archive" aria-hidden="true"></i><span>Event recovery</span></p><h1>Deleted events</h1><p>Restore eligible registration-free events to their retained lifecycle state.</p></div><a class="button button--quiet" href="/organizer/events"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Event list</span></a></header>

<section class="dashboard-panel mt-8" aria-labelledby="organizer-trash-heading">
    <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span><div><h2 id="organizer-trash-heading">Recovery queue</h2><p>Restore never publishes an event or recreates removed media.</p></div></div>
    <?php if ($events === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span><strong>No deleted events</strong><p>Deleted events that belong to this organizer will appear here.</p><a class="button button--primary" href="/organizer/events">Manage events</a></div>
    <?php else: ?>
        <div class="organizer-table-wrap mt-6"><table class="operations-table organizer-table"><caption class="sr-only">Organizer deleted events</caption><thead><tr><th>Event</th><th>Lifecycle</th><th>Deleted</th><th>Registration history</th><th><span class="sr-only">Recovery action</span></th></tr></thead><tbody>
        <?php foreach ($events as $event): ?><tr>
            <td data-label="Event"><strong><?= e($event['title'] ?? 'Untitled event') ?></strong><small><?= e($event['slug'] ?? '') ?></small></td>
            <td data-label="Lifecycle"><span class="status-chip status-chip--<?= e($event['status'] ?? 'draft') ?>"><?= e(ucfirst((string) ($event['status'] ?? 'draft'))) ?></span></td>
            <td data-label="Deleted"><time datetime="<?= e(str_replace(' ', 'T', (string) ($event['deleted_at'] ?? ''))) ?>"><?= e($event['deleted_at'] ?? '') ?></time></td>
            <td data-label="Registration history"><?= e((int) ($event['registration_count'] ?? 0)) ?></td>
            <td class="organizer-table__action" data-label="Action"><?php if (!empty($event['restorable'])): ?><form action="/organizer/events/trash/<?= e($event['id']) ?>/restore" method="post"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="deleted_at" value="<?= e($event['deleted_at']) ?>"><button class="button button--primary button--compact" type="submit"><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i><span>Restore</span></button></form><?php else: ?><small>Recovery unavailable</small><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<aside class="organizer-action-note mt-6"><i class="ph ph-info" aria-hidden="true"></i><p>Registration history is retained. Events with any registration history cannot be restored from the web workspace, and permanent purge remains an operator-only database action.</p></aside>
