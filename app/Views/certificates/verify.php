<section class="page-shell py-14 lg:py-20">
    <div class="mx-auto max-w-3xl">
        <?php if (is_array($verification)): ?>
            <div class="rounded-[var(--radius-card)] border border-[var(--line)] bg-[var(--surface-raised)] p-7 text-center shadow-[var(--shadow-card)] sm:p-10">
                <span class="mx-auto inline-flex size-16 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"><i class="ph ph-seal-check" aria-hidden="true"></i></span>
                <p class="eyebrow mt-6 justify-center"><span>Authentic record</span></p>
                <h1 class="mt-4 text-4xl font-semibold tracking-[-.04em]">Certificate verified</h1>
                <p class="mx-auto mt-4 max-w-xl text-[var(--ink-muted)]">OEMS confirms this attendance certificate as valid.</p>
                <dl class="status-list mt-8 text-left">
                    <div><dt>Participant</dt><dd><?= e($verification['participant_name']) ?></dd></div>
                    <div><dt>Event</dt><dd><?= e($verification['event_title']) ?></dd></div>
                    <div><dt>Completed</dt><dd><?= e($verification['completion_display']) ?></dd></div>
                    <div><dt>Issued</dt><dd><?= e($verification['issued_display']) ?></dd></div>
                </dl>
                <a class="button button--primary mt-8" href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
            </div>
        <?php else: ?>
            <div class="empty-state" role="status" aria-labelledby="certificate-unavailable-heading"><i class="ph ph-seal-warning" aria-hidden="true"></i><h1 id="certificate-unavailable-heading">Certificate unavailable</h1><p>This verification link is invalid, expired, or no longer available. No certificate or participant details were disclosed.</p><a class="button button--primary" href="/">Return home</a></div>
        <?php endif; ?>
    </div>
</section>
