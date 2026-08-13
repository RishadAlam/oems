<?php $eventId = (int) ($event['event_id'] ?? 0); ?>
<div class="dashboard-page-heading organizer-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-scan" aria-hidden="true"></i><span>Event operations</span></p><h1>Check-in</h1><p>Welcome participants to <?= e($event['event_title'] ?? 'this event') ?>.</p></div>
    <div class="organizer-heading-actions"><a class="button button--quiet" href="/organizer/events/<?= e($eventId) ?>/participants"><i class="ph ph-users-three" aria-hidden="true"></i><span>Participants</span></a><a class="button button--quiet" href="/organizer/events/<?= e($eventId) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Event details</span></a></div>
</div>

<div class="organizer-detail-grid mt-8">
    <section class="dashboard-panel" aria-labelledby="manual-check-in-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-keyboard" aria-hidden="true"></i></span><div><h2 id="manual-check-in-heading">Scan or enter a ticket</h2><p>The ticket number field always works, even when camera access is unavailable.</p></div></div>
        <form class="form-stack mt-6" action="/organizer/events/<?= e($eventId) ?>/check-in" method="post" data-check-in-form data-form-kind="entry">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <?php if (is_array($scanCandidate ?? null)): ?>
                <div class="form-alert" role="status">
                    <i class="ph ph-qr-code" aria-hidden="true"></i>
                    <span><strong>QR code recognized.</strong> Review the ticket number, then confirm the participant check-in.</span>
                </div>
            <?php endif; ?>
            <?php
            $errors = is_string($scanError ?? null) && $scanError !== '' ? ['code' => [$scanError]] : [];
            $fieldTargets = ['code' => 'ticket-code'];
            $fieldLabels = ['code' => 'Ticket number or QR value'];
            $formErrorSummaryId = 'check-in-error-summary';
            require base_path('app/Views/components/form-errors.php');
            ?>
            <p class="form-required-note"><i class="ph ph-asterisk" aria-hidden="true"></i><span>A ticket number or QR value is required.</span></p>
            <label class="form-field" for="ticket-code"><span>Ticket number or QR value</span><input id="ticket-code" name="code" type="text" minlength="9" maxlength="300" autocomplete="off" autocapitalize="characters" spellcheck="false" value="<?= e($scanCandidate['ticket_number'] ?? '') ?>" data-form-label="Ticket number or QR value" required<?= form_control_attributes($errors, 'code', ['ticket-code-help'], 'ticket-code-error') ?>><small id="ticket-code-help">Enter the printed OEMS ticket number, or use the camera to read its QR code.</small><?php if ($error = field_error($errors, 'code')): ?><small id="ticket-code-error" class="field-error" role="alert"><?= e($error) ?></small><?php endif; ?></label>
            <button class="button button--primary" type="submit" data-submit-label="Checking in…"><i class="ph ph-check-circle" aria-hidden="true"></i><span data-submit-text>Check in participant</span></button>
        </form>
    </section>

    <section class="dashboard-panel" aria-labelledby="camera-check-in-heading" data-check-in-camera>
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-camera" aria-hidden="true"></i></span><div><h2 id="camera-check-in-heading">Camera scanner</h2><p>Optional local camera scanning. No external scanner library is loaded.</p></div></div>
        <div class="mt-6 grid gap-4"><video class="w-full rounded-[16px] bg-black" data-check-in-video playsinline muted hidden></video><p class="text-sm leading-6 text-[var(--ink-muted)]" data-check-in-camera-status aria-live="polite">Camera is ready when supported by this browser.</p><div class="flex flex-wrap gap-3"><button class="button button--quiet" type="button" data-check-in-camera-start><i class="ph ph-camera" aria-hidden="true"></i><span>Start camera</span></button><button class="button button--quiet" type="button" data-check-in-camera-stop hidden><i class="ph ph-stop-circle" aria-hidden="true"></i><span>Stop camera</span></button></div></div>
    </section>
</div>
