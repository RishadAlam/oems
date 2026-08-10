<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface ContactRepositoryInterface
{
    public function create(array $attributes): ?array;
    public function forAdmin(array $filters, int $limit, int $offset): array;
    public function countForAdmin(array $filters): int;
    public function findForAdmin(int $id, bool $lock = false): ?array;
    public function setStatus(int $id, string $from, string $to, int $administratorId): bool;
    public function markReplied(int $id, int $administratorId): bool;
}
