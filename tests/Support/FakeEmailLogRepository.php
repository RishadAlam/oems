<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\EmailLogRepositoryInterface;

final class FakeEmailLogRepository implements EmailLogRepositoryInterface
{
    public array $records = [];

    public ?\Throwable $failure = null;

    public function record(array $attributes): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->records[] = $attributes;
    }
}
