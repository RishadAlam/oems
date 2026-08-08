<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use PDO;

final class DashboardMetricsRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function totals(): array
    {
        $row = $this->connection->query(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS users,
                (SELECT COUNT(*) FROM organizers INNER JOIN users AS organizer_users ON organizer_users.id = organizers.user_id WHERE organizer_users.deleted_at IS NULL) AS organizers,
                (SELECT COUNT(*) FROM events WHERE deleted_at IS NULL) AS events',
        )->fetch();

        return [
            'users' => (int) $row['users'],
            'organizers' => (int) $row['organizers'],
            'events' => (int) $row['events'],
        ];
    }

    public function reviewsForParticipant(int $participantId): array
    {
        $row = $this->reviewSummary(
            'WHERE reviews.user_id = :participant_user_id',
            ['participant_user_id' => $participantId],
        );

        return [
            'submitted' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
        ];
    }

    public function reviewsForOrganizer(int $organizerUserId): array
    {
        $statement = $this->connection->prepare(
            "SELECT COALESCE(SUM(CASE WHEN reviews.status = 'published' THEN 1 ELSE 0 END), 0) AS published,
                    COALESCE(SUM(CASE WHEN reviews.status = 'published' AND reviews.organizer_reply IS NULL THEN 1 ELSE 0 END), 0) AS awaiting_reply
             FROM reviews
             INNER JOIN events ON events.id = reviews.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             INNER JOIN users ON users.id = reviews.user_id
             WHERE organizers.user_id = :organizer_user_id
               AND events.deleted_at IS NULL
               AND users.deleted_at IS NULL",
        );
        $statement->execute(['organizer_user_id' => $organizerUserId]);
        $row = $statement->fetch();

        return [
            'published' => (int) ($row['published'] ?? 0),
            'awaiting_reply' => (int) ($row['awaiting_reply'] ?? 0),
        ];
    }

    public function reviewsForAdmin(): array
    {
        $row = $this->reviewSummary('', []);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
        ];
    }

    private function reviewSummary(string $where, array $parameters): array
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN reviews.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN reviews.status = 'published' THEN 1 ELSE 0 END), 0) AS published
             FROM reviews
             INNER JOIN events ON events.id = reviews.event_id
             INNER JOIN users ON users.id = reviews.user_id
             {$where}" . ($where === '' ? ' WHERE' : ' AND') . " events.deleted_at IS NULL
               AND users.deleted_at IS NULL",
        );
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $row : [];
    }
}
