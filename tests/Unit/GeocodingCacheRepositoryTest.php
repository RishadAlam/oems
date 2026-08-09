<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\GeocodingCacheRepository;
use OEMS\Core\Logger;
use OEMS\Tests\Support\TestCase;
use PDO;

final class GeocodingCacheRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->exec(
            'CREATE TABLE geocoding_cache (
                query_hash TEXT PRIMARY KEY,
                normalized_query TEXT NOT NULL,
                provider TEXT NOT NULL,
                response_json TEXT NOT NULL,
                expires_at TEXT NOT NULL
            )',
        );
    }

    public function testFreshCachedResultsRoundTripAndExpiredRowsMiss(): void
    {
        $repository = new GeocodingCacheRepository($this->connection);
        $now = new DateTimeImmutable('2026-08-09 12:00:00');
        $hash = hash('sha256', 'bashundhara dhaka');
        $results = [['label' => 'Bashundhara, Dhaka', 'latitude' => '23.8151', 'longitude' => '90.4255']];

        $repository->upsert($hash, 'bashundhara dhaka', 'nominatim', $results, $now->modify('+30 days'));

        $fresh = $repository->findFresh($hash, $now);
        $this->assertNotNull($fresh);
        $this->assertSame($results, $fresh['results']);
        $this->assertNull($repository->findFresh($hash, $now->modify('+31 days')));
    }

    public function testUpsertBoundsStoredProviderAndQueryAndReplacesPriorResults(): void
    {
        $repository = new GeocodingCacheRepository($this->connection);
        $now = new DateTimeImmutable('2026-08-09 12:00:00');
        $hash = hash('sha256', 'venue');

        $repository->upsert($hash, str_repeat('q', 300), str_repeat('p', 100), [['label' => 'Old', 'latitude' => '1', 'longitude' => '2']], $now->modify('+1 day'));
        $bounded = $this->connection->query('SELECT normalized_query, provider FROM geocoding_cache')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(str_repeat('q', 255), $bounded['normalized_query'] ?? null);
        $this->assertSame(str_repeat('p', 80), $bounded['provider'] ?? null);

        $repository->upsert($hash, 'new query', 'new provider', [['label' => 'New', 'latitude' => '3', 'longitude' => '4']], $now->modify('+2 days'));

        $row = $this->connection->query('SELECT normalized_query, provider, response_json FROM geocoding_cache')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('new query', $row['normalized_query'] ?? null);
        $this->assertSame('new provider', $row['provider'] ?? null);
        $this->assertSame('[{"label":"New","latitude":"3","longitude":"4"}]', $row['response_json'] ?? null);
    }

    public function testMalformedCachedDataMissesAndLogsOnlyHash(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'oems-geocode-log-');
        $this->assertNotNull($path);
        $hash = hash('sha256', 'Private venue address');
        $this->connection->prepare(
            'INSERT INTO geocoding_cache (query_hash, normalized_query, provider, response_json, expires_at)
             VALUES (:hash, :query, :provider, :response, :expires)',
        )->execute([
            'hash' => $hash,
            'query' => 'Private venue address',
            'provider' => 'nominatim',
            'response' => '{malformed',
            'expires' => '2026-09-09 12:00:00',
        ]);

        $repository = new GeocodingCacheRepository($this->connection, new Logger($path));
        $this->assertNull($repository->findFresh($hash, new DateTimeImmutable('2026-08-09 12:00:00')));
        $log = file_get_contents($path);
        $this->assertTrue(is_string($log) && str_contains($log, $hash));
        $this->assertFalse(str_contains((string) $log, 'Private venue address'));

        unlink($path);
    }
}
