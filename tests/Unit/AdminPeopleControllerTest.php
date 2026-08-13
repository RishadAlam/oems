<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminOrganizerController;
use OEMS\App\Controllers\AdminUserController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\AdminPeopleService;
use OEMS\App\Services\NotificationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeAdminPeopleRepository;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class AdminPeopleControllerTest extends TestCase
{
    private Session $session;

    private Security $security;

    private FakeAdminPeopleRepository $people;

    private AdminUserController $usersController;

    private AdminOrganizerController $organizersController;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/users';
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $users = $this->authenticatedUsers('super-admin', $this->session);
        $auth = new Auth($this->session, $users);
        $this->people = new FakeAdminPeopleRepository();
        $this->people->users = [
            10 => [
                'id' => 10,
                'name' => '<script>Participant</script>',
                'email' => 'participant@example.test',
                'role_name' => 'Participant',
                'role_slug' => 'participant',
                'status' => 'active',
                'email_verified_at' => '2026-08-01 09:00:00',
                'created_at' => '2026-08-01 09:00:00',
                'session_count' => 2,
                'registration_count' => 1,
                'password' => 'PRIVATE-PASSWORD-HASH',
            ],
            11 => [
                'id' => 11,
                'name' => 'Suspended Organizer',
                'email' => 'organizer@example.test',
                'role_name' => 'Organizer',
                'role_slug' => 'organizer',
                'status' => 'suspended',
                'email_verified_at' => '2026-08-01 09:00:00',
                'created_at' => '2026-08-02 09:00:00',
                'session_count' => 0,
                'registration_count' => 0,
            ],
            12 => [
                'id' => 12,
                'name' => 'Inactive Participant',
                'email' => 'inactive@example.test',
                'role_name' => 'Participant',
                'role_slug' => 'participant',
                'status' => 'inactive',
                'email_verified_at' => '2026-08-01 09:00:00',
                'created_at' => '2026-08-03 09:00:00',
                'session_count' => 0,
                'registration_count' => 0,
            ],
        ];
        $this->people->organizers = [
            20 => [
                'id' => 20,
                'user_id' => 11,
                'organization_name' => '<b>Community Events</b>',
                'name' => 'Suspended Organizer',
                'email' => 'organizer@example.test',
                'role_slug' => 'organizer',
                'approval_status' => 'pending',
                'rejection_reason' => null,
                'user_status' => 'active',
                'email_verified_at' => '2026-08-01 09:00:00',
                'created_at' => '2026-08-02 09:00:00',
                'event_count' => 0,
            ],
        ];
        $service = new AdminPeopleService(
            $this->people,
            new NotificationService(new FakeNotificationRepository()),
        );
        $dependencies = [
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            $auth,
            new Config(['name' => 'OEMS']),
            $this->people,
            $service,
        ];
        $this->usersController = new AdminUserController(...$dependencies);
        $this->organizersController = new AdminOrganizerController(...$dependencies);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testUserListAndDetailAreEscapedResponsiveAndActionExplicit(): void
    {
        $index = $this->usersController->index(Request::create(
            'GET',
            '/admin/users?role=participant',
            query: ['role' => 'participant', 'status' => 'active'],
        ));
        $show = $this->usersController->show($this->routed('GET', '/admin/users/10', '10'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), '&lt;script&gt;Participant&lt;/script&gt;'), 'User name was not escaped.');
        $this->assertTrue(str_contains($index->body(), 'data-label="Account"'), 'Mobile account label missing.');
        $this->assertTrue(str_contains($index->body(), 'class="result-summary'), 'User result summary missing.');
        $this->assertTrue(str_contains($index->body(), 'role="status"'), 'User result summary status role missing.');
        $this->assertTrue(str_contains($index->body(), '<span class="sr-only">3 matching users</span>'), 'User result count missing.');
        $this->assertTrue(str_contains($index->body(), 'Page 1 of 1'), 'User pagination summary missing.');
        $this->assertSame(200, $show->status());
        $this->assertTrue(str_contains($show->body(), 'Suspend account'), 'Suspend action missing.');
        $this->assertTrue(str_contains($show->body(), 'signs the user out'), 'Suspend consequence missing.');
        $this->assertTrue(str_contains($show->body(), 'name="expected_status" value="active"'), 'CAS field missing.');
        $this->assertFalse(str_contains($show->body(), 'PRIVATE-PASSWORD-HASH'));
    }

    public function testOrganizerListDetailAndRejectionFieldHaveAccessibleStates(): void
    {
        $index = $this->organizersController->index(Request::create('GET', '/admin/organizers'));
        $show = $this->organizersController->show($this->routed('GET', '/admin/organizers/20', '20'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), '&lt;b&gt;Community Events&lt;/b&gt;'));
        $this->assertTrue(str_contains($index->body(), 'data-label="Organization"'));
        $this->assertTrue(str_contains($index->body(), 'Page 1 of 1'));
        $this->assertSame(200, $show->status());
        $this->assertTrue(str_contains($show->body(), 'Ready to approve'));
        $this->assertTrue(str_contains($show->body(), 'Approve organizer'));
        $this->assertTrue(str_contains($show->body(), 'Reject organizer'));
        $this->assertTrue(str_contains($show->body(), 'maxlength="500"'));
        $this->assertTrue(str_contains($show->body(), 'aria-describedby="reason-help"'));

        $this->people->organizers[20]['user_status'] = 'suspended';
        $this->people->organizers[20]['email_verified_at'] = null;
        $this->people->organizers[20]['approval_status'] = 'approved';
        $ineligibleForApproval = $this->organizersController->show($this->routed('GET', '/admin/organizers/20', '20'));
        $this->assertTrue(str_contains($ineligibleForApproval->body(), 'Reject organizer'));
    }

    public function testPendingUnverifiedOrganizerShowsApprovalBlockersAndDisabledAction(): void
    {
        $this->people->organizers[20]['approval_status'] = 'pending';
        $this->people->organizers[20]['user_status'] = 'active';
        $this->people->organizers[20]['email_verified_at'] = null;

        $response = $this->organizersController->show($this->routed('GET', '/admin/organizers/20', '20'));
        $body = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($body, 'Approval blocked'));
        $this->assertTrue(str_contains($body, 'Email address verified'));
        $this->assertTrue(str_contains($body, 'Not completed'));
        $this->assertTrue(str_contains($body, 'aria-describedby="organizer-approval-readiness"'));
        $this->assertTrue(str_contains($body, 'type="button" disabled'));
        $this->assertFalse(str_contains($body, 'action="/admin/organizers/20/approve"'));
        $this->assertTrue(str_contains($body, 'Reject organizer'));
    }

    public function testEmptyListsHaveClearRecoveryCopy(): void
    {
        $this->people->users = [];
        $this->people->organizers = [];

        $users = $this->usersController->index(Request::create('GET', '/admin/users'));
        $organizers = $this->organizersController->index(Request::create('GET', '/admin/organizers'));

        $this->assertTrue(str_contains($users->body(), 'No users match these filters'));
        $this->assertTrue(str_contains($organizers->body(), 'No organizers match these filters'));
    }

    public function testMalformedDirectoryFiltersFailClosedInsteadOfShowingAllPeople(): void
    {
        $users = $this->usersController->index(Request::create(
            'GET',
            '/admin/users?role[]=participant',
            query: ['role' => ['participant']],
        ));
        $organizers = $this->organizersController->index(Request::create(
            'GET',
            '/admin/organizers?approval_status[]=pending',
            query: ['approval_status' => ['pending']],
        ));

        $this->assertTrue(str_contains($users->body(), 'No users match these filters'));
        $this->assertFalse(str_contains($users->body(), '&lt;script&gt;Participant&lt;/script&gt;'));
        $this->assertTrue(str_contains($organizers->body(), 'No organizers match these filters'));
        $this->assertFalse(str_contains($organizers->body(), '&lt;b&gt;Community Events&lt;/b&gt;'));
    }

    public function testActionsUseCasInputAndReturnConflictForStaleState(): void
    {
        $suspended = $this->usersController->suspend($this->routed('POST', '/admin/users/10/suspend', '10', [
            'expected_status' => 'active',
        ]));
        $this->assertSame('/admin/users/10', $suspended->header('Location'));
        $this->assertSame('suspended', $this->people->users[10]['status']);

        $stale = $this->usersController->suspend($this->routed('POST', '/admin/users/10/suspend', '10', [
            'expected_status' => 'active',
        ]));
        $this->assertSame(409, $stale->status());

        $approved = $this->organizersController->approve($this->routed('POST', '/admin/organizers/20/approve', '20', [
            'expected_status' => 'pending',
        ]));
        $this->assertSame('/admin/organizers/20', $approved->header('Location'));
        $this->assertSame('approved', $this->people->organizers[20]['approval_status']);

        $approvedPage = $this->organizersController->show($this->routed('GET', '/admin/organizers/20', '20'));
        $this->assertTrue(str_contains($approvedPage->body(), 'Reject organizer'));

        $replayed = $this->organizersController->approve($this->routed('POST', '/admin/organizers/20/approve', '20', [
            'expected_status' => 'pending',
        ]));
        $this->assertSame('/admin/organizers/20', $replayed->header('Location'));
        $this->assertSame(1, count($this->people->approvalChanges));

        $forgedReplay = $this->organizersController->approve($this->routed('POST', '/admin/organizers/20/approve', '20', [
            'expected_status' => 'compromised',
        ]));
        $this->assertSame(409, $forgedReplay->status());
    }

    public function testInactiveLifecycleHasExplicitActionsAndRoutes(): void
    {
        $active = $this->usersController->show($this->routed('GET', '/admin/users/10', '10'));
        $inactive = $this->usersController->show($this->routed('GET', '/admin/users/12', '12'));

        $this->assertTrue(str_contains($active->body(), 'Deactivate account'));
        $this->assertTrue(str_contains($inactive->body(), 'Reactivate account'));

        $forged = $this->usersController->reactivate($this->routed('POST', '/admin/users/12/reactivate', '12', [
            'expected_status' => 'suspended',
        ]));
        $this->assertSame(409, $forged->status());
        $this->assertSame('inactive', $this->people->users[12]['status']);

        $deactivated = $this->usersController->deactivate($this->routed('POST', '/admin/users/10/deactivate', '10', [
            'expected_status' => 'active',
        ]));
        $reactivated = $this->usersController->reactivate($this->routed('POST', '/admin/users/12/reactivate', '12', [
            'expected_status' => 'inactive',
        ]));

        $this->assertSame('/admin/users/10', $deactivated->header('Location'));
        $this->assertSame('inactive', $this->people->users[10]['status']);
        $this->assertSame('/admin/users/12', $reactivated->header('Location'));
        $this->assertSame('active', $this->people->users[12]['status']);
    }

    public function testInvalidReasonPreservesOnlyReasonAndAssociatesError(): void
    {
        $response = $this->organizersController->reject($this->routed('POST', '/admin/organizers/20/reject', '20', [
            'expected_status' => 'pending',
            'reason' => '',
            'password' => 'must-not-survive',
        ]));

        $this->assertSame('/admin/organizers/20', $response->header('Location'));
        $this->assertArrayHasKey('reason', $this->session->get('_flash.errors', []));
        $this->assertFalse(array_key_exists('password', $this->session->get('_flash.old', [])));
        $rendered = $this->organizersController->show($this->routed('GET', '/admin/organizers/20', '20'));
        $this->assertTrue(str_contains($rendered->body(), 'id="reason-error"'));
        $this->assertTrue(str_contains($rendered->body(), 'aria-invalid="true"'));
    }

    public function testMalformedAndMissingIdsAreNotFound(): void
    {
        foreach (['999', '0', '-1', 'person'] as $id) {
            $this->assertSame(404, $this->usersController->show($this->routed('GET', '/admin/users/' . $id, $id))->status());
            $this->assertSame(404, $this->organizersController->show($this->routed('GET', '/admin/organizers/' . $id, $id))->status());
        }
    }

    public function testEveryPeopleRouteRequiresAdministratorPostsRequireCsrfAndWrongMethodIsRejected(): void
    {
        foreach (['/admin/users', '/admin/users/10', '/admin/organizers', '/admin/organizers/20'] as $uri) {
            $router = $this->routerForRole('organizer');
            $this->assertSame(403, $router['router']->dispatch(Request::create('GET', $uri))->status());
        }

        foreach (['/admin/users/10/suspend', '/admin/users/10/deactivate', '/admin/users/11/reactivate', '/admin/organizers/20/approve', '/admin/organizers/20/reject'] as $uri) {
            $router = $this->routerForRole('super-admin');
            $this->assertSame(419, $router['router']->dispatch(Request::create('POST', $uri, input: ['_token' => 'invalid']))->status());
            $this->assertSame(405, $router['router']->dispatch(Request::create('GET', $uri))->status());
        }
    }

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = $this->authenticatedUsers($role, $session);
        $auth = new Auth($session, $users);
        $container = new Container();
        $container->instance(AdminUserController::class, $this->usersController);
        $container->instance(AdminOrganizerController::class, $this->organizersController);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function authenticatedUsers(string $role, Session $session): FakeUserRepository
    {
        $users = new FakeUserRepository();
        $users->users[99] = [
            'id' => 99,
            'role_id' => $role === 'super-admin' ? 1 : 2,
            'name' => 'Route Administrator',
            'email' => 'admin@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];
        $this->authenticateSession($session, $users, 99);

        return $users;
    }

    private function routed(string $method, string $uri, string $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input, headers: [
            'User-Agent' => 'Admin browser',
        ], server: ['REMOTE_ADDR' => '203.0.113.21'])->withRouteParameters(['id' => $id]);
    }
}
