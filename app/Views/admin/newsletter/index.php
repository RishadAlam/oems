<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-megaphone" aria-hidden="true"></i><span>Email operations</span></p><h1>Newsletter campaigns</h1><p>Campaigns are sent only to confirmed subscribers through the durable outbox.</p></div>
    <a class="button button--primary" href="/admin/newsletter/create"><i class="ph ph-plus" aria-hidden="true"></i><span>Create campaign</span></a>
</header>

<section class="dashboard-panel mt-8" aria-labelledby="campaigns-heading">
    <h2 id="campaigns-heading" class="text-xl font-bold">Campaigns</h2>
    <?php if ($campaigns === []): ?>
        <div class="empty-state mt-6"><i class="ph ph-envelope-simple" aria-hidden="true"></i><h3>No campaigns yet</h3><p>Create a draft when an update is ready.</p></div>
    <?php else: ?>
        <div class="organizer-table-wrap mt-6"><table class="operations-table organizer-table"><caption class="sr-only">Newsletter campaigns</caption><thead><tr><th>Campaign</th><th>Status</th><th>Recipients</th><th>Created</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($campaigns as $campaign): ?><?php $campaignStatus = (string) ($campaign['status'] ?? ''); ?><tr><td data-label="Campaign"><div class="organizer-table__primary min-w-0"><strong class="break-words"><?= e($campaign['subject']) ?></strong><p class="mt-2 max-w-xl whitespace-pre-wrap break-words text-sm text-[var(--ink-muted)]"><?= e($campaign['message']) ?></p></div></td><td data-label="Status"><span class="status-badge status-badge--<?= e(status_modifier($campaignStatus, 'newsletter_campaign')) ?>"><?= e(oems_status_label($campaignStatus)) ?></span></td><td data-label="Recipients"><?= e((int) $campaign['queued_count']) ?> of <?= e((int) $campaign['recipient_count']) ?></td><td data-label="Created"><?= e($campaign['created_at']) ?></td><td class="organizer-table__action" data-label="Action"><?php if ($campaignStatus === 'draft'): ?><form method="post" action="/admin/newsletter/<?= e($campaign['id']) ?>/queue" data-form-kind="action" data-confirm="Queue this campaign for all currently confirmed subscribers?"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--compact button--primary" type="submit" data-submit-label="Queueing campaign…"><span data-submit-text>Queue campaign</span></button></form><?php else: ?><span class="text-sm text-[var(--ink-muted)]">No action needed</span><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
