<?php $statusLabels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded']; ?>
<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-list-checks" aria-hidden="true"></i><span>Participant history</span></p><h1>My registrations</h1><p>Track payment, confirmation, ticket, and cancellation status.</p></div>
    <a class="button button--primary" href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
</header>

<?php if ($registrations === []): ?>
    <section class="dashboard-panel mt-8 text-center"><i class="ph ph-calendar-blank text-3xl" aria-hidden="true"></i><h2 class="mt-3 text-xl font-bold">No registrations yet</h2><p class="mt-2 text-sm text-[var(--ink-muted)]">Choose a published event when you are ready.</p></section>
<?php else: ?>
    <div class="mt-8 grid gap-4">
        <?php foreach ($registrations as $registration): ?>
            <article class="dashboard-panel flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0"><p class="text-sm text-[var(--ink-muted)]"><?= e($registration['registration_number']) ?></p><h2 class="mt-1 text-lg font-bold"><?= e($registration['event_title']) ?></h2><p class="mt-2 text-sm text-[var(--ink-muted)]"><?= e($registration['event_start_display']) ?> · <?= e($registration['amount_display']) ?></p></div>
                <div class="flex flex-wrap items-center gap-3"><span class="status-chip status-chip--<?= e($registration['registration_status']) ?>"><?= e($statusLabels[$registration['registration_status']] ?? ucfirst($registration['registration_status'])) ?></span><a class="button button--quiet button--compact" href="/participant/registrations/<?= e($registration['id']) ?>">View registration</a></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
