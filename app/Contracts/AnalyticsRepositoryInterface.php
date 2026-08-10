<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface AnalyticsRepositoryInterface
{
    public function organizerSummary(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId = null,
    ): ?array;

    public function organizerEventRows(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId,
        int $limit,
        int $offset,
    ): ?array;

    public function adminSummary(string $startAt, string $endExclusive, array $filters = []): array;

    public function organizerSeries(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId = null,
    ): ?array;

    public function adminSeries(string $startAt, string $endExclusive, array $filters = []): array;

    public function adminReportRows(
        string $type,
        string $startAt,
        string $endExclusive,
        array $filters,
        int $limit,
        int $offset,
    ): array;
}
