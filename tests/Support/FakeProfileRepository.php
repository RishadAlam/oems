<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\ProfileRepositoryInterface;

final class FakeProfileRepository implements ProfileRepositoryInterface
{
    public array $profiles = [];

    public array $updates = [];

    public function findForUser(int $userId): ?array
    {
        return $this->profiles[$userId] ?? null;
    }

    public function updateForUser(int $userId, array $attributes): void
    {
        $this->updates[$userId] = $attributes;
        $this->profiles[$userId] = array_merge($this->profiles[$userId] ?? [], $attributes);
    }
}
