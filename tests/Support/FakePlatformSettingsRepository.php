<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\PlatformSettingsRepositoryInterface;
use RuntimeException;

final class FakePlatformSettingsRepository implements PlatformSettingsRepositoryInterface
{
    public int $updateCalls = 0;

    public bool $failRead = false;

    public bool $failUpdate = false;

    public function __construct(public array $values = [])
    {
    }

    public function valuesForKeys(array $keys): array
    {
        if ($this->failRead) {
            throw new RuntimeException('SQL secret');
        }

        return array_intersect_key($this->values, array_flip($keys));
    }

    public function updateMany(array $values): void
    {
        $this->updateCalls++;
        if ($this->failUpdate) {
            throw new RuntimeException('SQL secret');
        }

        $this->values = $values;
    }
}
