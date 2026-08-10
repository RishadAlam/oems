<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface CouponRepositoryInterface
{
    public function forOrganizerUser(int $organizerUserId): array;

    public function eventsForOrganizerUser(int $organizerUserId): array;

    public function findOwned(int $organizerUserId, int $couponId): ?array;

    public function createOwned(int $organizerUserId, array $attributes): ?array;

    public function updateOwned(int $organizerUserId, int $couponId, array $attributes): bool;

    public function setActiveOwned(int $organizerUserId, int $couponId, bool $active): bool;

    public function findRedeemable(
        int $participantId,
        int $eventId,
        string $code,
        DateTimeImmutable $now,
        bool $lock,
    ): ?array;

    public function consume(
        int $couponId,
        int $participantId,
        int $registrationId,
        string $discountAmount,
        DateTimeImmutable $usedAt,
    ): bool;
}
