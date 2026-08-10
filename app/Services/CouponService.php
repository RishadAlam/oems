<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\CouponRepositoryInterface;
use OEMS\App\Support\Money;
use OEMS\Core\Logger;
use Throwable;

final class CouponService
{
    public function __construct(
        private readonly CouponRepositoryInterface $coupons,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function index(int $organizerUserId): array
    {
        return ['coupons' => $this->coupons->forOrganizerUser($organizerUserId), 'events' => $this->coupons->eventsForOrganizerUser($organizerUserId)];
    }

    public function create(int $organizerUserId, array $input): array
    {
        [$attributes, $errors] = $this->attributes($organizerUserId, $input);
        if ($errors !== []) return $this->failure($errors);
        try {
            $coupon = $this->coupons->createOwned($organizerUserId, $attributes);
        } catch (Throwable $exception) {
            $this->log('create', $organizerUserId, null, $exception);
            return $this->failure(['coupon' => ['The coupon could not be created.']]);
        }
        return $coupon === null
            ? $this->failure(['code' => ['Use a unique coupon code and an event you own.']])
            : ['success' => true, 'errors' => [], 'coupon' => $coupon];
    }

    public function update(int $organizerUserId, int $couponId, array $input): array
    {
        $current = $this->coupons->findOwned($organizerUserId, $couponId);
        if ($current === null) return ['success' => false, 'code' => 'not_found', 'errors' => []];
        [$attributes, $errors] = $this->attributes($organizerUserId, $input, (int) ($current['used_count'] ?? 0));
        if ($errors !== []) return $this->failure($errors);
        try {
            $updated = $this->coupons->updateOwned($organizerUserId, $couponId, $attributes);
        } catch (Throwable $exception) {
            $this->log('update', $organizerUserId, $couponId, $exception);
            return $this->failure(['coupon' => ['The coupon could not be updated.']]);
        }
        return $updated ? ['success' => true, 'errors' => []] : $this->failure(['code' => ['Use a unique coupon code.']]);
    }

    public function setActive(int $organizerUserId, int $couponId, mixed $state): array
    {
        $current = $this->coupons->findOwned($organizerUserId, $couponId);
        if ($current === null) return ['success' => false, 'code' => 'not_found', 'errors' => []];
        $active = filter_var($state, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null || !in_array((string) $state, ['0', '1'], true)) return $this->failure(['is_active' => ['Choose an explicit coupon status.']]);
        if (!empty($current['is_active']) === $active) return ['success' => true, 'errors' => [], 'is_active' => $active];
        try {
            $updated = $this->coupons->setActiveOwned($organizerUserId, $couponId, $active);
        } catch (Throwable $exception) {
            $this->log('status', $organizerUserId, $couponId, $exception);
            return $this->failure(['coupon' => ['The coupon status could not be changed.']]);
        }
        return $updated ? ['success' => true, 'errors' => [], 'is_active' => $active] : $this->failure(['coupon' => ['The coupon status could not be changed.']]);
    }

    public function quoteForRegistration(int $userId, int $eventId, ?string $code, DateTimeImmutable $now): array
    {
        return $this->quote($userId, $eventId, $code, $now, false);
    }

    public function lockedQuoteForRegistration(int $userId, int $eventId, ?string $code, DateTimeImmutable $now): array
    {
        return $this->quote($userId, $eventId, $code, $now, true);
    }

    public function consume(int $couponId, int $userId, int $registrationId, string $discountAmount, DateTimeImmutable $now): bool
    {
        if ($couponId <= 0 || $userId <= 0 || $registrationId <= 0 || Money::minorUnits($discountAmount) === null) return false;
        return $this->coupons->consume($couponId, $userId, $registrationId, $discountAmount, $now);
    }

    private function quote(int $userId, int $eventId, ?string $code, DateTimeImmutable $now, bool $lock): array
    {
        $normalized = $this->code($code);
        if ($normalized === null || $userId <= 0 || $eventId <= 0) return $this->failure(['coupon_code' => ['Enter a valid available coupon code.']]);
        try {
            $coupon = $this->coupons->findRedeemable($userId, $eventId, $normalized, $now, $lock);
        } catch (Throwable $exception) {
            $this->log('quote', $userId, null, $exception);
            return $this->failure(['coupon_code' => ['The coupon could not be checked.']]);
        }
        if ($coupon === null) return $this->failure(['coupon_code' => ['This coupon is unavailable, expired, exhausted, or already used.']]);
        $base = Money::minorUnits(is_scalar($coupon['ticket_price'] ?? null) ? (string) $coupon['ticket_price'] : null);
        $value = Money::minorUnits((string) ($coupon['discount_value'] ?? ''));
        if ($base === null || $value === null) return $this->failure(['coupon_code' => ['This coupon has invalid pricing.']]);
        $discount = ($coupon['discount_type'] ?? null) === 'percentage'
            ? intdiv($base * min(10000, $value), 10000)
            : min($base, $value);

        return [
            'success' => true,
            'errors' => [],
            'coupon' => $coupon,
            'base_amount' => $this->decimal($base),
            'discount_amount' => $this->decimal($discount),
            'final_amount' => $this->decimal($base - $discount),
            'currency' => strtoupper((string) ($coupon['currency'] ?? 'BDT')),
        ];
    }

    private function attributes(int $userId, array $input, int $usedCount = 0): array
    {
        $errors = [];
        $code = $this->code($input['code'] ?? null);
        if ($code === null) $errors['code'][] = 'Use 3 to 80 letters, numbers, underscores, or hyphens.';
        $type = is_scalar($input['discount_type'] ?? null) ? strtolower(trim((string) $input['discount_type'])) : '';
        if (!in_array($type, ['fixed', 'percentage'], true)) $errors['discount_type'][] = 'Choose fixed or percentage discount.';
        $value = Money::normalize(is_scalar($input['discount_value'] ?? null) ? (string) $input['discount_value'] : null);
        $minor = $value === null ? null : Money::minorUnits($value);
        if ($minor === null || $minor <= 0 || ($type === 'percentage' && $minor > 10000)) $errors['discount_value'][] = 'Enter a positive discount, up to 100.00 percent.';
        $limitRaw = $input['usage_limit'] ?? null;
        $limit = $limitRaw === '' || $limitRaw === null ? null : filter_var($limitRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($limit === false || ($limit !== null && $limit < $usedCount)) $errors['usage_limit'][] = 'Enter a positive limit not lower than current usage.';
        $eventRaw = $input['event_id'] ?? null;
        $eventId = $eventRaw === '' || $eventRaw === null ? null : filter_var($eventRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $ownedIds = array_map('intval', array_column($this->coupons->eventsForOrganizerUser($userId), 'id'));
        if ($eventId === false || ($eventId !== null && !in_array($eventId, $ownedIds, true))) $errors['event_id'][] = 'Choose an event you own or all events.';
        $starts = $this->date($input['starts_at'] ?? null);
        $expires = $this->date($input['expires_at'] ?? null);
        if (($input['starts_at'] ?? '') !== '' && $starts === false) $errors['starts_at'][] = 'Enter a valid start date and time.';
        if (($input['expires_at'] ?? '') !== '' && $expires === false) $errors['expires_at'][] = 'Enter a valid expiry date and time.';
        if (is_string($starts) && is_string($expires) && $expires < $starts) $errors['expires_at'][] = 'Expiry must be after the start date.';

        return [[
            'event_id' => $eventId === false ? null : $eventId,
            'code' => $code ?? '', 'discount_type' => $type, 'discount_value' => $value ?? '0.00',
            'usage_limit' => $limit === false ? null : $limit, 'starts_at' => $starts === false ? null : $starts,
            'expires_at' => $expires === false ? null : $expires, 'is_active' => 1,
        ], $errors];
    }

    private function code(mixed $value): ?string
    {
        if (!is_scalar($value)) return null;
        $value = strtoupper(trim((string) $value));
        return preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,79}\z/D', $value) === 1 ? $value : null;
    }

    private function date(mixed $value): string|false|null
    {
        if ($value === null || $value === '') return null;
        if (!is_scalar($value)) return false;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim((string) $value));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return false;
        return $date->format('Y-m-d H:i:s');
    }

    private function decimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function failure(array $errors): array
    {
        return ['success' => false, 'errors' => $errors];
    }

    private function log(string $operation, int $userId, ?int $couponId, Throwable $exception): void
    {
        try {
            $this->logger?->error('Coupon operation failed.', [
                'operation' => $operation, 'actor_id' => $userId, 'coupon_id' => $couponId, 'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
        }
    }
}
