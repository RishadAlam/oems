<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use OEMS\App\Contracts\WaitlistRepositoryInterface;
use OEMS\App\Support\Money;
use PDO;
use PDOException;
use Throwable;

final class WaitlistRepository implements WaitlistRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findJoinableEvent(int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect()
            . " WHERE events.id = :event_id
                AND events.status = 'published'
                AND events.deleted_at IS NULL
                AND events.waitlist_enabled = 1
                AND events.available_seats = 0
                AND events.registration_deadline > CURRENT_TIMESTAMP
                AND events.start_date > CURRENT_TIMESTAMP
                AND categories.is_active = 1
                AND organizers.approval_status = 'approved'
              LIMIT 1",
        );
        $statement->execute(['event_id' => $eventId]);

        return $this->hydrateEvent($statement->fetch());
    }

    public function findParticipantEntry(int $participantId, int $eventId): ?array
    {
        return $this->entry('registrations.user_id = :participant_id AND registrations.event_id = :event_id', [
            'participant_id' => $participantId,
            'event_id' => $eventId,
        ]);
    }

    public function findParticipantEntryById(int $participantId, int $registrationId): ?array
    {
        return $this->entry('registrations.user_id = :participant_id AND registrations.id = :registration_id', [
            'participant_id' => $participantId,
            'registration_id' => $registrationId,
        ]);
    }

    public function forParticipant(int $participantId): array
    {
        $statement = $this->connection->prepare(
            $this->entrySelect()
            . " WHERE registrations.user_id = :participant_id
                AND registrations.status = 'waitlisted'
                AND events.deleted_at IS NULL
                AND users.deleted_at IS NULL
              ORDER BY registrations.waitlisted_at ASC, registrations.id ASC",
        );
        $statement->execute(['participant_id' => $participantId]);

        return array_map($this->hydrateEntry(...), $statement->fetchAll());
    }

    public function join(int $participantId, int $eventId, array $attributes): ?array
    {
        return $this->transactional(function () use ($participantId, $eventId, $attributes): ?array {
            $event = $this->lockedJoinableEvent($eventId);
            if ($event === null) {
                return null;
            }

            $existing = $this->lockedParticipantEntry($participantId, $eventId);
            if (($existing['status'] ?? null) === 'waitlisted') {
                return $existing;
            }

            $number = trim((string) ($attributes['registration_number'] ?? ''));
            $joinedAt = trim((string) ($attributes['waitlisted_at'] ?? ''));
            if ($number === '' || $joinedAt === '') {
                return null;
            }

            if ($existing !== null) {
                if (!in_array((string) ($existing['status'] ?? ''), ['cancelled', 'refunded'], true)
                    || $this->hasFulfillment((int) $existing['id'])) {
                    return null;
                }
                $statement = $this->connection->prepare(
                    "UPDATE registrations
                     SET registration_number = :registration_number,
                         status = 'waitlisted',
                         amount = :amount,
                         currency = :currency,
                         registered_at = :registered_at,
                         waitlisted_at = :waitlisted_at,
                         promoted_at = NULL,
                         waitlist_claim_expires_at = NULL,
                         cancelled_at = NULL,
                         cancellation_reason = NULL,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :registration_id
                       AND user_id = :participant_id
                       AND status IN ('cancelled', 'refunded')",
                );
                $statement->execute([
                    'registration_number' => $number,
                    'amount' => (string) $event['ticket_price'],
                    'currency' => (string) $event['currency'],
                    'registered_at' => $joinedAt,
                    'waitlisted_at' => $joinedAt,
                    'registration_id' => (int) $existing['id'],
                    'participant_id' => $participantId,
                ]);
                if ($statement->rowCount() !== 1) {
                    return null;
                }
            } else {
                $statement = $this->connection->prepare(
                    "INSERT INTO registrations
                        (event_id, user_id, registration_number, status, amount, currency, registered_at, waitlisted_at)
                     VALUES
                        (:event_id, :participant_id, :registration_number, 'waitlisted', :amount, :currency, :registered_at, :waitlisted_at)",
                );
                try {
                    $statement->execute([
                        'event_id' => $eventId,
                        'participant_id' => $participantId,
                        'registration_number' => $number,
                        'amount' => (string) $event['ticket_price'],
                        'currency' => (string) $event['currency'],
                        'registered_at' => $joinedAt,
                        'waitlisted_at' => $joinedAt,
                    ]);
                } catch (PDOException $exception) {
                    $winner = $this->lockedParticipantEntry($participantId, $eventId);
                    if (($winner['status'] ?? null) === 'waitlisted') {
                        return $winner;
                    }
                    throw $exception;
                }
            }

            return $this->lockedParticipantEntry($participantId, $eventId);
        });
    }

    public function leave(int $participantId, int $registrationId, string $reason, DateTimeImmutable $leftAt): ?array
    {
        return $this->transactional(function () use ($participantId, $registrationId, $reason, $leftAt): ?array {
            $current = $this->lockedEntryById($participantId, $registrationId);
            if ($current === null) {
                return null;
            }
            if (($current['status'] ?? null) === 'cancelled') {
                return $current;
            }
            if (($current['status'] ?? null) !== 'waitlisted') {
                return null;
            }
            $statement = $this->connection->prepare(
                "UPDATE registrations
                 SET status = 'cancelled',
                     waitlist_claim_expires_at = NULL,
                     cancelled_at = :cancelled_at,
                     cancellation_reason = :reason,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :registration_id
                   AND user_id = :participant_id
                   AND status = 'waitlisted'",
            );
            $statement->execute([
                'cancelled_at' => $leftAt->format('Y-m-d H:i:s'),
                'reason' => $reason,
                'registration_id' => $registrationId,
                'participant_id' => $participantId,
            ]);
            if ($statement->rowCount() !== 1) {
                return null;
            }

            return $this->lockedEntryById($participantId, $registrationId);
        });
    }

    public function position(int $registrationId): ?int
    {
        $statement = $this->connection->prepare(
            "SELECT 1 + (
                SELECT COUNT(*)
                FROM registrations AS ahead
                WHERE ahead.event_id = target.event_id
                  AND ahead.status = 'waitlisted'
                  AND (ahead.waitlisted_at < target.waitlisted_at
                       OR (ahead.waitlisted_at = target.waitlisted_at AND ahead.id < target.id))
             )
             FROM registrations AS target
             WHERE target.id = :registration_id AND target.status = 'waitlisted'
             LIMIT 1",
        );
        $statement->execute(['registration_id' => $registrationId]);
        $position = $statement->fetchColumn();

        return $position === false || (int) $position < 1 ? null : (int) $position;
    }

    public function claimOldest(
        int $eventId,
        DateTimeImmutable $promotedAt,
        DateTimeImmutable $claimExpiresAt,
    ): ?array {
        return $this->transactional(function () use ($eventId, $promotedAt, $claimExpiresAt): ?array {
            $event = $this->lockedPromotableEvent($eventId);
            if ($event === null) {
                return null;
            }
            $registrationDeadline = new DateTimeImmutable((string) $event['registration_deadline']);
            if ($registrationDeadline < $claimExpiresAt) {
                $claimExpiresAt = $registrationDeadline;
            }
            if ($claimExpiresAt <= $promotedAt) {
                return null;
            }
            $statement = $this->connection->prepare(
                "SELECT registrations.id, registrations.user_id
                 FROM registrations
                 INNER JOIN users ON users.id = registrations.user_id
                 WHERE registrations.event_id = :event_id
                   AND registrations.status = 'waitlisted'
                   AND users.status = 'active'
                   AND users.email_verified_at IS NOT NULL
                   AND users.deleted_at IS NULL
                   AND NOT EXISTS (SELECT 1 FROM payments WHERE payments.registration_id = registrations.id)
                   AND NOT EXISTS (SELECT 1 FROM tickets WHERE tickets.registration_id = registrations.id)
                 ORDER BY registrations.waitlisted_at ASC, registrations.id ASC
                 LIMIT 1" . $this->lockingClause(),
            );
            $statement->execute(['event_id' => $eventId]);
            $entry = $statement->fetch();
            if (!is_array($entry)) {
                return null;
            }

            $consume = $this->connection->prepare(
                'UPDATE events SET available_seats = available_seats - 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :event_id AND available_seats > 0',
            );
            $consume->execute(['event_id' => $eventId]);
            if ($consume->rowCount() !== 1) {
                return null;
            }

            $promote = $this->connection->prepare(
                "UPDATE registrations
                 SET status = 'pending', promoted_at = :promoted_at,
                     waitlist_claim_expires_at = :claim_expires_at,
                     amount = :amount, currency = :currency,
                     cancelled_at = NULL, cancellation_reason = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :registration_id AND status = 'waitlisted'",
            );
            $promote->execute([
                'promoted_at' => $promotedAt->format('Y-m-d H:i:s'),
                'claim_expires_at' => $claimExpiresAt->format('Y-m-d H:i:s'),
                'amount' => (string) $event['ticket_price'],
                'currency' => (string) $event['currency'],
                'registration_id' => (int) $entry['id'],
            ]);
            if ($promote->rowCount() !== 1) {
                throw new \RuntimeException('The waitlist promotion compare-and-swap was lost.');
            }

            return $this->lockedEntryById((int) $entry['user_id'], (int) $entry['id']);
        });
    }

    public function completeClaim(int $registrationId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE registrations
             SET waitlist_claim_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = :registration_id AND status IN ('pending', 'confirmed') AND promoted_at IS NOT NULL",
        );
        $statement->execute(['registration_id' => $registrationId]);
        if ($statement->rowCount() === 1) {
            return true;
        }
        $check = $this->connection->prepare(
            "SELECT 1 FROM registrations
             WHERE id = :registration_id AND status IN ('pending', 'confirmed')
               AND promoted_at IS NOT NULL AND waitlist_claim_expires_at IS NULL LIMIT 1",
        );
        $check->execute(['registration_id' => $registrationId]);

        return $check->fetchColumn() !== false;
    }

    public function expiredClaims(DateTimeImmutable $now, int $limit): array
    {
        $statement = $this->connection->prepare(
            "SELECT registrations.id, registrations.event_id, registrations.user_id,
                    registrations.waitlist_claim_expires_at
             FROM registrations
             WHERE registrations.status = 'pending'
               AND registrations.promoted_at IS NOT NULL
               AND registrations.waitlist_claim_expires_at IS NOT NULL
               AND registrations.waitlist_claim_expires_at <= :expired_at
               AND NOT EXISTS (SELECT 1 FROM payments WHERE payments.registration_id = registrations.id)
               AND NOT EXISTS (SELECT 1 FROM tickets WHERE tickets.registration_id = registrations.id)
             ORDER BY registrations.waitlist_claim_expires_at ASC, registrations.id ASC
             LIMIT :claim_limit",
        );
        $statement->bindValue('expired_at', $now->format('Y-m-d H:i:s'));
        $statement->bindValue('claim_limit', min(100, max(1, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['event_id'] = (int) $row['event_id'];
            $row['user_id'] = (int) $row['user_id'];
            return $row;
        }, $statement->fetchAll());
    }

    public function releaseExpiredClaim(int $registrationId, DateTimeImmutable $expiredAt): ?array
    {
        return $this->transactional(function () use ($registrationId, $expiredAt): ?array {
            $identity = $this->connection->prepare(
                'SELECT event_id FROM registrations WHERE id = :registration_id LIMIT 1',
            );
            $identity->execute(['registration_id' => $registrationId]);
            $eventId = $identity->fetchColumn();
            if ($eventId === false) {
                return null;
            }

            $eventLock = $this->connection->prepare(
                'SELECT id, capacity, available_seats FROM events WHERE id = :event_id LIMIT 1' . $this->lockingClause(),
            );
            $eventLock->execute(['event_id' => (int) $eventId]);
            $event = $eventLock->fetch();
            if (!is_array($event)) {
                return null;
            }

            $entry = $this->systemEntryById($registrationId, true);
            $claimExpires = trim((string) ($entry['waitlist_claim_expires_at'] ?? ''));
            if ($entry === null
                || ($entry['status'] ?? null) !== 'pending'
                || empty($entry['promoted_at'])
                || $claimExpires === ''
                || new DateTimeImmutable($claimExpires) > $expiredAt
                || $this->hasFulfillment($registrationId)) {
                return null;
            }

            $restore = $this->connection->prepare(
                'UPDATE events
                 SET available_seats = available_seats + 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :event_id AND available_seats < capacity',
            );
            $restore->execute(['event_id' => (int) $eventId]);
            if ($restore->rowCount() !== 1 && (int) $event['available_seats'] < (int) $event['capacity']) {
                throw new \RuntimeException('The expired waitlist seat could not be restored.');
            }

            $release = $this->connection->prepare(
                "UPDATE registrations
                 SET status = 'cancelled', waitlist_claim_expires_at = NULL,
                     cancelled_at = :cancelled_at,
                     cancellation_reason = 'Waitlist payment window expired',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :registration_id AND status = 'pending'
                   AND promoted_at IS NOT NULL AND waitlist_claim_expires_at <= :claim_expired_at
                   AND NOT EXISTS (SELECT 1 FROM payments WHERE payments.registration_id = registrations.id)
                   AND NOT EXISTS (SELECT 1 FROM tickets WHERE tickets.registration_id = registrations.id)",
            );
            $release->execute([
                'cancelled_at' => $expiredAt->format('Y-m-d H:i:s'),
                'registration_id' => $registrationId,
                'claim_expired_at' => $expiredAt->format('Y-m-d H:i:s'),
            ]);
            if ($release->rowCount() !== 1) {
                throw new \RuntimeException('The expired waitlist claim compare-and-swap was lost.');
            }

            return $this->systemEntryById($registrationId, true);
        });
    }

    public function eventsWithAvailableSeats(int $limit): array
    {
        $statement = $this->connection->prepare(
            "SELECT events.id
             FROM events
             INNER JOIN categories ON categories.id = events.category_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE events.status = 'published' AND events.deleted_at IS NULL
               AND events.waitlist_enabled = 1 AND events.available_seats > 0
               AND events.registration_deadline > CURRENT_TIMESTAMP AND events.start_date > CURRENT_TIMESTAMP
               AND categories.is_active = 1 AND organizers.approval_status = 'approved'
               AND EXISTS (SELECT 1 FROM registrations WHERE registrations.event_id = events.id AND registrations.status = 'waitlisted')
             ORDER BY events.start_date ASC, events.id ASC LIMIT :event_limit",
        );
        $statement->bindValue('event_limit', min(100, max(1, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function lockedJoinableEvent(int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect()
            . " WHERE events.id = :event_id AND events.status = 'published' AND events.deleted_at IS NULL
                AND events.waitlist_enabled = 1 AND events.available_seats = 0
                AND events.registration_deadline > CURRENT_TIMESTAMP AND events.start_date > CURRENT_TIMESTAMP
                AND categories.is_active = 1 AND organizers.approval_status = 'approved'
              LIMIT 1" . $this->lockingClause(),
        );
        $statement->execute(['event_id' => $eventId]);

        return $this->hydrateEvent($statement->fetch());
    }

    private function lockedPromotableEvent(int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect()
            . " WHERE events.id = :event_id AND events.status = 'published' AND events.deleted_at IS NULL
                AND events.waitlist_enabled = 1 AND events.available_seats > 0
                AND events.registration_deadline > CURRENT_TIMESTAMP AND events.start_date > CURRENT_TIMESTAMP
                AND categories.is_active = 1 AND organizers.approval_status = 'approved'
              LIMIT 1" . $this->lockingClause(),
        );
        $statement->execute(['event_id' => $eventId]);

        return $this->hydrateEvent($statement->fetch());
    }

    private function lockedParticipantEntry(int $participantId, int $eventId): ?array
    {
        return $this->entry(
            'registrations.user_id = :participant_id AND registrations.event_id = :event_id',
            ['participant_id' => $participantId, 'event_id' => $eventId],
            true,
        );
    }

    private function lockedEntryById(int $participantId, int $registrationId): ?array
    {
        return $this->entry(
            'registrations.user_id = :participant_id AND registrations.id = :registration_id',
            ['participant_id' => $participantId, 'registration_id' => $registrationId],
            true,
        );
    }

    private function systemEntryById(int $registrationId, bool $current = false): ?array
    {
        $statement = $this->connection->prepare(
            $this->entrySelect()
            . ' WHERE registrations.id = :registration_id LIMIT 1'
            . ($current ? $this->lockingClause() : ''),
        );
        $statement->execute(['registration_id' => $registrationId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrateEntry($row) : null;
    }

    private function entry(string $where, array $parameters, bool $current = false): ?array
    {
        $statement = $this->connection->prepare(
            $this->entrySelect() . ' WHERE ' . $where
            . ' AND events.deleted_at IS NULL AND users.deleted_at IS NULL'
            . ' LIMIT 1' . ($current ? $this->lockingClause() : ''),
        );
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrateEntry($row) : null;
    }

    private function eventSelect(): string
    {
        return 'SELECT events.id, events.title, events.slug, events.start_date, events.registration_deadline,
                       events.capacity, events.available_seats, events.ticket_price, events.currency,
                       events.status AS event_status, events.waitlist_enabled
                FROM events
                INNER JOIN categories ON categories.id = events.category_id
                INNER JOIN organizers ON organizers.id = events.organizer_id';
    }

    private function entrySelect(): string
    {
        return 'SELECT registrations.*, registrations.status AS registration_status,
                       events.title AS event_title, events.slug AS event_slug,
                       events.start_date AS event_start_date, events.registration_deadline,
                       events.ticket_price, events.currency AS event_currency,
                       events.status AS event_status, events.available_seats, events.waitlist_enabled,
                       users.name AS participant_name, users.email AS participant_email
                FROM registrations
                INNER JOIN events ON events.id = registrations.event_id
                INNER JOIN users ON users.id = registrations.user_id';
    }

    private function hasFulfillment(int $registrationId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT EXISTS(SELECT 1 FROM payments WHERE registration_id = :payment_registration)
                    OR EXISTS(SELECT 1 FROM tickets WHERE registration_id = :ticket_registration)',
        );
        $statement->execute([
            'payment_registration' => $registrationId,
            'ticket_registration' => $registrationId,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function hydrateEvent(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }
        $row['ticket_price'] = Money::normalize((string) ($row['ticket_price'] ?? ''));

        return $row['ticket_price'] === null ? null : $row;
    }

    private function hydrateEntry(array $row): array
    {
        $row['amount'] = Money::normalize((string) ($row['amount'] ?? '')) ?? '0.00';
        $row['ticket_price'] = Money::normalize((string) ($row['ticket_price'] ?? '')) ?? '0.00';

        return $row;
    }

    private function lockingClause(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function transactional(callable $operation): mixed
    {
        $owns = !$this->connection->inTransaction();
        if ($owns) {
            $this->connection->beginTransaction();
        }
        try {
            $result = $operation();
            if ($owns) {
                $this->connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
