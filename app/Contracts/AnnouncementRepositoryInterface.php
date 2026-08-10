<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface AnnouncementRepositoryInterface
{
    public function findOwnedEvent(int $organizerUserId, int $eventId): ?array;

    public function historyForOwnedEvent(int $organizerUserId, int $eventId, int $limit): array;

    public function deliverToConfirmedParticipants(
        int $organizerUserId,
        int $eventId,
        string $subject,
        string $message,
        string $requestKey,
        array $context,
    ): array;
}
