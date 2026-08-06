<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface EmailLogRepositoryInterface
{
    public function record(array $attributes): void;
}
