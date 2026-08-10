<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\AnnouncementRepositoryInterface;
use RuntimeException;

final class FakeAnnouncementRepository implements AnnouncementRepositoryInterface
{
    public array $events = [];

    public array $announcements = [];

    public array $deliveries = [];

    public bool $throwOnDelivery = false;

    public ?array $forcedDeliveryResult = null;

    public int $historyLimit = 0;

    public function findOwnedEvent(int $organizerUserId, int $eventId): ?array
    {
        $event = $this->events[$eventId] ?? null;

        return is_array($event)
            && (int) ($event['user_id'] ?? 0) === $organizerUserId
            && empty($event['deleted_at'])
                ? $event
                : null;
    }

    public function historyForOwnedEvent(int $organizerUserId, int $eventId, int $limit): array
    {
        $this->historyLimit = $limit;
        if ($this->findOwnedEvent($organizerUserId, $eventId) === null) {
            return [];
        }

        $items = array_values(array_filter(
            $this->announcements,
            static fn (array $announcement): bool => (int) ($announcement['event_id'] ?? 0) === $eventId,
        ));
        usort($items, static fn (array $left, array $right): int =>
            [$right['sent_at'] ?? '', $right['id'] ?? 0] <=> [$left['sent_at'] ?? '', $left['id'] ?? 0],
        );

        return array_slice($items, 0, min(50, max(1, $limit)));
    }

    public function deliverToConfirmedParticipants(
        int $organizerUserId,
        int $eventId,
        string $subject,
        string $message,
        string $requestKey,
        array $context,
    ): array {
        if ($this->throwOnDelivery) {
            throw new RuntimeException('Database password secret-example should never be exposed.');
        }

        foreach ($this->announcements as $announcement) {
            if (($announcement['request_key'] ?? null) === $requestKey
                && (int) ($announcement['event_id'] ?? 0) === $eventId
                && (int) ($announcement['sent_by'] ?? 0) === $organizerUserId) {
                return array_merge($announcement, ['status' => 'replayed']);
            }
        }

        $this->deliveries[] = compact(
            'organizerUserId',
            'eventId',
            'subject',
            'message',
            'requestKey',
            'context',
        );
        if ($this->forcedDeliveryResult !== null) {
            return $this->forcedDeliveryResult;
        }

        $id = count($this->announcements) + 1;
        $announcement = [
            'id' => $id,
            'event_id' => $eventId,
            'sent_by' => $organizerUserId,
            'subject' => $subject,
            'message' => $message,
            'audience' => 'confirmed',
            'recipient_count' => 2,
            'request_key' => $requestKey,
            'sent_at' => '2026-08-10 12:00:00',
        ];
        $this->announcements[$id] = $announcement;

        return array_merge($announcement, ['status' => 'sent']);
    }
}
