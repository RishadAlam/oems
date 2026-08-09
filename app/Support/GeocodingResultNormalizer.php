<?php

declare(strict_types=1);

namespace OEMS\App\Support;

final class GeocodingResultNormalizer
{
    private const MAX_RESULTS = 5;

    private const MAX_LABEL_LENGTH = 255;

    public static function providerResults(array $places, int $limit): array
    {
        $results = [];
        $seenCoordinates = [];

        foreach ($places as $place) {
            $result = self::providerResult($place);

            if ($result === null) {
                continue;
            }

            self::appendUnique($results, $seenCoordinates, $result, $limit);

            if (count($results) >= min(self::MAX_RESULTS, max(1, $limit))) {
                break;
            }
        }

        return $results;
    }

    public static function cachedResults(mixed $results): ?array
    {
        if (!is_array($results) || !array_is_list($results)) {
            return null;
        }

        $normalized = [];
        $seenCoordinates = [];

        foreach ($results as $result) {
            $result = self::cachedResult($result);

            if ($result === null) {
                return null;
            }

            self::appendUnique($normalized, $seenCoordinates, $result, self::MAX_RESULTS);
        }

        return $normalized;
    }

    private static function providerResult(mixed $place): ?array
    {
        if (!is_array($place) || !is_string($place['display_name'] ?? null)) {
            return null;
        }

        $label = trim($place['display_name']);

        if ($label === '' || !self::validCoordinate($place['lat'] ?? null, -90, 90) || !self::validCoordinate($place['lon'] ?? null, -180, 180)) {
            return null;
        }

        return [
            'label' => mb_substr($label, 0, self::MAX_LABEL_LENGTH),
            'latitude' => trim((string) $place['lat']),
            'longitude' => trim((string) $place['lon']),
        ];
    }

    private static function cachedResult(mixed $result): ?array
    {
        if (!is_array($result)
            || !is_string($result['label'] ?? null)
            || !is_string($result['latitude'] ?? null)
            || !is_string($result['longitude'] ?? null)) {
            return null;
        }

        $label = $result['label'];
        $latitude = $result['latitude'];
        $longitude = $result['longitude'];

        if ($label === '' || trim($label) !== $label || mb_strlen($label) > self::MAX_LABEL_LENGTH
            || trim($latitude) !== $latitude || trim($longitude) !== $longitude
            || !self::validCoordinate($latitude, -90, 90) || !self::validCoordinate($longitude, -180, 180)) {
            return null;
        }

        return [
            'label' => $label,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private static function appendUnique(array &$results, array &$seenCoordinates, array $result, int $limit): void
    {
        $coordinateKey = number_format((float) $result['latitude'], 7, '.', '')
            . ':' . number_format((float) $result['longitude'], 7, '.', '');

        if (isset($seenCoordinates[$coordinateKey])) {
            return;
        }

        $seenCoordinates[$coordinateKey] = true;

        if (count($results) < min(self::MAX_RESULTS, max(1, $limit))) {
            $results[] = $result;
        }
    }

    private static function validCoordinate(mixed $value, float $minimum, float $maximum): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= $minimum && (float) $value <= $maximum;
    }
}
