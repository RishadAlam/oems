<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface PlatformSettingsRepositoryInterface
{
    /** @return array<string, mixed> */
    public function valuesForKeys(array $keys): array;

    public function updateMany(array $values): void;
}
