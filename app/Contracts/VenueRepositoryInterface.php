<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface VenueRepositoryInterface
{
    public function forOrganizerUser(int $userId): array;

    public function findOwned(int $userId, int $venueId): ?array;

    public function createForUser(int $userId, array $attributes): ?int;

    public function updateOwned(int $userId, int $venueId, array $attributes): bool;

    public function deleteOwnedIfUnused(int $userId, int $venueId): bool;
}
