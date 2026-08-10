<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface PlatformSettingsRepositoryInterface
{
    /** @return array<string, mixed> */
    public function valuesForKeys(array $keys): array;

    public function updateMany(array $values): void;

    /** @return array<string, mixed> */
    public function privateValuesForKeys(array $keys): array;

    public function setMaintenance(bool $enabled, int $adminUserId): void;
}
