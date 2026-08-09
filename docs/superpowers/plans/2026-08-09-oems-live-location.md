# OEMS Meetup-Style Live Location Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Add privacy-first nearby event discovery, interactive public maps, organizer venue pinning, restricted exact-location visibility, and directions without attendee tracking.

**Architecture:** Extend the existing venue coordinates and event model. A pure LocationService validates rounded session preferences and presentation rules, EventRepository performs prepared bounding-box and Haversine search, server-rendered views remain the accessible source of truth, and local Leaflet assets progressively add maps. Organizer address searches pass through a cached, rate-limited, provider-neutral geocoder boundary.

**Tech Stack:** PHP 8.2, custom OEMS MVC, PDO/MySQL 8, PHPUnit-style custom test runner, Tailwind CSS v4, vanilla JavaScript, Leaflet 1.9.x, local Phosphor icons, browser Geolocation API

## Global Constraints

- Follow strict red-green-refactor: every production behavior needs a failing test observed before implementation.
- Preserve existing route slugs, role boundaries, organizer ownership, event lifecycle predicates, and public SEO behavior.
- Never use watchPosition, IP geolocation, background tracking, attendee broadcasting, or database persistence for participant device coordinates.
- Round browser coordinates to three decimal places before sending or storing them in the session.
- Allow only 5, 10, 25, 50, and 100 kilometre radiuses; default to 25.
- Restricted event locations must not leak exact address, coordinates, map URL, directions, arrival notes, or JSON-LD.
- Self-host Leaflet JavaScript, CSS, and marker images. Do not add a map CDN or remote font.
- Keep tile and geocoding providers configurable and show required map attribution.
- Public Nominatim use is explicit-search only, cached, rate-limited to at most one request per second, and never client-side autocomplete.
- Maintain the OEMS cobalt, cool-neutral, Manrope, Phosphor, 12/18/24 radius, light/dark, 44-pixel target, visible-focus, and reduced-motion system.
- Preserve unrelated untracked workspace files and stage only the paths named by each task.
- End every task with focused tests, the full relevant regression set, diff checks, scoped review, and one Git commit.

---

## File Structure

### New production files

- app/Services/LocationService.php: coordinate, radius, session expiry, distance label, privacy, and directions rules
- app/Contracts/GeocoderInterface.php: provider-neutral address search contract
- app/Contracts/HttpClientInterface.php: narrow external GET transport contract
- app/Contracts/GeocodingCacheRepositoryInterface.php: fresh-cache read and upsert contract
- app/Support/StreamHttpClient.php: bounded native HTTPS transport
- app/Services/NominatimGeocoder.php: provider response adapter and validation
- app/Services/VenueGeocodingService.php: query normalization, cache coordination, and safe errors
- app/Repositories/GeocodingCacheRepository.php: prepared cache persistence
- app/Controllers/PublicLocationController.php: set and clear session preference
- public/assets/js/location.js: public geolocation, list/map toggle, and event markers
- public/assets/js/venue-map.js: organizer address search and draggable venue pin
- database/migrations/2026-08-09-live-location.sql: guarded forward schema upgrade

### New test files

- tests/Unit/LocationServiceTest.php
- tests/Unit/LiveLocationSchemaTest.php
- tests/Unit/GeocodingCacheRepositoryTest.php
- tests/Unit/VenueGeocodingServiceTest.php
- tests/Unit/PublicLocationControllerTest.php
- tests/Unit/LocationJavascriptTest.php
- tests/js/location.test.mjs
- tests/js/venue-map.test.mjs

### Existing files changed across tasks

- database/schema.sql, database/seed.sql, database/demo_seed.sql
- app/Contracts/EventRepositoryInterface.php
- app/Repositories/EventRepository.php
- app/Repositories/VenueRepository.php
- app/Services/EventService.php, app/Services/VenueService.php
- app/Controllers/PublicEventController.php
- app/Controllers/OrganizerVenueController.php
- app/Controllers/OrganizerEventController.php
- app/Views/events/index.php, app/Views/events/show.php
- app/Views/organizer/venues/form.php
- app/Views/organizer/events/form.php, app/Views/organizer/events/show.php
- Core/Response.php, public/index.php
- config/app.php, bootstrap/app.php, routes/web.php
- package.json, package-lock.json, scripts/copy-fonts.mjs
- resources/css/app.css, public/assets/css/app.css
- public/assets/js/app.js only if shared initialization needs an import-free hook
- tests/Support/FakeEventRepository.php, tests/Support/FakeVenueRepository.php
- tests/Unit/EventRepositoryTest.php
- tests/Unit/EventServiceTest.php
- tests/Unit/VenueServiceTest.php
- tests/Unit/PublicEventControllerTest.php
- tests/Unit/OrganizerVenueControllerTest.php
- tests/Unit/OrganizerEventControllerTest.php
- tests/Unit/ResponseTest.php
- tests/Unit/RouterTest.php
- tests/Unit/DemoSeedIntegrityTest.php
- tests/Unit/UiLayoutTest.php or tests/Unit/TransactionUiTest.php
- .env.example, README.md

---

### Task 1: Location schema and pure domain rules

**Files:**
- Create: database/migrations/2026-08-09-live-location.sql
- Create: app/Services/LocationService.php
- Create: tests/Unit/LiveLocationSchemaTest.php
- Create: tests/Unit/LocationServiceTest.php
- Modify: database/schema.sql
- Modify: database/seed.sql
- Modify: database/demo_seed.sql
- Modify: tests/Unit/TransactionSchemaTest.php
- Modify: tests/Unit/DemoSeedIntegrityTest.php

**Interfaces:**
- Produces: LocationService::preference(mixed $latitude, mixed $longitude, mixed $radius, string $label, string $source): array
- Produces: LocationService::fromSession(mixed $value): ?array
- Produces: LocationService::radius(mixed $value): int
- Produces: LocationService::bounds(array $preference): array{latitude_min: string, latitude_max: string, longitude_min: string, longitude_max: string}
- Produces: LocationService::distanceLabel(mixed $distanceKm, bool $exact): ?string
- Produces: LocationService::directionsUrl(array $location): ?string
- Produces schema fields events.location_visibility, events.arrival_notes, venues coordinate index/check, and geocoding_cache
- Consumes: Configured session TTL and injected clock closure

- [ ] **Step 1: Write failing schema tests**

Add source-level structure checks plus a disposable SQLite contract that catches missing columns, defaults, coordinate-pair enforcement, coordinate index, cache key, and expiry:

~~~php
public function testLiveLocationSchemaDefinesPrivacyCoordinatesAndGeocodingCache(): void
{
    $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');

    $this->assertTrue(str_contains($schema, "location_visibility ENUM('public', 'registered') NOT NULL DEFAULT 'public'"));
    $this->assertTrue(str_contains($schema, 'arrival_notes VARCHAR(500) NULL'));
    $this->assertTrue(str_contains($schema, 'INDEX idx_venues_coordinates (latitude, longitude)'));
    $this->assertTrue(str_contains($schema, 'CREATE TABLE geocoding_cache'));
    $this->assertTrue(str_contains($schema, 'query_hash CHAR(64) PRIMARY KEY'));
}

public function testForwardMigrationContainsGuardedLiveLocationChanges(): void
{
    $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026-08-09-live-location.sql');

    $this->assertTrue(str_contains($migration, 'information_schema.COLUMNS'));
    $this->assertTrue(str_contains($migration, 'information_schema.STATISTICS'));
    $this->assertTrue(str_contains($migration, 'geocoding_cache'));
}
~~~

- [ ] **Step 2: Run schema tests and observe RED**

Run:

~~~bash
rtk php tests/run.php tests/Unit/LiveLocationSchemaTest.php
rtk php tests/run.php tests/Unit/TransactionSchemaTest.php
~~~

Expected: failures name the missing event columns, venue index, cache table, and migration.

- [ ] **Step 3: Add guarded schema and seed defaults**

In fresh schema add:

~~~sql
location_visibility ENUM('public', 'registered') NOT NULL DEFAULT 'public',
arrival_notes VARCHAR(500) NULL,
~~~

Add the coordinate pair check and index:

~~~sql
INDEX idx_venues_coordinates (latitude, longitude),
CONSTRAINT chk_venues_coordinate_pair CHECK (
    (latitude IS NULL AND longitude IS NULL)
    OR (latitude IS NOT NULL AND longitude IS NOT NULL)
),
~~~

Create cache:

~~~sql
CREATE TABLE geocoding_cache (
    query_hash CHAR(64) PRIMARY KEY,
    normalized_query VARCHAR(255) NOT NULL,
    provider VARCHAR(80) NOT NULL,
    response_json JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_geocoding_cache_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
~~~

The migration must query information_schema before each ALTER, create the cache table with IF NOT EXISTS, and be safe when run twice against populated baseline commit 90cb666. Seed all existing events explicitly as public and give demo events realistic bounded arrival notes where useful.

- [ ] **Step 4: Write failing LocationService tests**

Use a fixed clock and hand-derived literals:

~~~php
public function testPreferenceRoundsCoordinatesAndExpiresAtConfiguredTtl(): void
{
    $service = new LocationService(1209600, static fn (): int => 1_800_000_000);
    $preference = $service->preference('23.810331', '90.412521', '25', 'Current area', 'device');

    $this->assertSame('23.810', $preference['latitude']);
    $this->assertSame('90.413', $preference['longitude']);
    $this->assertSame(25, $preference['radius']);
    $this->assertSame(1_801_209_600, $preference['expires_at']);
}

public function testInvalidOrExpiredSessionPreferenceIsRejected(): void
{
    $service = new LocationService(1209600, static fn (): int => 1_800_000_000);

    $this->assertNull($service->fromSession(['latitude' => '91', 'longitude' => '90', 'expires_at' => 1_900_000_000]));
    $this->assertNull($service->fromSession(['latitude' => '23.8', 'longitude' => '90.4', 'expires_at' => 1_799_999_999]));
}

public function testRestrictedDistanceUsesCoarseBand(): void
{
    $service = new LocationService();

    $this->assertSame('3.4 km away', $service->distanceLabel('3.36', true));
    $this->assertSame('Within 5 km', $service->distanceLabel('3.36', false));
}
~~~

Also test latitude/longitude boundaries, nonnumeric values, radius fallback to 25, source allow-list, 180-degree longitude bounds, pole-safe bounding boxes, HTTPS custom directions URL, generated directions fallback, and javascript/data URL rejection.

- [ ] **Step 5: Run LocationService tests and observe RED**

Run:

~~~bash
rtk php tests/run.php tests/Unit/LocationServiceTest.php
~~~

Expected: class-not-found failure only.

- [ ] **Step 6: Implement minimal LocationService**

Use decimal string output and no float persistence:

~~~php
final class LocationService
{
    private const RADII = [5, 10, 25, 50, 100];
    private const SOURCES = ['device', 'manual'];

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
        [$lat, $lng] = $this->coordinates($latitude, $longitude);

        return [
            'latitude' => number_format($lat, 3, '.', ''),
            'longitude' => number_format($lng, 3, '.', ''),
            'radius' => $this->radius($radius),
            'label' => mb_substr(trim($label) ?: 'Current area', 0, 80),
            'source' => in_array($source, self::SOURCES, true) ? $source : 'manual',
            'expires_at' => ($this->clock)() + $this->ttlSeconds,
        ];
    }
}
~~~

Bounds use earth radius 6371.0088 km, clamp latitude to -90/90, and use -180/180 for longitude at polar cosine collapse. directionsUrl accepts configured HTTPS map_url or emits a percent-encoded Google Maps directions URL from validated numeric coordinates.

- [ ] **Step 7: Run focused and schema regressions GREEN**

Run:

~~~bash
rtk php tests/run.php tests/Unit/LocationServiceTest.php
rtk php tests/run.php tests/Unit/LiveLocationSchemaTest.php
rtk php tests/run.php tests/Unit/TransactionSchemaTest.php tests/Unit/DemoSeedIntegrityTest.php
rtk composer check:syntax
rtk git diff --check
~~~

Expected: zero failures and no syntax/diff errors.

- [ ] **Step 8: Commit Task 1**

Stage only Task 1 files and commit:

~~~bash
rtk git commit -m "build: add live location domain foundation"
~~~

---

### Task 2: Cached provider-neutral organizer geocoding

**Files:**
- Create: app/Contracts/GeocoderInterface.php
- Create: app/Contracts/HttpClientInterface.php
- Create: app/Contracts/GeocodingCacheRepositoryInterface.php
- Create: app/Support/StreamHttpClient.php
- Create: app/Services/NominatimGeocoder.php
- Create: app/Services/VenueGeocodingService.php
- Create: app/Repositories/GeocodingCacheRepository.php
- Create: tests/Support/FakeHttpClient.php
- Create: tests/Unit/GeocodingCacheRepositoryTest.php
- Create: tests/Unit/VenueGeocodingServiceTest.php
- Modify: config/app.php
- Modify: bootstrap/app.php

**Interfaces:**
- Produces: HttpClientInterface::get(string $url, array $headers, int $timeoutSeconds): array{status: int, body: string}
- Produces: GeocoderInterface::search(string $query, int $limit): array
- Produces: GeocodingCacheRepositoryInterface::findFresh(string $queryHash, DateTimeImmutable $now): ?array
- Produces: GeocodingCacheRepositoryInterface::upsert(string $queryHash, string $query, string $provider, array $results, DateTimeImmutable $expiresAt): void
- Produces: VenueGeocodingService::search(string $query): array{success: bool, results: array, errors: array}
- Consumes: configured HTTPS endpoint, provider name, user agent, contact email, Logger

- [ ] **Step 1: Write real SQLite cache repository tests**

Test fresh hit, expired miss, JSON round trip, bounded provider/query data, and repeat upsert:

~~~php
public function testFreshCachedResultsRoundTripAndExpiredRowsMiss(): void
{
    $repository = new GeocodingCacheRepository($this->connection);
    $now = new DateTimeImmutable('2026-08-09 12:00:00');
    $repository->upsert(
        hash('sha256', 'bashundhara dhaka'),
        'bashundhara dhaka',
        'nominatim',
        [['label' => 'Bashundhara, Dhaka', 'latitude' => '23.8151', 'longitude' => '90.4255']],
        $now->modify('+30 days'),
    );

    $this->assertCount(1, $repository->findFresh(hash('sha256', 'bashundhara dhaka'), $now)['results']);
    $this->assertNull($repository->findFresh(hash('sha256', 'bashundhara dhaka'), $now->modify('+31 days')));
}
~~~

- [ ] **Step 2: Run cache tests RED, then implement repository GREEN**

Run before and after:

~~~bash
rtk php tests/run.php tests/Unit/GeocodingCacheRepositoryTest.php
~~~

Use one prepared SELECT with expires_at greater than the supplied time and a driver-compatible upsert. Decode JSON with JSON_THROW_ON_ERROR and return null for malformed cached data after logging only the query hash.

- [ ] **Step 3: Write geocoder and service RED tests**

External HTTP is the only mocked boundary. Exercise real normalization, URL construction, provider parsing, cache, and error mapping:

~~~php
public function testExplicitSearchNormalizesCachesAndReturnsBoundedResults(): void
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
}

public function testMalformedProviderResponseReturnsSafeUnavailableError(): void
{
    $result = $this->service(new FakeHttpClient(200, '{"bad":true}'))->search('Dhaka venue');

    $this->assertFalse($result['success']);
    $this->assertSame(['location' => ['Address search is temporarily unavailable.']], $result['errors']);
}
~~~

Also cover query length 3-160, no automatic empty search, HTTPS-only endpoint, HTTP 429/500, timeout exception, maximum five results, invalid provider coordinates, label length, duplicate coordinates, one-second provider throttle, and logs that omit raw query/address/coordinates/body.

- [ ] **Step 4: Implement narrow transport, provider, and orchestration**

Contracts:

~~~php
interface HttpClientInterface
{
    public function get(string $url, array $headers, int $timeoutSeconds): array;
}

interface GeocoderInterface
{
    public function search(string $query, int $limit): array;
}
~~~

Provider URL:

~~~php
$url = $endpoint . '?' . http_build_query([
    'q' => $query,
    'format' => 'jsonv2',
    'limit' => min(5, max(1, $limit)),
    'addressdetails' => 0,
], '', '&', PHP_QUERY_RFC3986);
~~~

StreamHttpClient uses fopen with ignore_errors, a connect/read timeout, TLS verification, Accept application/json, and configured User-Agent. VenueGeocodingService hashes the normalized lowercase query, checks cache first, enforces provider call spacing, stores results for 30 days, catches Throwable, and logs only operation, provider, exception class, and query_hash.

- [ ] **Step 5: Register configuration and dependencies**

Add config keys under map:

~~~php
'map' => [
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    'default_lat' => (float) env('MAP_DEFAULT_LAT', 23.8103),
    'default_lng' => (float) env('MAP_DEFAULT_LNG', 90.4125),
    'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 11),
    'geocoder_url' => env('MAP_GEOCODER_URL', 'https://nominatim.openstreetmap.org/search'),
    'provider_name' => env('MAP_PROVIDER_NAME', 'OpenStreetMap Nominatim'),
    'user_agent' => env('MAP_USER_AGENT', 'OEMS/1.0'),
    'contact_email' => env('MAP_CONTACT_EMAIL', ''),
    'location_session_ttl' => (int) env('LOCATION_SESSION_TTL', 1209600),
],
~~~

Wire contracts and services in bootstrap with the shared PDO, Config, and Logger.

- [ ] **Step 6: Verify and commit Task 2**

Run:

~~~bash
rtk php tests/run.php tests/Unit/GeocodingCacheRepositoryTest.php tests/Unit/VenueGeocodingServiceTest.php
rtk composer test
rtk composer check:syntax
rtk composer validate --strict
rtk git diff --check
rtk git commit -m "feat: add cached venue geocoding"
~~~

---

### Task 3: Session location and distance-aware public discovery

**Files:**
- Create: app/Controllers/PublicLocationController.php
- Create: tests/Unit/PublicLocationControllerTest.php
- Modify: app/Contracts/EventRepositoryInterface.php
- Modify: app/Repositories/EventRepository.php
- Modify: app/Controllers/PublicEventController.php
- Modify: tests/Support/FakeEventRepository.php
- Modify: tests/Unit/EventRepositoryTest.php
- Modify: tests/Unit/PublicEventControllerTest.php
- Modify: routes/web.php
- Modify: bootstrap/app.php

**Interfaces:**
- Produces: POST /events/location
- Produces: POST /events/location/clear
- Extends: EventRepository::publicSearch(array $filters) accepts latitude, longitude, radius, four bounds, and distance sort
- Produces: public event rows with nullable distance_km and complete venue fields
- Consumes: LocationService session preference and existing Session/Security

- [ ] **Step 1: Write PublicLocationController RED tests**

Test valid rounded storage, invalid no-mutation, radius fallback, clear idempotence, safe redirect, and method/CSRF routes:

~~~php
public function testStorePersistsRoundedExpiringPreferenceAndRedirects(): void
{
    $response = $this->controller->store(Request::create('POST', '/events/location', input: [
        'latitude' => '23.810331',
        'longitude' => '90.412521',
        'radius' => '25',
    ]));

    $this->assertSame(302, $response->status());
    $this->assertSame('/events?radius=25&sort=distance', $response->header('Location'));
    $this->assertSame('23.810', $this->session->get('event_location.latitude'));
}
~~~

- [ ] **Step 2: Observe RED and implement controller/routes GREEN**

Run:

~~~bash
rtk php tests/run.php tests/Unit/PublicLocationControllerTest.php tests/Unit/RouterTest.php
~~~

Controller returns 422 JSON for fetch requests or redirects with flash errors for standard form requests. It never logs coordinates. Clear removes event_location and redirects to /events. Register both POST routes with csrf middleware.

- [ ] **Step 3: Write distance repository RED tests**

Use real SQLite with math functions available in the test runtime. Seed events inside, on, and outside a hand-checked radius plus missing-coordinate, inactive-category, deleted, completed, and restricted rows:

~~~php
public function testNearbySearchFiltersAndOrdersByDistanceWithoutLifecycleLeaks(): void
{
    $events = $this->repository->publicSearch([
        'latitude' => '23.810',
        'longitude' => '90.413',
        'latitude_min' => '23.585',
        'latitude_max' => '24.035',
        'longitude_min' => '90.168',
        'longitude_max' => '90.658',
        'radius' => 25,
        'sort' => 'distance',
    ]);

    $this->assertSame(['near-one', 'near-two'], array_column($events, 'slug'));
    $this->assertTrue((float) $events[0]['distance_km'] < (float) $events[1]['distance_km']);
}
~~~

Mutation coverage must fail if the bounding box, exact radius, active category, event lifecycle, missing-coordinate exclusion, distance order, or unique named PDO binding is removed.

- [ ] **Step 4: Implement prepared distance query**

Extend eventSelect with address_line, postal_code, venue latitude/longitude/map_url, organizer user id, visibility, and arrival notes. For nearby search, add unique bound parameters for bounding and Haversine expressions. MySQL expression:

~~~sql
6371.0088 * 2 * ASIN(
    SQRT(
        POWER(SIN(RADIANS(venues.latitude - :distance_latitude) / 2), 2)
        + COS(RADIANS(:origin_latitude))
        * COS(RADIANS(venues.latitude))
        * POWER(SIN(RADIANS(venues.longitude - :distance_longitude) / 2), 2)
    )
)
~~~

Wrap the select in a derived query so distance_km can be filtered with distance_km <= :distance_radius and sorted by distance_km ASC, start_date ASC, id ASC. Keep current search/category/city/date/price behavior unchanged when no preference exists.

- [ ] **Step 5: Feed valid session preference into PublicEventController**

On index:

~~~php
$location = $this->locations->fromSession($this->session->get('event_location'));
if ($location === null) {
    $this->session->forget('event_location');
} else {
    $location['radius'] = $this->locations->radius($request->query('radius', $location['radius']));
    $filters = array_merge($filters, $location, $this->locations->bounds($location));
}
~~~

Pass location, radiuses, map config, distance labels, and whether distance sort is available to the view. Invalid distance sort falls back to soonest.

- [ ] **Step 6: Focused/full verification and commit Task 3**

Run:

~~~bash
rtk php tests/run.php tests/Unit/PublicLocationControllerTest.php tests/Unit/EventRepositoryTest.php tests/Unit/PublicEventControllerTest.php tests/Unit/RouterTest.php
rtk composer test
rtk composer check:syntax
rtk git diff --check
rtk git commit -m "feat: add nearby event discovery"
~~~

---

### Task 4: Self-hosted Leaflet and privacy-safe public maps

**Files:**
- Create: public/assets/js/location.js
- Create: tests/js/location.test.mjs
- Create: tests/Unit/LocationJavascriptTest.php
- Modify: package.json
- Modify: package-lock.json
- Modify: scripts/copy-fonts.mjs
- Modify: app/Controllers/PublicEventController.php
- Modify: app/Views/events/index.php
- Modify: app/Views/events/show.php
- Modify: resources/css/app.css
- Modify: public/assets/css/app.css
- Modify: Core/Response.php
- Modify: public/index.php
- Modify: tests/Unit/PublicEventControllerTest.php
- Modify: tests/Unit/ResponseTest.php
- Modify: tests/Unit/UiLayoutTest.php

**Interfaces:**
- Produces local /assets/vendor/leaflet/leaflet.css, leaflet.js, and images
- Produces safe JSON map payload for public exact locations only
- Produces Response::withHeader(string $name, string $value): Response
- Produces global Permissions-Policy geolocation=(self)
- Consumes public event rows, authenticated registration state, map configuration, and LocationService

- [ ] **Step 1: Add Leaflet dependency after checking package.json**

Run:

~~~bash
rtk npm install --save-dev leaflet@1.9.4
~~~

Extend the asset copy script to recreate public/assets/vendor/leaflet from node_modules/leaflet/dist, copying leaflet.css, leaflet.js, leaflet.js.map, and images. Do not copy development source trees.

- [ ] **Step 2: Write public privacy and presentation RED tests**

Add cases for public, restricted guest, restricted pending participant, restricted confirmed participant, owner, and super-admin. The restricted guest assertion must scan the full response:

~~~php
public function testRestrictedLocationDoesNotLeakExactDataToGuest(): void
{
    $body = $this->showRestrictedEventAsGuest()->body();

    foreach (['23.8103', '90.4125', 'Secret Hall', 'Road 12', 'maps.example.test', 'Use gate B'] as $secret) {
        $this->assertFalse(str_contains($body, $secret), 'Leaked restricted location value: ' . $secret);
    }

    $this->assertTrue(str_contains($body, 'Exact location shared after confirmation'));
    $this->assertFalse(str_contains($body, 'application/ld+json') && str_contains($body, 'Secret Hall'));
}
~~~

Also prove public map payload escapes script terminators, marker data contains published public events only, distance bands are coarse for restricted rows, and exact JSON-LD renders only for authorized viewers.

- [ ] **Step 3: Implement exact-location authorization**

Add a private presenter:

~~~php
private function canViewExactLocation(array $event): bool
{
    if (($event['location_visibility'] ?? 'public') === 'public') {
        return true;
    }

    $userId = $this->auth->id();
    if ($userId === null) {
        return false;
    }

    if ($this->auth->hasRole('super-admin')) {
        return true;
    }

    if ($this->auth->hasRole('organizer')
        && $userId === (int) ($event['organizer_user_id'] ?? 0)) {
        return true;
    }

    $registration = $this->auth->hasRole('participant')
        ? $this->registrations->findForParticipantEvent($userId, (int) $event['id'])
        : null;

    return ($registration['status'] ?? null) === 'confirmed';
}
~~~

Build public marker payload only from rows whose exact location is public and coordinates validate. For show, unset restricted exact fields before passing data to the view or JSON-LD.

- [ ] **Step 4: Write JavaScript RED tests**

The Node harness uses real module behavior and fake DOM/geolocation/Leaflet boundaries. Cover:

~~~javascript
test('use my location rounds coordinates and posts csrf payload once', async () => {
  const harness = createHarness({ latitude: 23.810331, longitude: 90.412521 });
  await harness.clickUseLocation();

  assert.equal(harness.requests.length, 1);
  assert.deepEqual(harness.requests[0].body, {
    latitude: '23.810',
    longitude: '90.413',
    radius: '25',
    _token: 'csrf-token',
  });
});
~~~

Also cover permission denial, timeout, unsupported browser, no request before click, list/map aria-pressed state, mobile panel visibility, marker/card focus, malformed marker payload, reduced motion, and pagehide cleanup.

- [ ] **Step 5: Implement public map UI**

The index includes:

- One location control row with use/change and clear forms
- Radius select and nearest sort only with an active location
- Two buttons with aria-pressed for List and Map
- Existing semantic result cards as the primary content
- Reserved map container with accessible label and inline fallback
- One application/json payload encoded with JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS, and JSON_HEX_QUOT

The detail page includes the map, complete address, arrival notes, and directions only when exact_location_visible is true. Restricted viewers see city/country plus the confirmation message.

Location JavaScript must use getCurrentPosition only:

~~~javascript
navigator.geolocation.getCurrentPosition(onSuccess, onError, {
  enableHighAccuracy: false,
  timeout: 10000,
  maximumAge: 300000,
});
~~~

Do not invoke geolocation at module initialization.

- [ ] **Step 6: Add security header test and implementation**

RED:

~~~php
public function testWithHeaderReturnsNewResponseWithoutDroppingExistingHeaders(): void
{
    $response = Response::html('ok')->withHeader('Permissions-Policy', 'geolocation=(self)');

    $this->assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
    $this->assertSame('geolocation=(self)', $response->header('Permissions-Policy'));
}
~~~

Use the immutable helper on the dispatched response in public/index.php. Apply the same header to the 500 response.

- [ ] **Step 7: Style and build assets**

Use existing semantic tokens, 18-pixel map panels, 12-pixel controls, 44-pixel targets, no external shadows/glows, and explicit mobile collapse below 768 pixels. Reserve at least 360 pixels desktop and 300 pixels mobile for the map. Add dark-mode Leaflet control treatment without hiding attribution. Map transitions must be disabled under prefers-reduced-motion.

Run:

~~~bash
rtk npm run build:css
rtk node tests/js/location.test.mjs
rtk node --check public/assets/js/location.js
~~~

- [ ] **Step 8: Verify and commit Task 4**

Run:

~~~bash
rtk php tests/run.php tests/Unit/PublicEventControllerTest.php tests/Unit/LocationJavascriptTest.php tests/Unit/ResponseTest.php tests/Unit/UiLayoutTest.php
rtk composer test
rtk composer check:syntax
rtk npm run build:css
rtk node tests/js/location.test.mjs
rtk git diff --check
rtk git commit -m "feat: add privacy-safe public event maps"
~~~

---

### Task 5: Organizer venue pinning, geocoding endpoint, and event privacy controls

**Files:**
- Create: public/assets/js/venue-map.js
- Create: tests/js/venue-map.test.mjs
- Modify: app/Repositories/VenueRepository.php
- Modify: app/Repositories/EventRepository.php
- Modify: app/Services/VenueService.php
- Modify: app/Services/EventService.php
- Modify: app/Controllers/OrganizerVenueController.php
- Modify: app/Controllers/OrganizerEventController.php
- Modify: app/Views/organizer/venues/form.php
- Modify: app/Views/organizer/events/form.php
- Modify: app/Views/organizer/events/show.php
- Modify: routes/web.php
- Modify: bootstrap/app.php
- Modify: resources/css/app.css
- Modify: public/assets/css/app.css
- Modify: tests/Support/FakeVenueRepository.php
- Modify: tests/Support/FakeEventRepository.php
- Modify: tests/Unit/VenueServiceTest.php
- Modify: tests/Unit/EventServiceTest.php
- Modify: tests/Unit/OrganizerVenueControllerTest.php
- Modify: tests/Unit/OrganizerEventControllerTest.php
- Modify: tests/Unit/EventRepositoryTest.php
- Modify: tests/Unit/UiLayoutTest.php

**Interfaces:**
- Produces: POST /organizer/venues/geocode returning bounded JSON
- Extends event create/update attributes with location_visibility and arrival_notes
- Produces browser map click, drag, current position, clear pin, and explicit address-result selection
- Consumes VenueGeocodingService, RateLimiter, LocationService, map config, and existing role/csrf middleware

- [ ] **Step 1: Write venue coordinate pair RED tests**

Add service and real repository tests:

~~~php
public function testVenueRejectsOnlyOneCoordinate(): void
{
    $result = $this->service->create(10, $this->validInput([
        'latitude' => '23.8103',
        'longitude' => '',
    ]));

    $this->assertFalse($result['success']);
    $this->assertArrayHasKey('longitude', $result['errors']);
}
~~~

Also cover both null, both valid at boundaries, out-of-range values, no-op update, ownership, and database check behavior.

- [ ] **Step 2: Implement coordinate validation GREEN**

Normalize values to null or seven-decimal strings. If exactly one is present, attach errors to both coordinate fields. Keep map_url HTTPS-only and bounded. VenueRepository remains owner-scoped for create/update/read.

- [ ] **Step 3: Write geocoding controller/route RED tests**

Cover organizer success, guest redirect, participant 403, CSRF 419, GET 405, foreign data absence, invalid query 422, sixth attempt 429, provider 503, and escaped bounded JSON:

~~~php
public function testOrganizerAddressSearchReturnsFiveSafeResultsAtMost(): void
{
    $response = $this->controller->geocode(Request::create('POST', '/organizer/venues/geocode', input: [
        'query' => 'Bashundhara Dhaka',
    ]));

    $this->assertSame(200, $response->status());
    $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
    $this->assertLessThanOrEqual(5, count($payload['results']));
}
~~~

Use a limiter key derived from organizer user id and IP hash. Do not put raw query text in the key.

- [ ] **Step 4: Implement geocoding endpoint GREEN**

Add OrganizerVenueController dependencies VenueGeocodingService, LocationService, and RateLimiter. Return:

~~~php
return Response::json([
    'results' => array_map(static fn (array $result): array => [
        'label' => (string) $result['label'],
        'latitude' => (string) $result['latitude'],
        'longitude' => (string) $result['longitude'],
    ], $result['results']),
], 200, ['Cache-Control' => 'private, no-store']);
~~~

Register POST /organizer/venues/geocode before the dynamic venue-id routes with role:organizer and csrf.

- [ ] **Step 5: Write event privacy and arrival RED tests**

Extend EventService and repository tests:

~~~php
public function testEventPersistsRestrictedLocationAndArrivalNotes(): void
{
    $result = $this->service->createDraft(10, $this->validInput([
        'location_visibility' => 'registered',
        'arrival_notes' => 'Use the north entrance beside the library.',
    ]), null, []);

    $this->assertTrue($result['success']);
    $this->assertSame('registered', $this->events->events[$result['event_id']]['location_visibility']);
}
~~~

Also reject unknown visibility, more than 500 characters, arrival notes without a venue, and restricted location with a venue missing coordinates. Public visibility may retain a written venue without coordinates but will render no interactive map.

- [ ] **Step 6: Implement event fields and organizer UI**

Add fields to OrganizerEventController::FIELDS, EventService normalization/rules, repository insert/update parameter maps, and organizer preview. Form controls:

~~~php
<select id="location_visibility" name="location_visibility" aria-describedby="location-visibility-help">
    <option value="public">Public exact location</option>
    <option value="registered">Confirmed participants only</option>
</select>
<p id="location-visibility-help" class="field-help">Restricted mode hides the exact address, pin, directions, and arrival notes until registration is confirmed.</p>
~~~

Arrival notes use a textarea maxlength 500 with helper and error IDs merged correctly.

- [ ] **Step 7: Write venue-map JavaScript RED tests**

Cover no map without container, default configured center, existing coordinate pin, map click, marker drag, address search only on button action, five-result rendering, result selection, current-position success/denial, clear pin, form validation error retention, no hidden automatic geocoding, status announcements, and cleanup.

~~~javascript
test('dragging the marker updates both exact coordinate fields', async () => {
  const harness = createVenueHarness();
  harness.dragMarker(23.8151, 90.4255);

  assert.equal(harness.latitude.value, '23.8151000');
  assert.equal(harness.longitude.value, '90.4255000');
  assert.match(harness.status.textContent, /Pin moved/);
});
~~~

- [ ] **Step 8: Implement map-led venue form and styles**

The section order is address, explicit Find address control, results, map, location actions, advanced coordinates, map URL, capacity. Selecting or dragging the pin never rewrites address fields. The map uses one draggable marker, accessible title, keyboard-safe controls, local Leaflet assets, and existing OEMS buttons/icons.

- [ ] **Step 9: Verify and commit Task 5**

Run:

~~~bash
rtk php tests/run.php tests/Unit/VenueServiceTest.php tests/Unit/EventServiceTest.php tests/Unit/OrganizerVenueControllerTest.php tests/Unit/OrganizerEventControllerTest.php tests/Unit/EventRepositoryTest.php tests/Unit/UiLayoutTest.php
rtk node tests/js/venue-map.test.mjs
rtk node --check public/assets/js/venue-map.js
rtk npm run build:css
rtk composer test
rtk composer check:syntax
rtk git diff --check
rtk git commit -m "feat: add organizer venue map controls"
~~~

---

### Task 6: Demo journey, documentation, native verification, browser QA, and push

**Files:**
- Modify: .env.example
- Modify: README.md
- Modify: database/demo_seed.sql
- Modify: tests/Unit/DemoSeedIntegrityTest.php
- Modify: tests/Unit/UiLayoutTest.php
- Modify any location files only when a new failing release regression proves a defect

**Interfaces:**
- Produces repeatable public and restricted demo location journeys
- Produces documented provider, privacy, setup, and production policy
- Consumes all prior task interfaces

- [ ] **Step 1: Write demo/documentation RED tests**

Demo integrity must prove:

- At least three published future events have valid coordinate pairs
- One published event uses public exact location
- One published event uses registered-only exact location
- Restricted demo arrival notes are present but never rendered to guests
- Reimport does not duplicate geocoding cache or reset transaction state
- Demo venues belong to the matching event organizer

Run the focused test and observe the exact missing-fixture failures.

- [ ] **Step 2: Update demo data and README**

Document:

- npm install and npm run build:css/build:assets
- HTTPS requirement outside localhost
- Every MAP_* and LOCATION_SESSION_TTL value
- Development-only public OSM/Nominatim limits, visible attribution, and production provider switching
- No attendee tracking, no IP lookup, session-only rounded device location, 14-day expiry, and clear action
- Organizer address search, map pin, visibility, arrival notes, and directions flow
- Public/confirmed location behavior
- Demo accounts/events that exercise public and restricted locations
- Exact migration order for populated databases

Do not include SMTP, provider, or environment secrets.

- [ ] **Step 3: Run full automated release gates**

Run:

~~~bash
rtk composer test
rtk composer check:syntax
rtk composer validate --strict
rtk composer check-platform-reqs
rtk composer audit
rtk npm run build:css
rtk node --check public/assets/js/location.js
rtk node --check public/assets/js/venue-map.js
rtk node tests/js/location.test.mjs
rtk node tests/js/venue-map.test.mjs
rtk git diff --check
~~~

Expected: zero failures, warnings, dependency advisories, syntax errors, or asset drift.

- [ ] **Step 4: Verify populated MySQL upgrade and native distance behavior**

Create a uniquely named disposable database. Never touch the configured application database for migration testing.

1. Import schema from baseline commit 90cb666 plus baseline seed/demo.
2. Record table counts and representative event/registration/payment/ticket rows.
3. Apply 2026-08-09-live-location.sql twice.
4. Prove counts and representative rows are unchanged.
5. Prove columns, check, coordinate index, and cache table exist.
6. Run a prepared nearby query at 5, 25, and 100 km.
7. Prove distance order, radius exclusion, lifecycle exclusion, active category, and restricted presentation source fields.
8. Drop the exact disposable database after all evidence is recorded.

- [ ] **Step 5: Run live HTTP acceptance**

Against a disposable or rollback-safe database:

- Guest event list without location: 200 and city filters work
- Guest valid location POST with CSRF: redirect and nearby results
- Missing/invalid CSRF: 419
- Invalid coordinates: 422/no session mutation
- Clear location: idempotent redirect
- Organizer geocode success/cache and rate limit
- Guest/participant geocode role boundaries
- Organizer venue create with map coordinates
- Organizer event create with registered-only visibility and arrival notes
- Admin approval, organizer publication, guest restricted detail
- Participant pending registration remains restricted
- Admin payment verification confirms registration
- Confirmed participant sees exact map, directions, and arrival notes
- Public-location event exposes correct map and JSON-LD
- Wrong methods return 405; foreign organizer IDs return 404/403 according to current policy

- [ ] **Step 6: Run in-app browser QA**

Use the browser control skill and inspect at 320, 768, and 1440 pixels in light and dark modes:

- No horizontal overflow
- Map/list controls, radius, location permission success and denial
- Keyboard order, 3-pixel focus, marker keyboard access, no focus trap
- 44-pixel targets and no wrapped desktop CTA labels
- Public/restricted detail states
- Organizer address results, pin click/drag, current position denial, clear pin
- Inline errors, aria-live announcements, labels/help/error association
- Visible attribution and directions behavior
- Reduced motion
- Zero console errors/warnings and zero sampled WCAG AA contrast failures

- [ ] **Step 7: Perform final security/package audit**

Check:

- No exact restricted values in guest HTML, JSON, JSON-LD, logs, or client payloads
- No watchPosition, IP geolocation, location analytics, CDN, unconfigured provider host, or raw address logging
- No secrets in tracked files
- Only local Leaflet distribution files are committed
- package lock matches package.json
- Existing unrelated untracked artifacts remain unstaged

- [ ] **Step 8: Fix only proven release defects test-first**

For every defect found in Steps 3-7:

1. Add a focused test that fails for the observed behavior.
2. Run it and record the expected failure.
3. Implement the smallest correction.
4. Run focused and full gates again.

Do not add unplanned adjacent features.

- [ ] **Step 9: Commit release evidence and push**

Stage only the final documented project files. Commit:

~~~bash
rtk git commit -m "fix: close live location release findings"
~~~

Confirm clean tracked status, expected commit chain, and no staged user artifacts. Push main:

~~~bash
rtk git push origin main
~~~

Final acceptance requires the GitHub remote head to equal local HEAD and the configured local server health probe to return 200.

---

## Plan Self-Review

- Spec coverage: every approved schema, provider, public discovery, organizer, privacy, accessibility, security, configuration, demo, migration, browser, and push requirement maps to Tasks 1-6.
- Placeholder scan: prohibited placeholder patterns and unbounded implementation instructions are absent.
- Interface consistency: LocationService preferences feed Task 3; Task 3 repository fields feed Task 4; Task 2 geocoder feeds Task 5; Task 1 schema supports every later task.
- Mutation coverage: tests fail when coordinate bounds, radius allow-list, lifecycle scope, privacy removal, authorization, caching, rate limiting, marker filtering, or field persistence is removed.
- Delivery independence: each task ends with a usable, reviewable behavior and a scoped commit.
