<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Closure;
use InvalidArgumentException;

final class LocationService
{
    private const EARTH_RADIUS_KM = 6371.0088;

    private const RADII = [5, 10, 25, 50, 100];

    private const SOURCES = ['device', 'manual'];

    private const MAX_TTL_SECONDS = 1209600;

    private const DEFAULT_DIRECTIONS_HOSTS = [
        'www.google.com',
        'maps.google.com',
        'maps.app.goo.gl',
        'www.openstreetmap.org',
    ];

    private readonly Closure $clock;

    private readonly int $ttlSeconds;

    /** @var list<string> */
    private readonly array $trustedDirectionsHosts;

    public function __construct(
        int $ttlSeconds = self::MAX_TTL_SECONDS,
        ?Closure $clock = null,
        array $trustedDirectionsHosts = self::DEFAULT_DIRECTIONS_HOSTS,
    ) {
        $this->ttlSeconds = max(1, min(self::MAX_TTL_SECONDS, $ttlSeconds));
        $this->clock = $clock ?? static fn (): int => time();
        $this->trustedDirectionsHosts = $this->normalizeHosts($trustedDirectionsHosts);
    }

    public function preference(
        mixed $latitude,
        mixed $longitude,
        mixed $radius,
        string $label,
        string $source,
    ): array {
        [$latitude, $longitude] = $this->coordinates($latitude, $longitude);

        return [
            'latitude' => number_format($latitude, 3, '.', ''),
            'longitude' => number_format($longitude, 3, '.', ''),
            'radius' => $this->radius($radius),
            'label' => $this->label($label),
            'source' => $this->source($source),
            'expires_at' => ($this->clock)() + $this->ttlSeconds,
        ];
    }

    public function fromSession(mixed $value): ?array
    {
        if (!is_array($value) || !$this->futureTimestamp($value['expires_at'] ?? null)) {
            return null;
        }

        try {
            [$latitude, $longitude] = $this->coordinates($value['latitude'] ?? null, $value['longitude'] ?? null);
        } catch (InvalidArgumentException) {
            return null;
        }

        return [
            'latitude' => number_format($latitude, 3, '.', ''),
            'longitude' => number_format($longitude, 3, '.', ''),
            'radius' => $this->radius($value['radius'] ?? null),
            'label' => $this->label(is_string($value['label'] ?? null) ? $value['label'] : ''),
            'source' => $this->source(is_string($value['source'] ?? null) ? $value['source'] : ''),
            'expires_at' => (int) $value['expires_at'],
        ];
    }

    public function radius(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 25;
        }

        $radius = (int) $value;

        return (string) $radius === trim((string) $value) && in_array($radius, self::RADII, true)
            ? $radius
            : 25;
    }

    /**
     * Returns longitude_min/longitude_max as a normal inclusive interval unless
     * longitude_wraps is true. A wrapped interval means longitude >= min OR
     * longitude <= max. At either pole, the interval always covers all longitudes.
     *
     * @return array{latitude_min: string, latitude_max: string, longitude_min: string, longitude_max: string, longitude_wraps: bool}
     */
    public function bounds(array $preference): array
    {
        [$latitude, $longitude] = $this->coordinates($preference['latitude'] ?? null, $preference['longitude'] ?? null);
        $radius = $this->radius($preference['radius'] ?? null);
        $angularDistance = $radius / self::EARTH_RADIUS_KM;
        $latitudeDelta = rad2deg($angularDistance);
        $latitudeMin = max(-90.0, $latitude - $latitudeDelta);
        $latitudeMax = min(90.0, $latitude + $latitudeDelta);
        $allLongitudes = $latitudeMin <= -90.0 || $latitudeMax >= 90.0;
        $cosine = cos(deg2rad($latitude));

        if ($allLongitudes || abs($cosine) < 0.000000000001) {
            $longitudeMin = -180.0;
            $longitudeMax = 180.0;
            $longitudeWraps = false;
        } else {
            $longitudeRatio = max(-1.0, min(1.0, sin($angularDistance) / $cosine));
            $longitudeDelta = rad2deg(asin($longitudeRatio));
            $longitudeMin = $longitude - $longitudeDelta;
            $longitudeMax = $longitude + $longitudeDelta;
            $longitudeWraps = false;

            if ($longitudeMin < -180.0) {
                $longitudeMin += 360.0;
                $longitudeWraps = true;
            }

            if ($longitudeMax > 180.0) {
                $longitudeMax -= 360.0;
                $longitudeWraps = true;
            }
        }

        return [
            'latitude_min' => number_format($latitudeMin, 6, '.', ''),
            'latitude_max' => number_format($latitudeMax, 6, '.', ''),
            'longitude_min' => number_format($longitudeMin, 6, '.', ''),
            'longitude_max' => number_format($longitudeMax, 6, '.', ''),
            'longitude_wraps' => $longitudeWraps,
        ];
    }

    public function distanceLabel(mixed $distanceKm, bool $exact): ?string
    {
        if (!is_numeric($distanceKm) || !is_finite((float) $distanceKm) || (float) $distanceKm < 0) {
            return null;
        }

        $distance = (float) $distanceKm;

        if ($exact) {
            return number_format($distance, 1, '.', '') . ' km away';
        }

        foreach (self::RADII as $radius) {
            if ($distance <= $radius) {
                return "Within {$radius} km";
            }
        }

        return 'More than 100 km away';
    }

    public function canViewExactLocation(
        array $event,
        ?int $userId,
        bool $isAdministrator = false,
        bool $isOrganizer = false,
        ?string $registrationStatus = null,
    ): bool {
        if (($event['location_visibility'] ?? 'public') === 'public') {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        if ($isAdministrator) {
            return true;
        }

        if ($isOrganizer && $userId === (int) ($event['organizer_user_id'] ?? 0)) {
            return true;
        }

        return $registrationStatus === 'confirmed';
    }

    public function presentEventLocation(array $event, bool $exactLocationVisible): array
    {
        $parts = $exactLocationVisible ? [
            trim((string) ($event['venue_name'] ?? '')),
            trim((string) ($event['venue_address_line'] ?? '')),
            trim((string) ($event['venue_city'] ?? '')),
            trim((string) ($event['venue_postal_code'] ?? '')),
            trim((string) ($event['venue_country'] ?? '')),
        ] : [
            trim((string) ($event['venue_city'] ?? '')),
            trim((string) ($event['venue_country'] ?? '')),
        ];
        $parts = array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));
        $display = $parts === [] ? 'Venue to be announced' : implode(', ', $parts);
        $presented = array_merge($event, [
            'address' => $display,
            'venue_display' => $display,
            'exact_location_visible' => $exactLocationVisible,
        ]);

        if (!$exactLocationVisible) {
            foreach ([
                'venue_name',
                'venue_address_line',
                'venue_postal_code',
                'venue_latitude',
                'venue_longitude',
                'venue_map_url',
                'map_url',
                'arrival_notes',
                'latitude',
                'longitude',
            ] as $field) {
                unset($presented[$field]);
            }
        }

        return $presented;
    }

    public function directionsUrl(array $location): ?string
    {
        $mapUrl = $location['map_url'] ?? null;

        if (is_string($mapUrl) && $this->isTrustedDirectionsUrl($mapUrl)) {
            $mapUrl = trim($mapUrl);

            return $mapUrl;
        }

        try {
            [$latitude, $longitude] = $this->coordinates($location['latitude'] ?? null, $location['longitude'] ?? null);
        } catch (InvalidArgumentException) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='
            . rawurlencode($this->coordinateString($latitude) . ',' . $this->coordinateString($longitude));
    }

    public function isTrustedDirectionsUrl(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $parts = parse_url(trim($value));

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        return $host !== '' && in_array($host, $this->trustedDirectionsHosts, true);
    }

    private function coordinates(mixed $latitude, mixed $longitude): array
    {
        if (!is_numeric($latitude) || !is_numeric($longitude) || !is_finite((float) $latitude) || !is_finite((float) $longitude)) {
            throw new InvalidArgumentException('Coordinates must be numeric.');
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Coordinates are outside supported bounds.');
        }

        return [$latitude, $longitude];
    }

    private function coordinateString(float $coordinate): string
    {
        return rtrim(rtrim(number_format($coordinate, 7, '.', ''), '0'), '.');
    }

    private function futureTimestamp(mixed $value): bool
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return false;
        }

        $timestamp = (int) $value;
        $now = ($this->clock)();

        return $timestamp > $now && $timestamp <= $now + self::MAX_TTL_SECONDS;
    }

    private function label(string $label): string
    {
        return mb_substr(trim($label) ?: 'Current area', 0, 80);
    }

    private function source(string $source): string
    {
        return in_array($source, self::SOURCES, true) ? $source : 'manual';
    }

    /** @param array<array-key, mixed> $hosts */
    private function normalizeHosts(array $hosts): array
    {
        $normalized = [];

        foreach ($hosts as $host) {
            if (!is_string($host)) {
                continue;
            }

            $host = strtolower(rtrim(trim($host), '.'));

            if ($host !== '' && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
                $normalized[] = $host;
            }
        }

        return array_values(array_unique($normalized));
    }
}
