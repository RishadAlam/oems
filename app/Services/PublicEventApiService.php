<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Support\Money;
use Throwable;

final class PublicEventApiService
{
    private const INDEX_KEYS = ['search', 'category', 'city', 'date_from', 'date_to', 'price', 'sort', 'page', 'limit'];

    private const SORTS = ['soonest', 'latest', 'price_low', 'price_high'];

    private readonly DateTimeZone $timezone;

    private readonly DateTimeImmutable $now;

    public function __construct(
        private readonly EventRepositoryInterface $events,
        string $timezone,
        private readonly string $origin,
        ?DateTimeImmutable $now = null,
    ) {
        $this->timezone = new DateTimeZone($timezone);
        $this->now = ($now ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
    }

    public function index(array $query): array
    {
        $errors = $this->indexErrors($query);
        if ($errors !== []) {
            return ['success' => false, 'events' => [], 'pagination' => null, 'errors' => $errors];
        }

        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 20);
        $from = $this->date((string) ($query['date_from'] ?? $this->now->format('Y-m-d')));
        $toDate = $this->date((string) ($query['date_to'] ?? $from->modify('+365 days')->format('Y-m-d')));
        $to = $toDate->modify('+1 day');
        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'category' => trim((string) ($query['category'] ?? '')),
            'city' => trim((string) ($query['city'] ?? '')),
            'price' => (string) ($query['price'] ?? ''),
            'sort' => (string) ($query['sort'] ?? 'soonest'),
        ];
        $total = $this->events->countPublicRange($from, $to, $filters);
        $rows = $this->events->publicRange($from, $to, $filters, $limit, ($page - 1) * $limit);

        return [
            'success' => true,
            'events' => array_map($this->present(...), $rows),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $limit)),
            ],
            'filters' => array_merge($filters, [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $toDate->format('Y-m-d'),
            ]),
            'errors' => [],
        ];
    }

    public function calendar(mixed $month): array
    {
        if (!is_scalar($month)) {
            return $this->calendarFailure();
        }
        $month = trim((string) $month);
        if ($month === '') {
            $month = $this->now->format('Y-m');
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m', $month, $this->timezone);
        $parseErrors = DateTimeImmutable::getLastErrors();
        if (!$start instanceof DateTimeImmutable
            || ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0))
            || $start->format('Y-m') !== $month
            || $start < $this->now->modify('first day of this month -24 months')->setTime(0, 0)
            || $start > $this->now->modify('first day of this month +60 months')->setTime(0, 0)) {
            return $this->calendarFailure();
        }

        $end = $start->modify('+1 month');
        $rows = $this->events->publicRange($start, $end, ['sort' => 'soonest'], 1000, 0);
        $events = array_map($this->present(...), $rows);
        $gridStart = $start->modify('-' . ((int) $start->format('N') - 1) . ' days');
        $days = [];
        for ($index = 0; $index < 42; $index++) {
            $date = $gridStart->modify('+' . $index . ' days');
            $dateKey = $date->format('Y-m-d');
            $days[] = [
                'date' => $dateKey,
                'day' => (int) $date->format('j'),
                'label' => $date->format('l, F j, Y'),
                'in_month' => $date->format('Y-m') === $month,
                'events' => array_values(array_filter(
                    $events,
                    static fn (array $event): bool => str_starts_with((string) $event['schedule']['starts_at'], $dateKey),
                )),
            ];
        }

        return [
            'success' => true,
            'month' => $month,
            'label' => $start->format('F Y'),
            'previous_month' => $start->modify('-1 month')->format('Y-m'),
            'next_month' => $start->modify('+1 month')->format('Y-m'),
            'days' => $days,
            'events' => $events,
            'errors' => [],
        ];
    }

    public function detail(mixed $slug): array
    {
        if (!is_scalar($slug)) {
            return ['success' => false, 'event' => null, 'errors' => ['event' => ['Event not found.']]];
        }
        $slug = mb_strtolower(trim((string) $slug));
        if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) !== 1) {
            return ['success' => false, 'event' => null, 'errors' => ['event' => ['Event not found.']]];
        }
        $event = $this->events->findPublishedBySlug($slug);
        if (!is_array($event)
            || !in_array((string) ($event['status'] ?? ''), ['published', 'completed'], true)
            || !empty($event['deleted_at'])) {
            return ['success' => false, 'event' => null, 'errors' => ['event' => ['Event not found.']]];
        }

        return ['success' => true, 'event' => $this->present($event), 'errors' => []];
    }

    private function indexErrors(array $query): array
    {
        if (array_diff(array_keys($query), self::INDEX_KEYS) !== []) {
            return ['query' => ['One or more query parameters are not supported.']];
        }
        foreach ($query as $value) {
            if (!is_scalar($value)) {
                return ['query' => ['Every query parameter must be a single value.']];
            }
        }
        $search = trim((string) ($query['search'] ?? ''));
        $category = trim((string) ($query['category'] ?? ''));
        $city = trim((string) ($query['city'] ?? ''));
        if (mb_strlen($search) > 100
            || ($category !== '' && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $category) !== 1)
            || mb_strlen($city) > 100) {
            return ['query' => ['One or more query parameters are invalid.']];
        }
        if (!in_array((string) ($query['price'] ?? ''), ['', 'free', 'paid'], true)
            || !in_array((string) ($query['sort'] ?? 'soonest'), self::SORTS, true)) {
            return ['query' => ['One or more query parameters are invalid.']];
        }
        if (preg_match('/\A[1-9][0-9]{0,3}\z/D', (string) ($query['page'] ?? '1')) !== 1
            || preg_match('/\A(?:[1-9]|[1-9][0-9]|100)\z/D', (string) ($query['limit'] ?? '20')) !== 1) {
            return ['pagination' => ['Page and limit values are invalid.']];
        }

        try {
            $from = $this->date((string) ($query['date_from'] ?? $this->now->format('Y-m-d')));
            $to = $this->date((string) ($query['date_to'] ?? $from->modify('+365 days')->format('Y-m-d')));
        } catch (Throwable) {
            return ['date' => ['Enter real dates in YYYY-MM-DD format.']];
        }
        $earliest = $this->now->modify('first day of this month -24 months')->setTime(0, 0);
        $latest = $this->now->modify('first day of this month +61 months')->setTime(0, 0);
        if ($to < $from || $to > $from->modify('+365 days') || $from < $earliest || $to >= $latest) {
            return ['date' => ['The date range must be ordered and no longer than 366 days.']];
        }

        return [];
    }

    private function present(array $event): array
    {
        $visibility = ($event['location_visibility'] ?? 'public') === 'registered' ? 'registered' : 'public';
        $location = [
            'visibility' => $visibility,
            'city' => $this->scalar($event['venue_city'] ?? null, 100),
            'country' => $this->scalar($event['venue_country'] ?? null, 100),
        ];
        if ($visibility === 'public') {
            $location = array_merge($location, [
                'venue' => $this->scalar($event['venue_name'] ?? null, 190),
                'address' => $this->scalar($event['venue_address_line'] ?? null, 255),
                'postal_code' => $this->scalar($event['venue_postal_code'] ?? null, 32),
                'latitude' => $this->coordinate($event['venue_latitude'] ?? null, -90, 90),
                'longitude' => $this->coordinate($event['venue_longitude'] ?? null, -180, 180),
                'map_url' => $this->httpsUrl($event['venue_map_url'] ?? $event['map_url'] ?? null),
                'arrival_notes' => $this->scalar($event['arrival_notes'] ?? null, 500),
            ]);
        }

        $amount = Money::normalize((string) ($event['ticket_price'] ?? '')) ?? '0.00';

        return [
            'slug' => $this->scalar($event['slug'] ?? null, 190),
            'title' => $this->scalar($event['title'] ?? null, 180),
            'description' => $this->scalar($event['description'] ?? null, 20000),
            'banner_url' => $this->publicUrl($event['banner'] ?? null),
            'speaker' => $this->scalar($event['speaker'] ?? null, 190),
            'status' => (string) ($event['status'] ?? ''),
            'category' => [
                'name' => $this->scalar($event['category_name'] ?? null, 120),
                'slug' => $this->scalar($event['category_slug'] ?? null, 140),
            ],
            'organizer' => ['name' => $this->scalar($event['organization_name'] ?? null, 190)],
            'schedule' => [
                'starts_at' => $this->iso($event['start_date'] ?? null),
                'ends_at' => $this->iso($event['end_date'] ?? null),
                'registration_deadline' => $this->iso($event['registration_deadline'] ?? null),
            ],
            'price' => [
                'amount' => $amount,
                'currency' => $this->scalar($event['currency'] ?? 'BDT', 3),
                'free' => Money::isFree($amount),
            ],
            'availability' => [
                'capacity' => max(0, (int) ($event['capacity'] ?? 0)),
                'available_seats' => max(0, (int) ($event['available_seats'] ?? 0)),
                'waitlist_enabled' => (int) ($event['waitlist_enabled'] ?? 0) === 1,
            ],
            'location' => $location,
            'tags' => array_values(array_slice(array_filter(
                is_array($event['tags'] ?? null) ? $event['tags'] : [],
                static fn (mixed $tag): bool => is_string($tag) && trim($tag) !== '',
            ), 0, 12)),
            'links' => [
                'web' => $this->origin('/events/' . rawurlencode((string) ($event['slug'] ?? ''))),
                'calendar' => $this->origin('/events/' . rawurlencode((string) ($event['slug'] ?? '')) . '/calendar.ics'),
            ],
        ];
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid date.');
        }

        return $date;
    }

    private function iso(mixed $value): ?string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable((string) $value, $this->timezone))->format(DATE_ATOM);
        } catch (Throwable) {
            return null;
        }
    }

    private function scalar(mixed $value, int $limit): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '';
        $value = mb_substr(trim($value), 0, $limit);

        return $value === '' ? null : $value;
    }

    private function coordinate(mixed $value, float $minimum, float $maximum): ?string
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < $minimum || (float) $value > $maximum) {
            return null;
        }

        return number_format((float) $value, 7, '.', '');
    }

    private function httpsUrl(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_URL) !== false && parse_url($value, PHP_URL_SCHEME) === 'https'
            ? $value
            : null;
    }

    private function publicUrl(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return str_starts_with($value, '/') && !str_starts_with($value, '//') ? $this->origin($value) : null;
    }

    private function origin(string $path): string
    {
        return rtrim($this->origin, '/') . '/' . ltrim($path, '/');
    }

    private function calendarFailure(): array
    {
        return ['success' => false, 'month' => null, 'days' => [], 'events' => [], 'errors' => ['month' => ['Enter an available month in YYYY-MM format.']]];
    }
}
