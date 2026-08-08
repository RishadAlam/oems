<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\FavoriteRepositoryInterface;

final class FakeFavoriteRepository implements FavoriteRepositoryInterface
{
    /** @var array<int, array<int, bool>> */
    public array $favorites = [];

    /** @var array<int, array{items: array, pagination: array}> */
    public array $pages = [];

    public int $bulkStateCalls = 0;

    public bool $allowsSave = true;

    public function addForParticipant(int $participantId, int $eventId): bool
    {
        if (!$this->allowsSave) {
            return false;
        }

        $this->favorites[$participantId][$eventId] = true;

        return true;
    }

    public function removeForParticipant(int $participantId, int $eventId): bool
    {
        unset($this->favorites[$participantId][$eventId]);

        return true;
    }

    public function existsForParticipant(int $participantId, int $eventId): bool
    {
        return isset($this->favorites[$participantId][$eventId]);
    }

    public function forParticipant(int $participantId, int $page, int $perPage): array
    {
        return $this->pages[$participantId] ?? [
            'items' => [],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
        ];
    }

    /** @return array<int, bool> */
    public function statesForParticipant(int $participantId, array $eventIds): array
    {
        $this->bulkStateCalls++;
        $saved = $this->favorites[$participantId] ?? [];
        $states = [];

        foreach ($eventIds as $eventId) {
            $id = (int) $eventId;
            if ($id > 0 && isset($saved[$id])) {
                $states[$id] = true;
            }
        }

        return $states;
    }
}
