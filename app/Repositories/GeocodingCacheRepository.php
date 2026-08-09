<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use JsonException;
use OEMS\App\Contracts\GeocodingCacheRepositoryInterface;
use OEMS\App\Support\GeocodingResultNormalizer;
use OEMS\Core\Logger;
use PDO;

final class GeocodingCacheRepository implements GeocodingCacheRepositoryInterface
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function findFresh(string $queryHash, string $provider, DateTimeImmutable $now): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT normalized_query, provider, response_json, expires_at
             FROM geocoding_cache
             WHERE query_hash = :query_hash AND provider = :provider AND expires_at > :now
             LIMIT 1',
        );
        $statement->execute([
            'query_hash' => $queryHash,
            'provider' => mb_substr(trim($provider), 0, 80),
            'now' => $this->timestamp($now),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $responseJson = (string) $row['response_json'];

        if (!str_starts_with(ltrim($responseJson), '[')) {
            $this->logMalformedCache($queryHash);

            return null;
        }

        try {
            $results = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->logMalformedCache($queryHash);

            return null;
        }

        $results = GeocodingResultNormalizer::cachedResults($results);

        if ($results === null) {
            $this->logMalformedCache($queryHash);

            return null;
        }

        return [
            'query' => (string) $row['normalized_query'],
            'provider' => (string) $row['provider'],
            'results' => $results,
            'expires_at' => (string) $row['expires_at'],
        ];
    }

    public function upsert(
        string $queryHash,
        string $query,
        string $provider,
        array $results,
        DateTimeImmutable $expiresAt,
    ): void {
        $upsert = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' ON DUPLICATE KEY UPDATE
                    normalized_query = VALUES(normalized_query),
                    provider = VALUES(provider),
                    response_json = VALUES(response_json),
                    expires_at = VALUES(expires_at)'
            : ' ON CONFLICT(query_hash) DO UPDATE SET
                    normalized_query = excluded.normalized_query,
                    provider = excluded.provider,
                    response_json = excluded.response_json,
                    expires_at = excluded.expires_at';
        $statement = $this->connection->prepare(
            'INSERT INTO geocoding_cache (query_hash, normalized_query, provider, response_json, expires_at)
             VALUES (:query_hash, :normalized_query, :provider, :response_json, :expires_at)'
            . $upsert,
        );
        $statement->execute([
            'query_hash' => $queryHash,
            'normalized_query' => mb_substr($query, 0, 255),
            'provider' => mb_substr($provider, 0, 80),
            'response_json' => json_encode($results, JSON_THROW_ON_ERROR),
            'expires_at' => $this->timestamp($expiresAt),
        ]);
    }

    private function timestamp(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    private function logMalformedCache(string $queryHash): void
    {
        $this->logger?->warning('geocoding cache entry was malformed', [
            'operation' => 'geocoding_cache_read',
            'query_hash' => $queryHash,
        ]);
    }
}
