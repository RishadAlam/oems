<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface FavoriteRepositoryInterface
{
    public function addForParticipant(int $participantId, int $eventId): bool;

    public function removeForParticipant(int $participantId, int $eventId): bool;

    public function existsForParticipant(int $participantId, int $eventId): bool;

    public function forParticipant(int $participantId, int $page, int $perPage): array;
}
