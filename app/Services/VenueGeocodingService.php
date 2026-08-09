<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\GeocoderInterface;
use OEMS\App\Contracts\GeocodingCacheRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class VenueGeocodingService
{
    private const MIN_QUERY_LENGTH = 3;

    private const MAX_QUERY_LENGTH = 160;

    private const MAX_RESULTS = 5;

    private ?float $lastProviderCallAt = null;

    public function __construct(
        private readonly GeocodingCacheRepositoryInterface $cache,
        private readonly GeocoderInterface $geocoder,
        private readonly string $provider,
        private readonly ?Logger $logger = null,
    ) {
    }

    /** @return array{success: bool, results: array, errors: array} */
    public function search(string $query): array
    {
        $query = $this->normalize($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return $this->failure(['location' => ['Enter an address between 3 and 160 characters.']]);
        }

        $queryHash = hash('sha256', $query);

        try {
            $cached = $this->cache->findFresh($queryHash, new DateTimeImmutable('now'));

            if ($cached !== null) {
                return $this->success(array_slice($cached['results'], 0, self::MAX_RESULTS));
            }

            $this->waitForProviderWindow();
            $results = array_slice($this->geocoder->search($query, self::MAX_RESULTS), 0, self::MAX_RESULTS);
            $this->cache->upsert(
                $queryHash,
                $query,
                $this->provider,
                $results,
                new DateTimeImmutable('+30 days'),
            );

            return $this->success($results);
        } catch (Throwable $exception) {
            $this->logger?->error('venue geocoding failed', [
                'operation' => 'venue_geocoding_search',
                'provider' => mb_substr($this->provider, 0, 80),
                'exception' => $exception::class,
                'query_hash' => $queryHash,
            ]);

            return $this->failure(['location' => ['Address search is temporarily unavailable.']]);
        }
    }

    private function normalize(string $query): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', trim($query))));
    }

    private function waitForProviderWindow(): void
    {
        $now = microtime(true);

        if ($this->lastProviderCallAt !== null) {
            $delay = 1 - ($now - $this->lastProviderCallAt);

            if ($delay > 0) {
                usleep((int) ceil($delay * 1_000_000));
            }
        }

        $this->lastProviderCallAt = microtime(true);
    }

    private function success(array $results): array
    {
        return ['success' => true, 'results' => $results, 'errors' => []];
    }

    private function failure(array $errors): array
    {
        return ['success' => false, 'results' => [], 'errors' => $errors];
    }
}
