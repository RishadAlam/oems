<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface HttpClientInterface
{
    /** @return array{status: int, body: string} */
    public function get(string $url, array $headers, int $timeoutSeconds): array;
}
