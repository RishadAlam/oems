<?php
$statusLabels = ['pending' => 'Pending moderation', 'published' => 'Published', 'hidden' => 'Hidden'];
$statusCopy = [
    'pending' => 'An administrator will review this before it appears publicly.',
    'published' => 'This review is visible on the public event page.',
    'hidden' => 'This review is not visible publicly. You can update and resubmit it.',
];
?>

<section class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-star" aria-hidden="true"></i><span>Participant workspace</span></p>
        <h1>My reviews</h1>
        <p>See moderation status and update feedback for eligible past events.</p>
    </div>
    <a class="button button--quiet" href="/participant/registrations"><i class="ph ph-list-checks" aria-hidden="true"></i><span>Registrations</span></a>
</section>

<?php if ($eligibleEvents !== []): ?>
    <section class="dashboard-panel mt-8" aria-labelledby="eligible-review-events-heading">
        <div class="dashboard-panel__heading"><span class="dashboard-panel__icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></span><div><h2 id="eligible-review-events-heading">Events ready for review</h2><p>Confirmed events appear here after they end.</p></div></div>
        <div class="action-list">
            <?php foreach ($eligibleEvents as $eligibleEvent): ?><a href="/participant/events/<?= e($eligibleEvent['id']) ?>/review"><i class="ph ph-star" aria-hidden="true"></i><span><strong><?= e($eligibleEvent['title']) ?></strong><small>Write a participant review</small></span><i class="ph ph-arrow-right" aria-hidden="true"></i></a><?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($reviews === [] && $eligibleEvents === []): ?>
    <section class="empty-state dashboard-panel mt-8" aria-labelledby="reviews-empty-heading">
        <span class="empty-state__icon"><i class="ph ph-chat-centered-text" aria-hidden="true"></i></span>
        <strong id="reviews-empty-heading">No reviews yet</strong>
        <p>After a confirmed event ends, open its review form to share your experience.</p>
        <a class="button button--primary" href="/participant/registrations">View registrations</a>
    </section>
<?php elseif ($reviews !== []): ?>
    <div class="mt-8 grid gap-5 lg:grid-cols-2">
        <?php foreach ($reviews as $review): ?>
            <?php $status = (string) ($review['status'] ?? 'pending'); ?>
            <article class="dashboard-panel grid gap-5">
                <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><p class="event-card__category">Event review</p><h2 class="mt-2 text-lg font-semibold"><?= e($review['event_title'] ?? 'Event') ?></h2></div>
                    <span class="status-chip status-chip--<?= e($status) ?>"><?= e($statusLabels[$status] ?? ucfirst($status)) ?></span>
                </header>
                <p class="text-sm leading-6 text-[var(--ink-muted)]"><?= e($review['review'] ?? '') ?></p>
                <div class="flex items-center gap-2" aria-label="<?= e($review['rating'] ?? 0) ?> out of 5 stars"><i class="ph ph-star-fill text-[var(--warning)]" aria-hidden="true"></i><strong><?= e($review['rating'] ?? 0) ?>/5</strong></div>
                <?php if (!empty($review['organizer_reply'])): ?><div class="rounded-[12px] bg-[var(--surface)] p-4"><strong class="text-sm">Organizer reply</strong><p class="mt-2 text-sm leading-6 text-[var(--ink-muted)]"><?= e($review['organizer_reply']) ?></p></div><?php endif; ?>
                <footer class="flex flex-col gap-3 border-t border-[var(--line)] pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-[var(--ink-muted)]"><?= e($statusCopy[$status] ?? 'Review status unavailable.') ?></p>
                    <a class="button button--quiet button--compact" href="/participant/events/<?= e($review['event_id']) ?>/review"><i class="ph ph-pencil-simple" aria-hidden="true"></i><span>Update</span></a>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
