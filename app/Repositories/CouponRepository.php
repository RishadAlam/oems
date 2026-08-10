<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use OEMS\App\Contracts\CouponRepositoryInterface;
use PDO;
use PDOException;
use Throwable;

final class CouponRepository implements CouponRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function forOrganizerUser(int $organizerUserId): array
    {
        $statement = $this->connection->prepare(
            $this->select()
            . ' WHERE organizers.user_id = :organizer_user_id
                ORDER BY coupons.created_at DESC, coupons.id DESC',
        );
        $statement->execute(['organizer_user_id' => $organizerUserId]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function eventsForOrganizerUser(int $organizerUserId): array
    {
        $statement = $this->connection->prepare(
            'SELECT events.id, events.organizer_id, organizers.user_id AS organizer_user_id,
                    events.title, events.status, events.ticket_price, events.currency
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE organizers.user_id = :organizer_user_id AND events.deleted_at IS NULL
             ORDER BY events.start_date DESC, events.id DESC',
        );
        $statement->execute(['organizer_user_id' => $organizerUserId]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findOwned(int $organizerUserId, int $couponId): ?array
    {
        $statement = $this->connection->prepare(
            $this->select()
            . ' WHERE organizers.user_id = :organizer_user_id AND coupons.id = :coupon_id LIMIT 1',
        );
        $statement->execute(['organizer_user_id' => $organizerUserId, 'coupon_id' => $couponId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function createOwned(int $organizerUserId, array $attributes): ?array
    {
        $organizerId = $this->organizerId($organizerUserId);
        if ($organizerId === null || !$this->ownedEvent($organizerId, $attributes['event_id'] ?? null)) {
            return null;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO coupons
                (event_id, organizer_id, code, discount_type, discount_value, usage_limit,
                 used_count, starts_at, expires_at, is_active)
             VALUES
                (:event_id, :organizer_id, :code, :discount_type, :discount_value, :usage_limit,
                 0, :starts_at, :expires_at, :is_active)',
        );
        try {
            $statement->execute($this->bindings($organizerId, $attributes));
        } catch (PDOException $exception) {
            if ($this->uniqueViolation($exception)) return null;
            throw $exception;
        }

        return $this->findOwned($organizerUserId, (int) $this->connection->lastInsertId());
    }

    public function updateOwned(int $organizerUserId, int $couponId, array $attributes): bool
    {
        $owned = $this->findOwned($organizerUserId, $couponId);
        if ($owned === null || !$this->ownedEvent((int) $owned['organizer_id'], $attributes['event_id'] ?? null)) {
            return false;
        }

        $statement = $this->connection->prepare(
            'UPDATE coupons
             SET event_id = :event_id, code = :code, discount_type = :discount_type,
                 discount_value = :discount_value, usage_limit = :usage_limit,
                 starts_at = :starts_at, expires_at = :expires_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :coupon_id AND organizer_id = :organizer_id',
        );
        $bindings = $this->bindings((int) $owned['organizer_id'], $attributes);
        unset($bindings['is_active']);
        $bindings['coupon_id'] = $couponId;
        try {
            $statement->execute($bindings);
        } catch (PDOException $exception) {
            if ($this->uniqueViolation($exception)) return false;
            throw $exception;
        }

        return $statement->rowCount() === 1 || $this->matches($organizerUserId, $couponId, $attributes);
    }

    public function setActiveOwned(int $organizerUserId, int $couponId, bool $active): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE coupons
             SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP
             WHERE id = :coupon_id
               AND organizer_id = (SELECT id FROM organizers WHERE user_id = :organizer_user_id LIMIT 1)',
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'coupon_id' => $couponId,
            'organizer_user_id' => $organizerUserId,
        ]);
        if ($statement->rowCount() === 1) return true;
        $coupon = $this->findOwned($organizerUserId, $couponId);

        return $coupon !== null && !empty($coupon['is_active']) === $active;
    }

    public function findRedeemable(
        int $participantId,
        int $eventId,
        string $code,
        DateTimeImmutable $now,
        bool $lock,
    ): ?array {
        $locking = $lock && $this->driver() === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare(
            'SELECT coupons.*, organizers.user_id AS organizer_user_id,
                    events.ticket_price, events.currency, events.title AS event_title
             FROM coupons
             INNER JOIN events ON events.id = :event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             LEFT JOIN coupon_usage ON coupon_usage.coupon_id = coupons.id AND coupon_usage.user_id = :participant_id
             WHERE coupons.code = :coupon_code
               AND coupons.organizer_id = events.organizer_id
               AND (coupons.event_id IS NULL OR coupons.event_id = events.id)
               AND coupons.is_active = 1
               AND (coupons.starts_at IS NULL OR coupons.starts_at <= :window_start)
               AND (coupons.expires_at IS NULL OR coupons.expires_at >= :window_end)
               AND (coupons.usage_limit IS NULL OR coupons.used_count < coupons.usage_limit)
               AND coupon_usage.id IS NULL
               AND events.deleted_at IS NULL
             LIMIT 1' . $locking,
        );
        $statement->execute([
            'event_id' => $eventId,
            'participant_id' => $participantId,
            'coupon_code' => $code,
            'window_start' => $now->format('Y-m-d H:i:s'),
            'window_end' => $now->format('Y-m-d H:i:s'),
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function consume(
        int $couponId,
        int $participantId,
        int $registrationId,
        string $discountAmount,
        DateTimeImmutable $usedAt,
    ): bool {
        $owns = !$this->connection->inTransaction();
        if ($owns) $this->connection->beginTransaction();
        try {
            $update = $this->connection->prepare(
                'UPDATE coupons
                 SET used_count = used_count + 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :coupon_id AND is_active = 1
                   AND (usage_limit IS NULL OR used_count < usage_limit)
                   AND (starts_at IS NULL OR starts_at <= :used_start)
                   AND (expires_at IS NULL OR expires_at >= :used_end)',
            );
            $update->execute([
                'coupon_id' => $couponId,
                'used_start' => $usedAt->format('Y-m-d H:i:s'),
                'used_end' => $usedAt->format('Y-m-d H:i:s'),
            ]);
            if ($update->rowCount() !== 1) {
                if ($owns) $this->connection->rollBack();
                return false;
            }

            $insert = $this->connection->prepare(
                'INSERT INTO coupon_usage (coupon_id, user_id, registration_id, discount_amount, used_at)
                 VALUES (:coupon_id, :user_id, :registration_id, :discount_amount, :used_at)',
            );
            $insert->execute([
                'coupon_id' => $couponId,
                'user_id' => $participantId,
                'registration_id' => $registrationId,
                'discount_amount' => $discountAmount,
                'used_at' => $usedAt->format('Y-m-d H:i:s'),
            ]);
            if ($owns) $this->connection->commit();
            return true;
        } catch (PDOException $exception) {
            if ($owns && $this->connection->inTransaction()) $this->connection->rollBack();
            if ($this->uniqueViolation($exception)) return false;
            throw $exception;
        } catch (Throwable $exception) {
            if ($owns && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    private function select(): string
    {
        return 'SELECT coupons.*, organizers.user_id AS organizer_user_id, events.title AS event_title,
                       events.ticket_price AS event_ticket_price, events.currency AS event_currency
                FROM coupons
                INNER JOIN organizers ON organizers.id = coupons.organizer_id
                LEFT JOIN events ON events.id = coupons.event_id';
    }

    private function bindings(int $organizerId, array $attributes): array
    {
        return [
            'event_id' => $attributes['event_id'] ?? null,
            'organizer_id' => $organizerId,
            'code' => (string) $attributes['code'],
            'discount_type' => (string) $attributes['discount_type'],
            'discount_value' => (string) $attributes['discount_value'],
            'usage_limit' => $attributes['usage_limit'] ?? null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'expires_at' => $attributes['expires_at'] ?? null,
            'is_active' => !empty($attributes['is_active']) ? 1 : 0,
        ];
    }

    private function matches(int $userId, int $couponId, array $attributes): bool
    {
        $row = $this->findOwned($userId, $couponId);
        if ($row === null) return false;
        foreach (['event_id', 'code', 'discount_type', 'discount_value', 'usage_limit', 'starts_at', 'expires_at'] as $key) {
            if (($row[$key] === null ? null : (string) $row[$key]) !== (($attributes[$key] ?? null) === null ? null : (string) $attributes[$key])) return false;
        }
        return true;
    }

    private function organizerId(int $userId): ?int
    {
        $statement = $this->connection->prepare('SELECT id FROM organizers WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function ownedEvent(int $organizerId, mixed $eventId): bool
    {
        if ($eventId === null) return true;
        $statement = $this->connection->prepare('SELECT 1 FROM events WHERE id = :event_id AND organizer_id = :organizer_id AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['event_id' => (int) $eventId, 'organizer_id' => $organizerId]);
        return $statement->fetchColumn() !== false;
    }

    private function driver(): string
    {
        return (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function uniqueViolation(PDOException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505', '19'], true);
    }
}
