<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\AnalyticsRepositoryInterface;

final class FakeAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public array $organizerSummary = [];

    public array $adminSummary = [];

    public array $organizerRows = [];

    public array $reportRows = [];

    public array $organizerSeries = [];

    public array $adminSeries = [];

    public array $calls = [];

    public array $foreignEventIds = [];

    public function organizerSummary(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId = null,
    ): ?array {
        $this->calls[] = ['organizerSummary', $organizerUserId, $startAt, $endExclusive, $eventId];

        return $eventId !== null && in_array($eventId, $this->foreignEventIds, true)
            ? null
            : $this->organizerSummary;
    }

    public function organizerEventRows(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId,
        int $limit,
        int $offset,
    ): ?array {
        $this->calls[] = ['organizerEventRows', $organizerUserId, $startAt, $endExclusive, $eventId, $limit, $offset];
        if ($eventId !== null && in_array($eventId, $this->foreignEventIds, true)) {
            return null;
        }

        return array_slice($this->organizerRows, $offset, $limit);
    }

    public function adminSummary(string $startAt, string $endExclusive, array $filters = []): array
    {
        $this->calls[] = ['adminSummary', $startAt, $endExclusive, $filters];

        return $this->adminSummary;
    }

    public function organizerSeries(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId = null,
    ): ?array {
        $this->calls[] = ['organizerSeries', $organizerUserId, $startAt, $endExclusive, $eventId];

        return $eventId !== null && in_array($eventId, $this->foreignEventIds, true)
            ? null
            : $this->organizerSeries;
    }

    public function adminSeries(string $startAt, string $endExclusive, array $filters = []): array
    {
        $this->calls[] = ['adminSeries', $startAt, $endExclusive, $filters];

        return $this->adminSeries;
    }

    public function adminReportRows(
        string $type,
        string $startAt,
        string $endExclusive,
        array $filters,
        int $limit,
        int $offset,
    ): array {
        $this->calls[] = ['adminReportRows', $type, $startAt, $endExclusive, $filters, $limit, $offset];

        return array_slice($this->reportRows[$type] ?? [], $offset, $limit);
    }
}
