<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\FavoriteRepositoryInterface;
use PDO;

final class FavoriteRepository implements FavoriteRepositoryInterface
{
    private const MAX_PAGE = 10000;

    private const MAX_PER_PAGE = 50;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function addForParticipant(int $participantId, int $eventId): bool
    {
        $statement = $this->connection->prepare(
            $this->insertIgnore() . ' INTO favorites (user_id, event_id)
             SELECT :user_id, events.id
             FROM events
             INNER JOIN categories ON categories.id = events.category_id
             WHERE events.id = :event_id
               AND events.status = :published_status
               AND events.deleted_at IS NULL
               AND categories.is_active = 1',
        );
        $statement->execute([
            'user_id' => $participantId,
            'event_id' => $eventId,
            'published_status' => 'published',
        ]);

        if ($statement->rowCount() === 1) {
            return true;
        }

        return $this->eventCanBeSaved($eventId) && $this->existsForParticipant($participantId, $eventId);
    }

    public function removeForParticipant(int $participantId, int $eventId): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM favorites WHERE user_id = :user_id AND event_id = :event_id',
        );
        $statement->execute(['user_id' => $participantId, 'event_id' => $eventId]);

        return true;
    }

    public function existsForParticipant(int $participantId, int $eventId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM favorites WHERE user_id = :user_id AND event_id = :event_id LIMIT 1',
        );
        $statement->execute(['user_id' => $participantId, 'event_id' => $eventId]);

        return $statement->fetchColumn() !== false;
    }

    public function statesForParticipant(int $participantId, array $eventIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $eventIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $parameters = ['user_id' => $participantId];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = 'event_' . $index;
            $parameters[$key] = $id;
            $placeholders[] = ':' . $key;
        }

        $statement = $this->connection->prepare(
            'SELECT event_id
             FROM favorites
             WHERE user_id = :user_id
               AND event_id IN (' . implode(', ', $placeholders) . ')',
        );
        $statement->execute($parameters);
        $states = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $eventId) {
            $states[(int) $eventId] = true;
        }

        return $states;
    }

    public function forParticipant(int $participantId, int $page, int $perPage): array
    {
        $page = min(max(1, $page), self::MAX_PAGE);
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);
        $count = $this->connection->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :user_id');
        $count->execute(['user_id' => $participantId]);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $statement = $this->connection->prepare(
            'SELECT favorites.event_id,
                    favorites.created_at AS saved_at,
                    events.title,
                    events.slug,
                    events.start_date,
                    events.ticket_price,
                    events.currency,
                    events.banner,
                    events.status AS event_status,
                    categories.name AS category_name,
                    CASE
                        WHEN events.status = :published_status
                         AND events.deleted_at IS NULL
                         AND COALESCE(categories.is_active, 0) = 1
                        THEN 1 ELSE 0
                    END AS is_available
             FROM favorites
             INNER JOIN events ON events.id = favorites.event_id
             LEFT JOIN categories ON categories.id = events.category_id
             WHERE favorites.user_id = :user_id
             ORDER BY favorites.created_at DESC, favorites.event_id DESC
             LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue('published_status', 'published');
        $statement->bindValue('user_id', $participantId, PDO::PARAM_INT);
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();

        return [
            'items' => is_array($items) ? array_map(static function (array $item): array {
                $item['is_available'] = (bool) ($item['is_available'] ?? false);

                return $item;
            }, $items) : [],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function eventCanBeSaved(int $eventId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1
             FROM events
             INNER JOIN categories ON categories.id = events.category_id
             WHERE events.id = :event_id
               AND events.status = :published_status
               AND events.deleted_at IS NULL
               AND categories.is_active = 1
             LIMIT 1',
        );
        $statement->execute(['event_id' => $eventId, 'published_status' => 'published']);

        return $statement->fetchColumn() !== false;
    }

    private function insertIgnore(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'INSERT IGNORE'
            : 'INSERT OR IGNORE';
    }
}
