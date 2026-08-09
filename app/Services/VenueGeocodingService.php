<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Closure;
use DateTimeImmutable;
use OEMS\App\Contracts\GeocoderInterface;
use OEMS\App\Contracts\GeocodingCacheRepositoryInterface;
use OEMS\App\Support\GeocodingResultNormalizer;
use OEMS\Core\Logger;
use RuntimeException;
use Throwable;

final class VenueGeocodingService
{
    private const MIN_QUERY_LENGTH = 3;

    private const MAX_QUERY_LENGTH = 160;

    private const MAX_RESULTS = 5;

    private readonly string $throttlePath;

    private readonly Closure $clock;

    private readonly Closure $sleeper;

    public function __construct(
        private readonly GeocodingCacheRepositoryInterface $cache,
        private readonly GeocoderInterface $geocoder,
        private readonly string $provider,
        private readonly ?Logger $logger = null,
        ?string $throttlePath = null,
        ?Closure $clock = null,
        ?Closure $sleeper = null,
    ) {
        $this->throttlePath = $throttlePath ?? base_path('storage/cache/geocoding-provider-' . hash('sha256', $provider) . '.lock');
        $this->clock = $clock ?? static fn (): float => microtime(true);
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int) ceil($seconds * 1_000_000));
        };
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
                $results = GeocodingResultNormalizer::cachedResults($cached['results'] ?? null);

                if ($results !== null) {
                    return $this->success($results);
                }

                $this->logger?->warning('venue geocoding cache entry was malformed', [
                    'operation' => 'venue_geocoding_cache_read',
                    'provider' => mb_substr($this->provider, 0, 80),
                    'query_hash' => $queryHash,
                ]);
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
        $directory = dirname($this->throttlePath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the geocoding throttle directory.');
        }

        $lock = fopen($this->throttlePath, 'c+');

        if ($lock === false) {
            throw new RuntimeException('Unable to open the geocoding throttle lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the geocoding throttle.');
            }

            rewind($lock);
            $stored = stream_get_contents($lock);
            $lastCallAt = is_string($stored) && is_numeric(trim($stored)) && is_finite((float) $stored)
                ? (float) trim($stored)
                : null;
            $delay = $lastCallAt === null ? 0.0 : 1 - (($this->clock)() - $lastCallAt);

            if ($delay > 0) {
                ($this->sleeper)($delay);
            }

            rewind($lock);
            ftruncate($lock, 0);
            fwrite($lock, (string) ($this->clock)());
            fflush($lock);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
