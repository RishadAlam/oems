<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\NotificationRepositoryInterface;

final class FakeNotificationRepository implements NotificationRepositoryInterface
{
    public array $notifications = [];

    public bool $throwOnCreate = false;

    public function createForUser(int $userId, array $attributes): int
    {
        if ($this->throwOnCreate) {
            throw new \RuntimeException('Notification delivery failed.');
        }

        $id = $this->notifications === [] ? 1 : max(array_keys($this->notifications)) + 1;
        $this->notifications[$id] = array_merge($attributes, [
            'id' => $id,
            'user_id' => $userId,
            'read_at' => null,
            'created_at' => '2026-08-09 12:00:00',
        ]);

        return $id;
    }

    public function unreadCountForUser(int $userId): int
    {
        return count(array_filter($this->notifications, static fn (array $notification): bool =>
            (int) $notification['user_id'] === $userId && $notification['read_at'] === null,
        ));
    }

    public function forUser(int $userId, int $page, int $perPage): array
    {
        $items = array_values(array_filter($this->notifications, static fn (array $notification): bool =>
            (int) $notification['user_id'] === $userId,
        ));
        usort($items, static fn (array $left, array $right): int => $right['id'] <=> $left['id']);
        $total = count($items);
        $perPage = min(50, max(1, $perPage));
        $page = min(max(1, $page), max(1, (int) ceil($total / $perPage)));

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function markReadForUser(int $userId, int $notificationId): bool
    {
        if (!isset($this->notifications[$notificationId]) || (int) $this->notifications[$notificationId]['user_id'] !== $userId) {
            return false;
        }

        $this->notifications[$notificationId]['read_at'] ??= '2026-08-09 12:00:00';

        return true;
    }

    public function markAllReadForUser(int $userId): int
    {
        $count = 0;
        foreach ($this->notifications as &$notification) {
            if ((int) $notification['user_id'] === $userId && $notification['read_at'] === null) {
                $notification['read_at'] = '2026-08-09 12:00:00';
                $count++;
            }
        }
        unset($notification);

        return $count;
    }
}
