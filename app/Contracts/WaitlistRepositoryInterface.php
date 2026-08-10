<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface WaitlistRepositoryInterface
{
    public function findJoinableEvent(int $eventId): ?array;

    public function findParticipantEntry(int $participantId, int $eventId): ?array;

    public function findParticipantEntryById(int $participantId, int $registrationId): ?array;

    public function forParticipant(int $participantId): array;

    public function join(int $participantId, int $eventId, array $attributes): ?array;

    public function leave(int $participantId, int $registrationId, string $reason, DateTimeImmutable $leftAt): ?array;

    public function position(int $registrationId): ?int;

    public function claimOldest(
        int $eventId,
        DateTimeImmutable $promotedAt,
        DateTimeImmutable $claimExpiresAt,
    ): ?array;

    public function completeClaim(int $registrationId): bool;

    public function expiredClaims(DateTimeImmutable $now, int $limit): array;

    public function releaseExpiredClaim(int $registrationId, DateTimeImmutable $expiredAt): ?array;

    public function eventsWithAvailableSeats(int $limit): array;
}
