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

    private readonly Closure $clock;

    public function __construct(
        private readonly int $ttlSeconds = 1209600,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
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
        $latitudeDelta = rad2deg($radius / self::EARTH_RADIUS_KM);
        $latitudeMin = max(-90.0, $latitude - $latitudeDelta);
        $latitudeMax = min(90.0, $latitude + $latitudeDelta);
        $allLongitudes = $latitudeMin <= -90.0 || $latitudeMax >= 90.0;
        $cosine = cos(deg2rad($latitude));

        if ($allLongitudes || abs($cosine) < 0.000000000001) {
            $longitudeMin = -180.0;
            $longitudeMax = 180.0;
            $longitudeWraps = false;
        } else {
            $longitudeDelta = rad2deg($radius / (self::EARTH_RADIUS_KM * $cosine));
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

    public function directionsUrl(array $location): ?string
    {
        $mapUrl = $location['map_url'] ?? null;

        if (is_string($mapUrl) && trim($mapUrl) !== '') {
            $mapUrl = trim($mapUrl);
            $parts = parse_url($mapUrl);

            return is_array($parts)
                && ($parts['scheme'] ?? null) === 'https'
                && isset($parts['host'])
                ? $mapUrl
                : null;
        }

        try {
            [$latitude, $longitude] = $this->coordinates($location['latitude'] ?? null, $location['longitude'] ?? null);
        } catch (InvalidArgumentException) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='
            . rawurlencode($this->coordinateString($latitude) . ',' . $this->coordinateString($longitude));
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

        return (int) $value > ($this->clock)();
    }

    private function label(string $label): string
    {
        return mb_substr(trim($label) ?: 'Current area', 0, 80);
    }

    private function source(string $source): string
    {
        return in_array($source, self::SOURCES, true) ? $source : 'manual';
    }
}
