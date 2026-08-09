<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\NotificationRepositoryInterface;

final class DashboardLayoutDataProvider
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications)
    {
    }

    public function forLayout(array $data, string $layout): array
    {
        $currentUser = $data['currentUser'] ?? null;
        if ($layout !== 'dashboard'
            || !is_array($currentUser)
            || ($currentUser['role_slug'] ?? '') !== 'participant'
            || (int) ($currentUser['id'] ?? 0) <= 0) {
            return [];
        }

        return ['unreadNotifications' => $this->notifications->unreadCountForUser((int) $currentUser['id'])];
    }
}
