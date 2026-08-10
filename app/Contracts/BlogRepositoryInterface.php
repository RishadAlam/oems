<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface BlogRepositoryInterface
{
    public function create(int $authorId, array $attributes): int;

    public function update(int $postId, string $expectedUpdatedAt, array $attributes): bool;

    public function transition(int $postId, string $expectedUpdatedAt, string $status, string $changedAt): bool;

    public function softDelete(int $postId, string $expectedUpdatedAt, string $deletedAt): bool;

    public function findAdmin(int $postId): ?array;

    public function adminList(array $filters, int $limit, int $offset): array;

    public function countAdmin(array $filters): int;

    public function publicList(?string $category, int $limit, int $offset): array;

    public function countPublic(?string $category): int;

    public function publicCategories(): array;

    public function findPublicBySlug(string $slug): ?array;
}
