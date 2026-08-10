<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\AnalyticsRepositoryInterface;
use PDO;

final class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    private const EVENT_STATUSES = ['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'];

    private const REGISTRATION_STATUSES = ['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'];

    private const REPORT_TYPES = ['events', 'registrations', 'payments', 'attendance', 'organizers'];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function organizerSummary(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId = null,
    ): ?array {
        if ($eventId !== null && !$this->organizerOwnsEvent($organizerUserId, $eventId)) {
            return null;
        }

        $scope = 'organizers.user_id = :organizer_user_id';
        $parameters = ['organizer_user_id' => $organizerUserId];
        if ($eventId !== null) {
            $scope .= ' AND events.id = :event_id';
            $parameters['event_id'] = $eventId;
        }

        return $this->summary($startAt, $endExclusive, $scope, $parameters);
    }

    public function organizerEventRows(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId,
        int $limit,
        int $offset,
    ): ?array {
        if ($eventId !== null && !$this->organizerOwnsEvent($organizerUserId, $eventId)) {
            return null;
        }

        $where = 'organizers.user_id = :organizer_user_id
            AND events.start_date >= :start_at
            AND events.start_date < :end_exclusive';
        $parameters = [
            'organizer_user_id' => $organizerUserId,
            'start_at' => $startAt,
            'end_exclusive' => $endExclusive,
        ];
        if ($eventId !== null) {
            $where .= ' AND events.id = :event_id';
            $parameters['event_id'] = $eventId;
        }

        return $this->eventRows($where, $parameters, $startAt, $endExclusive, $limit, $offset);
    }

    public function adminSummary(string $startAt, string $endExclusive, array $filters = []): array
    {
        $scope = '1 = 1';
        $parameters = [];
        if (isset($filters['event_status']) && in_array($filters['event_status'], self::EVENT_STATUSES, true)) {
            $scope .= ' AND events.status = :event_status';
            $parameters['event_status'] = $filters['event_status'];
        }
        $summary = $this->summary(
            $startAt,
            $endExclusive,
            $scope,
            $parameters,
            is_string($filters['currency'] ?? null) ? $filters['currency'] : null,
        );
        $summary['active_users'] = (int) $this->connection->query(
            "SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL",
        )->fetchColumn();
        $summary['approved_organizers'] = (int) $this->connection->query(
            "SELECT COUNT(*)
             FROM organizers
             INNER JOIN users ON users.id = organizers.user_id
             WHERE organizers.approval_status = 'approved'
               AND users.status = 'active'
               AND users.deleted_at IS NULL",
        )->fetchColumn();
        $summary['pending_event_queue'] = (int) $this->connection->query(
            "SELECT COUNT(*) FROM events WHERE status = 'pending' AND deleted_at IS NULL",
        )->fetchColumn();
        $summary['pending_payment_queue'] = (int) $this->connection->query(
            "SELECT COUNT(*) FROM payments WHERE status = 'pending'",
        )->fetchColumn();
        $summary['top_events'] = $this->topEvents($startAt, $endExclusive, $scope, $parameters);
        $summary['top_categories'] = $this->topCategories($startAt, $endExclusive, $scope, $parameters);

        return $summary;
    }

    public function organizerSeries(
        int $organizerUserId,
        string $startAt,
        string $endExclusive,
        ?int $eventId = null,
    ): ?array {
        if ($eventId !== null && !$this->organizerOwnsEvent($organizerUserId, $eventId)) {
            return null;
        }
        $scope = 'organizers.user_id = :series_organizer_user_id';
        $parameters = ['series_organizer_user_id' => $organizerUserId];
        if ($eventId !== null) {
            $scope .= ' AND events.id = :series_event_id';
            $parameters['series_event_id'] = $eventId;
        }

        return $this->series($startAt, $endExclusive, $scope, $parameters);
    }

    public function adminSeries(string $startAt, string $endExclusive, array $filters = []): array
    {
        $scope = '1 = 1';
        $parameters = [];
        if (is_string($filters['event_status'] ?? null)
            && in_array($filters['event_status'], self::EVENT_STATUSES, true)) {
            $scope .= ' AND events.status = :series_event_status';
            $parameters['series_event_status'] = $filters['event_status'];
        }

        return $this->series(
            $startAt,
            $endExclusive,
            $scope,
            $parameters,
            is_string($filters['currency'] ?? null) ? $filters['currency'] : null,
        );
    }

    public function adminReportRows(
        string $type,
        string $startAt,
        string $endExclusive,
        array $filters,
        int $limit,
        int $offset,
    ): array {
        if (!in_array($type, self::REPORT_TYPES, true)) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $status = is_string($filters['event_status'] ?? null)
            && in_array($filters['event_status'], self::EVENT_STATUSES, true)
                ? $filters['event_status']
                : null;
        $currency = is_string($filters['currency'] ?? null) ? $filters['currency'] : null;

        return match ($type) {
            'events' => $this->adminEventReportRows($startAt, $endExclusive, $status, $currency, $limit, $offset),
            'registrations' => $this->registrationReportRows($startAt, $endExclusive, $status, $limit, $offset),
            'payments' => $this->paymentReportRows($startAt, $endExclusive, $status, $currency, $limit, $offset),
            'attendance' => $this->attendanceReportRows($startAt, $endExclusive, $status, $limit, $offset),
            'organizers' => $this->organizerReportRows($startAt, $endExclusive, $status, $limit, $offset),
        };
    }

    private function summary(
        string $startAt,
        string $endExclusive,
        string $scope,
        array $scopeParameters,
        ?string $currency = null,
    ): array {
        $dateParameters = array_merge($scopeParameters, [
            'start_at' => $startAt,
            'end_exclusive' => $endExclusive,
        ]);
        $eventRows = $this->fetchAll(
            "SELECT events.status, COUNT(*) AS aggregate_count, COALESCE(SUM(events.capacity), 0) AS capacity_total
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND events.start_date >= :start_at
               AND events.start_date < :end_exclusive
             GROUP BY events.status",
            $dateParameters,
        );
        $lifecycle = array_fill_keys(self::EVENT_STATUSES, 0);
        $capacity = 0;
        foreach ($eventRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $lifecycle)) {
                $lifecycle[$status] = (int) $row['aggregate_count'];
                $capacity += (int) $row['capacity_total'];
            }
        }
        $lifecycle['total'] = array_sum($lifecycle);

        $registrationRows = $this->fetchAll(
            "SELECT registrations.status, COUNT(*) AS aggregate_count
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND registrations.registered_at >= :start_at
               AND registrations.registered_at < :end_exclusive
             GROUP BY registrations.status",
            $dateParameters,
        );
        $registrations = array_fill_keys(self::REGISTRATION_STATUSES, 0);
        foreach ($registrationRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $registrations)) {
                $registrations[$status] = (int) $row['aggregate_count'];
            }
        }
        $registrations['total'] = array_sum($registrations);

        $attendance = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM attendance
             INNER JOIN registrations ON registrations.id = attendance.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND attendance.status = 'present'
               AND attendance.scanned_at >= :start_at
               AND attendance.scanned_at < :end_exclusive",
            $dateParameters,
        );
        $favorites = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM favorites
             INNER JOIN events ON events.id = favorites.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND favorites.created_at >= :start_at
               AND favorites.created_at < :end_exclusive",
            $dateParameters,
        );
        $review = $this->fetchOne(
            "SELECT COUNT(*) AS published_count, AVG(reviews.rating) AS rating_average
             FROM reviews
             INNER JOIN events ON events.id = reviews.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND reviews.status = 'published'
               AND reviews.created_at >= :start_at
               AND reviews.created_at < :end_exclusive",
            $dateParameters,
        );
        $moneyScope = $scope;
        $moneyParameters = $dateParameters;
        if ($currency !== null) {
            $moneyScope .= ' AND payments.currency = :currency';
            $moneyParameters['currency'] = $currency;
        }
        $moneyRows = $this->fetchAll(
            "SELECT payments.currency, SUM(payments.amount) AS amount_total
             FROM payments
             INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$moneyScope}
               AND payments.status = 'paid'
               AND payments.paid_at >= :start_at
               AND payments.paid_at < :end_exclusive
             GROUP BY payments.currency
             ORDER BY payments.currency",
            $moneyParameters,
        );
        $verifiedPayments = [];
        foreach ($moneyRows as $row) {
            $verifiedPayments[(string) $row['currency']] = $this->moneyDecimal($row['amount_total'] ?? 0);
        }
        $refundAttention = (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM payments
             INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND payments.status = 'paid'
               AND registrations.status = 'cancelled'
               AND payments.paid_at >= :start_at
               AND payments.paid_at < :end_exclusive",
            $dateParameters,
        );

        return [
            'lifecycle' => $lifecycle,
            'registrations' => $registrations,
            'attendance_count' => $attendance,
            'favorites_count' => $favorites,
            'reviews' => [
                'published' => (int) ($review['published_count'] ?? 0),
                'average' => $this->ratingDecimal($review['rating_average'] ?? 0),
            ],
            'verified_payments' => $verifiedPayments,
            'refund_attention_count' => $refundAttention,
            'capacity_total' => $capacity,
            'capacity_utilization_rate' => $this->rate($registrations['confirmed'], $capacity),
            'attendance_rate' => $this->rate($attendance, $registrations['confirmed']),
        ];
    }

    private function eventRows(
        string $where,
        array $parameters,
        string $startAt,
        string $endExclusive,
        int $limit,
        int $offset,
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $parameters['metric_start'] = $startAt;
        $parameters['metric_end'] = $endExclusive;
        $sql = "SELECT events.id AS event_id,
                       events.title AS event_title,
                       events.status AS event_status,
                       events.start_date,
                       events.capacity,
                       CASE WHEN events.deleted_at IS NULL THEN 0 ELSE 1 END AS archived,
                       (SELECT COUNT(*) FROM registrations AS rc WHERE rc.event_id = events.id AND rc.status = 'confirmed' AND rc.registered_at >= metric_bounds.start_at AND rc.registered_at < metric_bounds.end_exclusive) AS confirmed_count,
                       (SELECT COUNT(*) FROM registrations AS rp WHERE rp.event_id = events.id AND rp.status = 'pending' AND rp.registered_at >= metric_bounds.start_at AND rp.registered_at < metric_bounds.end_exclusive) AS pending_count,
                       (SELECT COUNT(*) FROM registrations AS rx WHERE rx.event_id = events.id AND rx.status = 'cancelled' AND rx.registered_at >= metric_bounds.start_at AND rx.registered_at < metric_bounds.end_exclusive) AS cancelled_count,
                       (SELECT COUNT(*) FROM attendance AS a INNER JOIN registrations AS ar ON ar.id = a.registration_id WHERE ar.event_id = events.id AND a.status = 'present' AND a.scanned_at >= metric_bounds.start_at AND a.scanned_at < metric_bounds.end_exclusive) AS attendance_count,
                       (SELECT COUNT(*) FROM favorites AS f WHERE f.event_id = events.id AND f.created_at >= metric_bounds.start_at AND f.created_at < metric_bounds.end_exclusive) AS favorites_count,
                       (SELECT COUNT(*) FROM reviews AS rv WHERE rv.event_id = events.id AND rv.status = 'published' AND rv.created_at >= metric_bounds.start_at AND rv.created_at < metric_bounds.end_exclusive) AS review_count,
                       (SELECT AVG(rv2.rating) FROM reviews AS rv2 WHERE rv2.event_id = events.id AND rv2.status = 'published' AND rv2.created_at >= metric_bounds.start_at AND rv2.created_at < metric_bounds.end_exclusive) AS review_average,
                       (SELECT COUNT(*) FROM payments AS pa INNER JOIN registrations AS pra ON pra.id = pa.registration_id WHERE pra.event_id = events.id AND pra.status = 'cancelled' AND pa.status = 'paid' AND pa.paid_at >= metric_bounds.start_at AND pa.paid_at < metric_bounds.end_exclusive) AS refund_attention_count
                FROM events
                INNER JOIN organizers ON organizers.id = events.organizer_id
                CROSS JOIN (SELECT :metric_start AS start_at, :metric_end AS end_exclusive) AS metric_bounds
                WHERE {$where}
                ORDER BY events.start_date DESC, events.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $rows = $this->fetchAll($sql, $parameters);
        $paymentMaps = $this->paymentMapsForEvents(array_column($rows, 'event_id'), $startAt, $endExclusive);

        return array_map(function (array $row) use ($paymentMaps): array {
            $eventId = (int) $row['event_id'];

            return [
                'event_id' => $eventId,
                'event_title' => (string) $row['event_title'],
                'event_status' => (string) $row['event_status'],
                'start_date' => (string) $row['start_date'],
                'capacity' => (int) $row['capacity'],
                'archived' => (int) $row['archived'],
                'registration_counts' => [
                    'confirmed' => (int) $row['confirmed_count'],
                    'pending' => (int) $row['pending_count'],
                    'cancelled' => (int) $row['cancelled_count'],
                ],
                'attendance_count' => (int) $row['attendance_count'],
                'favorites_count' => (int) $row['favorites_count'],
                'review_count' => (int) $row['review_count'],
                'review_average' => $this->ratingDecimal($row['review_average'] ?? 0),
                'verified_payments' => $paymentMaps[$eventId] ?? [],
                'refund_attention_count' => (int) $row['refund_attention_count'],
            ];
        }, $rows);
    }

    private function series(
        string $startAt,
        string $endExclusive,
        string $scope,
        array $scopeParameters,
        ?string $currency = null,
    ): array {
        $start = new \DateTimeImmutable($startAt);
        $end = new \DateTimeImmutable($endExclusive);
        $granularity = (int) $start->diff($end)->format('%a') > 90 ? 'month' : 'day';
        $length = $granularity === 'month' ? 7 : 10;
        $periodExpression = static fn (string $column): string => "SUBSTR({$column}, 1, {$length})";
        $periods = [];
        $cursor = $granularity === 'month'
            ? $start->modify('first day of this month')
            : $start;
        while ($cursor < $end) {
            $periods[] = $cursor->format($granularity === 'month' ? 'Y-m' : 'Y-m-d');
            $cursor = $cursor->modify($granularity === 'month' ? '+1 month' : '+1 day');
        }
        $dateParameters = array_merge($scopeParameters, [
            'series_start_at' => $startAt,
            'series_end_exclusive' => $endExclusive,
        ]);

        $events = $this->seriesCounts(
            "SELECT {$periodExpression('events.start_date')} AS period, COUNT(*) AS aggregate_count
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND events.start_date >= :series_start_at
               AND events.start_date < :series_end_exclusive
             GROUP BY period ORDER BY period",
            $dateParameters,
        );
        $registrations = $this->seriesCounts(
            "SELECT {$periodExpression('registrations.registered_at')} AS period, COUNT(*) AS aggregate_count
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND registrations.registered_at >= :series_start_at
               AND registrations.registered_at < :series_end_exclusive
             GROUP BY period ORDER BY period",
            $dateParameters,
        );
        $attendance = $this->seriesCounts(
            "SELECT {$periodExpression('attendance.scanned_at')} AS period, COUNT(*) AS aggregate_count
             FROM attendance
             INNER JOIN registrations ON registrations.id = attendance.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$scope}
               AND attendance.status = 'present'
               AND attendance.scanned_at >= :series_start_at
               AND attendance.scanned_at < :series_end_exclusive
             GROUP BY period ORDER BY period",
            $dateParameters,
        );
        $paymentScope = $scope;
        $paymentParameters = $dateParameters;
        if ($currency !== null) {
            $paymentScope .= ' AND payments.currency = :series_currency';
            $paymentParameters['series_currency'] = $currency;
        }
        $paymentRows = $this->fetchAll(
            "SELECT {$periodExpression('payments.paid_at')} AS period,
                    payments.currency,
                    SUM(payments.amount) AS amount_total
             FROM payments
             INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE {$paymentScope}
               AND payments.status = 'paid'
               AND payments.paid_at >= :series_start_at
               AND payments.paid_at < :series_end_exclusive
             GROUP BY period, payments.currency
             ORDER BY period, payments.currency",
            $paymentParameters,
        );
        $payments = [];
        foreach ($paymentRows as $row) {
            $payments[(string) $row['currency']][(string) $row['period']] = $this->moneyDecimal($row['amount_total'] ?? 0);
        }
        $categoryRows = $this->fetchAll(
            "SELECT categories.name AS category_label, COUNT(*) AS aggregate_count
             FROM registrations
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             INNER JOIN categories ON categories.id = events.category_id
             WHERE {$scope}
               AND registrations.registered_at >= :series_start_at
               AND registrations.registered_at < :series_end_exclusive
             GROUP BY categories.id, categories.name
             ORDER BY aggregate_count DESC, categories.id ASC
             LIMIT 8",
            $dateParameters,
        );

        return [
            'granularity' => $granularity,
            'periods' => $periods,
            'events' => $this->fillSeries($periods, $events, 0),
            'registrations' => $this->fillSeries($periods, $registrations, 0),
            'attendance' => $this->fillSeries($periods, $attendance, 0),
            'payments' => array_map(
                fn (array $values): array => $this->fillSeries($periods, $values, '0.00'),
                $payments,
            ),
            'categories' => array_map(static fn (array $row): array => [
                'label' => (string) $row['category_label'],
                'count' => (int) $row['aggregate_count'],
            ], $categoryRows),
        ];
    }

    private function seriesCounts(string $sql, array $parameters): array
    {
        $counts = [];
        foreach ($this->fetchAll($sql, $parameters) as $row) {
            $counts[(string) $row['period']] = (int) $row['aggregate_count'];
        }

        return $counts;
    }

    private function fillSeries(array $periods, array $values, int|string $default): array
    {
        $filled = [];
        foreach ($periods as $period) {
            $filled[$period] = $values[$period] ?? $default;
        }

        return $filled;
    }

    private function adminEventReportRows(
        string $startAt,
        string $endExclusive,
        ?string $status,
        ?string $currency,
        int $limit,
        int $offset,
    ): array {
        $where = 'events.start_date >= :start_at AND events.start_date < :end_exclusive';
        $parameters = ['start_at' => $startAt, 'end_exclusive' => $endExclusive];
        if ($status !== null) {
            $where .= ' AND events.status = :event_status';
            $parameters['event_status'] = $status;
        }
        $rows = $this->eventRows($where, $parameters, $startAt, $endExclusive, $limit, $offset);

        return array_map(function (array $row) use ($currency): array {
            $payments = $row['verified_payments'];
            if ($currency !== null) {
                $payments = isset($payments[$currency]) ? [$currency => $payments[$currency]] : [];
            }

            return [
                'event_id' => $row['event_id'],
                'event_status' => $row['event_status'],
                'start_date' => $row['start_date'],
                'capacity' => $row['capacity'],
                'confirmed_registrations' => $row['registration_counts']['confirmed'],
                'attendance_count' => $row['attendance_count'],
                'favorites_count' => $row['favorites_count'],
                'published_review_count' => $row['review_count'],
                'published_review_average' => $row['review_average'],
                'verified_payments' => $this->moneyMapText($payments),
                'refund_attention_count' => $row['refund_attention_count'],
                'archived' => $row['archived'],
            ];
        }, $rows);
    }

    private function registrationReportRows(string $startAt, string $endExclusive, ?string $eventStatus, int $limit, int $offset): array
    {
        $where = 'registrations.registered_at >= :start_at AND registrations.registered_at < :end_exclusive';
        $parameters = ['start_at' => $startAt, 'end_exclusive' => $endExclusive];
        if ($eventStatus !== null) {
            $where .= ' AND events.status = :event_status';
            $parameters['event_status'] = $eventStatus;
        }

        return $this->typedRows($this->fetchAll(
            "SELECT events.id AS event_id, events.status AS event_status, registrations.status AS registration_status, COUNT(*) AS registration_count
             FROM registrations INNER JOIN events ON events.id = registrations.event_id
             WHERE {$where}
             GROUP BY events.id, events.status, registrations.status
             ORDER BY events.id, registrations.status
             LIMIT {$limit} OFFSET {$offset}",
            $parameters,
        ), ['event_id', 'registration_count']);
    }

    private function paymentReportRows(string $startAt, string $endExclusive, ?string $eventStatus, ?string $currency, int $limit, int $offset): array
    {
        $where = 'payments.created_at >= :start_at AND payments.created_at < :end_exclusive';
        $parameters = ['start_at' => $startAt, 'end_exclusive' => $endExclusive];
        if ($eventStatus !== null) {
            $where .= ' AND events.status = :event_status';
            $parameters['event_status'] = $eventStatus;
        }
        if ($currency !== null) {
            $where .= ' AND payments.currency = :currency';
            $parameters['currency'] = $currency;
        }
        $rows = $this->fetchAll(
            "SELECT events.id AS event_id, events.status AS event_status, payments.currency, payments.status AS payment_status, COUNT(*) AS payment_count, SUM(payments.amount) AS amount_total,
                    SUM(CASE WHEN payments.status = 'paid' AND registrations.status = 'cancelled' THEN 1 ELSE 0 END) AS refund_attention_count
             FROM payments
             INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             WHERE {$where}
             GROUP BY events.id, events.status, payments.currency, payments.status
             ORDER BY events.id, payments.currency, payments.status
             LIMIT {$limit} OFFSET {$offset}",
            $parameters,
        );

        return array_map(fn (array $row): array => [
            'event_id' => (int) $row['event_id'],
            'event_status' => (string) $row['event_status'],
            'currency' => (string) $row['currency'],
            'payment_status' => (string) $row['payment_status'],
            'payment_count' => (int) $row['payment_count'],
            'amount_total' => $this->moneyDecimal($row['amount_total']),
            'refund_attention_count' => (int) $row['refund_attention_count'],
        ], $rows);
    }

    private function attendanceReportRows(string $startAt, string $endExclusive, ?string $eventStatus, int $limit, int $offset): array
    {
        $where = 'attendance.scanned_at >= :start_at AND attendance.scanned_at < :end_exclusive';
        $parameters = ['start_at' => $startAt, 'end_exclusive' => $endExclusive];
        if ($eventStatus !== null) {
            $where .= ' AND events.status = :event_status';
            $parameters['event_status'] = $eventStatus;
        }

        return $this->typedRows($this->fetchAll(
            "SELECT events.id AS event_id, events.status AS event_status, attendance.status AS attendance_status, COUNT(*) AS attendance_count
             FROM attendance
             INNER JOIN registrations ON registrations.id = attendance.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             WHERE {$where}
             GROUP BY events.id, events.status, attendance.status
             ORDER BY events.id, attendance.status
             LIMIT {$limit} OFFSET {$offset}",
            $parameters,
        ), ['event_id', 'attendance_count']);
    }

    private function organizerReportRows(string $startAt, string $endExclusive, ?string $eventStatus, int $limit, int $offset): array
    {
        $statusSql = $eventStatus === null ? '' : ' AND events.status = :event_status';
        $parameters = ['start_at' => $startAt, 'end_exclusive' => $endExclusive];
        if ($eventStatus !== null) {
            $parameters['event_status'] = $eventStatus;
        }
        $rows = $this->fetchAll(
            "SELECT organizers.id AS organizer_id,
                    organizers.approval_status,
                    COUNT(events.id) AS event_count,
                    COALESCE(SUM((SELECT COUNT(*) FROM registrations WHERE registrations.event_id = events.id AND registrations.registered_at >= report_bounds.start_at AND registrations.registered_at < report_bounds.end_exclusive)), 0) AS registration_count,
                    COALESCE(SUM((SELECT COUNT(*) FROM attendance INNER JOIN registrations AS ar ON ar.id = attendance.registration_id WHERE ar.event_id = events.id AND attendance.status = 'present' AND attendance.scanned_at >= report_bounds.start_at AND attendance.scanned_at < report_bounds.end_exclusive)), 0) AS attendance_count
             FROM organizers
             CROSS JOIN (SELECT :start_at AS start_at, :end_exclusive AS end_exclusive) AS report_bounds
             INNER JOIN events ON events.organizer_id = organizers.id
                AND events.start_date >= report_bounds.start_at AND events.start_date < report_bounds.end_exclusive{$statusSql}
             GROUP BY organizers.id, organizers.approval_status
             ORDER BY organizers.id
             LIMIT {$limit} OFFSET {$offset}",
            $parameters,
        );

        return $this->typedRows($rows, ['organizer_id', 'event_count', 'registration_count', 'attendance_count']);
    }

    private function paymentMapsForEvents(array $eventIds, string $startAt, string $endExclusive): array
    {
        $ids = array_values(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }
        $placeholders = [];
        $parameters = ['start_at' => $startAt, 'end_exclusive' => $endExclusive];
        foreach ($ids as $index => $id) {
            $key = 'event_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $id;
        }
        $rows = $this->fetchAll(
            'SELECT registrations.event_id, payments.currency, SUM(payments.amount) AS amount_total
             FROM payments
             INNER JOIN registrations ON registrations.id = payments.registration_id
             WHERE registrations.event_id IN (' . implode(', ', $placeholders) . ")
               AND payments.status = 'paid'
               AND payments.paid_at >= :start_at
               AND payments.paid_at < :end_exclusive
             GROUP BY registrations.event_id, payments.currency
             ORDER BY payments.currency",
            $parameters,
        );
        $maps = [];
        foreach ($rows as $row) {
            $maps[(int) $row['event_id']][(string) $row['currency']] = $this->moneyDecimal($row['amount_total']);
        }

        return $maps;
    }

    private function topEvents(string $startAt, string $endExclusive, string $scope, array $parameters): array
    {
        $parameters = array_merge($parameters, ['start_at' => $startAt, 'end_exclusive' => $endExclusive]);

        return $this->typedRows($this->fetchAll(
            "SELECT events.id AS event_id, events.status AS event_status, COUNT(registrations.id) AS registration_count
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             CROSS JOIN (SELECT :start_at AS start_at, :end_exclusive AS end_exclusive) AS report_bounds
             LEFT JOIN registrations ON registrations.event_id = events.id AND registrations.registered_at >= report_bounds.start_at AND registrations.registered_at < report_bounds.end_exclusive
             WHERE {$scope}
               AND events.start_date >= report_bounds.start_at AND events.start_date < report_bounds.end_exclusive
             GROUP BY events.id, events.status
             ORDER BY registration_count DESC, events.id ASC
             LIMIT 5",
            $parameters,
        ), ['event_id', 'registration_count']);
    }

    private function topCategories(string $startAt, string $endExclusive, string $scope, array $parameters): array
    {
        $parameters = array_merge($parameters, ['start_at' => $startAt, 'end_exclusive' => $endExclusive]);

        return $this->typedRows($this->fetchAll(
            "SELECT categories.id AS category_id, categories.name AS category_name, COUNT(registrations.id) AS registration_count
             FROM categories
             INNER JOIN events ON events.category_id = categories.id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             CROSS JOIN (SELECT :start_at AS start_at, :end_exclusive AS end_exclusive) AS report_bounds
             LEFT JOIN registrations ON registrations.event_id = events.id AND registrations.registered_at >= report_bounds.start_at AND registrations.registered_at < report_bounds.end_exclusive
             WHERE {$scope}
               AND events.start_date >= report_bounds.start_at AND events.start_date < report_bounds.end_exclusive
             GROUP BY categories.id, categories.name
             ORDER BY registration_count DESC, categories.id ASC
             LIMIT 5",
            $parameters,
        ), ['category_id', 'registration_count']);
    }

    private function organizerOwnsEvent(int $organizerUserId, int $eventId): bool
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM events INNER JOIN organizers ON organizers.id = events.organizer_id WHERE organizers.user_id = :user_id AND events.id = :event_id',
            ['user_id' => $organizerUserId, 'event_id' => $eventId],
        ) === 1;
    }

    private function moneyMapText(array $map): string
    {
        $parts = [];
        foreach ($map as $currency => $amount) {
            $parts[] = $currency . ' ' . $amount;
        }

        return implode('; ', $parts);
    }

    private function rate(int $numerator, int $denominator): string
    {
        return $denominator <= 0 ? '0.0' : number_format(($numerator / $denominator) * 100, 1, '.', '');
    }

    private function moneyDecimal(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '0.00';
        }
        $decimal = trim((string) $value);
        if (preg_match('/\A([+-]?)([0-9]+)(?:\.([0-9]+))?\z/D', $decimal, $parts) !== 1) {
            return '0.00';
        }
        $whole = ltrim($parts[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = substr(str_pad($parts[3] ?? '', 2, '0'), 0, 2);
        $sign = $parts[1] === '-' && ($whole !== '0' || $fraction !== '00') ? '-' : '';

        return $sign . $whole . '.' . $fraction;
    }

    private function ratingDecimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function typedRows(array $rows, array $integerColumns): array
    {
        foreach ($rows as &$row) {
            foreach ($integerColumns as $column) {
                $row[$column] = (int) ($row[$column] ?? 0);
            }
        }
        unset($row);

        return $rows;
    }

    private function fetchAll(string $sql, array $parameters): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll() ?: [];
    }

    private function fetchOne(string $sql, array $parameters): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        return is_array($row) ? $row : [];
    }

    private function fetchValue(string $sql, array $parameters = []): mixed
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
