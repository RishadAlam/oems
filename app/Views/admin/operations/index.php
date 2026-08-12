<?php
$ready = ($readiness['status'] ?? 'unavailable') === 'ok';
$checks = is_array($readiness['checks'] ?? null) ? $readiness['checks'] : [];
$nextEnabled = !$maintenanceEnabled;
$phrase = $nextEnabled ? 'ENABLE MAINTENANCE' : 'DISABLE MAINTENANCE';
?>
<header class="dashboard-page-header">
    <div><p class="dashboard-kicker"><i class="ph ph-pulse" aria-hidden="true"></i><span>Production controls</span></p><h1>Operations</h1><p>Check application readiness and control planned maintenance without exposing infrastructure details.</p></div>
</header>

<?php if (!empty($errors['operations'])): ?><div class="alert alert--error mt-6" role="alert"><?= e($errors['operations'][0]) ?></div><?php endif; ?>

<div class="mt-7 grid gap-6 xl:grid-cols-2">
    <section class="panel" aria-labelledby="readiness-title">
        <div class="flex items-start justify-between gap-4"><div><p class="dashboard-kicker">Readiness</p><h2 id="readiness-title" class="mt-1 text-xl font-bold">Application checks</h2></div><span class="status-badge <?= $ready ? 'status-badge--success' : 'status-badge--danger' ?>"><?= $ready ? 'Ready' : 'Unavailable' ?></span></div>
        <dl class="mt-6 grid gap-3">
            <?php foreach (['database' => 'Database connection', 'schema' => 'Required schema', 'storage' => 'Private writable storage'] as $key => $label): ?>
                <div class="flex min-h-11 items-center justify-between gap-4 border-b border-[var(--line)] py-2 last:border-0"><dt><?= e($label) ?></dt><dd class="font-semibold <?= !empty($checks[$key]) ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' ?>"><?= !empty($checks[$key]) ? 'Passing' : 'Needs attention' ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <p class="mt-5 text-sm text-[var(--ink-muted)]">The public readiness endpoint returns component state only. Paths, versions, credentials, and exception details are never included.</p>
    </section>

    <section class="panel" aria-labelledby="maintenance-control-title">
        <div class="flex items-start justify-between gap-4"><div><p class="dashboard-kicker">Maintenance</p><h2 id="maintenance-control-title" class="mt-1 text-xl font-bold">Traffic control</h2></div><span class="status-badge <?= $maintenanceEnabled ? 'status-badge--warning' : 'status-badge--neutral' ?>"><?= $maintenanceEnabled ? 'Active' : 'Inactive' ?></span></div>
        <p class="mt-4 text-[var(--ink-muted)]"><?= $maintenanceEnabled ? 'Public and non-administrator application routes return an accessible 503 response.' : 'All application routes are available according to their normal access rules.' ?></p>
        <form class="mt-6" action="/admin/operations/maintenance" method="post" data-form-kind="entry">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="enabled" value="<?= $nextEnabled ? '1' : '0' ?>">
            <?php $fieldTargets = ['confirmation' => 'maintenance-confirmation', 'operations' => 'maintenance-confirmation']; $fieldLabels = ['confirmation' => 'Maintenance confirmation', 'operations' => 'Maintenance control']; $formErrorSummaryId = 'maintenance-error-summary'; require base_path('app/Views/components/form-errors.php'); ?>
            <label class="form-label" for="maintenance-confirmation">Type <strong><?= e($phrase) ?></strong> to continue</label>
            <input class="form-input" id="maintenance-confirmation" name="confirmation" type="text" autocomplete="off" required aria-describedby="maintenance-help<?= !empty($errors['confirmation']) ? ' maintenance-confirmation-error' : '' ?>"<?= !empty($errors['confirmation']) ? ' aria-invalid="true"' : '' ?>>
            <p id="maintenance-help" class="form-help">Health endpoints, the login page, static assets, and signed-in super administrators remain available.</p>
            <?php if (!empty($errors['confirmation'])): ?><p id="maintenance-confirmation-error" class="form-error" role="alert"><?= e($errors['confirmation'][0]) ?></p><?php endif; ?>
            <button class="button <?= $nextEnabled ? 'button--danger' : 'button--primary' ?> mt-5" type="submit" data-submit-label="Updating maintenance…"><i class="ph <?= $nextEnabled ? 'ph-warning' : 'ph-check-circle' ?>" aria-hidden="true"></i><span data-submit-text><?= $nextEnabled ? 'Enable maintenance' : 'Disable maintenance' ?></span></button>
        </form>
    </section>
</div>

<section class="panel mt-6" aria-labelledby="operator-runbook-title">
    <p class="dashboard-kicker">Operator runbook</p><h2 id="operator-runbook-title" class="mt-1 text-xl font-bold">Safe release sequence</h2>
    <ol class="mt-5 grid gap-3 text-[var(--ink-muted)] sm:grid-cols-2 xl:grid-cols-4">
        <li><strong class="block text-[var(--ink)]">1. Back up</strong>Create and verify a private database archive.</li>
        <li><strong class="block text-[var(--ink)]">2. Maintain</strong>Enable maintenance before schema or artifact changes.</li>
        <li><strong class="block text-[var(--ink)]">3. Verify</strong>Run migrations, workers, and the readiness probe.</li>
        <li><strong class="block text-[var(--ink)]">4. Reopen</strong>Disable maintenance only after live acceptance passes.</li>
    </ol>
</section>
