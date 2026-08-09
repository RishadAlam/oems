<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface GeocoderInterface
{
    public function search(string $query, int $limit): array;
}
