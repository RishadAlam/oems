<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface ReviewRepositoryInterface
{
    public function reviewableEventForParticipant(int $participantId, int $eventId): ?array;

    public function reviewableEventsForParticipant(int $participantId): array;

    public function findForParticipantEvent(int $participantId, int $eventId): ?array;

    public function forParticipant(int $participantId): array;

    public function saveForParticipant(int $participantId, int $eventId, array $attributes): int;

    public function publicForEvent(int $eventId): array;

    public function summaryForEvent(int $eventId): array;

    public function pendingForAdmin(?string $status = null): array;

    public function forOrganizer(int $organizerId): array;

    public function findForOrganizer(int $organizerId, int $reviewId): ?array;

    public function findForAdmin(int $reviewId): ?array;

    public function replyForOrganizer(int $organizerId, int $reviewId, string $reply): ?array;

    public function moderate(int $administratorId, int $reviewId, string $status): ?array;
}
