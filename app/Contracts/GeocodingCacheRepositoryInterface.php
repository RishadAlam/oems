<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface GeocodingCacheRepositoryInterface
{
    public function findFresh(string $queryHash, string $provider, DateTimeImmutable $now): ?array;

    public function upsert(
        string $queryHash,
        string $query,
        string $provider,
        array $results,
        DateTimeImmutable $expiresAt,
    ): void;
}
