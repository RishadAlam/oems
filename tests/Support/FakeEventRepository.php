<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\EventRepositoryInterface;
use RuntimeException;

final class FakeEventRepository implements EventRepositoryInterface
{
    public array $events = [];

    public array $galleries = [];

    public bool $failCreate = false;

    public bool $failUpdate = false;

    public bool $failGalleryReplacement = false;

    public function featured(int $limit): array
    {
        return array_slice(array_values($this->events), 0, $limit);
    }

    public function publicSearch(array $filters): array
    {
        return array_values($this->events);
    }

    public function publicCities(): array
    {
        return [];
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        foreach ($this->events as $event) {
            if ($event['slug'] === $slug && $event['status'] === 'published') {
                return $event;
            }
        }

        return null;
    }

    public function gallery(int $eventId): array
    {
        return $this->galleries[$eventId] ?? [];
    }

    public function organizerSummary(int $userId): array
    {
        return ['total' => count($this->forOrganizerUser($userId, null))];
    }

    public function forOrganizerUser(int $userId, ?string $status): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (array $event): bool => (int) $event['user_id'] === $userId
                && ($status === null || $event['status'] === $status)
                && empty($event['deleted_at']),
        ));
    }

    public function findOwned(int $userId, int $eventId): ?array
    {
        $event = $this->events[$eventId] ?? null;

        return $event !== null
            && (int) $event['user_id'] === $userId
            && empty($event['deleted_at'])
                ? $event
                : null;
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        foreach ($this->events as $id => $event) {
            if ($id !== $exceptId && $event['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    public function createForUser(int $userId, array $attributes): ?int
    {
        if ($this->failCreate) {
            return null;
        }

        $id = $this->events === [] ? 1 : max(array_keys($this->events)) + 1;
        $this->events[$id] = array_merge($attributes, [
            'id' => $id,
            'user_id' => $userId,
            'status' => 'draft',
            'deleted_at' => null,
        ]);

        return $id;
    }

    public function createWithGalleryForUser(int $userId, array $attributes, array $images): ?int
    {
        if ($this->failCreate) {
            return null;
        }

        if ($this->failGalleryReplacement) {
            throw new RuntimeException('Gallery persistence failed.');
        }

        $id = $this->createForUser($userId, $attributes);

        if ($id !== null) {
            $this->galleries[$id] = array_slice($images, 0, 6);
        }

        return $id;
    }

    public function updateOwned(int $userId, int $eventId, array $attributes): bool
    {
        if ($this->failUpdate || $this->findOwned($userId, $eventId) === null) {
            return false;
        }

        $this->events[$eventId] = array_merge($this->events[$eventId], $attributes);

        return true;
    }

    public function updateWithGalleryOwned(
        int $userId,
        int $eventId,
        array $attributes,
        ?array $images,
    ): ?array {
        $event = $this->findOwned($userId, $eventId);

        if ($event === null || $this->failUpdate) {
            return null;
        }

        if ($this->failGalleryReplacement) {
            throw new RuntimeException('Gallery persistence failed.');
        }

        $prior = [
            'banner' => is_string($event['banner'] ?? null) ? $event['banner'] : null,
            'gallery' => $this->galleryPaths($eventId),
        ];
        $this->events[$eventId] = array_merge($event, $attributes);

        if ($images !== null) {
            $this->galleries[$eventId] = array_slice($images, 0, 6);
        }

        return $prior;
    }

    public function softDeleteOwned(int $userId, int $eventId, array $context): bool
    {
        if ($this->findOwned($userId, $eventId) === null) {
            return false;
        }

        $this->events[$eventId]['deleted_at'] = 'now';

        return true;
    }

    public function transitionOwned(int $userId, int $eventId, array $context, string $status): bool
    {
        if ($this->findOwned($userId, $eventId) === null) {
            return false;
        }

        $allowed = [
            'pending' => ['draft', 'rejected'],
            'cancelled' => ['approved', 'published'],
        ];

        if (!in_array($this->events[$eventId]['status'], $allowed[$status] ?? [], true)) {
            return false;
        }

        $this->events[$eventId]['status'] = $status;

        if ($status !== 'rejected') {
            $this->events[$eventId]['rejection_reason'] = null;
        }

        return true;
    }

    public function forAdmin(?string $status): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (array $event): bool => $status === null || $event['status'] === $status,
        ));
    }

    public function findForAdmin(int $eventId): ?array
    {
        return $this->events[$eventId] ?? null;
    }

    public function transitionAdmin(int $userId, int $eventId, array $context, string $status, ?string $reason): bool
    {
        if (!isset($this->events[$eventId])) {
            return false;
        }

        $allowed = [
            'approved' => ['pending'],
            'rejected' => ['pending'],
            'published' => ['approved'],
            'completed' => ['published'],
            'cancelled' => ['approved', 'published'],
        ];

        if (!in_array($this->events[$eventId]['status'], $allowed[$status] ?? [], true)) {
            return false;
        }

        $this->events[$eventId]['status'] = $status;
        $this->events[$eventId]['rejection_reason'] = $status === 'rejected' ? $reason : null;

        return true;
    }

    public function replaceGallery(int $eventId, array $images): void
    {
        if ($this->failGalleryReplacement) {
            throw new RuntimeException('Gallery persistence failed.');
        }

        $this->galleries[$eventId] = array_slice($images, 0, 6);
    }

    public function deleteGalleryImageOwned(int $userId, int $eventId, int $imageId): ?string
    {
        $image = $this->galleries[$eventId][$imageId] ?? null;

        if ($image === null || $this->findOwned($userId, $eventId) === null) {
            return null;
        }

        unset($this->galleries[$eventId][$imageId]);

        return is_array($image) ? ($image['image_path'] ?? null) : $image;
    }

    private function galleryPaths(int $eventId): array
    {
        $paths = [];

        foreach ($this->galleries[$eventId] ?? [] as $image) {
            $path = is_array($image) ? ($image['image_path'] ?? $image['path'] ?? null) : $image;

            if (is_string($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
