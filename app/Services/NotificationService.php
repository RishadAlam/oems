<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use JsonException;
use OEMS\App\Contracts\NotificationRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class NotificationService
{
    private const TYPES = [
        'registration_pending',
        'registration_confirmed',
        'registration_cancelled',
        'payment_pending',
        'payment_verified',
        'payment_rejected',
        'ticket_issued',
        'review_submitted',
        'review_published',
        'review_hidden',
        'review_reply',
        'event_cancelled',
    ];

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = [],
    ): bool {
        if (!$this->isValid($userId, $type, $title, $message, $actionUrl, $data)) {
            return false;
        }

        try {
            return $this->notifications->createForUser($userId, [
                'type' => $type,
                'title' => trim($title),
                'message' => trim($message),
                'action_url' => $actionUrl,
                'data' => $data,
            ]) > 0;
        } catch (Throwable $exception) {
            try {
                $this->logger?->error('notification_dispatch', [
                    'user_id' => $userId,
                    'type' => $type,
                    'exception' => $exception::class,
                ]);
            } catch (Throwable) {
            }

            return false;
        }
    }

    private function isValid(int $userId, string $type, string $title, string $message, ?string $actionUrl, array $data): bool
    {
        if ($userId <= 0 || !in_array($type, self::TYPES, true)) {
            return false;
        }

        $title = trim($title);
        $message = trim($message);
        if ($title === '' || mb_strlen($title) > 180 || $message === '' || mb_strlen($message) > 1000) {
            return false;
        }

        if ($actionUrl !== null && preg_match('#^/participant/(?:registrations|tickets|reviews)(?:/[1-9][0-9]*)?$#', $actionUrl) !== 1) {
            return false;
        }

        try {
            return strlen(json_encode($data, JSON_THROW_ON_ERROR)) <= 4096;
        } catch (JsonException) {
            return false;
        }
    }
}
