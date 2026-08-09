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

    public function participantWorkspace(int $participantId): array
    {
        $upcoming = $this->connection->prepare(
            "SELECT registrations.id,
                    registrations.status AS registration_status,
                    registrations.registration_number,
                    events.title AS event_title,
                    events.slug AS event_slug,
                    events.start_date AS event_start_date,
                    COALESCE(payments.status, 'not_required') AS payment_status
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id
             LEFT JOIN payments ON payments.id = (
                 SELECT MAX(latest_payments.id)
                 FROM payments AS latest_payments
                 WHERE latest_payments.registration_id = registrations.id
             )
             WHERE registrations.user_id = :participant_user_id
               AND registrations.status IN ('pending', 'confirmed')
               AND events.status <> 'cancelled'
               AND events.start_date > CURRENT_TIMESTAMP
               AND events.deleted_at IS NULL
               AND users.deleted_at IS NULL
             ORDER BY events.start_date ASC, registrations.id ASC
             LIMIT 3",
        );
        $upcoming->execute(['participant_user_id' => $participantId]);
        $items = $upcoming->fetchAll();

        $favorites = $this->connection->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :participant_user_id');
        $favorites->execute(['participant_user_id' => $participantId]);

        $reviewActions = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id
             LEFT JOIN reviews ON reviews.event_id = registrations.event_id AND reviews.user_id = registrations.user_id
             WHERE registrations.user_id = :participant_user_id
               AND registrations.status = 'confirmed'
               AND (events.status = 'completed' OR events.end_date <= CURRENT_TIMESTAMP)
               AND events.deleted_at IS NULL
               AND users.deleted_at IS NULL
               AND reviews.id IS NULL",
        );
        $reviewActions->execute(['participant_user_id' => $participantId]);

        $tickets = $this->connection->prepare(
            "SELECT tickets.id,
                    tickets.ticket_number,
                    tickets.status AS ticket_status,
                    tickets.issued_at,
                    registrations.id AS registration_id,
                    events.title AS event_title,
                    events.start_date AS event_start_date
             FROM tickets
             INNER JOIN registrations ON registrations.id = tickets.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id
             WHERE registrations.user_id = :participant_user_id
               AND events.deleted_at IS NULL
               AND users.deleted_at IS NULL
             ORDER BY tickets.issued_at DESC, tickets.id DESC
             LIMIT 3",
        );
        $tickets->execute(['participant_user_id' => $participantId]);

        $notifications = $this->connection->prepare(
            "SELECT notifications.id,
                    notifications.type,
                    notifications.title,
                    notifications.message,
                    notifications.action_url,
                    notifications.read_at,
                    notifications.created_at
             FROM notifications
             INNER JOIN users ON users.id = notifications.user_id
             WHERE notifications.user_id = :participant_user_id
               AND users.deleted_at IS NULL
             ORDER BY notifications.created_at DESC, notifications.id DESC
             LIMIT 3",
        );
        $notifications->execute(['participant_user_id' => $participantId]);

        return [
            'upcoming' => is_array($items) ? $items : [],
            'favorite_count' => (int) $favorites->fetchColumn(),
            'review_actions' => (int) $reviewActions->fetchColumn(),
            'tickets' => $tickets->fetchAll() ?: [],
            'recent_notifications' => $notifications->fetchAll() ?: [],
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
