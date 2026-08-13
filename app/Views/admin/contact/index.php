<header class="dashboard-page-heading">
    <div><p class="dashboard-kicker"><i class="ph ph-chats" aria-hidden="true"></i><span>Support operations</span></p><h1>Contact inbox</h1><p>Review public messages and keep every state change explicit.</p></div>
</header>

<section class="dashboard-panel mt-8">
    <form class="flex flex-wrap gap-3" method="get" action="/admin/contact" data-form-kind="filter">
        <label class="sr-only" for="contact-search">Search contact messages</label>
        <input id="contact-search" name="search" type="search" maxlength="100" value="<?= e($filters['search']) ?>" placeholder="Search name, email, subject">
        <label class="sr-only" for="contact-status">Status</label>
        <select id="contact-status" name="status"><option value="">All statuses</option><?php foreach (['new', 'read', 'replied', 'archived'] as $state): ?><option value="<?= e($state) ?>"<?= $filters['status'] === $state ? ' selected' : '' ?>><?= e(ucfirst($state)) ?></option><?php endforeach; ?></select>
        <button class="button button--quiet" type="submit">Filter</button>
        <?php if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?><a class="button button--quiet" href="/admin/contact">Reset</a><?php endif; ?>
    </form>

    <?php if ($messages === []): ?>
        <div class="empty-state mt-6"><i class="ph ph-inbox" aria-hidden="true"></i><h2>No messages found</h2><p>Adjust the filters or wait for a new contact request.</p></div>
    <?php else: ?>
        <div class="table-shell mt-6"><table class="operations-table"><thead><tr><th>Sender</th><th>Subject</th><th>Status</th><th>Received</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($messages as $message): ?><?php $messageStatus = (string) ($message['status'] ?? ''); ?><tr><td data-label="Sender"><strong><?= e($message['name']) ?></strong><br><span class="text-sm text-[var(--ink-muted)]"><?= e($message['email']) ?></span></td><td data-label="Subject"><?= e($message['subject']) ?></td><td data-label="Status"><span class="status-badge status-badge--<?= e(status_modifier($messageStatus, 'contact')) ?>"><?= e(oems_status_label($messageStatus)) ?></span></td><td data-label="Received"><?= e($message['created_at']) ?></td><td data-label="Action"><a class="button button--compact button--quiet" href="/admin/contact/<?= e($message['id']) ?>">Review</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
