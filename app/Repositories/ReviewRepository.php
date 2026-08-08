<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use OEMS\App\Contracts\ReviewRepositoryInterface;
use PDO;

final class ReviewRepository implements ReviewRepositoryInterface
{
    private const STATUSES = ['pending', 'published', 'hidden'];

    private readonly Closure $clock;

    public function __construct(private readonly PDO $connection, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
    }

    public function reviewableEventForParticipant(int $participantId, int $eventId): ?array
    {
        return $this->reviewableEventForParticipantAt($participantId, $eventId, $this->currentTime());
    }

    private function reviewableEventForParticipantAt(int $participantId, int $eventId, string $currentTime): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT events.id, events.title, events.slug, events.end_date, events.status AS event_status
             FROM events
             INNER JOIN registrations
                     ON registrations.event_id = events.id
                    AND registrations.user_id = :user_id
                    AND registrations.status = :registration_status
             WHERE events.id = :event_id
               AND events.deleted_at IS NULL
               AND (events.status = :completed_status OR events.end_date <= :eligibility_now)
             LIMIT 1',
        );
        $statement->execute([
            'user_id' => $participantId,
            'registration_status' => 'confirmed',
            'event_id' => $eventId,
            'completed_status' => 'completed',
            'eligibility_now' => $currentTime,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function reviewableEventsForParticipant(int $participantId): array
    {
        $currentTime = $this->currentTime();
        $statement = $this->connection->prepare(
            'SELECT events.id, events.title, events.slug, events.end_date, events.status AS event_status
             FROM events
             INNER JOIN registrations
                     ON registrations.event_id = events.id
                    AND registrations.user_id = :registration_user_id
                    AND registrations.status = :registration_status
             WHERE events.deleted_at IS NULL
               AND (events.status = :completed_status OR events.end_date <= :eligibility_now)
               AND NOT EXISTS (
                   SELECT 1 FROM reviews
                   WHERE reviews.event_id = events.id AND reviews.user_id = :review_user_id
               )
             ORDER BY events.end_date DESC, events.id DESC',
        );
        $statement->execute([
            'registration_user_id' => $participantId,
            'registration_status' => 'confirmed',
            'completed_status' => 'completed',
            'review_user_id' => $participantId,
            'eligibility_now' => $currentTime,
        ]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findForParticipantEvent(int $participantId, int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->reviewSelect()
            . ' WHERE reviews.user_id = :user_id AND reviews.event_id = :event_id LIMIT 1',
        );
        $statement->execute(['user_id' => $participantId, 'event_id' => $eventId]);

        return $this->rowOrNull($statement->fetch());
    }

    public function forParticipant(int $participantId): array
    {
        $statement = $this->connection->prepare(
            $this->reviewSelect()
            . ' WHERE reviews.user_id = :user_id'
            . ' ORDER BY reviews.updated_at DESC, reviews.id DESC',
        );
        $statement->execute(['user_id' => $participantId]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function saveForParticipant(int $participantId, int $eventId, array $attributes): int
    {
        $currentTime = $this->currentTime();
        $upsertClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    review = VALUES(review),
                    status = VALUES(status),
                    updated_at = CURRENT_TIMESTAMP'
            : ' ON CONFLICT(event_id, user_id) DO UPDATE SET
                    rating = excluded.rating,
                    review = excluded.review,
                    status = excluded.status,
                    updated_at = CURRENT_TIMESTAMP';
        $statement = $this->connection->prepare(
            'INSERT INTO reviews (event_id, user_id, rating, review, status)
             SELECT events.id, :insert_user_id, :insert_rating, :insert_review, :insert_pending_status
             FROM events
             INNER JOIN registrations
                     ON registrations.event_id = events.id
                    AND registrations.user_id = :registration_user_id
                    AND registrations.status = :registration_status
             WHERE events.id = :insert_event_id
               AND events.deleted_at IS NULL
               AND (events.status = :completed_status OR events.end_date <= :eligibility_now)'
            . $upsertClause,
        );
        $statement->execute([
            'insert_user_id' => $participantId,
            'insert_rating' => (int) $attributes['rating'],
            'insert_review' => (string) $attributes['review'],
            'insert_pending_status' => 'pending',
            'registration_user_id' => $participantId,
            'registration_status' => 'confirmed',
            'insert_event_id' => $eventId,
            'completed_status' => 'completed',
            'eligibility_now' => $currentTime,
        ]);

        if ($this->reviewableEventForParticipantAt($participantId, $eventId, $currentTime) === null) {
            return 0;
        }

        return (int) ($this->findForParticipantEvent($participantId, $eventId)['id'] ?? 0);
    }

    public function publicForEvent(int $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT reviews.id,
                    reviews.event_id,
                    reviews.user_id,
                    reviews.rating,
                    reviews.review,
                    reviews.organizer_reply,
                    reviews.replied_at,
                    reviews.created_at,
                    reviews.updated_at,
                    users.name AS participant_name,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM registrations AS verified_registrations
                        INNER JOIN attendance ON attendance.registration_id = verified_registrations.id
                        WHERE verified_registrations.event_id = reviews.event_id
                          AND verified_registrations.user_id = reviews.user_id
                          AND attendance.status = :present_status
                    ) THEN 1 ELSE 0 END AS verified_attendee
             FROM reviews
             INNER JOIN users ON users.id = reviews.user_id
             WHERE reviews.event_id = :event_id
               AND reviews.status = :published_status
             ORDER BY reviews.created_at DESC, reviews.id DESC',
        );
        $statement->execute([
            'present_status' => 'present',
            'event_id' => $eventId,
            'published_status' => 'published',
        ]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function summaryForEvent(int $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS review_count, AVG(rating) AS average_rating
             FROM reviews
             WHERE event_id = :event_id AND status = :published_status',
        );
        $statement->execute(['event_id' => $eventId, 'published_status' => 'published']);
        $row = $statement->fetch();

        return [
            'count' => (int) ($row['review_count'] ?? 0),
            'average' => ($row['average_rating'] ?? null) === null ? null : (float) $row['average_rating'],
        ];
    }

    public function pendingForAdmin(?string $status = null): array
    {
        $status = in_array($status, self::STATUSES, true) ? $status : null;
        $sql = $this->adminSelect();
        $parameters = ['pending_status' => 'pending'];

        if ($status !== null) {
            $sql .= ' WHERE reviews.status = :status_filter';
            $parameters['status_filter'] = $status;
        }

        $sql .= ' ORDER BY CASE WHEN reviews.status = :pending_status THEN 0 ELSE 1 END,
                           reviews.created_at ASC,
                           reviews.id ASC';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function forOrganizer(int $organizerId): array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . ' WHERE organizers.user_id = :organizer_user_id'
            . ' AND events.deleted_at IS NULL'
            . ' AND reviews.status = :published_status'
            . ' ORDER BY reviews.updated_at DESC, reviews.id DESC',
        );
        $statement->execute([
            'organizer_user_id' => $organizerId,
            'published_status' => 'published',
        ]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findForOrganizer(int $organizerId, int $reviewId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . ' WHERE organizers.user_id = :organizer_user_id'
            . ' AND reviews.id = :review_id'
            . ' AND events.deleted_at IS NULL'
            . ' AND reviews.status = :published_status'
            . ' LIMIT 1',
        );
        $statement->execute([
            'organizer_user_id' => $organizerId,
            'review_id' => $reviewId,
            'published_status' => 'published',
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function findForAdmin(int $reviewId): ?array
    {
        $statement = $this->connection->prepare($this->adminSelect() . ' WHERE reviews.id = :review_id LIMIT 1');
        $statement->execute(['review_id' => $reviewId]);

        return $this->rowOrNull($statement->fetch());
    }

    public function replyForOrganizer(int $organizerId, int $reviewId, string $reply): ?array
    {
        $statement = $this->connection->prepare(
            'UPDATE reviews
             SET organizer_reply = :organizer_reply,
                 replied_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :review_id
               AND status = :published_status
               AND EXISTS (
                   SELECT 1
                   FROM events
                   INNER JOIN organizers ON organizers.id = events.organizer_id
                   WHERE events.id = reviews.event_id
                     AND organizers.user_id = :organizer_user_id
                     AND events.deleted_at IS NULL
               )',
        );
        $statement->execute([
            'organizer_reply' => $reply,
            'review_id' => $reviewId,
            'published_status' => 'published',
            'organizer_user_id' => $organizerId,
        ]);

        $current = $this->findForOrganizer($organizerId, $reviewId);

        return $current !== null && ($current['organizer_reply'] ?? null) === $reply ? $current : null;
    }

    public function moderate(int $administratorId, int $reviewId, string $status): ?array
    {
        if (!in_array($status, ['published', 'hidden'], true)) {
            return null;
        }

        $statement = $this->connection->prepare(
            'UPDATE reviews
             SET status = :target_status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :review_id AND status = :pending_status',
        );
        $statement->execute([
            'target_status' => $status,
            'review_id' => $reviewId,
            'pending_status' => 'pending',
        ]);
        $current = $this->findForAdmin($reviewId);

        return $current !== null && ($current['status'] ?? null) === $status ? $current : null;
    }

    private function reviewSelect(): string
    {
        return 'SELECT reviews.id,
                       reviews.event_id,
                       reviews.user_id,
                       reviews.rating,
                       reviews.review,
                       reviews.organizer_reply,
                       reviews.replied_at,
                       reviews.status,
                       reviews.created_at,
                       reviews.updated_at,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       events.end_date AS event_end_date,
                       events.status AS event_status
                FROM reviews
                INNER JOIN events ON events.id = reviews.event_id';
    }

    private function adminSelect(): string
    {
        return 'SELECT reviews.id,
                       reviews.event_id,
                       reviews.user_id,
                       reviews.rating,
                       reviews.review,
                       reviews.organizer_reply,
                       reviews.replied_at,
                       reviews.status,
                       reviews.created_at,
                       reviews.updated_at,
                       users.name AS participant_name,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       events.deleted_at AS event_deleted_at,
                       organizers.user_id AS organizer_user_id,
                       organizers.organization_name
                FROM reviews
                INNER JOIN users ON users.id = reviews.user_id
                INNER JOIN events ON events.id = reviews.event_id
                INNER JOIN organizers ON organizers.id = events.organizer_id';
    }

    private function rowOrNull(mixed $row): ?array
    {
        return is_array($row) ? $row : null;
    }

    private function currentTime(): string
    {
        $current = ($this->clock)();

        if (!$current instanceof DateTimeInterface) {
            throw new \UnexpectedValueException('Review repository clock must return a date and time.');
        }

        return $current->format('Y-m-d H:i:s');
    }
}
