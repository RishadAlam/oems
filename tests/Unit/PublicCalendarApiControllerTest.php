<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use ReflectionClass;
use OEMS\App\Controllers\ApiEventController;
use OEMS\App\Controllers\PublicCalendarController;
use OEMS\App\Services\PublicEventApiService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class PublicCalendarApiControllerTest extends TestCase
{
    private PublicEventApiService $service;

    private array $dependencies;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $this->dependencies = [
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
        ];
        $events = new FakeEventRepository();
        $events->events = [
            1 => $this->event(1, 'public-summit', 'Public <Summit>', 'public', '2026-09-12 18:00:00'),
            2 => $this->event(2, 'restricted-gathering', 'Restricted gathering', 'registered', '2026-09-13 18:00:00'),
        ];
        $this->service = new PublicEventApiService(
            $events,
            'Asia/Dhaka',
            'https://events.example.test',
            new DateTimeImmutable('2026-08-10 09:00:00+06:00'),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCalendarRendersSemanticMonthGridAndCanonicalPrivacySafeList(): void
    {
        $controller = new PublicCalendarController(...$this->dependencies, events: $this->service);
        $response = $controller->index(Request::create('GET', '/events/calendar?month=2026-09', query: ['month' => '2026-09']));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'September 2026'), 'Month label is missing.');
        $this->assertTrue(str_contains($response->body(), 'aria-label="Saturday, September 12, 2026"'), 'Accessible day label is missing.');
        $this->assertTrue(str_contains($response->body(), 'Public &lt;Summit&gt;'), 'Escaped event title is missing.');
        $this->assertTrue(str_contains($response->body(), 'Dhaka'), 'Coarse city is missing.');
        $this->assertFalse(str_contains($response->body(), 'Private Hall'), 'Restricted venue identity leaked.');
        $this->assertTrue(str_contains($response->body(), 'Chronological event list'), 'Canonical list is missing.');
        $this->assertTrue(str_contains($response->body(), 'flex flex-wrap items-center justify-between'), 'Month controls need a wrapping mobile layout.');
        $this->assertTrue(str_contains($response->body(), 'order-first w-full'), 'The month label needs its own mobile row.');

        $invalid = $controller->index(Request::create('GET', '/events/calendar?month=bad', query: ['month' => 'bad']));
        $this->assertSame(422, $invalid->status());
        $this->assertTrue(str_contains($invalid->body(), 'YYYY-MM'));
    }

    public function testApiListUsesStableHeadersPaginationAndConditionalEtag(): void
    {
        $controller = $this->apiController(20);
        $request = Request::create('GET', '/api/v1/events', query: [
            'date_from' => '2026-09-01', 'date_to' => '2026-10-01', 'limit' => '20',
        ], server: ['REMOTE_ADDR' => '203.0.113.7']);
        $response = $controller->index($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        $this->assertSame('public, max-age=60', $response->header('Cache-Control'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertNull($response->header('Access-Control-Allow-Origin'));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $payload['meta']['pagination']['total']);
        $this->assertFalse(array_key_exists('id', $payload['data'][0]));
        $etag = $response->header('ETag');
        $this->assertNotNull($etag);

        $conditional = $controller->index(Request::create('GET', '/api/v1/events', query: [
            'date_from' => '2026-09-01', 'date_to' => '2026-10-01', 'limit' => '20',
        ], headers: ['If-None-Match' => $etag], server: ['REMOTE_ADDR' => '203.0.113.7']));
        $this->assertSame(304, $conditional->status());
        $this->assertSame('', $conditional->body());
    }

    public function testApiFailsClosedForInvalidFiltersHiddenSlugsAndRateLimits(): void
    {
        $controller = $this->apiController(2);
        $invalid = $controller->index(Request::create('GET', '/api/v1/events', query: ['unknown' => 'value'], server: ['REMOTE_ADDR' => '203.0.113.8']));
        $this->assertSame(422, $invalid->status());

        $malformed = $controller->show(Request::create('GET', '/api/v1/events/private')->withRouteParameters(['slug' => '../private']));
        $this->assertSame(404, $malformed->status());
        $missing = $controller->show(Request::create('GET', '/api/v1/events/missing-event')->withRouteParameters(['slug' => 'missing-event']));
        $this->assertSame(404, $missing->status());

        $request = Request::create('GET', '/api/v1/events/calendar', query: ['month' => '2026-09'], server: ['REMOTE_ADDR' => '203.0.113.9']);
        $this->assertSame(200, $controller->calendar($request)->status());
        $this->assertSame(200, $controller->calendar($request)->status());
        $this->assertSame(429, $controller->calendar($request)->status());
    }

    public function testCalendarAndApiRoutesAreGetOnlyAndCalendarStaticRouteWins(): void
    {
        $router = new Router(new Container());
        $register = require base_path('routes/web.php');
        $register($router);

        $this->assertSame(405, $router->dispatch(Request::create('POST', '/events/calendar'))->status());
        $this->assertSame(405, $router->dispatch(Request::create('POST', '/api/v1/events'))->status());
        $this->assertSame(405, $router->dispatch(Request::create('POST', '/api/v1/events/calendar'))->status());
        $this->assertSame(405, $router->dispatch(Request::create('POST', '/api/v1/events/public-summit'))->status());
    }

    public function testPublicApiEntrypointDoesNotStartAuthenticationSessions(): void
    {
        $entrypoint = file_get_contents(base_path('public/index.php'));
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $constructor = (new ReflectionClass(ApiEventController::class))->getConstructor();
        $parameters = array_map(static fn ($parameter): string => $parameter->getName(), $constructor?->getParameters() ?? []);

        $this->assertTrue(is_string($entrypoint));
        $this->assertTrue(is_string($bootstrap));
        $this->assertTrue(str_contains($entrypoint, '$statelessApiRequest'));
        $this->assertTrue(str_contains($entrypoint, "\$statelessApiRequest = str_starts_with(\$request->path(), '/api/v1/');"));
        $this->assertTrue(str_contains($entrypoint, 'if (!$healthRequest && !$statelessApiRequest)'));
        $this->assertSame(['events', 'limiter'], $parameters);
        $this->assertTrue(str_contains($bootstrap, "new AuthMiddleware(static fn (): Auth"));
        $this->assertTrue(str_contains($bootstrap, "new CsrfMiddleware(static fn (): Security"));
    }

    private function apiController(int $attempts): ApiEventController
    {
        return new ApiEventController(
            events: $this->service,
            limiter: new RateLimiter(sys_get_temp_dir() . '/oems-api-' . bin2hex(random_bytes(5)), $attempts, 60),
        );
    }

    private function event(int $id, string $slug, string $title, string $visibility, string $start): array
    {
        return [
            'id' => $id, 'slug' => $slug, 'title' => $title,
            'description' => 'A public event description.', 'banner' => null, 'speaker' => null,
            'start_date' => $start, 'end_date' => str_replace('18:00:00', '20:00:00', $start),
            'registration_deadline' => str_replace('18:00:00', '12:00:00', $start),
            'capacity' => 50, 'available_seats' => 5, 'ticket_price' => '0.00', 'currency' => 'BDT',
            'tags' => ['community'], 'status' => 'published', 'waitlist_enabled' => 1,
            'category_name' => 'Community', 'category_slug' => 'community', 'organization_name' => 'OEMS Community',
            'location_visibility' => $visibility,
            'arrival_notes' => $visibility === 'public' ? 'Public entrance.' : 'Secret arrival note.',
            'venue_name' => $visibility === 'public' ? 'Public Hall' : 'Private Hall',
            'venue_address_line' => $visibility === 'public' ? '12 Public Road' : '12 Private Road',
            'venue_city' => 'Dhaka', 'venue_country' => 'Bangladesh', 'venue_postal_code' => '1205',
            'venue_latitude' => '23.8100000', 'venue_longitude' => '90.4130000',
            'venue_map_url' => 'https://maps.example.test/event', 'deleted_at' => null,
        ];
    }
}
