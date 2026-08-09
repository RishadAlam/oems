<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use OEMS\App\Repositories\GeocodingCacheRepository;
use OEMS\App\Services\NominatimGeocoder;
use OEMS\App\Services\VenueGeocodingService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeHttpClient;
use OEMS\Tests\Support\TestCase;
use PDO;
use RuntimeException;

final class VenueGeocodingServiceTest extends TestCase
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

    public function testExplicitSearchNormalizesCachesBuildsProviderUrlAndReturnsBoundedResults(): void
    {
        $http = new FakeHttpClient(200, json_encode([
            ['display_name' => 'Bashundhara, Dhaka, Bangladesh', 'lat' => '23.8151001', 'lon' => '90.4255001'],
        ], JSON_THROW_ON_ERROR));
        $service = $this->service($http);

        $result = $service->search('  Bashundhara   Dhaka  ');
        $second = $service->search('bashundhara dhaka');

        $this->assertTrue($result['success']);
        $this->assertSame('23.8151001', $result['results'][0]['latitude']);
        $this->assertSame(1, $http->calls);
        $this->assertSame($result['results'], $second['results']);
        $this->assertSame('https://nominatim.example.test/search?q=bashundhara%20dhaka&format=jsonv2&limit=5&addressdetails=0', $http->requests[0]['url']);
        $this->assertSame('application/json', $http->requests[0]['headers']['Accept']);
        $this->assertSame('OEMS Test/1.0 (ops@example.test)', $http->requests[0]['headers']['User-Agent']);
    }

    public function testInvalidOrEmptyQueriesNeverCallTheProvider(): void
    {
        $http = new FakeHttpClient(200, '[]');
        $service = $this->service($http);

        foreach (['', '  ', 'ab', str_repeat('a', 161)] as $query) {
            $result = $service->search($query);
            $this->assertFalse($result['success']);
            $this->assertSame(['location' => ['Enter an address between 3 and 160 characters.']], $result['errors']);
        }

        $this->assertSame(0, $http->calls);
    }

    public function testMalformedAndFailedProviderResponsesReturnSafeUnavailableError(): void
    {
        foreach ([
            new FakeHttpClient(200, '{"bad":true}'),
            new FakeHttpClient(429, 'rate limit response'),
            new FakeHttpClient(500, 'server response'),
            new FakeHttpClient(200, '', new RuntimeException('Connection timeout for 23.8,90.4')),
        ] as $http) {
            $result = $this->service($http)->search('Dhaka venue');
            $this->assertFalse($result['success']);
            $this->assertSame(['location' => ['Address search is temporarily unavailable.']], $result['errors']);
        }
    }

    public function testProviderFiltersInvalidCoordinatesTruncatesLabelsAndDeduplicatesCoordinates(): void
    {
        $label = str_repeat('L', 300);
        $http = new FakeHttpClient(200, json_encode([
            ['display_name' => 'Invalid latitude', 'lat' => '91', 'lon' => '90'],
            ['display_name' => 'Invalid longitude', 'lat' => '23', 'lon' => '181'],
            ['display_name' => $label, 'lat' => '23.8', 'lon' => '90.4'],
            ['display_name' => 'Duplicate coordinate', 'lat' => '23.8', 'lon' => '90.4'],
            ['display_name' => 'Second venue', 'lat' => '23.9', 'lon' => '90.5'],
        ], JSON_THROW_ON_ERROR));

        $result = $this->service($http)->search('Dhaka venues');

        $this->assertTrue($result['success']);
        $this->assertSame(2, count($result['results']));
        $this->assertSame(str_repeat('L', 255), $result['results'][0]['label']);
        $this->assertSame('23.8', $result['results'][0]['latitude']);
    }

    public function testProviderResultsAreLimitedToFive(): void
    {
        $providerResults = [];
        for ($index = 0; $index < 7; $index++) {
            $providerResults[] = ['display_name' => "Venue {$index}", 'lat' => (string) (20 + $index), 'lon' => (string) (80 + $index)];
        }

        $result = $this->service(new FakeHttpClient(200, json_encode($providerResults, JSON_THROW_ON_ERROR)))->search('Many venues');

        $this->assertTrue($result['success']);
        $this->assertSame(5, count($result['results']));
        $this->assertSame('Venue 4', $result['results'][4]['label']);
    }

    public function testDistinctProviderCallsAreSpacedByAtLeastOneSecond(): void
    {
        $http = FakeHttpClient::sequence([
            ['status' => 200, 'body' => '[{"display_name":"First","lat":"23.8","lon":"90.4"}]'],
            ['status' => 200, 'body' => '[{"display_name":"Second","lat":"23.9","lon":"90.5"}]'],
        ]);
        $service = $this->service($http);
        $startedAt = microtime(true);

        $service->search('First venue');
        $service->search('Second venue');

        $this->assertSame(2, $http->calls);
        $this->assertTrue(microtime(true) - $startedAt >= 0.9, 'Provider calls must be spaced by one second.');
    }

    public function testNonHttpsEndpointIsRejected(): void
    {
        try {
            new NominatimGeocoder(new FakeHttpClient(200, '[]'), 'http://nominatim.example.test/search', 'OEMS Test/1.0', 'ops@example.test');
            $this->assertTrue(false, 'Geocoder endpoints must require HTTPS.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    public function testFailureLogsNeverContainRawQueryCoordinatesOrResponseBody(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'oems-geocode-log-');
        $this->assertNotNull($path);
        $privateQuery = 'Private Hall, 12 Secret Road';
        $privateBody = 'body-has-23.8151-and-90.4255';
        $service = $this->service(new FakeHttpClient(500, $privateBody), new Logger($path));

        $result = $service->search($privateQuery);
        $log = file_get_contents($path);

        $this->assertFalse($result['success']);
        $this->assertTrue(is_string($log) && str_contains($log, hash('sha256', strtolower($privateQuery))));
        $this->assertFalse(str_contains((string) $log, $privateQuery));
        $this->assertFalse(str_contains((string) $log, '23.8151'));
        $this->assertFalse(str_contains((string) $log, $privateBody));

        unlink($path);
    }

    private function service(FakeHttpClient $http, ?Logger $logger = null): VenueGeocodingService
    {
        return new VenueGeocodingService(
            new GeocodingCacheRepository($this->connection, $logger),
            new NominatimGeocoder($http, 'https://nominatim.example.test/search', 'OEMS Test/1.0', 'ops@example.test'),
            'OpenStreetMap Nominatim',
            $logger,
        );
    }
}
