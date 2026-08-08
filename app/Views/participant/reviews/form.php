<?php
$ratingError = field_error($errors, 'rating');
$reviewError = field_error($errors, 'review');
$selectedRating = old_value($old, 'rating', (string) ($review['rating'] ?? ''));
$comment = old_value($old, 'review', (string) ($review['review'] ?? ''));
?>

<section class="dashboard-page-heading">
    <div>
        <p class="dashboard-kicker"><i class="ph ph-star" aria-hidden="true"></i><span>Participant review</span></p>
        <h1><?= $review === null ? 'Review event' : 'Update review' ?></h1>
        <p>Your review is checked by an administrator before it appears publicly.</p>
    </div>
    <a class="button button--quiet" href="/participant/reviews"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>My reviews</span></a>
</section>

<form class="dashboard-panel mt-8 grid max-w-3xl gap-7" action="/participant/events/<?= e($event['id']) ?>/review" method="post">
    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
    <div class="dashboard-panel__heading">
        <span class="dashboard-panel__icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></span>
        <div><h2><?= e($event['title'] ?? 'Completed event') ?></h2><p>Share specific, respectful feedback about your experience.</p></div>
    </div>

    <fieldset class="field-group" aria-describedby="rating-help<?= $ratingError !== null ? ' rating-error' : '' ?>"<?= $ratingError !== null ? ' aria-invalid="true"' : '' ?>>
        <legend>Rating</legend>
        <div class="mt-3 grid grid-cols-5 gap-2" role="radiogroup" aria-label="Event rating">
            <?php foreach (range(1, 5) as $rating): ?>
                <label class="grid min-h-11 cursor-pointer place-items-center rounded-[12px] border border-[var(--line)] bg-[var(--surface)] text-sm font-bold has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)] has-[:checked]:text-[var(--accent)]">
                    <input class="sr-only" type="radio" name="rating" value="<?= $rating ?>"<?= $selectedRating === (string) $rating ? ' checked' : '' ?> required>
                    <span><?= $rating ?><span class="sr-only"> out of 5</span></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p id="rating-help" class="field-help">Choose one star for poor through five stars for excellent.</p>
        <?php if ($ratingError !== null): ?><p id="rating-error" class="field-error" role="alert"><?= e($ratingError) ?></p><?php endif; ?>
    </fieldset>

    <div class="field-group">
        <label for="review">Your review</label>
        <textarea id="review" name="review" rows="8" minlength="10" maxlength="2000" required aria-describedby="review-help<?= $reviewError !== null ? ' review-error' : '' ?>"<?= $reviewError !== null ? ' aria-invalid="true"' : '' ?>><?= $comment ?></textarea>
        <p id="review-help" class="field-help">Use 10 to 2000 characters. Your name and review may appear publicly after moderation.</p>
        <?php if ($reviewError !== null): ?><p id="review-error" class="field-error" role="alert"><?= e($reviewError) ?></p><?php endif; ?>
    </div>

    <div class="flex flex-col gap-3 border-t border-[var(--line)] pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs leading-5 text-[var(--ink-muted)]"><?= $review === null ? 'New reviews start as pending.' : 'Updating returns this review to pending moderation.' ?></p>
        <button class="button button--primary w-full sm:w-auto" type="submit"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i><span><?= $review === null ? 'Submit review' : 'Submit update' ?></span></button>
    </div>
</form>
