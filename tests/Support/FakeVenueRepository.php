<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\VenueRepositoryInterface;

final class FakeVenueRepository implements VenueRepositoryInterface
{
    public array $venues = [
        1 => ['id' => 1, 'user_id' => 10, 'name' => 'Owned Hall', 'capacity' => 100],
        2 => ['id' => 2, 'user_id' => 20, 'name' => 'Foreign Hall', 'capacity' => 500],
        3 => ['id' => 3, 'user_id' => 11, 'name' => 'Pending Hall', 'capacity' => 100],
    ];

    public function forOrganizerUser(int $userId): array
    {
        return array_values(array_filter(
            $this->venues,
            static fn (array $venue): bool => (int) $venue['user_id'] === $userId,
        ));
    }

    public function findOwned(int $userId, int $venueId): ?array
    {
        $venue = $this->venues[$venueId] ?? null;

        return $venue !== null && (int) $venue['user_id'] === $userId ? $venue : null;
    }

    public function createForUser(int $userId, array $attributes): ?int
    {
        $id = $this->venues === [] ? 1 : max(array_keys($this->venues)) + 1;
        $this->venues[$id] = array_merge($attributes, ['id' => $id, 'user_id' => $userId]);

        return $id;
    }

    public function updateOwned(int $userId, int $venueId, array $attributes): bool
    {
        if ($this->findOwned($userId, $venueId) === null) {
            return false;
        }

        $this->venues[$venueId] = array_merge($this->venues[$venueId], $attributes);

        return true;
    }

    public function deleteOwnedIfUnused(int $userId, int $venueId): bool
    {
        if ($this->findOwned($userId, $venueId) === null) {
            return false;
        }

        unset($this->venues[$venueId]);

        return true;
    }
}
