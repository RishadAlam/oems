<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use InvalidArgumentException;
use JsonException;
use OEMS\App\Contracts\GeocoderInterface;
use OEMS\App\Contracts\HttpClientInterface;
use OEMS\App\Support\GeocodingResultNormalizer;
use RuntimeException;
use UnexpectedValueException;

final class NominatimGeocoder implements GeocoderInterface
{
    private const MAX_RESULTS = 5;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $endpoint,
        private readonly string $userAgent,
        private readonly string $contactEmail,
        private readonly int $timeoutSeconds = 5,
    ) {
        $parts = parse_url($endpoint);

        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || !isset($parts['host'])) {
            throw new InvalidArgumentException('Geocoder endpoint must use HTTPS.');
        }
    }

    public function search(string $query, int $limit): array
    {
        $url = $this->endpoint . '?' . http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => min(self::MAX_RESULTS, max(1, $limit)),
            'addressdetails' => 0,
        ], '', '&', PHP_QUERY_RFC3986);
        $response = $this->http->get($url, [
            'Accept' => 'application/json',
            'User-Agent' => $this->requestUserAgent(),
        ], max(1, $this->timeoutSeconds));

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            throw new RuntimeException('Geocoding provider returned an unsuccessful response.');
        }

        try {
            $payload = json_decode((string) ($response['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('Geocoding provider returned invalid JSON.', 0, $exception);
        }

        if (!is_array($payload) || !array_is_list($payload)) {
            throw new UnexpectedValueException('Geocoding provider returned an invalid response shape.');
        }

        return GeocodingResultNormalizer::providerResults($payload, min(self::MAX_RESULTS, max(1, $limit)));
    }

    private function requestUserAgent(): string
    {
        $userAgent = trim($this->userAgent);
        $email = trim($this->contactEmail);

        return $email === '' ? $userAgent : $userAgent . ' (' . $email . ')';
    }

}
