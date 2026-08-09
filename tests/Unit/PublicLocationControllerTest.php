<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\PublicLocationController;
use OEMS\App\Controllers\PublicEventController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Services\LocationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\TestCase;

final class PublicLocationControllerTest extends TestCase
{
    private PublicLocationController $controller;

    private Security $security;

    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $this->controller = new PublicLocationController(
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            new Auth($this->session, new FakeUserRepository()),
            new Config(['name' => 'OEMS']),
            new LocationService(1209600, static fn (): int => 1_800_000_000),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

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
        $this->assertSame('90.413', $this->session->get('event_location.longitude'));
        $this->assertSame(1_801_209_600, $this->session->get('event_location.expires_at'));
    }

    public function testStoreRejectsInvalidCoordinatesWithoutMutatingExistingPreference(): void
    {
        $this->session->put('event_location', ['latitude' => '23.810', 'longitude' => '90.413']);

        $response = $this->controller->store(Request::create('POST', '/events/location', input: [
            'latitude' => 'not-a-coordinate',
            'longitude' => '90.413',
            'radius' => '25',
        ]));

        $this->assertSame('/events', $response->header('Location'));
        $this->assertSame(['latitude' => '23.810', 'longitude' => '90.413'], $this->session->get('event_location'));
        $this->assertSame(['location' => ['Enter a valid location.']], $this->session->get('_flash.errors'));
    }

    public function testStoreFallsBackToDefaultRadius(): void
    {
        $response = $this->controller->store(Request::create('POST', '/events/location', input: [
            'latitude' => '23.810',
            'longitude' => '90.413',
            'radius' => '999',
        ]));

        $this->assertSame('/events?radius=25&sort=distance', $response->header('Location'));
        $this->assertSame(25, $this->session->get('event_location.radius'));
    }

    public function testStoreReturnsJsonValidationErrorsForFetchRequests(): void
    {
        $response = $this->controller->store(Request::create('POST', '/events/location', input: [
            'latitude' => '91',
            'longitude' => '90.413',
        ], headers: ['Accept' => 'application/json']));

        $this->assertSame(422, $response->status());
        $this->assertSame('{"errors":{"location":["Enter a valid location."]}}', $response->body());
        $this->assertNull($this->session->get('event_location'));
    }

    public function testClearIsIdempotentAndRedirectsToSafeDiscoveryUrl(): void
    {
        $this->session->put('event_location', ['latitude' => '23.810', 'longitude' => '90.413']);

        $first = $this->controller->clear(Request::create('POST', '/events/location/clear'));
        $second = $this->controller->clear(Request::create('POST', '/events/location/clear'));

        $this->assertSame('/events', $first->header('Location'));
        $this->assertSame('/events', $second->header('Location'));
        $this->assertNull($this->session->get('event_location'));
    }

    public function testLocationRoutesRequirePostAndCsrf(): void
    {
        $router = $this->router();

        foreach (['/events/location', '/events/location/clear'] as $uri) {
            $this->assertSame(419, $router->dispatch(Request::create('POST', $uri, input: ['_token' => 'invalid']))->status());
        }

        $this->assertSame(405, $router->dispatch(Request::create('GET', '/events/location/clear'))->status());
        $this->assertSame(405, $router->dispatch(Request::create('PUT', '/events/location'))->status());
        $this->assertNull($this->session->get('event_location'));
    }

    public function testLocationPostEndpointDoesNotShadowAPublishedLocationSlugOnGet(): void
    {
        $router = $this->routerWithLocationEvent();

        $get = $router->dispatch(Request::create('GET', '/events/location'));

        $this->assertSame(200, $get->status());
        $this->assertTrue(str_contains($get->body(), 'Location event'));
        $this->assertNull($this->session->get('event_location'));

        $post = $router->dispatch(Request::create('POST', '/events/location', input: [
            '_token' => $this->security->csrfToken(),
            'latitude' => '23.810',
            'longitude' => '90.413',
            'radius' => '25',
        ]));

        $this->assertSame('/events?radius=25&sort=distance', $post->header('Location'));
        $this->assertSame('23.810', $this->session->get('event_location.latitude'));
    }

    private function router(): Router
    {
        $container = new Container();
        $container->instance(PublicLocationController::class, $this->controller);
        $router = new Router($container);
        $router->aliasMiddleware('csrf', new CsrfMiddleware($this->security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return $router;
    }

    private function routerWithLocationEvent(): Router
    {
        $events = new FakeEventRepository();
        $events->events[1] = [
            'id' => 1,
            'title' => 'Location event',
            'slug' => 'location',
            'description' => 'A published event which uses the location slug.',
            'start_date' => '2030-01-10 10:00:00',
            'end_date' => '2030-01-10 12:00:00',
            'registration_deadline' => '2030-01-09 10:00:00',
            'capacity' => 10,
            'available_seats' => 10,
            'ticket_price' => '0.00',
            'currency' => 'BDT',
            'status' => 'published',
            'deleted_at' => null,
            'category_name' => 'Technology',
            'venue_name' => 'Dhaka Hall',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
            'organization_name' => 'OEMS Studio',
        ];
        $eventController = new PublicEventController(
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            new Auth($this->session, new FakeUserRepository()),
            new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
            $events,
            new FakeCategoryRepository(),
            new FakeRegistrationRepository(),
        );
        $container = new Container();
        $container->instance(PublicLocationController::class, $this->controller);
        $container->instance(PublicEventController::class, $eventController);
        $router = new Router($container);
        $router->aliasMiddleware('csrf', new CsrfMiddleware($this->security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return $router;
    }
}
