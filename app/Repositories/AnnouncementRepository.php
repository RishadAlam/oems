<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\AnnouncementRepositoryInterface;
use PDO;
use Throwable;

final class AnnouncementRepository implements AnnouncementRepositoryInterface
{
    private readonly string $driver;

    public function __construct(private readonly PDO $connection)
    {
        $this->driver = (string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function findOwnedEvent(int $organizerUserId, int $eventId): ?array
    {
        if ($organizerUserId <= 0 || $eventId <= 0) {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT events.id, events.title, events.status, events.organizer_id,
                    organizers.organization_name,
                    organizers.approval_status AS organizer_approval_status,
                    users.status AS organizer_user_status,
                    users.email_verified_at AS organizer_email_verified_at,
                    users.deleted_at AS organizer_deleted_at,
                    roles.slug AS organizer_role
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             INNER JOIN users ON users.id = organizers.user_id
             INNER JOIN roles ON roles.id = users.role_id
             WHERE events.id = :event_id
               AND organizers.user_id = :organizer_user_id
               AND events.deleted_at IS NULL
             LIMIT 1',
        );
        $statement->execute([
            'event_id' => $eventId,
            'organizer_user_id' => $organizerUserId,
        ]);
        $event = $statement->fetch();

        return is_array($event) ? $event : null;
    }

    public function historyForOwnedEvent(int $organizerUserId, int $eventId, int $limit): array
    {
        if ($organizerUserId <= 0 || $eventId <= 0) {
            return [];
        }

        $statement = $this->connection->prepare(
            'SELECT event_announcements.id, event_announcements.event_id,
                    event_announcements.sent_by, event_announcements.subject,
                    event_announcements.message, event_announcements.audience,
                    event_announcements.recipient_count, event_announcements.sent_at,
                    event_announcements.created_at, users.name AS author_name
             FROM event_announcements
             INNER JOIN events ON events.id = event_announcements.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             LEFT JOIN users ON users.id = event_announcements.sent_by
             WHERE event_announcements.event_id = :event_id
               AND organizers.user_id = :organizer_user_id
               AND events.deleted_at IS NULL
             ORDER BY event_announcements.sent_at DESC, event_announcements.id DESC
             LIMIT :limit',
        );
        $statement->bindValue('event_id', $eventId, PDO::PARAM_INT);
        $statement->bindValue('organizer_user_id', $organizerUserId, PDO::PARAM_INT);
        $statement->bindValue('limit', min(50, max(1, $limit)), PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();

        return is_array($items)
            ? array_map($this->hydrate(...), $items)
            : [];
    }

    public function deliverToConfirmedParticipants(
        int $organizerUserId,
        int $eventId,
        string $subject,
        string $message,
        string $requestKey,
        array $context,
    ): array {
        $transaction = $this->beginTransactionBoundary();

        try {
            $event = $this->lockedOwnedEvent($organizerUserId, $eventId);
            if ($event === null) {
                $this->commitTransactionBoundary($transaction);

                return ['status' => 'not_found'];
            }

            $existing = $this->findByRequestKey($organizerUserId, $eventId, $requestKey);
            if ($existing !== null) {
                $this->commitTransactionBoundary($transaction);

                return array_merge($existing, ['status' => 'replayed']);
            }

            if (!$this->eventIsEligible($event)) {
                $this->commitTransactionBoundary($transaction);

                return ['status' => 'ineligible'];
            }

            $announcement = $this->connection->prepare(
                'INSERT INTO event_announcements
                    (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at, created_at)
                 VALUES
                    (:event_id, :sent_by, :subject, :message, \'confirmed\', 0, :request_key, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );
            $announcement->execute([
                'event_id' => $eventId,
                'sent_by' => $organizerUserId,
                'subject' => $subject,
                'message' => $message,
                'request_key' => $requestKey,
            ]);
            $announcementId = (int) $this->connection->lastInsertId();
            $notificationData = json_encode([
                'event_id' => $eventId,
                'announcement_id' => $announcementId,
            ], JSON_THROW_ON_ERROR);
            $actionExpression = $this->driver === 'mysql'
                ? "CONCAT('/participant/registrations/', registrations.id)"
                : "'/participant/registrations/' || registrations.id";
            $notifications = $this->connection->prepare(
                'INSERT INTO notifications
                    (user_id, type, title, message, action_url, data, created_at)
                 SELECT DISTINCT registrations.user_id, \'event_announcement\', :title, :message,
                        ' . $actionExpression . ', :data, CURRENT_TIMESTAMP
                 FROM registrations
                 INNER JOIN users ON users.id = registrations.user_id
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE registrations.event_id = :event_id
                   AND registrations.status = \'confirmed\'
                   AND registrations.cancelled_at IS NULL
                   AND users.status = \'active\'
                   AND users.email_verified_at IS NOT NULL
                   AND users.deleted_at IS NULL
                   AND roles.slug = \'participant\'',
            );
            $notifications->execute([
                'title' => $subject,
                'message' => $message,
                'data' => $notificationData,
                'event_id' => $eventId,
            ]);
            $recipientCount = $notifications->rowCount();

            if ($recipientCount === 0) {
                $this->rollBackTransactionBoundary($transaction);

                return ['status' => 'no_recipients'];
            }

            $count = $this->connection->prepare(
                'UPDATE event_announcements
                 SET recipient_count = :recipient_count
                 WHERE id = :announcement_id AND recipient_count = 0',
            );
            $count->execute([
                'recipient_count' => $recipientCount,
                'announcement_id' => $announcementId,
            ]);

            if ($count->rowCount() !== 1) {
                throw new \RuntimeException('The announcement recipient count could not be persisted.');
            }

            $this->writeActivity(
                $organizerUserId,
                $announcementId,
                $eventId,
                $recipientCount,
                $context,
            );
            $created = $this->findByRequestKey($organizerUserId, $eventId, $requestKey);
            if ($created === null) {
                throw new \RuntimeException('The persisted announcement could not be reloaded.');
            }

            $this->commitTransactionBoundary($transaction);

            return array_merge($created, ['status' => 'sent']);
        } catch (Throwable $exception) {
            $this->rollBackTransactionBoundary($transaction);

            throw $exception;
        }
    }

    private function lockedOwnedEvent(int $organizerUserId, int $eventId): ?array
    {
        $lock = $this->driver === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare(
            'SELECT events.id, events.title, events.status, events.organizer_id,
                    organizers.approval_status AS organizer_approval_status,
                    users.status AS organizer_user_status,
                    users.email_verified_at AS organizer_email_verified_at,
                    users.deleted_at AS organizer_deleted_at,
                    roles.slug AS organizer_role
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             INNER JOIN users ON users.id = organizers.user_id
             INNER JOIN roles ON roles.id = users.role_id
             WHERE events.id = :event_id
               AND organizers.user_id = :organizer_user_id
               AND events.deleted_at IS NULL
             LIMIT 1' . $lock,
        );
        $statement->execute([
            'event_id' => $eventId,
            'organizer_user_id' => $organizerUserId,
        ]);
        $event = $statement->fetch();

        return is_array($event) ? $event : null;
    }

    private function findByRequestKey(int $organizerUserId, int $eventId, string $requestKey): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT event_announcements.id, event_announcements.event_id,
                    event_announcements.sent_by, event_announcements.subject,
                    event_announcements.message, event_announcements.audience,
                    event_announcements.recipient_count, event_announcements.sent_at,
                    event_announcements.created_at
             FROM event_announcements
             INNER JOIN events ON events.id = event_announcements.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE event_announcements.event_id = :event_id
               AND event_announcements.request_key = :request_key
               AND organizers.user_id = :organizer_user_id
             LIMIT 1',
        );
        $statement->execute([
            'event_id' => $eventId,
            'request_key' => $requestKey,
            'organizer_user_id' => $organizerUserId,
        ]);
        $announcement = $statement->fetch();

        return is_array($announcement) ? $this->hydrate($announcement) : null;
    }

    private function eventIsEligible(array $event): bool
    {
        return in_array($event['status'] ?? null, ['published', 'completed'], true)
            && ($event['organizer_approval_status'] ?? null) === 'approved'
            && ($event['organizer_user_status'] ?? null) === 'active'
            && !empty($event['organizer_email_verified_at'])
            && empty($event['organizer_deleted_at'])
            && ($event['organizer_role'] ?? null) === 'organizer';
    }

    private function writeActivity(
        int $organizerUserId,
        int $announcementId,
        int $eventId,
        int $recipientCount,
        array $context,
    ): void {
        $properties = json_encode([
            'event_id' => $eventId,
            'audience' => 'confirmed',
            'recipient_count' => $recipientCount,
        ], JSON_THROW_ON_ERROR);
        $ip = is_scalar($context['ip_address'] ?? null)
            ? mb_substr(trim((string) $context['ip_address']), 0, 45)
            : null;
        $agent = is_scalar($context['user_agent'] ?? null)
            ? mb_substr(trim((string) $context['user_agent']), 0, 500)
            : null;
        $statement = $this->connection->prepare(
            'INSERT INTO activity_logs
                (user_id, action, subject_type, subject_id, description, properties, ip_address, user_agent, created_at)
             VALUES
                (:user_id, \'announcement.sent\', \'event_announcement\', :subject_id,
                 \'Organizer announcement sent to confirmed participants.\', :properties,
                 :ip_address, :user_agent, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'user_id' => $organizerUserId,
            'subject_id' => $announcementId,
            'properties' => $properties,
            'ip_address' => $ip === '' ? null : $ip,
            'user_agent' => $agent === '' ? null : $agent,
        ]);
    }

    private function hydrate(array $announcement): array
    {
        foreach (['id', 'event_id', 'sent_by', 'recipient_count'] as $key) {
            if (array_key_exists($key, $announcement) && $announcement[$key] !== null) {
                $announcement[$key] = (int) $announcement[$key];
            }
        }

        return $announcement;
    }

    /** @return array{started: bool, savepoint: ?string} */
    private function beginTransactionBoundary(): array
    {
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();

            return ['started' => true, 'savepoint' => null];
        }

        $savepoint = 'announcement_delivery';
        $this->connection->exec('SAVEPOINT ' . $savepoint);

        return ['started' => false, 'savepoint' => $savepoint];
    }

    /** @param array{started: bool, savepoint: ?string} $transaction */
    private function commitTransactionBoundary(array $transaction): void
    {
        if ($transaction['started']) {
            if ($this->connection->inTransaction()) {
                $this->connection->commit();
            }

            return;
        }

        if ($transaction['savepoint'] !== null) {
            $this->connection->exec('RELEASE SAVEPOINT ' . $transaction['savepoint']);
        }
    }

    /** @param array{started: bool, savepoint: ?string} $transaction */
    private function rollBackTransactionBoundary(array $transaction): void
    {
        if ($transaction['started']) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            return;
        }

        if ($transaction['savepoint'] !== null && $this->connection->inTransaction()) {
            $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $transaction['savepoint']);
            $this->connection->exec('RELEASE SAVEPOINT ' . $transaction['savepoint']);
        }
    }
}
