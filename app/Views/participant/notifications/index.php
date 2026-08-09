<?php
$items = is_array($notifications['items'] ?? null) ? $notifications['items'] : [];
$pagination = is_array($notifications['pagination'] ?? null) ? $notifications['pagination'] : [];
?>

<div class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-bell" aria-hidden="true"></i><span>Participant workspace</span></p>
        <h1>Notifications</h1>
        <p><?= e((int) ($unreadCount ?? 0)) ?> unread updates about your registrations, tickets, and reviews.</p>
    </div>
    <?php if ((int) ($unreadCount ?? 0) > 0): ?>
        <form action="/participant/notifications/read-all" method="post"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--quiet" type="submit">Mark all read</button></form>
    <?php endif; ?>
</div>

<section class="dashboard-panel mt-8">
    <?php if ($items === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><i class="ph ph-bell-slash" aria-hidden="true"></i></span><strong>You are up to date</strong><p>New registration, payment, ticket, and review updates will appear here.</p></div>
    <?php else: ?>
        <div class="grid gap-3">
            <?php foreach ($items as $notification): ?>
                <article class="rounded-[18px] border border-[var(--line)] p-5<?= empty($notification['read_at']) ? ' bg-[var(--surface-soft)]' : '' ?>">
                    <div class="flex items-start justify-between gap-4"><div><h2 class="text-base font-bold"><?= e($notification['title'] ?? '') ?></h2><p class="mt-1 text-sm text-[var(--ink-muted)]"><?= e($notification['message'] ?? '') ?></p></div><small class="shrink-0 text-xs text-[var(--ink-muted)]"><?= e($notification['created_at'] ?? '') ?></small></div>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <?php if (!empty($notification['action_url'])): ?><a class="button button--quiet button--compact" href="<?= e($notification['action_url']) ?>">View update</a><?php endif; ?>
                        <?php if (empty($notification['read_at'])): ?><form action="/participant/notifications/<?= e((int) $notification['id']) ?>/read" method="post"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--quiet button--compact" type="submit">Mark read</button></form><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="mt-6 flex items-center justify-between gap-3 text-sm text-[var(--ink-muted)]">
            <p>Page <?= e((int) ($pagination['page'] ?? 1)) ?> of <?= e((int) ($pagination['last_page'] ?? 1)) ?></p>
            <div class="flex gap-2">
                <?php if ((int) ($pagination['page'] ?? 1) > 1): ?><a class="button button--quiet button--compact" href="/participant/notifications?page=<?= e((int) $pagination['page'] - 1) ?>">Previous</a><?php endif; ?>
                <?php if ((int) ($pagination['page'] ?? 1) < (int) ($pagination['last_page'] ?? 1)): ?><a class="button button--quiet button--compact" href="/participant/notifications?page=<?= e((int) $pagination['page'] + 1) ?>">Next</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
