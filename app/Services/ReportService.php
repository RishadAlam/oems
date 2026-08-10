<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\AnalyticsRepositoryInterface;
use RuntimeException;

final class ReportService
{
    private const EVENT_STATUSES = ['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'];

    private const REPORT_COLUMNS = [
        'events' => [
            'event_id' => 'Event ID', 'event_status' => 'Lifecycle status', 'start_date' => 'Starts at',
            'capacity' => 'Capacity', 'confirmed_registrations' => 'Confirmed registrations',
            'attendance_count' => 'Attendance', 'favorites_count' => 'Favorites',
            'published_review_count' => 'Published reviews', 'published_review_average' => 'Average rating',
            'verified_payments' => 'Verified payments', 'refund_attention_count' => 'Refund attention',
            'archived' => 'Archived',
        ],
        'registrations' => [
            'event_id' => 'Event ID', 'event_status' => 'Event status',
            'registration_status' => 'Registration status', 'registration_count' => 'Registration count',
        ],
        'payments' => [
            'event_id' => 'Event ID', 'event_status' => 'Event status', 'currency' => 'Currency',
            'payment_status' => 'Payment status', 'payment_count' => 'Payment count',
            'amount_total' => 'Amount total', 'refund_attention_count' => 'Refund attention',
        ],
        'attendance' => [
            'event_id' => 'Event ID', 'event_status' => 'Event status',
            'attendance_status' => 'Attendance status', 'attendance_count' => 'Attendance count',
        ],
        'organizers' => [
            'organizer_id' => 'Organizer ID', 'approval_status' => 'Approval status',
            'event_count' => 'Event count', 'registration_count' => 'Registration count',
            'attendance_count' => 'Attendance count',
        ],
    ];

    private const ORGANIZER_COLUMNS = [
        'event_id' => 'Event ID', 'event_title' => 'Event', 'event_status' => 'Lifecycle status',
        'start_date' => 'Starts at', 'capacity' => 'Capacity', 'confirmed' => 'Confirmed registrations',
        'pending' => 'Pending registrations', 'cancelled' => 'Cancelled registrations',
        'attendance_count' => 'Attendance', 'favorites_count' => 'Favorites',
        'review_count' => 'Published reviews', 'review_average' => 'Average rating',
        'verified_payments' => 'Verified payments', 'refund_attention_count' => 'Refund attention',
        'archived' => 'Archived',
    ];

    public function __construct(
        private readonly AnalyticsRepositoryInterface $analytics,
        private readonly ?DateTimeImmutable $clock = null,
    ) {
    }

    public function dateRange(mixed $start, mixed $end): array
    {
        $today = ($this->clock ?? new DateTimeImmutable('now'))->setTime(0, 0);
        $startValue = $start === null || $start === '' ? $today->modify('-29 days')->format('Y-m-d') : $start;
        $endValue = $end === null || $end === '' ? $today->format('Y-m-d') : $end;
        if (!is_string($startValue) || !is_string($endValue)) {
            return $this->invalidRange('Dates must use the YYYY-MM-DD format.');
        }
        $timezone = $today->getTimezone() ?: new DateTimeZone('UTC');
        $startDate = $this->parseDate($startValue, $timezone);
        $endDate = $this->parseDate($endValue, $timezone);
        if ($startDate === null || $endDate === null) {
            return $this->invalidRange('Enter real dates using the YYYY-MM-DD format.');
        }
        $days = (int) $startDate->diff($endDate)->format('%r%a');
        if ($days < 0) {
            return $this->invalidRange('The start date must be on or before the end date.');
        }
        if ($days > 365) {
            return $this->invalidRange('The date range cannot exceed 366 days.');
        }

        return [
            'valid' => true,
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
            'start_at' => $startDate->format('Y-m-d') . ' 00:00:00',
            'end_exclusive' => $endDate->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
    }

    public function adminFilters(mixed $eventStatus, mixed $currency): array
    {
        $filters = [];
        if ($eventStatus !== null && $eventStatus !== '') {
            if (!is_string($eventStatus) || !in_array($eventStatus, self::EVENT_STATUSES, true)) {
                return ['valid' => false, 'error' => 'Choose a valid event status.'];
            }
            $filters['event_status'] = $eventStatus;
        }
        if ($currency !== null && $currency !== '') {
            if (!is_string($currency)) {
                return ['valid' => false, 'error' => 'Choose a valid three-letter currency.'];
            }
            $normalized = strtoupper(trim($currency));
            if (preg_match('/\A[A-Z]{3}\z/D', $normalized) !== 1) {
                return ['valid' => false, 'error' => 'Choose a valid three-letter currency.'];
            }
            $filters['currency'] = $normalized;
        }

        return ['valid' => true, 'filters' => $filters];
    }

    public function reportType(mixed $type): ?string
    {
        if ($type === null || $type === '') {
            return 'events';
        }

        return is_string($type) && array_key_exists($type, self::REPORT_COLUMNS) ? $type : null;
    }

    public function organizerData(int $organizerUserId, mixed $start, mixed $end, mixed $event): array
    {
        $range = $this->dateRange($start, $end);
        if (!$range['valid']) {
            return ['success' => false, 'code' => 'invalid', 'error' => $range['error'], 'data' => []];
        }
        $eventId = $this->eventId($event);
        if (($event !== null && $event !== '') && $eventId === null) {
            return ['success' => false, 'code' => 'invalid', 'error' => 'Choose a valid event.', 'data' => []];
        }
        $summary = $this->analytics->organizerSummary(
            $organizerUserId,
            $range['start_at'],
            $range['end_exclusive'],
            $eventId,
        );
        if ($summary === null) {
            return ['success' => false, 'code' => 'not_found', 'data' => []];
        }
        $rows = $this->analytics->organizerEventRows(
            $organizerUserId,
            $range['start_at'],
            $range['end_exclusive'],
            $eventId,
            100,
            0,
        );
        if ($rows === null) {
            return ['success' => false, 'code' => 'not_found', 'data' => []];
        }

        return ['success' => true, 'data' => ['range' => $range, 'event_id' => $eventId, 'summary' => $summary, 'rows' => $rows]];
    }

    public function adminData(mixed $start, mixed $end, mixed $eventStatus, mixed $currency): array
    {
        $range = $this->dateRange($start, $end);
        $filters = $this->adminFilters($eventStatus, $currency);
        if (!$range['valid'] || !$filters['valid']) {
            return [
                'success' => false,
                'code' => 'invalid',
                'error' => $range['valid'] ? $filters['error'] : $range['error'],
                'data' => [],
            ];
        }

        return ['success' => true, 'data' => [
            'range' => $range,
            'filters' => $filters['filters'],
            'summary' => $this->analytics->adminSummary($range['start_at'], $range['end_exclusive'], $filters['filters']),
        ]];
    }

    public function reportData(mixed $type, mixed $start, mixed $end, mixed $eventStatus, mixed $currency): array
    {
        $reportType = $this->reportType($type);
        $range = $this->dateRange($start, $end);
        $filters = $this->adminFilters($eventStatus, $currency);
        if ($reportType === null || !$range['valid'] || !$filters['valid']) {
            return [
                'success' => false,
                'code' => 'invalid',
                'error' => $reportType === null
                    ? 'Choose an available report type.'
                    : ($range['valid'] ? $filters['error'] : $range['error']),
                'data' => [],
            ];
        }

        return ['success' => true, 'data' => [
            'type' => $reportType,
            'range' => $range,
            'filters' => $filters['filters'],
            'columns' => self::REPORT_COLUMNS[$reportType],
            'rows' => $this->analytics->adminReportRows(
                $reportType,
                $range['start_at'],
                $range['end_exclusive'],
                $filters['filters'],
                100,
                0,
            ),
        ]];
    }

    public function streamAdminCsv(
        string $type,
        mixed $start,
        mixed $end,
        array $filters,
        callable $emit,
    ): array {
        $reportType = $this->reportType($type);
        $range = $this->dateRange($start, $end);
        if ($reportType === null || !$range['valid']) {
            return ['success' => false, 'code' => 'invalid'];
        }
        $emit("\xEF\xBB\xBF");
        $this->streamRows(
            self::REPORT_COLUMNS[$reportType],
            function (int $limit, int $offset) use ($reportType, $range, $filters): array {
                return $this->analytics->adminReportRows(
                    $reportType,
                    $range['start_at'],
                    $range['end_exclusive'],
                    $filters,
                    $limit,
                    $offset,
                );
            },
            $emit,
        );

        return ['success' => true];
    }

    public function streamOrganizerCsv(
        int $organizerUserId,
        mixed $start,
        mixed $end,
        mixed $event,
        callable $emit,
    ): array {
        $range = $this->dateRange($start, $end);
        $eventId = $this->eventId($event);
        if (!$range['valid'] || (($event !== null && $event !== '') && $eventId === null)) {
            return ['success' => false, 'code' => 'invalid'];
        }
        if ($eventId !== null && $this->analytics->organizerSummary(
            $organizerUserId,
            $range['start_at'],
            $range['end_exclusive'],
            $eventId,
        ) === null) {
            return ['success' => false, 'code' => 'not_found'];
        }
        $emit("\xEF\xBB\xBF");
        $notFound = false;
        $this->streamRows(
            self::ORGANIZER_COLUMNS,
            function (int $limit, int $offset) use ($organizerUserId, $range, $eventId, &$notFound): array {
                $rows = $this->analytics->organizerEventRows(
                    $organizerUserId,
                    $range['start_at'],
                    $range['end_exclusive'],
                    $eventId,
                    $limit,
                    $offset,
                );
                if ($rows === null) {
                    $notFound = true;

                    return [];
                }

                return array_map($this->flattenOrganizerRow(...), $rows);
            },
            $emit,
        );

        return $notFound ? ['success' => false, 'code' => 'not_found'] : ['success' => true];
    }

    private function streamRows(array $columns, callable $page, callable $emit): void
    {
        $rowStream = fopen('php://temp/maxmemory:65536', 'w+');
        if ($rowStream === false) {
            throw new RuntimeException('The report stream could not be opened.');
        }
        try {
            $this->emitCsvRow($rowStream, array_values($columns), $emit);
            for ($offset = 0; ; $offset += 100) {
                $rows = $page(100, $offset);
                foreach ($rows as $row) {
                    $cells = [];
                    foreach (array_keys($columns) as $key) {
                        $cells[] = $this->csvCell($row[$key] ?? '');
                    }
                    $this->emitCsvRow($rowStream, $cells, $emit);
                }
                if (count($rows) < 100) {
                    break;
                }
            }
        } finally {
            fclose($rowStream);
        }
    }

    private function emitCsvRow($stream, array $cells, callable $emit): void
    {
        if (!ftruncate($stream, 0) || !rewind($stream) || fputcsv($stream, $cells, ',', '"', '') === false || !rewind($stream)) {
            throw new RuntimeException('The report row could not be encoded.');
        }
        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                throw new RuntimeException('The report row could not be read.');
            }
            if ($chunk === '') {
                break;
            }
            $emit($chunk);
        }
    }

    private function csvCell(mixed $value): string
    {
        $cell = is_scalar($value) ? (string) $value : '';
        $dangerous = preg_match('/\A[=+\-@\t\r]/u', $cell) === 1;
        $cell = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $cell) ?? '';
        $cell = preg_replace('/ {2,}/u', ' ', $cell) ?? '';
        if ($dangerous || preg_match('/\A[=+\-@]/u', $cell) === 1) {
            $cell = "'" . $cell;
        }

        return $cell;
    }

    private function flattenOrganizerRow(array $row): array
    {
        $payments = [];
        foreach ((array) ($row['verified_payments'] ?? []) as $currency => $amount) {
            $payments[] = $currency . ' ' . $amount;
        }

        return [
            'event_id' => $row['event_id'] ?? '',
            'event_title' => $row['event_title'] ?? '',
            'event_status' => $row['event_status'] ?? '',
            'start_date' => $row['start_date'] ?? '',
            'capacity' => $row['capacity'] ?? 0,
            'confirmed' => $row['registration_counts']['confirmed'] ?? 0,
            'pending' => $row['registration_counts']['pending'] ?? 0,
            'cancelled' => $row['registration_counts']['cancelled'] ?? 0,
            'attendance_count' => $row['attendance_count'] ?? 0,
            'favorites_count' => $row['favorites_count'] ?? 0,
            'review_count' => $row['review_count'] ?? 0,
            'review_average' => $row['review_average'] ?? '0.00',
            'verified_payments' => implode('; ', $payments),
            'refund_attention_count' => $row['refund_attention_count'] ?? 0,
            'archived' => $row['archived'] ?? 0,
        ];
    }

    private function eventId(mixed $event): ?int
    {
        return (is_int($event) || is_string($event))
            && ctype_digit((string) $event)
            && (int) $event > 0
                ? (int) $event
                : null;
    }

    private function parseDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value
                ? $date
                : null;
    }

    private function invalidRange(string $error): array
    {
        return ['valid' => false, 'error' => $error];
    }
}
