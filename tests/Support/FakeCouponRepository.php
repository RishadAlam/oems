<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use DateTimeImmutable;
use OEMS\App\Contracts\CouponRepositoryInterface;

final class FakeCouponRepository implements CouponRepositoryInterface
{
    public array $coupons = [];
    public array $events = [];
    public array $usage = [];
    public bool $failWrites = false;

    public function forOrganizerUser(int $organizerUserId): array
    {
        return array_values(array_filter($this->coupons, fn (array $coupon): bool => (int) $coupon['organizer_user_id'] === $organizerUserId));
    }

    public function eventsForOrganizerUser(int $organizerUserId): array
    {
        return array_values(array_filter($this->events, fn (array $event): bool => (int) $event['organizer_user_id'] === $organizerUserId));
    }

    public function findOwned(int $organizerUserId, int $couponId): ?array
    {
        $coupon = $this->coupons[$couponId] ?? null;
        return is_array($coupon) && (int) $coupon['organizer_user_id'] === $organizerUserId ? $coupon : null;
    }

    public function createOwned(int $organizerUserId, array $attributes): ?array
    {
        if ($this->failWrites) return null;
        foreach ($this->coupons as $coupon) if ($coupon['code'] === $attributes['code']) return null;
        $id = $this->coupons === [] ? 1 : max(array_keys($this->coupons)) + 1;
        $organizerId = 0;
        foreach ($this->events as $event) if ((int) $event['organizer_user_id'] === $organizerUserId) $organizerId = (int) $event['organizer_id'];
        $this->coupons[$id] = array_merge($attributes, ['id' => $id, 'organizer_id' => $organizerId, 'organizer_user_id' => $organizerUserId, 'used_count' => 0]);
        return $this->coupons[$id];
    }

    public function updateOwned(int $organizerUserId, int $couponId, array $attributes): bool
    {
        if ($this->failWrites || $this->findOwned($organizerUserId, $couponId) === null) return false;
        $this->coupons[$couponId] = array_merge($this->coupons[$couponId], $attributes);
        return true;
    }

    public function setActiveOwned(int $organizerUserId, int $couponId, bool $active): bool
    {
        return $this->updateOwned($organizerUserId, $couponId, ['is_active' => $active ? 1 : 0]);
    }

    public function findRedeemable(int $participantId, int $eventId, string $code, DateTimeImmutable $now, bool $lock): ?array
    {
        foreach ($this->coupons as $coupon) {
            if ($coupon['code'] !== $code || empty($coupon['is_active'])) continue;
            $event = $this->events[$eventId] ?? null;
            if (!is_array($event) || (int) $coupon['organizer_id'] !== (int) $event['organizer_id']) continue;
            if ($coupon['event_id'] !== null && (int) $coupon['event_id'] !== $eventId) continue;
            if (($coupon['usage_limit'] ?? null) !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) continue;
            if (isset($this->usage[$coupon['id'] . ':' . $participantId])) continue;
            if (($coupon['starts_at'] ?? null) !== null && $now < new DateTimeImmutable($coupon['starts_at'])) continue;
            if (($coupon['expires_at'] ?? null) !== null && $now > new DateTimeImmutable($coupon['expires_at'])) continue;
            return array_merge($coupon, ['event_id' => $eventId, 'ticket_price' => $event['ticket_price'], 'currency' => $event['currency']]);
        }
        return null;
    }

    public function consume(int $couponId, int $participantId, int $registrationId, string $discountAmount, DateTimeImmutable $usedAt): bool
    {
        if ($this->failWrites || isset($this->usage[$couponId . ':' . $participantId])) return false;
        $coupon = $this->coupons[$couponId] ?? null;
        if (!is_array($coupon) || empty($coupon['is_active']) || (($coupon['usage_limit'] ?? null) !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit'])) return false;
        $this->coupons[$couponId]['used_count']++;
        $this->usage[$couponId . ':' . $participantId] = compact('couponId', 'participantId', 'registrationId', 'discountAmount');
        return true;
    }
}
