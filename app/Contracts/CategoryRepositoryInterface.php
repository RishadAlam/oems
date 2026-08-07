<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface CategoryRepositoryInterface
{
    public function active(): array;

    public function all(): array;

    public function find(int $id): ?array;

    public function slugExists(string $slug, ?int $exceptId): bool;

    public function create(array $attributes): int;

    public function update(int $id, array $attributes): bool;

    public function setActive(int $id, bool $isActive): bool;
}
