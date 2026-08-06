<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\EmailLogRepositoryInterface;

final class FakeEmailLogRepository implements EmailLogRepositoryInterface
{
    public array $records = [];

    public function record(array $attributes): void
    {
        $this->records[] = $attributes;
    }
}
