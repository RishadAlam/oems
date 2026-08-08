<section class="dashboard-page-heading organizer-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-chat-centered-text" aria-hidden="true"></i><span>Organizer workspace</span></p>
        <h1>Event reviews</h1>
        <p>Reply to published feedback for events owned by your organizer account.</p>
    </div>
</section>

<?php if ($reviews === []): ?>
    <section class="empty-state dashboard-panel mt-8" aria-labelledby="organizer-reviews-empty-heading">
        <span class="empty-state__icon"><i class="ph ph-chat-circle" aria-hidden="true"></i></span>
        <strong id="organizer-reviews-empty-heading">No published reviews yet</strong>
        <p>Published participant reviews for your events will appear here.</p>
    </section>
<?php else: ?>
    <div class="mt-8 grid gap-5">
        <?php foreach ($reviews as $review): ?>
            <?php
            $isFailedReply = (string) ($old['reply_review_id'] ?? '') === (string) $review['id'];
            $replyError = $isFailedReply ? field_error($errors, 'reply') : null;
            $replyValue = $isFailedReply ? old_value($old, 'reply') : '';
            $replyErrorId = 'reply-' . (int) $review['id'] . '-error';
            ?>
            <article class="dashboard-panel grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.8fr)]">
                <div>
                    <div class="flex flex-wrap items-center gap-3"><span class="status-chip status-chip--published">Published</span><strong class="text-sm"><?= e($review['event_title'] ?? 'Event') ?></strong></div>
                    <h2 class="mt-5 text-lg font-semibold"><?= e($review['participant_name'] ?? 'Participant') ?></h2>
                    <p class="mt-1 text-sm font-semibold text-[var(--accent)]"><?= e($review['rating'] ?? 0) ?>/5 rating</p>
                    <p class="mt-4 text-sm leading-7 text-[var(--ink-muted)]"><?= e($review['review'] ?? '') ?></p>
                    <?php if (!empty($review['organizer_reply'])): ?>
                        <div class="mt-5 rounded-[12px] bg-[var(--surface)] p-4"><strong class="text-sm">Current reply</strong><p class="mt-2 text-sm leading-6 text-[var(--ink-muted)]"><?= e($review['organizer_reply']) ?></p></div>
                    <?php endif; ?>
                </div>
                <form class="field-group rounded-[14px] bg-[var(--surface)] p-5" action="/organizer/reviews/<?= e($review['id']) ?>/reply" method="post">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <label for="reply-<?= e($review['id']) ?>"><?= empty($review['organizer_reply']) ? 'Write a reply' : 'Update reply' ?></label>
                    <textarea id="reply-<?= e($review['id']) ?>" name="reply" rows="6" minlength="2" maxlength="1000" required aria-describedby="reply-<?= e($review['id']) ?>-help<?= $replyError !== null ? ' ' . e($replyErrorId) : '' ?>"<?= $replyError !== null ? ' aria-invalid="true"' : '' ?>><?= $replyValue ?></textarea>
                    <p id="reply-<?= e($review['id']) ?>-help" class="field-help">Use 2 to 1000 characters. The reply appears publicly with this review.</p>
                    <?php if ($replyError !== null): ?><p id="<?= e($replyErrorId) ?>" class="field-error" role="alert"><?= e($replyError) ?></p><?php endif; ?>
                    <button class="button button--primary mt-4 w-full" type="submit"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span>Save reply</span></button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
