<header class="dashboard-page-header">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-hourglass-medium" aria-hidden="true"></i><span>Seat queue</span></p>
        <h1>My waitlist</h1>
        <p>Track your place in line. When a seat opens, the oldest eligible entry is promoted automatically.</p>
    </div>
    <a class="button button--quiet" href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
</header>

<?php if ($entries === []): ?>
    <section class="dashboard-empty mt-8" aria-labelledby="waitlist-empty-heading">
        <i class="ph ph-hourglass-simple" aria-hidden="true"></i>
        <h2 id="waitlist-empty-heading">No active waitlist entries</h2>
        <p>Sold-out events with waitlisting enabled will offer a Join waitlist action.</p>
        <a class="button button--primary" href="/events">Find events</a>
    </section>
<?php else: ?>
    <div class="mt-8 grid gap-5 lg:grid-cols-2">
        <?php foreach ($entries as $entry):
            $reasonError = (int) ($old['registration_id'] ?? 0) === (int) $entry['id']
                ? (field_error($errors, 'reason') ?? field_error($errors, 'waitlist'))
                : null;
            $reasonId = 'waitlist-reason-' . (int) $entry['id'];
        ?>
            <article class="dashboard-panel" aria-labelledby="waitlist-event-<?= e($entry['id']) ?>">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="dashboard-kicker"><i class="ph ph-users-three" aria-hidden="true"></i><span>Position <?= e($entry['position'] ?? 'Not available') ?></span></p>
                        <h2 id="waitlist-event-<?= e($entry['id']) ?>" class="mt-2 text-xl font-bold"><?= e($entry['event_title']) ?></h2>
                    </div>
                    <span class="status-chip status-chip--pending">Waitlisted</span>
                </div>
                <dl class="status-list mt-5">
                    <div><dt>Starts</dt><dd><?= e($entry['start_display']) ?></dd></div>
                    <div><dt>Current price</dt><dd><?= e($entry['amount_display']) ?> <?= e($entry['currency']) ?></dd></div>
                </dl>
                <p class="mt-4 text-sm text-[var(--ink-muted)]">Joining does not reserve a seat or create a payment. If promoted, you will receive an in-app and email notice.</p>
                <form class="form-stack mt-5" action="/participant/waitlist/<?= e($entry['id']) ?>/leave" method="post" data-form-kind="entry">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <?php if ($reasonError !== null):
                        $allWaitlistErrors = $errors;
                        $errors = ['reason' => [$reasonError]];
                        $fieldTargets = ['reason' => $reasonId];
                        $fieldLabels = ['reason' => 'Reason for leaving'];
                        $formErrorSummaryId = 'waitlist-error-summary-' . (int) $entry['id'];
                        require base_path('app/Views/components/form-errors.php');
                        $errors = $allWaitlistErrors;
                    endif; ?>
                    <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>A reason is required to leave the waitlist.</span></p>
                    <div class="field-group">
                        <label for="<?= e($reasonId) ?>">Reason for leaving</label>
                        <textarea id="<?= e($reasonId) ?>" name="reason" rows="2" maxlength="500" required aria-describedby="waitlist-reason-help-<?= e($entry['id']) ?><?= $reasonError !== null ? ' waitlist-reason-error-' . e($entry['id']) : '' ?>"<?= $reasonError !== null ? ' aria-invalid="true"' : '' ?> data-form-label="Reason for leaving"><?= (int) ($old['registration_id'] ?? 0) === (int) $entry['id'] ? e($old['reason'] ?? '') : '' ?></textarea>
                        <p id="waitlist-reason-help-<?= e($entry['id']) ?>" class="field-help">Do not include payment or other sensitive information.</p>
                        <?php if ($reasonError !== null): ?><p id="waitlist-reason-error-<?= e($entry['id']) ?>" class="field-error" role="alert"><?= e($reasonError) ?></p><?php endif; ?>
                    </div>
                    <button class="button button--danger w-full sm:w-auto" type="submit" data-submit-label="Leaving waitlist…"><i class="ph ph-sign-out" aria-hidden="true"></i><span data-submit-text>Leave waitlist</span></button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
