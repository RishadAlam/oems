<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminCategoryController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\CategoryService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class AdminCategoryControllerTest extends TestCase
{
    private Session $session;

    private Security $security;

    private FakeCategoryRepository $categories;

    private AdminCategoryController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/categories';
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $auth = new Auth($this->session, $this->users('super-admin'));
        $this->categories = new FakeCategoryRepository();
        $this->controller = new AdminCategoryController(
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            $auth,
            new Config(['name' => 'OEMS']),
            $this->categories,
            new CategoryService($this->categories),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testAdministratorCanRenderCategoryIndexCreateAndEditPages(): void
    {
        $index = $this->controller->index(Request::create('GET', '/admin/categories'));
        $create = $this->controller->create(Request::create('GET', '/admin/categories/create'));
        $edit = $this->controller->edit($this->routed('GET', '/admin/categories/1/edit', '1'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), 'Technology'));
        $this->assertTrue(str_contains($index->body(), 'Archived'));
        $this->assertTrue(str_contains($index->body(), 'href="/admin/events"'));
        $this->assertTrue(str_contains($index->body(), 'href="/admin/categories"'));
        $this->assertSame(200, $create->status());
        $this->assertTrue(str_contains($create->body(), 'Create category'));
        $this->assertSame(200, $edit->status());
        $this->assertTrue(str_contains($edit->body(), 'Edit category'));
    }

    public function testDuplicateCategorySlugRedirectsWithErrorsAndDoesNotPersist(): void
    {
        $response = $this->controller->store(Request::create('POST', '/admin/categories', input: [
            'name' => 'Technology Meetups',
            'slug' => ' TECHNOLOGY ',
            'sort_order' => '4',
        ]));

        $this->assertSame(302, $response->status());
        $this->assertSame('/admin/categories/create', $response->header('Location'));
        $this->assertArrayHasKey('slug', $this->session->get('_flash.errors', []));
        $this->assertSame(2, count($this->categories->categories));
    }

    public function testInvalidCategoryFlashesOnlyWhitelistedScalarOldInput(): void
    {
        $response = $this->controller->store(Request::create('POST', '/admin/categories', input: [
            'name' => ['unsafe'],
            'slug' => 'new-category',
            'sort_order' => ['unsafe'],
            'is_active' => '0',
            'owner_id' => '99',
        ]));
        $old = $this->session->get('_flash.old', []);

        $this->assertSame('/admin/categories/create', $response->header('Location'));
        $this->assertArrayHasKey('name', $this->session->get('_flash.errors', []));
        $this->assertFalse(array_key_exists('name', $old));
        $this->assertFalse(array_key_exists('sort_order', $old));
        $this->assertFalse(array_key_exists('is_active', $old));
        $this->assertFalse(array_key_exists('owner_id', $old));
    }

    public function testCreateUpdateAndExplicitActivationStateUseClearRedirects(): void
    {
        $create = $this->controller->store(Request::create('POST', '/admin/categories', input: [
            'name' => 'Community Learning',
            'slug' => 'community-learning',
            'description' => 'Events for community groups.',
            'icon' => 'users-three',
            'sort_order' => '8',
        ]));
        $createdId = max(array_keys($this->categories->categories));

        $this->assertSame('/admin/categories', $create->header('Location'));
        $this->assertSame('Category created.', $this->session->get('_flash.success'));
        $this->assertSame(1, $this->categories->categories[$createdId]['is_active']);

        $update = $this->controller->update($this->routed('POST', '/admin/categories/1', '1', [
            'name' => 'Technology and Innovation',
            'slug' => 'technology-innovation',
            'sort_order' => '2',
        ]));
        $this->assertSame('/admin/categories', $update->header('Location'));
        $this->assertSame('Category updated.', $this->session->get('_flash.success'));

        $deactivate = $this->controller->setActive($this->routed(
            'POST',
            '/admin/categories/1/status',
            '1',
            ['is_active' => '0'],
        ));
        $this->assertSame('/admin/categories', $deactivate->header('Location'));
        $this->assertSame('Category deactivated.', $this->session->get('_flash.success'));
        $this->assertSame(0, $this->categories->categories[1]['is_active']);
    }

    public function testMalformedAndMissingCategoryIdsReturnNotFoundWithoutMutation(): void
    {
        foreach (['999', '0', '-1', 'category'] as $id) {
            $response = $this->controller->edit($this->routed('GET', '/admin/categories/' . $id . '/edit', $id));
            $this->assertSame(404, $response->status());
        }

        $response = $this->controller->setActive($this->routed(
            'POST',
            '/admin/categories/0/status',
            '0',
            ['is_active' => '0'],
        ));
        $this->assertSame(404, $response->status());
        $this->assertSame(1, $this->categories->categories[1]['is_active']);
    }

    public function testRepositoryFailureIsVisibleOnTheCategoryForm(): void
    {
        $this->categories->failCreate = true;
        $response = $this->controller->store(Request::create('POST', '/admin/categories', input: [
            'name' => 'Community Learning',
            'slug' => 'community-learning',
            'sort_order' => '0',
        ]));
        $rendered = $this->controller->create(Request::create('GET', '/admin/categories/create'));

        $this->assertSame('/admin/categories/create', $response->header('Location'));
        $this->assertTrue(str_contains($rendered->body(), 'The category could not be created.'));
        $this->assertTrue(str_contains($rendered->body(), 'role="alert"'));
    }

    public function testEveryCategoryRouteRequiresSuperAdministratorRoleAndPostsRequireCsrf(): void
    {
        foreach (['/admin/categories', '/admin/categories/create', '/admin/categories/1/edit'] as $uri) {
            $router = $this->routerForRole('organizer');
            $this->assertSame(403, $router['router']->dispatch(Request::create('GET', $uri))->status());
        }

        foreach (['/admin/categories', '/admin/categories/1', '/admin/categories/1/status'] as $uri) {
            $organizer = $this->routerForRole('organizer');
            $blockedRole = $organizer['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => $organizer['security']->csrfToken(),
            ]));
            $this->assertSame(403, $blockedRole->status());

            $administrator = $this->routerForRole('super-admin');
            $blockedCsrf = $administrator['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => 'invalid',
            ]));
            $this->assertSame(419, $blockedCsrf->status());
        }
    }

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = $this->users($role, $session);
        $auth = new Auth($session, $users);
        $container = new Container();
        $container->instance(AdminCategoryController::class, $this->controller);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function users(string $role, ?Session $session = null): FakeUserRepository
    {
        $session ??= $this->session;
        $users = new FakeUserRepository();
        $users->users[99] = [
            'id' => 99,
            'role_id' => $role === 'super-admin' ? 1 : 2,
            'name' => 'Route Administrator',
            'email' => 'admin@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-06 10:00:00',
        ];
        $this->authenticateSession($session, $users, 99);

        return $users;
    }

    private function routed(string $method, string $uri, string $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input)->withRouteParameters(['id' => $id]);
    }
}
