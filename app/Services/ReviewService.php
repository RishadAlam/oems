<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\ReviewRepositoryInterface;
use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\Core\Logger;
use PDO;
use Throwable;

final class ReviewService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly UserRepositoryInterface $users,
        private readonly ReviewRepositoryInterface $reviews,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function participantReviews(int $actorId): array
    {
        if ($this->authorizedUser($actorId, 'participant') === null) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }

        return $this->success([
            'reviews' => $this->reviews->forParticipant($actorId),
            'eligible_events' => $this->reviews->reviewableEventsForParticipant($actorId),
        ]);
    }

    public function participantForm(int $actorId, int $eventId): array
    {
        if ($this->authorizedUser($actorId, 'participant') === null) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }

        if ($eventId <= 0) {
            return $this->failure(['event' => ['This event is not available for review.']]);
        }

        $event = $this->reviews->reviewableEventForParticipant($actorId, $eventId);
        if ($event === null) {
            return $this->failure(['event' => ['This event is not available for review.']]);
        }

        return $this->success([
            'event' => $event,
            'review' => $this->reviews->findForParticipantEvent($actorId, $eventId),
        ]);
    }

    public function submit(int $actorId, int $eventId, mixed $rating, mixed $comment): array
    {
        if ($this->authorizedUser($actorId, 'participant') === null) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }

        $errors = $this->submissionErrors($eventId, $rating, $comment);
        if ($errors !== []) {
            return $this->failure($errors);
        }

        $rating = (int) $rating;
        $comment = trim((string) $comment);

        try {
            $this->connection->beginTransaction();
            $reviewId = $this->reviews->saveForParticipant($actorId, $eventId, [
                'rating' => $rating,
                'review' => $comment,
            ]);

            if ($reviewId <= 0) {
                $this->connection->rollBack();

                return $this->failure(['event' => ['This event is not available for review.']]);
            }

            $review = $this->reviews->findForParticipantEvent($actorId, $eventId);
            if ($review === null) {
                throw new \RuntimeException('Saved review could not be read.');
            }

            $this->connection->commit();

            return $this->success(['review' => $review]);
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->logFailure('review_submit', $actorId, $eventId, null, $exception);

            return $this->failure(['review' => ['The review could not be saved.']]);
        }
    }

    public function organizerReviews(int $actorId): array
    {
        if ($this->authorizedUser($actorId, 'organizer') === null) {
            return $this->failure(['account' => ['An active, verified organizer account is required.']]);
        }

        return $this->success(['reviews' => $this->reviews->forOrganizer($actorId)]);
    }

    public function reply(int $actorId, int $reviewId, mixed $reply): array
    {
        if ($this->authorizedUser($actorId, 'organizer') === null) {
            return $this->failure(['account' => ['An active, verified organizer account is required.']]);
        }

        $reply = is_scalar($reply) ? trim((string) $reply) : '';
        if ($reviewId <= 0) {
            return $this->failure(['review' => ['Review not found.']], 'not_found');
        }

        try {
            $ownedReview = $this->reviews->findForOrganizer($actorId, $reviewId);
        } catch (Throwable $exception) {
            $this->logFailure('review_reply_lookup', $actorId, null, $reviewId, $exception);

            return $this->failure(['reply' => ['The reply could not be saved.']]);
        }

        if ($ownedReview === null) {
            return $this->failure(['review' => ['Review not found.']], 'not_found');
        }

        if (mb_strlen($reply) < 2 || mb_strlen($reply) > 1000) {
            return $this->failure(['reply' => ['Use between 2 and 1000 characters.']]);
        }

        try {
            $review = $this->reviews->replyForOrganizer($actorId, $reviewId, $reply);
        } catch (Throwable $exception) {
            $this->logFailure('review_reply', $actorId, null, $reviewId, $exception);

            return $this->failure(['reply' => ['The reply could not be saved.']]);
        }

        if ($review === null) {
            return $this->failure(['review' => ['Review not found.']], 'not_found');
        }

        return $this->success([
            'review' => $review,
            'notification' => [
                'recipient_user_id' => (int) $review['user_id'],
                'type' => 'review_reply',
                'review_id' => (int) $review['id'],
                'event_id' => (int) $review['event_id'],
            ],
        ]);
    }

    public function adminQueue(int $actorId, ?string $status): array
    {
        if ($this->authorizedUser($actorId, 'super-admin') === null) {
            return $this->failure(['account' => ['An active, verified administrator account is required.']]);
        }

        $status = in_array($status, ['pending', 'published', 'hidden'], true) ? $status : null;

        return $this->success([
            'reviews' => $this->reviews->pendingForAdmin($status),
            'status' => $status,
        ]);
    }

    public function moderate(int $actorId, int $reviewId, string $status): array
    {
        if ($this->authorizedUser($actorId, 'super-admin') === null) {
            return $this->failure(['account' => ['An active, verified administrator account is required.']]);
        }

        if ($reviewId <= 0 || !in_array($status, ['published', 'hidden'], true)) {
            return $this->failure(['review' => ['The moderation request is invalid.']], 'invalid');
        }

        try {
            $review = $this->reviews->moderate($actorId, $reviewId, $status);
            if ($review !== null) {
                return $this->success(['review' => $review]);
            }

            $current = $this->reviews->findForAdmin($reviewId);
        } catch (Throwable $exception) {
            $this->logFailure('review_moderation', $actorId, null, $reviewId, $exception);

            return $this->failure(['review' => ['The review could not be moderated.']]);
        }

        if ($current === null) {
            return $this->failure(['review' => ['Review not found.']], 'not_found');
        }

        return $this->failure(['review' => ['This review was already moderated differently.']], 'conflict');
    }

    private function submissionErrors(int $eventId, mixed $rating, mixed $comment): array
    {
        $errors = [];
        if ($eventId <= 0) {
            $errors['event'][] = 'This event is not available for review.';
        }

        $ratingValue = is_scalar($rating) ? (string) $rating : '';
        if (preg_match('/^[1-5]$/', $ratingValue) !== 1) {
            $errors['rating'][] = 'Choose a whole-number rating from 1 to 5.';
        }

        $commentValue = is_scalar($comment) ? trim((string) $comment) : '';
        if (mb_strlen($commentValue) < 10 || mb_strlen($commentValue) > 2000) {
            $errors['review'][] = 'Use between 10 and 2000 characters.';
        }

        return $errors;
    }

    private function authorizedUser(int $userId, string $role): ?array
    {
        $user = $userId > 0 ? $this->users->findById($userId) : null;

        return $user !== null
            && ($user['role_slug'] ?? null) === $role
            && ($user['status'] ?? null) === 'active'
            && trim((string) ($user['email_verified_at'] ?? '')) !== ''
                ? $user
                : null;
    }

    private function logFailure(
        string $operation,
        int $actorId,
        ?int $eventId,
        ?int $reviewId,
        Throwable $exception,
    ): void {
        try {
            $context = [
                'actor_id' => $actorId,
                'exception' => $exception::class,
            ];
            if ($eventId !== null) {
                $context['event_id'] = $eventId;
            }
            if ($reviewId !== null) {
                $context['review_id'] = $reviewId;
            }
            $this->logger?->error($operation, $context);
        } catch (Throwable) {
        }
    }

    private function success(array $data): array
    {
        return array_merge(['success' => true, 'errors' => [], 'code' => 'ok'], $data);
    }

    private function failure(array $errors, string $code = 'invalid'): array
    {
        return ['success' => false, 'errors' => $errors, 'code' => $code];
    }
}
