<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Contracts\GeocoderInterface;
use OEMS\App\Contracts\GeocodingCacheRepositoryInterface;
use OEMS\App\Controllers\OrganizerVenueController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\VenueService;
use OEMS\App\Services\LocationService;
use OEMS\App\Services\VenueGeocodingService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeVenueRepository;
use OEMS\Tests\Support\TestCase;

final class OrganizerVenueTestCache implements GeocodingCacheRepositoryInterface
{
    public array $items = [];

    public function findFresh(string $queryHash, string $provider, DateTimeImmutable $now): ?array
    {
        return $this->items[$queryHash] ?? null;
    }

    public function upsert(string $queryHash, string $query, string $provider, array $results, DateTimeImmutable $expiresAt): void
    {
        $this->items[$queryHash] = ['results' => $results];
    }
}

final class OrganizerVenueTestGeocoder implements GeocoderInterface
{
    public bool $fails = false;

    public array $results = [[
        'label' => 'Bashundhara, Dhaka, Bangladesh',
        'latitude' => '23.8151001',
        'longitude' => '90.4255001',
    ]];

    public function search(string $query, int $limit): array
    {
        if ($this->fails) {
            throw new \RuntimeException('Provider failed with a private address.');
        }

        return array_slice($this->results, 0, $limit);
    }
}

final class OrganizerVenueControllerTest extends TestCase
{
    private Session $session;

    private FakeVenueRepository $venues;

    private OrganizerVenueController $controller;

    private OrganizerVenueTestGeocoder $geocoder;

    private string $rateDirectory;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/organizer/venues';
        $this->session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[10] = [
            'id' => 10,
            'role_id' => 2,
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-06 10:00:00',
        ];
        $this->authenticateSession($this->session, $users, 10);
        $this->venues = new FakeVenueRepository();
        $this->geocoder = new OrganizerVenueTestGeocoder();
        $this->rateDirectory = sys_get_temp_dir() . '/oems-venue-geocode-' . bin2hex(random_bytes(6));
        $this->venues->venues[1] = array_merge($this->venues->venues[1], $this->venueInput());
        $this->controller = new OrganizerVenueController(
            new View(base_path('app/Views')),
            $this->session,
            new Security($this->session),
            new Auth($this->session, $users),
            new Config(['name' => 'OEMS']),
            $this->venues,
            new VenueService($this->venues),
            new VenueGeocodingService(
                new OrganizerVenueTestCache(),
                $this->geocoder,
                'Test geocoder',
                throttlePath: $this->rateDirectory . '/provider.lock',
            ),
            new LocationService(),
            new RateLimiter($this->rateDirectory, 5, 900),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);

        if (is_dir($this->rateDirectory)) {
            foreach (scandir($this->rateDirectory) ?: [] as $entry) {
                if (!in_array($entry, ['.', '..'], true)) {
                    unlink($this->rateDirectory . '/' . $entry);
                }
            }
            rmdir($this->rateDirectory);
        }
    }

    public function testOrganizerCanRenderOwnedVenueIndexCreateAndEditPages(): void
    {
        $index = $this->controller->index(Request::create('GET', '/organizer/venues'));
        $create = $this->controller->create(Request::create('GET', '/organizer/venues/create'));
        $edit = $this->controller->edit($this->routed('GET', '/organizer/venues/1/edit', '1'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), 'Owned Hall'));
        $this->assertFalse(str_contains($index->body(), 'Foreign Hall'));
        $this->assertTrue(str_contains($index->body(), 'class="organizer-table__action" data-label="Action"'));
        $this->assertSame(200, $create->status());
        $this->assertTrue(str_contains($create->body(), 'Create venue'));
        $this->assertTrue(str_contains($create->body(), 'type="number"'));
        $this->assertTrue(str_contains($create->body(), 'data-form-kind="entry"'));
        $this->assertFalse(str_contains($create->body(), 'method="post" novalidate'));
        $this->assertTrue(str_contains($create->body(), 'data-paired-with="longitude"'));
        $this->assertTrue(str_contains($create->body(), 'data-paired-with="latitude"'));
        $this->assertTrue(str_contains($create->body(), 'data-submit-label="Creating venue…"'));
        $this->assertSame(200, $edit->status());
        $this->assertTrue(str_contains($edit->body(), 'Edit venue'));
    }

    public function testVenueFormLoadsLocalMapAssetsAndPresentsMapLedControlsInOrder(): void
    {
        $body = $this->controller->create(Request::create('GET', '/organizer/venues/create'))->body();
        $positions = array_map(static fn (string $needle): int|false => strpos($body, $needle), [
            'name="address_line"',
            'data-venue-find',
            'data-venue-results',
            'aria-label="Venue pin map"',
            'data-venue-use-location',
            '<summary>Advanced coordinates</summary>',
            'name="map_url"',
            'name="capacity"',
        ]);

        $this->assertTrue(str_contains($body, '/assets/vendor/leaflet/leaflet.css'));
        $this->assertTrue(str_contains($body, '/assets/vendor/leaflet/leaflet.js'));
        $this->assertTrue(str_contains($body, '/assets/js/venue-map.js?v=20260811-geolocation-secure'));
        $this->assertTrue(str_contains($body, 'data-venue-map-form'));
        $this->assertTrue(str_contains($body, 'aria-live="polite"'));
        $this->assertTrue(str_contains($body, 'aria-label="Venue pin map"'));
        $this->assertTrue(str_contains($body, 'open <strong>Advanced coordinates</strong> below'));
        $this->assertFalse(in_array(false, $positions, true));
        $numericPositions = array_map('intval', $positions);
        $sorted = $numericPositions;
        sort($sorted);
        $this->assertSame($sorted, $numericPositions);
    }

    public function testVenueFormLoadsLeafletStylesBeforeApplicationOverrides(): void
    {
        $body = $this->controller->create(Request::create('GET', '/organizer/venues/create'))->body();
        $leafletPosition = strpos($body, 'href="/assets/vendor/leaflet/leaflet.css"');
        $applicationPosition = strpos($body, 'href="/assets/css/app.css?v=20260813-global-status-v1"');

        $this->assertNotSame(false, $leafletPosition);
        $this->assertNotSame(false, $applicationPosition);
        $this->assertTrue($leafletPosition < $applicationPosition);
    }

    public function testEditKeepsSaveInTheEditFormAndDeletionInADistinctLaterForm(): void
    {
        $body = $this->controller->edit($this->routed('GET', '/organizer/venues/1/edit', '1'))->body();
        $editStart = strpos($body, '<form class="dashboard-panel organizer-form mt-8" action="/organizer/venues/1" method="post"');
        $editClose = $editStart === false ? false : strpos($body, '</form>', $editStart);
        $deleteStart = strpos($body, '<form', $editClose === false ? 0 : $editClose + 7);
        $editMarkup = $editStart === false || $editClose === false
            ? ''
            : substr($body, $editStart, $editClose - $editStart);
        $deleteMarkup = $deleteStart === false ? '' : substr($body, $deleteStart);

        $this->assertNotSame(false, $editStart);
        $this->assertNotSame(false, $editClose);
        $this->assertTrue(str_contains($editMarkup, '<button class="button button--primary" type="submit"'));
        $this->assertTrue(str_contains($editMarkup, '<span>Save venue</span>'));
        $this->assertFalse(str_contains($editMarkup, 'formaction='));
        $this->assertFalse(str_contains($editMarkup, '/delete'));
        $this->assertNotSame(false, $deleteStart);
        $this->assertTrue(str_contains($deleteMarkup, 'action="/organizer/venues/1/delete" method="post"'));
        $this->assertTrue(str_contains($deleteMarkup, 'data-form-kind="action"'));
        $this->assertTrue(str_contains($deleteMarkup, 'data-submit-label="Deleting venue…"'));
        $this->assertTrue(str_contains($deleteMarkup, '<span>Delete venue</span>'));
    }

    public function testForeignMissingAndMalformedVenueIdsReturnNotFound(): void
    {
        foreach (['2', '999', '0', '-1', 'venue'] as $id) {
            $response = $this->controller->edit($this->routed('GET', '/organizer/venues/' . $id . '/edit', $id));
            $this->assertSame(404, $response->status());
        }

        $response = $this->controller->update($this->routed(
            'POST',
            '/organizer/venues/2',
            '2',
            $this->venueInput(),
        ));
        $this->assertSame(404, $response->status());
    }

    public function testInvalidVenueRedirectsWithOnlyScalarSafeOldInput(): void
    {
        $input = $this->venueInput();
        $input['name'] = ['unsafe'];
        $input['organizer_id'] = '20';

        $response = $this->controller->store(Request::create('POST', '/organizer/venues', input: $input));
        $old = $this->session->get('_flash.old', []);

        $this->assertSame('/organizer/venues/create', $response->header('Location'));
        $this->assertArrayHasKey('name', $this->session->get('_flash.errors', []));
        $this->assertFalse(array_key_exists('name', $old));
        $this->assertFalse(array_key_exists('organizer_id', $old));
        foreach ($old as $value) {
            $this->assertTrue(is_scalar($value));
        }
    }

    public function testSuccessfulCreateAndUpdateUseAuthenticatedOwnershipAndFlashConfirmation(): void
    {
        $createInput = $this->venueInput();
        $createInput['organizer_id'] = '20';
        $create = $this->controller->store(Request::create('POST', '/organizer/venues', input: $createInput));
        $createdId = max(array_keys($this->venues->venues));

        $this->assertSame('/organizer/venues', $create->header('Location'));
        $this->assertSame('Venue created.', $this->session->get('_flash.success'));
        $this->assertSame(10, $this->venues->venues[$createdId]['user_id']);

        $updateInput = $this->venueInput();
        $updateInput['name'] = 'Updated Hall';
        $update = $this->controller->update($this->routed(
            'POST',
            '/organizer/venues/1',
            '1',
            $updateInput,
        ));

        $this->assertSame('/organizer/venues', $update->header('Location'));
        $this->assertSame('Venue updated.', $this->session->get('_flash.success'));
        $this->assertSame('Updated Hall', $this->venues->venues[1]['name']);
    }

    public function testDeletionReturnsNotFoundForForeignVenueWithoutConsultingInUseGuard(): void
    {
        $foreign = $this->controller->delete($this->routed('POST', '/organizer/venues/2/delete', '2'));
        $this->assertSame(404, $foreign->status());
        $this->assertSame(0, $this->venues->deleteOwnedCalls);
        $this->assertSame([], $this->venues->referencedVenueIds);
        $this->assertArrayHasKey(2, $this->venues->venues);
    }

    public function testDeletionBlocksAnOwnedReferencedVenue(): void
    {
        $this->venues->referencedVenueIds = [1];
        $blocked = $this->controller->delete($this->routed('POST', '/organizer/venues/1/delete', '1'));

        $this->assertSame('/organizer/venues', $blocked->header('Location'));
        $this->assertSame(
            'This venue cannot be deleted while an event uses it.',
            $this->session->get('_flash.error'),
        );
        $this->assertArrayHasKey(1, $this->venues->venues);
    }

    public function testUnusedOwnedVenueCanBeDeleted(): void
    {
        $response = $this->controller->delete($this->routed('POST', '/organizer/venues/1/delete', '1'));

        $this->assertSame('/organizer/venues', $response->header('Location'));
        $this->assertSame('Venue deleted.', $this->session->get('_flash.success'));
        $this->assertFalse(array_key_exists(1, $this->venues->venues));
    }

    public function testEveryVenuePostRouteRequiresOrganizerRoleAndCsrf(): void
    {
        foreach (['/organizer/venues/geocode', '/organizer/venues', '/organizer/venues/1', '/organizer/venues/1/delete'] as $uri) {
            $participant = $this->routerForRole('participant');
            $blockedRole = $participant['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => $participant['security']->csrfToken(),
            ]));
            $this->assertSame(403, $blockedRole->status());

            $organizer = $this->routerForRole('organizer');
            $blockedCsrf = $organizer['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => 'invalid',
            ]));
            $this->assertSame(419, $blockedCsrf->status());
        }
    }

    public function testOrganizerAddressSearchReturnsBoundedPrivateJson(): void
    {
        $this->geocoder->results = array_map(static fn (int $index): array => [
            'label' => "Venue <{$index}>",
            'latitude' => (string) (23 + ($index / 100)),
            'longitude' => (string) (90 + ($index / 100)),
        ], range(1, 7));

        $response = $this->controller->geocode(Request::create(
            'POST',
            '/organizer/venues/geocode',
            input: ['query' => 'Bashundhara Dhaka'],
            server: ['REMOTE_ADDR' => '203.0.113.10'],
        ));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->status());
        $this->assertSame('private, no-store', $response->header('Cache-Control'));
        $this->assertSame(5, count($payload['results']));
        $this->assertSame(['label', 'latitude', 'longitude'], array_keys($payload['results'][0]));
        $this->assertSame('Venue <1>', $payload['results'][0]['label']);
    }

    public function testOrganizerAddressSearchNormalizesAdversarialNumericCoordinates(): void
    {
        $this->geocoder->results = [[
            'label' => 'Long precision venue',
            'latitude' => '23.80000000000000000000000000000000000000001',
            'longitude' => '90.40000000000000000000000000000000000000001',
        ]];

        $response = $this->controller->geocode(Request::create(
            'POST',
            '/organizer/venues/geocode',
            input: ['query' => 'Long precision venue'],
            server: ['REMOTE_ADDR' => '203.0.113.14'],
        ));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->status());
        $this->assertSame('23.8000000', $payload['results'][0]['latitude']);
        $this->assertSame('90.4000000', $payload['results'][0]['longitude']);
        $this->assertSame(10, strlen($payload['results'][0]['latitude']));
        $this->assertSame(10, strlen($payload['results'][0]['longitude']));
    }

    public function testAddressSearchMapsValidationProviderAndRateLimitFailures(): void
    {
        $invalid = $this->controller->geocode(Request::create('POST', '/organizer/venues/geocode', input: [
            'query' => 'x',
        ], server: ['REMOTE_ADDR' => '203.0.113.11']));
        $this->geocoder->fails = true;
        $provider = $this->controller->geocode(Request::create('POST', '/organizer/venues/geocode', input: [
            'query' => 'Provider failure venue',
        ], server: ['REMOTE_ADDR' => '203.0.113.12']));
        $this->geocoder->fails = false;

        $this->assertSame(422, $invalid->status());
        $this->assertSame(503, $provider->status());
        $this->assertFalse(str_contains($provider->body(), 'private address'));

        $last = null;
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $last = $this->controller->geocode(Request::create('POST', '/organizer/venues/geocode', input: [
                'query' => 'Bashundhara Dhaka',
            ], server: ['REMOTE_ADDR' => '203.0.113.13']));
        }

        $this->assertSame(429, $last?->status());
        $this->assertSame('private, no-store', $last?->header('Cache-Control'));
    }

    public function testAddressSearchRouteEnforcesGuestRoleCsrfAndMethodBoundaries(): void
    {
        $guest = $this->routerForRole('guest');
        $guestResponse = $guest['router']->dispatch(Request::create('POST', '/organizer/venues/geocode'));
        $participant = $this->routerForRole('participant');
        $participantResponse = $participant['router']->dispatch(Request::create('POST', '/organizer/venues/geocode', input: [
            '_token' => $participant['security']->csrfToken(),
        ]));
        $organizer = $this->routerForRole('organizer');
        $csrfResponse = $organizer['router']->dispatch(Request::create('POST', '/organizer/venues/geocode', input: [
            '_token' => 'invalid',
        ]));
        $methodResponse = $organizer['router']->dispatch(Request::create('GET', '/organizer/venues/geocode'));

        $this->assertSame('/login', $guestResponse->header('Location'));
        $this->assertSame(403, $participantResponse->status());
        $this->assertSame(419, $csrfResponse->status());
        $this->assertSame(405, $methodResponse->status());
    }

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = new FakeUserRepository();
        $users->users[10] = [
            'id' => 10,
            'role_id' => $role === 'organizer' ? 2 : 3,
            'name' => 'Route User',
            'email' => 'route@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-06 10:00:00',
        ];
        if ($role !== 'guest') {
            $this->authenticateSession($session, $users, 10);
        }
        $auth = new Auth($session, $users);
        $container = new Container();
        $container->instance(OrganizerVenueController::class, $this->controller);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function routed(string $method, string $uri, string $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input)->withRouteParameters(['id' => $id]);
    }

    private function venueInput(): array
    {
        return [
            'name' => 'Owned Hall',
            'address_line' => '12 Lake Road',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1205',
            'latitude' => '23.7465',
            'longitude' => '90.3760',
            'map_url' => 'https://www.google.com/maps/venue',
            'capacity' => '100',
        ];
    }
}
