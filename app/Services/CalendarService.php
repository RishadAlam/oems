<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CalendarService
{
    private readonly DateTimeZone $timezone;

    private readonly string $host;

    public function __construct(
        string $timezone,
        private readonly string $origin,
        private readonly ?DateTimeImmutable $now = null,
    ) {
        $this->timezone = new DateTimeZone($timezone);
        $host = parse_url($origin, PHP_URL_HOST);
        $this->host = is_string($host) && $host !== ''
            ? preg_replace('/[^A-Za-z0-9.-]+/', '-', mb_strtolower($host))
            : 'oems.local';
    }

    public function forPublicEvent(array $event): string
    {
        return $this->render($this->payload($event, false), 'event-' . $this->positiveId($event['id'] ?? null));
    }

    public function forOwnedRegistration(array $registration): string
    {
        if (($registration['registration_status'] ?? $registration['status'] ?? null) !== 'confirmed') {
            throw new InvalidArgumentException('Only confirmed registrations can be added to a calendar.');
        }

        $registrationId = $this->positiveId($registration['registration_id'] ?? $registration['id'] ?? null);
        $eventId = $this->positiveId($registration['event_id'] ?? null);

        return $this->render(
            $this->payload($registration, true),
            'registration-' . $registrationId . '-event-' . $eventId,
        );
    }

    public function googleUrl(array $event, bool $exactLocation = false): string
    {
        $payload = $this->payload($event, $exactLocation);
        $query = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $payload['title'],
            'dates' => $this->utc($payload['start'])->format('Ymd\THis\Z')
                . '/' . $this->utc($payload['end'])->format('Ymd\THis\Z'),
            'details' => $payload['description'],
            'location' => $payload['location'],
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://calendar.google.com/calendar/render?' . $query;
    }

    private function render(array $payload, string $uidPrefix): string
    {
        $stamp = $this->utc(($this->now ?? new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s'));
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OEMS//Event Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uidPrefix . '@' . $this->host,
            'DTSTAMP:' . $stamp->format('Ymd\THis\Z'),
            'DTSTART:' . $this->utc($payload['start'])->format('Ymd\THis\Z'),
            'DTEND:' . $this->utc($payload['end'])->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->escape($payload['title']),
            'DESCRIPTION:' . $this->escape($payload['description']),
        ];
        if ($payload['location'] !== '') {
            $lines[] = 'LOCATION:' . $this->escape($payload['location']);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines)) . "\r\n";
    }

    private function payload(array $event, bool $exactLocation): array
    {
        $start = $this->scalar($event['start_date'] ?? $event['event_start_date'] ?? '', 64);
        $end = $this->scalar($event['end_date'] ?? $event['event_end_date'] ?? '', 64);
        if ($start === '' || $end === '' || $this->utc($end) <= $this->utc($start)) {
            throw new InvalidArgumentException('The event calendar schedule is invalid.');
        }

        $publicLocation = ($event['location_visibility'] ?? 'public') === 'public';
        $location = $exactLocation || $publicLocation
            ? $this->join([
                $event['venue_name'] ?? null,
                $event['venue_address_line'] ?? null,
                $event['venue_city'] ?? null,
                $event['venue_country'] ?? null,
            ])
            : $this->join([$event['venue_city'] ?? null, $event['venue_country'] ?? null]);

        return [
            'title' => $this->scalar($event['title'] ?? $event['event_title'] ?? '', 180),
            'description' => $this->scalar($event['description'] ?? 'Event details are available in OEMS.', 2000),
            'start' => $start,
            'end' => $end,
            'location' => $location,
        ];
    }

    private function utc(string $value): DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable($value, $this->timezone))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new InvalidArgumentException('The event calendar schedule is invalid.');
        }
    }

    private function escape(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\r", "\n", ';', ','],
            ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';
        $limit = 75;
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($current !== '' && strlen($current . $character) > $limit) {
                $parts[] = $current;
                $current = $character;
                $limit = 74;
                continue;
            }
            $current .= $character;
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return implode("\r\n ", $parts);
    }

    private function scalar(mixed $value, int $limit): string
    {
        $value = is_scalar($value) ? strip_tags((string) $value) : '';
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, $limit);
    }

    private function join(array $parts): string
    {
        return implode(', ', array_values(array_filter(
            array_map(fn (mixed $part): string => $this->scalar($part, 190), $parts),
            static fn (string $part): bool => $part !== '',
        )));
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('The calendar identity is invalid.');
        }

        return (int) $id;
    }
}
