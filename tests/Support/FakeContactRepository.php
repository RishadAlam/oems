<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\ContactRepositoryInterface;

final class FakeContactRepository implements ContactRepositoryInterface
{
    public array $rows = [];
    public array $statusCalls = [];
    public int $nextId = 1;

    public function create(array $attributes): ?array
    {
        $attributes['id'] = $this->nextId++;
        $attributes['status'] = 'new';
        return $this->rows[$attributes['id']] = $attributes;
    }

    public function forAdmin(array $filters, int $limit, int $offset): array { return array_values($this->rows); }
    public function countForAdmin(array $filters): int { return count($this->rows); }
    public function findForAdmin(int $id, bool $lock = false): ?array { return $this->rows[$id] ?? null; }

    public function setStatus(int $id, string $from, string $to, int $administratorId): bool
    {
        $this->statusCalls[] = compact('id', 'from', 'to', 'administratorId');
        if (!isset($this->rows[$id]) || ($this->rows[$id]['status'] ?? null) !== $from) return false;
        $this->rows[$id]['status'] = $to;
        return true;
    }

    public function markReplied(int $id, int $administratorId): bool
    {
        $current = $this->rows[$id]['status'] ?? null;
        if (!in_array($current, ['new', 'read'], true)) return false;
        $this->rows[$id]['status'] = 'replied';
        $this->rows[$id]['replied_by'] = $administratorId;
        return true;
    }
}
