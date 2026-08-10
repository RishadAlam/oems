<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Support\Money;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class RegistrationRepository implements RegistrationRepositoryInterface
{
    private const RESERVING_STATUSES = ['pending', 'confirmed'];

    private const ORGANIZER_FILTERS = [
        'registration_status' => ['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'],
        'payment_status' => ['none', 'pending', 'paid', 'failed', 'refunded', 'partially_refunded'],
        'ticket_status' => ['none', 'valid', 'used', 'cancelled'],
        'attendance_status' => ['not_checked_in', 'present', 'absent'],
    ];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findEligibleEventForReservation(int $eventId): ?array
    {
        return $this->eligibleEvent($eventId);
    }

    public function lockEventCurrent(int $eventId): bool
    {
        $lockingClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            'SELECT id FROM events WHERE id = :event_id LIMIT 1' . $lockingClause,
        );
        $statement->execute(['event_id' => $eventId]);

        return $statement->fetchColumn() !== false;
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

    public function findForParticipantEventCurrent(int $participantId, int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE registrations.user_id = :user_id AND registrations.event_id = :event_id LIMIT 1'
            . $this->lockingClause(),
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

    public function findForParticipantCurrent(int $participantId, int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE registrations.user_id = :user_id AND registrations.id = :registration_id LIMIT 1'
            . $this->lockingClause(),
        );
        $statement->execute([
            'registration_id' => $registrationId,
            'user_id' => $participantId,
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

    public function dueReminderRecipients(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit,
        int $offset = 0,
    ): array {
        $statement = $this->connection->prepare(
            "SELECT registrations.id AS registration_id,
                    registrations.status AS registration_status,
                    events.id AS event_id,
                    events.title AS event_title,
                    events.start_date,
                    events.status AS event_status,
                    events.deleted_at AS event_deleted_at,
                    users.id AS user_id,
                    users.name AS participant_name,
                    users.email AS recipient_email,
                    users.status AS user_status,
                    users.email_verified_at,
                    users.deleted_at AS user_deleted_at
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id
             WHERE registrations.status = 'confirmed'
               AND events.status = 'published'
               AND events.deleted_at IS NULL
               AND users.status = 'active'
               AND users.email_verified_at IS NOT NULL
               AND users.deleted_at IS NULL
               AND events.start_date > :reminder_from
               AND events.start_date <= :reminder_to
             ORDER BY events.start_date ASC, registrations.id ASC
             LIMIT :reminder_limit OFFSET :reminder_offset",
        );
        $statement->bindValue('reminder_from', $from->format('Y-m-d H:i:s'));
        $statement->bindValue('reminder_to', $to->format('Y-m-d H:i:s'));
        $statement->bindValue('reminder_limit', min(100, max(1, $limit)), PDO::PARAM_INT);
        $statement->bindValue('reminder_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findCalendarForParticipant(int $participantId, int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT registrations.id,
                    registrations.id AS registration_id,
                    registrations.status,
                    registrations.status AS registration_status,
                    events.id AS event_id,
                    events.title,
                    events.title AS event_title,
                    events.slug AS event_slug,
                    events.description,
                    events.start_date,
                    events.end_date,
                    events.status AS event_status,
                    events.location_visibility,
                    venues.name AS venue_name,
                    venues.address_line AS venue_address_line,
                    venues.city AS venue_city,
                    venues.country AS venue_country
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id
             LEFT JOIN venues ON venues.id = events.venue_id
             WHERE registrations.user_id = :calendar_user_id
                  AND registrations.id = :calendar_registration_id
                  AND registrations.status = 'confirmed'
                  AND events.status IN ('published', 'completed')
                  AND events.deleted_at IS NULL
                  AND users.status = 'active'
                  AND users.email_verified_at IS NOT NULL
                  AND users.deleted_at IS NULL
                LIMIT 1",
        );
        $statement->execute([
            'calendar_user_id' => $participantId,
            'calendar_registration_id' => $registrationId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function findOrganizerEvent(int $organizerUserId, int $eventId): ?array
    {
        if ($organizerUserId <= 0 || $eventId <= 0) {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT events.id AS event_id,
                    events.title AS event_title,
                    events.slug AS event_slug,
                    events.status AS event_status,
                    organizers.user_id AS organizer_user_id
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE events.id = :event_id
               AND organizers.user_id = :organizer_user_id
               AND events.deleted_at IS NULL
             LIMIT 1',
        );
        $statement->execute([
            'event_id' => $eventId,
            'organizer_user_id' => $organizerUserId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function forOrganizerEvent(
        int $organizerUserId,
        int $eventId,
        array $filters,
        int $limit,
        int $offset,
    ): array {
        if ($organizerUserId <= 0 || $eventId <= 0) {
            return [];
        }

        [$clauses, $parameters] = $this->organizerParticipantCriteria($organizerUserId, $eventId, $filters);
        $statement = $this->connection->prepare(
            $this->organizerParticipantSelect()
            . ' WHERE ' . implode(' AND ', $clauses)
            . ' ORDER BY registrations.registered_at DESC, registrations.id DESC
                LIMIT :participant_limit OFFSET :participant_offset',
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->bindValue('participant_limit', min(100, max(1, $limit)), PDO::PARAM_INT);
        $statement->bindValue('participant_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countForOrganizerEvent(int $organizerUserId, int $eventId, array $filters): int
    {
        if ($organizerUserId <= 0 || $eventId <= 0) {
            return 0;
        }

        [$clauses, $parameters] = $this->organizerParticipantCriteria($organizerUserId, $eventId, $filters);
        $statement = $this->connection->prepare(
            'SELECT COUNT(*)
             FROM registrations
             INNER JOIN users ON users.id = registrations.user_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             LEFT JOIN payments ON payments.id = (
                 SELECT latest_payment.id FROM payments AS latest_payment
                 WHERE latest_payment.registration_id = registrations.id
                 ORDER BY latest_payment.created_at DESC, latest_payment.id DESC LIMIT 1
             )
             LEFT JOIN tickets ON tickets.registration_id = registrations.id
             LEFT JOIN attendance ON attendance.ticket_id = tickets.id
             WHERE ' . implode(' AND ', $clauses),
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function reserve(int $participantId, int $eventId, array $attributes): ?array
    {
        return $this->transactional(
            fn (): ?array => $this->reserveWithinTransaction($participantId, $eventId, $attributes),
        );
    }

    private function reserveWithinTransaction(int $participantId, int $eventId, array $attributes): ?array
    {
        $status = (string) ($attributes['status'] ?? 'pending');

        if (!in_array($status, self::RESERVING_STATUSES, true)) {
            return null;
        }

        $event = $this->eligibleEvent($eventId);

        if ($event === null
            || $this->registrationIdFor($participantId, $eventId) !== null
            || $this->reservedCount($eventId) >= (int) $event['capacity']) {
            return null;
        }
        $amount = $this->reservationAmount($event, $attributes);
        if ($amount === null) return null;

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
                'amount' => $amount,
                'currency' => (string) $event['currency'],
                'registered_at' => (string) $attributes['registered_at'],
            ]);
        } catch (PDOException $exception) {
            if ($this->registrationIdFor($participantId, $eventId) !== null) {
                return null;
            }

            throw $exception;
        }

        if ($statement->rowCount() !== 1) {
            return null;
        }

        $registrationId = (int) $this->connection->lastInsertId();

        if (!$this->consumeSeat($eventId)) {
            throw new RuntimeException('The reserved event seat could not be consumed.');
        }

        return $this->findForParticipant($participantId, $registrationId);
    }

    public function reactivate(int $registrationId, array $attributes): bool
    {
        return $this->transactional(
            fn (): bool => $this->reactivateWithinTransaction($registrationId, $attributes),
        );
    }

    private function reactivateWithinTransaction(int $registrationId, array $attributes): bool
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
        $registration = $this->reactivatableRegistration($registrationId, true);

        if ($event === null
            || $registration === null
            || $this->reservedCount($eventId) >= (int) $event['capacity']) {
            return false;
        }
        $amount = $this->reservationAmount($event, $attributes);
        if ($amount === null) return false;

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
            'amount' => $amount,
            'currency' => (string) $event['currency'],
            'registered_at' => (string) $attributes['registered_at'],
            'registration_id' => $registrationId,
        ]);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        if (!$this->consumeSeat($eventId)) {
            throw new RuntimeException('The reactivated event seat could not be consumed.');
        }

        return true;
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

    public function cancel(int $registrationId, string $reason): bool
    {
        return $this->transactional(
            fn (): bool => $this->cancelWithinTransaction($registrationId, $reason),
        );
    }

    private function cancelWithinTransaction(int $registrationId, string $reason): bool
    {
        $identity = $this->registrationIdentity($registrationId);

        if ($identity === null || !$this->lockEventCurrent((int) $identity['event_id'])) {
            return false;
        }

        $registration = $this->cancellableRegistration($registrationId);

        if ($registration === null) {
            return false;
        }

        $statement = $this->connection->prepare(
            "UPDATE registrations
             SET status = 'cancelled',
                 cancelled_at = CURRENT_TIMESTAMP,
                 cancellation_reason = :cancellation_reason,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :registration_id
               AND status IN ('pending', 'confirmed')",
        );
        $statement->execute([
            'cancellation_reason' => $reason,
            'registration_id' => $registrationId,
        ]);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        $this->restoreSeat((int) $registration['event_id']);

        return true;
    }

    public function cancelForParticipant(int $participantId, int $registrationId, string $reason): ?array
    {
        return $this->transactional(
            fn (): ?array => $this->cancelForParticipantWithinTransaction(
                $participantId,
                $registrationId,
                $reason,
            ),
        );
    }

    private function cancelForParticipantWithinTransaction(
        int $participantId,
        int $registrationId,
        string $reason,
    ): ?array
    {
        $identity = $this->registrationIdentity($registrationId, $participantId);

        if ($identity === null || !$this->lockEventCurrent((int) $identity['event_id'])) {
            return null;
        }

        $registration = $this->cancellableRegistration($registrationId, $participantId);

        if ($registration === null) {
            return null;
        }

        $statement = $this->connection->prepare(
            "UPDATE registrations
             SET status = 'cancelled',
                 cancelled_at = CURRENT_TIMESTAMP,
                 cancellation_reason = :cancellation_reason,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :registration_id
               AND user_id = :user_id
               AND status IN ('pending', 'confirmed')
               AND EXISTS (
                   SELECT 1 FROM events
                   WHERE events.id = registrations.event_id
                     AND events.start_date > CURRENT_TIMESTAMP
               )
               AND NOT EXISTS (
                   SELECT 1 FROM attendance
                   WHERE attendance.registration_id = registrations.id
               )",
        );
        $statement->execute([
            'cancellation_reason' => $reason,
            'registration_id' => $registrationId,
            'user_id' => $participantId,
        ]);

        if ($statement->rowCount() !== 1) {
            return null;
        }

        $this->restoreSeat((int) $registration['event_id']);

        return $this->findForParticipant($participantId, $registrationId);
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->statusSummary(
            'INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id',
            'WHERE registrations.user_id = :participant_user_id AND events.deleted_at IS NULL AND users.deleted_at IS NULL',
            ['participant_user_id' => $participantId],
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->statusSummary(
            'INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             INNER JOIN users ON users.id = registrations.user_id',
            'WHERE organizers.user_id = :organizer_user_id AND events.deleted_at IS NULL AND users.deleted_at IS NULL',
            ['organizer_user_id' => $organizerUserId],
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->statusSummary(
            'INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id',
            'WHERE events.deleted_at IS NULL AND users.deleted_at IS NULL',
            [],
        );
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
                       registrations.waitlisted_at,
                       registrations.promoted_at,
                       registrations.waitlist_claim_expires_at,
                       registrations.cancelled_at,
                       registrations.cancellation_reason,
                       registrations.created_at,
                       registrations.updated_at,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       events.start_date AS event_start_date,
                       events.registration_deadline,
                       events.status AS event_status,
                       events.location_visibility,
                       events.arrival_notes,
                       events.ticket_price,
                       events.currency AS event_currency,
                       venues.name AS venue_name,
                       venues.address_line AS venue_address_line,
                       venues.city AS venue_city,
                       venues.country AS venue_country,
                       venues.postal_code AS venue_postal_code,
                       venues.latitude AS venue_latitude,
                       venues.longitude AS venue_longitude,
                       venues.map_url AS venue_map_url
                FROM registrations
                INNER JOIN events ON events.id = registrations.event_id
                LEFT JOIN venues ON venues.id = events.venue_id';
    }

    private function organizerParticipantSelect(): string
    {
        return 'SELECT registrations.id,
                       registrations.registration_number,
                       registrations.status AS registration_status,
                       registrations.amount,
                       registrations.currency,
                       registrations.registered_at,
                       registrations.waitlisted_at,
                       registrations.promoted_at,
                       registrations.waitlist_claim_expires_at,
                       users.name AS participant_name,
                       users.email AS participant_email,
                       events.id AS event_id,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       organizers.user_id AS organizer_user_id,
                       COALESCE(payments.status, \'none\') AS payment_status,
                       tickets.ticket_number,
                       COALESCE(tickets.status, \'none\') AS ticket_status,
                       COALESCE(attendance.status, \'not_checked_in\') AS attendance_status,
                       attendance.scanned_at
                FROM registrations
                INNER JOIN users ON users.id = registrations.user_id
                INNER JOIN events ON events.id = registrations.event_id
                INNER JOIN organizers ON organizers.id = events.organizer_id
                LEFT JOIN payments ON payments.id = (
                    SELECT latest_payment.id FROM payments AS latest_payment
                    WHERE latest_payment.registration_id = registrations.id
                    ORDER BY latest_payment.created_at DESC, latest_payment.id DESC LIMIT 1
                )
                LEFT JOIN tickets ON tickets.registration_id = registrations.id
                LEFT JOIN attendance ON attendance.ticket_id = tickets.id';
    }

    private function organizerParticipantCriteria(int $organizerUserId, int $eventId, array $filters): array
    {
        $clauses = [
            'organizers.user_id = :organizer_user_id',
            'registrations.event_id = :event_id',
            'events.deleted_at IS NULL',
            'users.deleted_at IS NULL',
        ];
        $parameters = [
            'organizer_user_id' => $organizerUserId,
            'event_id' => $eventId,
        ];
        $columns = [
            'registration_status' => 'registrations.status',
            'payment_status' => "COALESCE(payments.status, 'none')",
            'ticket_status' => "COALESCE(tickets.status, 'none')",
            'attendance_status' => "COALESCE(attendance.status, 'not_checked_in')",
        ];

        foreach (self::ORGANIZER_FILTERS as $filter => $allowed) {
            $value = $filters[$filter] ?? null;

            if (is_string($value) && in_array($value, $allowed, true)) {
                $clauses[] = $columns[$filter] . ' = :' . $filter;
                $parameters[$filter] = $value;
            }
        }

        $search = is_scalar($filters['search'] ?? null) ? trim((string) $filters['search']) : '';

        if ($search !== '' && mb_strlen($search) > 120) {
            $clauses[] = '1 = 0';
        } elseif ($search !== '') {
            $parameters['participant_search'] = '%' . mb_strtolower($search) . '%';
            $clauses[] = '(LOWER(users.name) LIKE :participant_search_name
                OR LOWER(users.email) LIKE :participant_search_email
                OR LOWER(registrations.registration_number) LIKE :participant_search_registration
                OR LOWER(COALESCE(tickets.ticket_number, \'\')) LIKE :participant_search_ticket)';
            $parameters['participant_search_name'] = $parameters['participant_search'];
            $parameters['participant_search_email'] = $parameters['participant_search'];
            $parameters['participant_search_registration'] = $parameters['participant_search'];
            $parameters['participant_search_ticket'] = $parameters['participant_search'];
            unset($parameters['participant_search']);
        }

        return [$clauses, $parameters];
    }

    private function eligibleEvent(int $eventId): ?array
    {
        $lockingClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            'SELECT events.id,
                    events.title,
                    events.slug,
                    events.start_date,
                    events.registration_deadline,
                    events.capacity,
                    events.available_seats,
                    events.ticket_price,
                    events.currency,
                    venues.name AS venue_name
             FROM events
             INNER JOIN categories ON categories.id = events.category_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             LEFT JOIN venues ON venues.id = events.venue_id
             WHERE events.id = :event_id
               AND events.status = :published_status
               AND events.deleted_at IS NULL
               AND categories.is_active = :active_category
               AND events.registration_deadline > CURRENT_TIMESTAMP
               AND events.start_date > CURRENT_TIMESTAMP
               AND events.available_seats > 0
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

    private function reactivatableRegistration(int $registrationId, bool $current = false): ?array
    {
        $lockingClause = $current && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            "SELECT id, event_id
             FROM registrations
             WHERE id = :registration_id
               AND status IN ('cancelled', 'refunded')
             LIMIT 1" . $lockingClause,
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $this->rowOrNull($statement->fetch());
    }

    private function registrationIdentity(int $registrationId, ?int $participantId = null): ?array
    {
        $query = 'SELECT id, event_id FROM registrations WHERE id = :registration_id';
        $parameters = ['registration_id' => $registrationId];

        if ($participantId !== null) {
            $query .= ' AND user_id = :user_id';
            $parameters['user_id'] = $participantId;
        }

        $statement = $this->connection->prepare($query . ' LIMIT 1');
        $statement->execute($parameters);

        return $this->rowOrNull($statement->fetch());
    }

    private function cancellableRegistration(int $registrationId, ?int $participantId = null): ?array
    {
        $lockingClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $query = "SELECT id, event_id
                  FROM registrations
                  WHERE id = :registration_id
                    AND status IN ('pending', 'confirmed')";
        $parameters = ['registration_id' => $registrationId];

        if ($participantId !== null) {
            $query .= ' AND user_id = :user_id';
            $parameters['user_id'] = $participantId;
        }

        $statement = $this->connection->prepare($query . ' LIMIT 1' . $lockingClause);
        $statement->execute($parameters);

        return $this->rowOrNull($statement->fetch());
    }

    private function lockingClause(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
    }

    private function consumeSeat(int $eventId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE events
             SET available_seats = available_seats - 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :event_id AND available_seats > 0',
        );
        $statement->execute(['event_id' => $eventId]);

        return $statement->rowCount() === 1;
    }

    private function reservationAmount(array $event, array $attributes): ?string
    {
        $base = Money::minorUnits((string) ($event['ticket_price'] ?? ''));
        if (($attributes['coupon_id'] ?? null) === null) {
            return $base === null ? null : intdiv($base, 100) . '.' . str_pad((string) ($base % 100), 2, '0', STR_PAD_LEFT);
        }

        $amount = Money::minorUnits(is_scalar($attributes['amount'] ?? null) ? (string) $attributes['amount'] : (string) ($event['ticket_price'] ?? ''));
        if ($base === null || $amount === null || $amount > $base) return null;
        return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    private function restoreSeat(int $eventId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE events
             SET available_seats = available_seats + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :event_id
               AND available_seats < capacity',
        );
        $statement->execute(['event_id' => $eventId]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The cancelled event seat could not be restored.');
        }
    }

    private function transactional(callable $operation): mixed
    {
        $ownsTransaction = !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $result = $operation();

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    private function rowOrNull(mixed $row): ?array
    {
        return is_array($row) ? $row : null;
    }
}
