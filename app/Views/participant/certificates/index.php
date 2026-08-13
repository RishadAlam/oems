<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-seal-check" aria-hidden="true"></i><span>Verified attendance</span></p><h1>My certificates</h1><p>Download certificates earned after confirmed attendance at completed events.</p></div>
    <a class="button button--quiet" href="/participant/registrations"><i class="ph ph-list-checks" aria-hidden="true"></i><span>Registrations</span></a>
</header>

<?php if ($certificates === []): ?>
    <section class="dashboard-panel mt-8 text-center" aria-labelledby="certificate-empty-heading"><i class="ph ph-certificate text-3xl" aria-hidden="true"></i><h2 id="certificate-empty-heading" class="mt-3 text-xl font-bold">No certificates yet</h2><p class="mt-2 text-sm text-[var(--ink-muted)]">After you check in and the organizer completes the event, request your certificate from the registration page.</p><a class="button button--primary mt-5" href="/participant/registrations">Open registrations</a></section>
<?php else: ?>
    <div class="mt-8 grid gap-4">
        <?php foreach ($certificates as $certificate): ?>
            <?php $certificateStatus = (string) ($certificate['status'] ?? ''); ?>
            <article class="dashboard-panel flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0"><p class="text-sm font-semibold text-[var(--accent)]"><?= e($certificate['certificate_number']) ?></p><h2 class="mt-1 text-lg font-bold"><?= e($certificate['event_title']) ?></h2><p class="mt-2 text-sm text-[var(--ink-muted)]">Completed <?= e($certificate['completion_display']) ?> · Issued <?= e($certificate['issued_display']) ?></p></div>
                <div class="flex flex-wrap items-center gap-3"><span class="status-chip status-chip--<?= e(status_modifier($certificateStatus, 'certificate')) ?>"><?= e(oems_status_label($certificateStatus, ['valid' => 'Valid', 'revoked' => 'Revoked'])) ?></span><?php if ($certificateStatus === 'valid'): ?><a class="button button--primary button--compact" href="/participant/certificates/<?= e($certificate['id']) ?>/pdf"><i class="ph ph-download-simple" aria-hidden="true"></i><span>Download PDF</span></a><?php endif; ?></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
