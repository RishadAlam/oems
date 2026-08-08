<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\RegistrationRepositoryInterface;
use PDO;
use PDOException;

final class RegistrationRepository implements RegistrationRepositoryInterface
{
    private const RESERVING_STATUSES = ['pending', 'confirmed'];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findForParticipantEvent(int $participantId, int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE registrations.user_id = :user_id AND registrations.event_id = :event_id LIMIT 1',
        );
        $statement->execute([
            'user_id' => $participantId,
            'event_id' => $eventId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function findForParticipant(int $participantId, int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE registrations.user_id = :user_id AND registrations.id = :registration_id LIMIT 1',
        );
        $statement->execute([
            'user_id' => $participantId,
            'registration_id' => $registrationId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function forParticipant(int $participantId): array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE registrations.user_id = :user_id'
            . ' ORDER BY registrations.registered_at DESC, registrations.id DESC',
        );
        $statement->execute(['user_id' => $participantId]);

        return $statement->fetchAll();
    }

    public function reserve(int $participantId, int $eventId, array $attributes): ?array
    {
        $status = (string) ($attributes['status'] ?? 'pending');

        if (!in_array($status, self::RESERVING_STATUSES, true)) {
            return null;
        }

        if ($this->registrationIdFor($participantId, $eventId) !== null) {
            return null;
        }

        $event = $this->eligibleEvent($eventId);

        if ($event === null || $this->reservedCount($eventId) >= (int) $event['capacity']) {
            return null;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO registrations
                (event_id, user_id, coupon_id, registration_number, status, amount, currency, registered_at)
             VALUES
                (:event_id, :user_id, :coupon_id, :registration_number, :registration_status, :amount, :currency, :registered_at)',
        );

        try {
            $statement->execute([
                'event_id' => $eventId,
                'user_id' => $participantId,
                'coupon_id' => $attributes['coupon_id'] ?? null,
                'registration_number' => (string) $attributes['registration_number'],
                'registration_status' => $status,
                'amount' => (string) $attributes['amount'],
                'currency' => (string) $attributes['currency'],
                'registered_at' => (string) $attributes['registered_at'],
            ]);
        } catch (PDOException $exception) {
            if ($this->registrationIdFor($participantId, $eventId) !== null) {
                return null;
            }

            throw $exception;
        }

        return $this->findForParticipant($participantId, (int) $this->connection->lastInsertId());
    }

    public function reactivate(int $registrationId, array $attributes): bool
    {
        $status = (string) ($attributes['status'] ?? 'pending');

        if (!in_array($status, self::RESERVING_STATUSES, true)) {
            return false;
        }

        $registration = $this->reactivatableRegistration($registrationId);

        if ($registration === null) {
            return false;
        }

        $eventId = (int) $registration['event_id'];
        $event = $this->eligibleEvent($eventId);

        if ($event === null || $this->reservedCount($eventId) >= (int) $event['capacity']) {
            return false;
        }

        $statement = $this->connection->prepare(
            "UPDATE registrations
             SET coupon_id = :coupon_id,
                 registration_number = :registration_number,
                 status = :registration_status,
                 amount = :amount,
                 currency = :currency,
                 registered_at = :registered_at,
                 cancelled_at = NULL,
                 cancellation_reason = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :registration_id
               AND status IN ('cancelled', 'refunded')",
        );
        $statement->execute([
            'coupon_id' => $attributes['coupon_id'] ?? null,
            'registration_number' => (string) $attributes['registration_number'],
            'registration_status' => $status,
            'amount' => (string) $attributes['amount'],
            'currency' => (string) $attributes['currency'],
            'registered_at' => (string) $attributes['registered_at'],
            'registration_id' => $registrationId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function confirm(int $registrationId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE registrations
             SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP
             WHERE id = :registration_id AND status = 'pending'",
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $statement->rowCount() === 1;
    }

    public function cancelForParticipant(int $participantId, int $registrationId, string $reason): ?array
    {
        $statement = $this->connection->prepare(
            "UPDATE registrations
             SET status = 'cancelled',
                 cancelled_at = CURRENT_TIMESTAMP,
                 cancellation_reason = :cancellation_reason,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :registration_id
               AND user_id = :user_id
               AND status IN ('pending', 'confirmed')",
        );
        $statement->execute([
            'cancellation_reason' => $reason,
            'registration_id' => $registrationId,
            'user_id' => $participantId,
        ]);

        if ($statement->rowCount() !== 1) {
            return null;
        }

        return $this->findForParticipant($participantId, $registrationId);
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->statusSummary(
            '',
            'WHERE registrations.user_id = :participant_user_id',
            ['participant_user_id' => $participantId],
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->statusSummary(
            'INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id',
            'WHERE organizers.user_id = :organizer_user_id',
            ['organizer_user_id' => $organizerUserId],
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->statusSummary('', '', []);
    }

    private function participantSelect(): string
    {
        return 'SELECT registrations.id,
                       registrations.event_id,
                       registrations.user_id,
                       registrations.coupon_id,
                       registrations.registration_number,
                       registrations.status,
                       registrations.status AS registration_status,
                       registrations.amount,
                       registrations.currency,
                       registrations.registered_at,
                       registrations.cancelled_at,
                       registrations.cancellation_reason,
                       registrations.created_at,
                       registrations.updated_at,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       events.start_date AS event_start_date,
                       events.registration_deadline,
                       events.status AS event_status,
                       events.ticket_price,
                       events.currency AS event_currency
                FROM registrations
                INNER JOIN events ON events.id = registrations.event_id';
    }

    private function eligibleEvent(int $eventId): ?array
    {
        $lockingClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            'SELECT events.id, events.capacity
             FROM events
             INNER JOIN categories ON categories.id = events.category_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE events.id = :event_id
               AND events.status = :published_status
               AND events.deleted_at IS NULL
               AND categories.is_active = :active_category
               AND events.registration_deadline > CURRENT_TIMESTAMP
               AND events.start_date > CURRENT_TIMESTAMP
               AND organizers.approval_status = :approved_status
             LIMIT 1' . $lockingClause,
        );
        $statement->execute([
            'event_id' => $eventId,
            'published_status' => 'published',
            'active_category' => 1,
            'approved_status' => 'approved',
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    private function reservedCount(int $eventId): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM registrations
             WHERE event_id = :event_id
               AND status IN ('pending', 'confirmed')",
        );
        $statement->execute(['event_id' => $eventId]);

        return (int) $statement->fetchColumn();
    }

    private function statusSummary(string $joins, string $where, array $bindings): array
    {
        $statement = $this->connection->prepare(
            "SELECT COALESCE(SUM(CASE WHEN registrations.status IN ('pending', 'confirmed') THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN registrations.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN registrations.status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed
             FROM registrations
             {$joins}
             {$where}",
        );
        $statement->execute($bindings);
        $summary = $statement->fetch();

        return [
            'active' => (int) ($summary['active'] ?? 0),
            'pending' => (int) ($summary['pending'] ?? 0),
            'confirmed' => (int) ($summary['confirmed'] ?? 0),
        ];
    }

    private function registrationIdFor(int $participantId, int $eventId): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM registrations WHERE user_id = :user_id AND event_id = :event_id LIMIT 1',
        );
        $statement->execute([
            'user_id' => $participantId,
            'event_id' => $eventId,
        ]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function reactivatableRegistration(int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT id, event_id
             FROM registrations
             WHERE id = :registration_id
               AND status IN ('cancelled', 'refunded')
             LIMIT 1",
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $this->rowOrNull($statement->fetch());
    }

    private function rowOrNull(mixed $row): ?array
    {
        return is_array($row) ? $row : null;
    }
}
