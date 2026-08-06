<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface ProfileRepositoryInterface
{
    public function findForUser(int $userId): ?array;

    public function updateForUser(int $userId, array $attributes): void;
}
