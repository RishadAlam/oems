<?php $statusLabels = ['valid' => 'Valid', 'used' => 'Checked in', 'cancelled' => 'Cancelled']; ?>
<header class="dashboard-page-header">
    <div><p class="dashboard-kicker"><i class="ph ph-ticket" aria-hidden="true"></i><span>Participant access</span></p><h1>My tickets</h1><p>Open a ticket for its check-in code or PDF.</p></div>
    <a class="button button--quiet" href="/participant/registrations"><i class="ph ph-list-checks" aria-hidden="true"></i><span>Registrations</span></a>
</header>

<?php if ($tickets === []): ?>
    <section class="dashboard-panel mt-8 text-center"><i class="ph ph-ticket text-3xl" aria-hidden="true"></i><h2 class="mt-3 text-xl font-bold">No tickets yet</h2><p class="mt-2 text-sm text-[var(--ink-muted)]">Tickets appear after a registration is confirmed.</p></section>
<?php else: ?>
    <div class="mt-8 grid gap-4">
        <?php foreach ($tickets as $ticket): ?>
            <article class="dashboard-panel flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0"><p class="text-sm font-semibold text-[var(--accent)]"><?= e($ticket['ticket_number']) ?></p><h2 class="mt-1 text-lg font-bold"><?= e($ticket['event_title']) ?></h2><p class="mt-2 text-sm text-[var(--ink-muted)]"><?= e($ticket['event_start_display']) ?></p></div>
                <div class="flex flex-wrap items-center gap-3"><span class="status-chip status-chip--<?= e($ticket['ticket_status']) ?>"><?= e($statusLabels[$ticket['ticket_status']] ?? ucfirst($ticket['ticket_status'])) ?></span><a class="button button--primary button--compact" href="/participant/tickets/<?= e($ticket['id']) ?>">Open ticket</a></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
