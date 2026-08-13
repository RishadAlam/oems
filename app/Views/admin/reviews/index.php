<?php $statusLabels = ['pending' => 'Pending', 'published' => 'Published', 'hidden' => 'Hidden']; ?>

<section class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-shield-check" aria-hidden="true"></i><span>Administrator review</span></p>
        <h1>Review moderation</h1>
        <p>Pending reviews appear first and oldest so participant feedback is handled fairly.</p>
    </div>
</section>

<div class="filter-toolbar mt-8">
    <p class="result-summary filter-toolbar__summary" role="status" aria-live="polite" aria-atomic="true">
        <strong class="result-summary__count" aria-hidden="true"><?= e(count($reviews)) ?></strong>
        <span class="result-summary__copy" aria-hidden="true">
            <span class="result-summary__context">In queue</span>
            <span class="result-summary__subject">Reviews</span>
        </span>
        <span class="sr-only"><?= e(count($reviews)) ?> <?= count($reviews) === 1 ? 'review' : 'reviews' ?> in this queue</span>
    </p>
    <form class="filter-toolbar__form filter-toolbar__form--compact" action="/admin/reviews" method="get" role="search" aria-label="Filter reviews" data-form-kind="filter">
        <div class="filter-toolbar__field">
            <label for="review-status">Status</label>
            <select id="review-status" name="status">
                <option value="">All statuses</option>
                <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending</option>
                <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
                <option value="hidden"<?= $status === 'hidden' ? ' selected' : '' ?>>Hidden</option>
            </select>
        </div>
        <div class="filter-toolbar__actions"><button class="button button--quiet button--compact" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i><span>Filter</span></button></div>
    </form>
</div>

<?php if ($reviews === []): ?>
    <section class="empty-state dashboard-panel mt-6" aria-labelledby="admin-reviews-empty-heading">
        <span class="empty-state__icon"><i class="ph ph-check-circle" aria-hidden="true"></i></span>
        <strong id="admin-reviews-empty-heading">No reviews in this queue</strong>
        <p>Choose another status or return later for new participant submissions.</p>
    </section>
<?php else: ?>
    <div class="queue-list">
        <?php foreach ($reviews as $review): ?>
            <?php $reviewStatus = (string) ($review['status'] ?? 'pending'); ?>
            <article class="queue-item dashboard-panel grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-3"><span class="status-chip status-chip--<?= e($reviewStatus) ?>"><?= e($statusLabels[$reviewStatus] ?? ucfirst($reviewStatus)) ?></span><strong class="text-sm"><?= e($review['event_title'] ?? 'Event') ?></strong></div>
                    <h2 class="mt-4 text-lg font-semibold"><?= e($review['participant_name'] ?? 'Participant') ?> rated <?= e($review['rating'] ?? 0) ?>/5</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-7 text-[var(--ink-muted)]"><?= e($review['review'] ?? '') ?></p>
                </div>
                <?php if ($reviewStatus === 'pending'): ?>
                    <div class="flex min-w-44 flex-col gap-3">
                        <form action="/admin/reviews/<?= e($review['id']) ?>/publish" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--primary w-full" type="submit" data-submit-label="Publishing review…"><i class="ph ph-check-circle" aria-hidden="true"></i><span data-submit-text>Publish</span></button></form>
                        <form action="/admin/reviews/<?= e($review['id']) ?>/hide" method="post" data-form-kind="action"><input type="hidden" name="_token" value="<?= e($csrfToken) ?>"><button class="button button--danger w-full" type="submit" data-submit-label="Hiding review…"><i class="ph ph-eye-slash" aria-hidden="true"></i><span data-submit-text>Hide</span></button></form>
                    </div>
                <?php else: ?>
                    <p class="max-w-48 text-xs leading-5 text-[var(--ink-muted)]"><?= $reviewStatus === 'published' ? 'This review is visible publicly.' : 'This review is not visible publicly.' ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
