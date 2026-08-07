<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\OrganizerVenueController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\VenueService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeVenueRepository;
use OEMS\Tests\Support\TestCase;

final class OrganizerVenueControllerTest extends TestCase
{
    private Session $session;

    private FakeVenueRepository $venues;

    private OrganizerVenueController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/organizer/venues';
        $this->session = new Session(false);
        $this->session->put('auth.user_id', 10);
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
        $this->venues = new FakeVenueRepository();
        $this->venues->venues[1] = array_merge($this->venues->venues[1], $this->venueInput());
        $this->controller = new OrganizerVenueController(
            new View(base_path('app/Views')),
            $this->session,
            new Security($this->session),
            new Auth($this->session, $users),
            new Config(['name' => 'OEMS']),
            $this->venues,
            new VenueService($this->venues),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testOrganizerCanRenderOwnedVenueIndexCreateAndEditPages(): void
    {
        $index = $this->controller->index(Request::create('GET', '/organizer/venues'));
        $create = $this->controller->create(Request::create('GET', '/organizer/venues/create'));
        $edit = $this->controller->edit($this->routed('GET', '/organizer/venues/1/edit', '1'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), 'Owned Hall'));
        $this->assertFalse(str_contains($index->body(), 'Foreign Hall'));
        $this->assertSame(200, $create->status());
        $this->assertTrue(str_contains($create->body(), 'Create venue'));
        $this->assertTrue(str_contains($create->body(), 'type="number"'));
        $this->assertSame(200, $edit->status());
        $this->assertTrue(str_contains($edit->body(), 'Edit venue'));
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

    public function testDeletionReturnsNotFoundForForeignVenueAndBlocksReferencedVenue(): void
    {
        $foreign = $this->controller->delete($this->routed('POST', '/organizer/venues/2/delete', '2'));
        $this->assertSame(404, $foreign->status());

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
        foreach (['/organizer/venues', '/organizer/venues/1', '/organizer/venues/1/delete'] as $uri) {
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

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $session->put('auth.user_id', 10);
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
            'map_url' => 'https://example.test/venue',
            'capacity' => '100',
        ];
    }
}
