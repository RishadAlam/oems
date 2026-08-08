<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface RegistrationRepositoryInterface
{
    public function findForParticipantEvent(int $participantId, int $eventId): ?array;

    public function findForParticipant(int $participantId, int $registrationId): ?array;

    public function forParticipant(int $participantId): array;

    public function reserve(int $participantId, int $eventId, array $attributes): ?array;

    public function reactivate(int $registrationId, array $attributes): bool;

    public function confirm(int $registrationId): bool;

    public function cancelForParticipant(int $participantId, int $registrationId, string $reason): ?array;
}
