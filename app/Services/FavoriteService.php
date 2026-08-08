<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Contracts\UserRepositoryInterface;

final class FavoriteService
{
    public function __construct(
        private readonly FavoriteRepositoryInterface $favorites,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function save(int $participantId, int $eventId): array
    {
        if ($eventId <= 0) {
            return ['success' => false, 'code' => 'invalid_event'];
        }

        if (!$this->isVerifiedParticipant($participantId)) {
            return ['success' => false, 'code' => 'invalid_participant'];
        }

        return $this->favorites->addForParticipant($participantId, $eventId)
            ? ['success' => true, 'code' => 'saved']
            : ['success' => false, 'code' => 'event_not_available'];
    }

    public function remove(int $participantId, int $eventId): array
    {
        if ($eventId <= 0) {
            return ['success' => false, 'code' => 'invalid_event'];
        }

        if (!$this->isVerifiedParticipant($participantId)) {
            return ['success' => false, 'code' => 'invalid_participant'];
        }

        $this->favorites->removeForParticipant($participantId, $eventId);

        return ['success' => true, 'code' => 'removed'];
    }

    private function isVerifiedParticipant(int $participantId): bool
    {
        $user = $participantId > 0 ? $this->users->findById($participantId) : null;

        return $user !== null
            && ($user['status'] ?? null) === 'active'
            && ($user['role_slug'] ?? null) === 'participant'
            && trim((string) ($user['email_verified_at'] ?? '')) !== '';
    }
}
