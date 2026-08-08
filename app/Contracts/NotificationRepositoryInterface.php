<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface NotificationRepositoryInterface
{
    public function createForUser(int $userId, array $attributes): int;

    public function unreadCountForUser(int $userId): int;

    public function forUser(int $userId, int $page, int $perPage): array;

    public function markReadForUser(int $userId, int $notificationId): bool;

    public function markAllReadForUser(int $userId): int;
}
