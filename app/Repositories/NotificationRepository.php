<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use JsonException;
use OEMS\App\Contracts\NotificationRepositoryInterface;
use PDO;

final class NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function createForUser(int $userId, array $attributes): int
    {
        $data = $attributes['data'] ?? [];
        $statement = $this->connection->prepare(
            'INSERT INTO notifications (user_id, type, title, message, action_url, data)
             VALUES (:user_id, :type, :title, :message, :action_url, :data)',
        );
        $statement->execute([
            'user_id' => $userId,
            'type' => (string) $attributes['type'],
            'title' => (string) $attributes['title'],
            'message' => (string) $attributes['message'],
            'action_url' => $attributes['action_url'] ?? null,
            'data' => $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function unreadCountForUser(int $userId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL',
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function forUser(int $userId, int $page, int $perPage): array
    {
        $perPage = min(50, max(1, $perPage));
        $total = $this->countForUser($userId);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $statement = $this->connection->prepare(
            'SELECT id, user_id, type, title, message, action_url, data, read_at, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();

        return [
            'items' => array_map(fn (array $item): array => $this->hydrate($item), is_array($items) ? $items : []),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function markReadForUser(int $userId, int $notificationId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE notifications SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE id = :notification_id AND user_id = :user_id',
        );
        $statement->execute(['notification_id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function markAllReadForUser(int $userId): int
    {
        $statement = $this->connection->prepare(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND read_at IS NULL',
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }

    private function countForUser(int $userId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    private function hydrate(array $item): array
    {
        $data = $item['data'] ?? null;
        if (is_string($data) && $data !== '') {
            try {
                $decoded = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
                $item['data'] = is_array($decoded) ? $decoded : [];
            } catch (JsonException) {
                $item['data'] = [];
            }
        } else {
            $item['data'] = [];
        }

        return $item;
    }
}
