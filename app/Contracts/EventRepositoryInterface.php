<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface EventRepositoryInterface
{
    public function featured(int $limit): array;

    public function publicSearch(array $filters): array;

    public function publicRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        array $filters,
        int $limit,
        int $offset,
    ): array;

    public function countPublicRange(DateTimeImmutable $from, DateTimeImmutable $to, array $filters): int;

    public function publicCities(): array;

    public function findPublishedBySlug(string $slug): ?array;

    public function gallery(int $eventId): array;

    public function galleryForOwned(int $userId, int $eventId): array;

    public function organizerSummary(int $userId): array;

    public function forOrganizerUser(int $userId, ?string $status): array;

    public function recentForOrganizerUser(int $userId, int $limit): array;

    public function findOwned(int $userId, int $eventId): ?array;

    public function slugExists(string $slug, ?int $exceptId): bool;

    public function createForUser(int $userId, array $attributes): ?int;

    public function createWithGalleryForUser(int $userId, array $attributes, array $images): ?int;

    public function updateOwned(int $userId, int $eventId, array $attributes): bool;

    public function updateWithGalleryOwned(
        int $userId,
        int $eventId,
        array $attributes,
        ?array $images,
    ): ?array;

    public function softDeleteOwned(int $userId, int $eventId, array $context): bool;

    public function softDeleteAdmin(int $userId, int $eventId, array $context): bool;

    public function trashOwned(int $userId, int $limit, int $offset): array;

    public function trashAdmin(int $limit, int $offset): array;

    public function findDeletedOwned(int $userId, int $eventId): ?array;

    public function findDeletedAdmin(int $eventId): ?array;

    public function restoreOwned(int $userId, int $eventId, string $expectedDeletedAt, array $context): bool;

    public function restoreAdmin(int $userId, int $eventId, string $expectedDeletedAt, array $context): bool;

    public function transitionOwned(int $userId, int $eventId, array $context, string $status): bool;

    public function participantIdsForEventCancellation(int $eventId): array;

    public function publishOwned(int $userId, int $eventId, array $context): bool;

    public function forAdmin(?string $status): array;

    public function countPendingForAdmin(): int;

    public function findForAdmin(int $eventId): ?array;

    public function galleryForAdmin(int $eventId): array;

    public function transitionAdmin(int $userId, int $eventId, array $context, string $status, ?string $reason): bool;

    public function replaceGallery(int $eventId, array $images): void;

    public function deleteGalleryImageOwned(int $userId, int $eventId, int $imageId): ?string;
}
