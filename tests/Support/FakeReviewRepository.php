<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\ReviewRepositoryInterface;

final class FakeReviewRepository implements ReviewRepositoryInterface
{
    public array $events = [];

    public array $reviews = [];

    public array $saved = [];

    public bool $throwOnSave = false;

    public function reviewableEventForParticipant(int $participantId, int $eventId): ?array
    {
        $event = $this->events[$eventId] ?? null;

        return $event !== null && in_array($participantId, $event['eligible_participants'] ?? [], true)
            ? $event
            : null;
    }

    public function reviewableEventsForParticipant(int $participantId): array
    {
        return array_values(array_filter($this->events, function (array $event) use ($participantId): bool {
            return in_array($participantId, $event['eligible_participants'] ?? [], true)
                && $this->findForParticipantEvent($participantId, (int) $event['id']) === null;
        }));
    }

    public function findForParticipantEvent(int $participantId, int $eventId): ?array
    {
        foreach ($this->reviews as $review) {
            if ((int) $review['user_id'] === $participantId && (int) $review['event_id'] === $eventId) {
                return $review;
            }
        }

        return null;
    }

    public function forParticipant(int $participantId): array
    {
        return array_values(array_filter(
            $this->reviews,
            static fn (array $review): bool => (int) $review['user_id'] === $participantId,
        ));
    }

    public function saveForParticipant(int $participantId, int $eventId, array $attributes): int
    {
        if ($this->throwOnSave) {
            throw new \RuntimeException('Database payload must not escape.');
        }

        if ($this->reviewableEventForParticipant($participantId, $eventId) === null) {
            return 0;
        }

        foreach ($this->reviews as $id => $review) {
            if ((int) $review['user_id'] === $participantId && (int) $review['event_id'] === $eventId) {
                $this->reviews[$id] = array_merge($review, $attributes, ['status' => 'pending']);
                $this->saved[] = $this->reviews[$id];

                return (int) $id;
            }
        }

        $id = $this->reviews === [] ? 1 : max(array_map('intval', array_keys($this->reviews))) + 1;
        $this->reviews[$id] = array_merge($attributes, [
            'id' => $id,
            'event_id' => $eventId,
            'user_id' => $participantId,
            'status' => 'pending',
            'organizer_reply' => null,
        ]);
        $this->saved[] = $this->reviews[$id];

        return $id;
    }

    public function publicForEvent(int $eventId): array
    {
        return array_values(array_filter(
            $this->reviews,
            static fn (array $review): bool => (int) $review['event_id'] === $eventId
                && ($review['status'] ?? null) === 'published',
        ));
    }

    public function summaryForEvent(int $eventId): array
    {
        $reviews = $this->publicForEvent($eventId);

        return [
            'count' => count($reviews),
            'average' => $reviews === [] ? null : array_sum(array_column($reviews, 'rating')) / count($reviews),
        ];
    }

    public function pendingForAdmin(?string $status = null): array
    {
        $allowed = ['pending', 'published', 'hidden'];
        $status = in_array($status, $allowed, true) ? $status : null;

        return array_values(array_filter(
            $this->reviews,
            static fn (array $review): bool => $status === null || ($review['status'] ?? null) === $status,
        ));
    }

    public function forOrganizer(int $organizerId): array
    {
        return array_values(array_filter(
            $this->reviews,
            static fn (array $review): bool => (int) ($review['organizer_user_id'] ?? 0) === $organizerId,
        ));
    }

    public function findForOrganizer(int $organizerId, int $reviewId): ?array
    {
        $review = $this->reviews[$reviewId] ?? null;

        return $review !== null
            && (int) ($review['organizer_user_id'] ?? 0) === $organizerId
            && ($review['status'] ?? null) === 'published'
                ? $review
                : null;
    }

    public function findForAdmin(int $reviewId): ?array
    {
        return $this->reviews[$reviewId] ?? null;
    }

    public function replyForOrganizer(int $organizerId, int $reviewId, string $reply): ?array
    {
        $review = $this->reviews[$reviewId] ?? null;
        if ($review === null
            || (int) ($review['organizer_user_id'] ?? 0) !== $organizerId
            || ($review['status'] ?? null) !== 'published') {
            return null;
        }

        $this->reviews[$reviewId]['organizer_reply'] = $reply;
        $this->reviews[$reviewId]['replied_at'] = '2026-08-09 12:00:00';

        return $this->reviews[$reviewId];
    }

    public function moderate(int $administratorId, int $reviewId, string $status): ?array
    {
        $review = $this->reviews[$reviewId] ?? null;
        if ($review === null || !in_array($status, ['published', 'hidden'], true)) {
            return null;
        }

        if (($review['status'] ?? null) === $status) {
            return $review;
        }

        if (($review['status'] ?? null) !== 'pending') {
            return null;
        }

        $this->reviews[$reviewId]['status'] = $status;

        return $this->reviews[$reviewId];
    }
}
