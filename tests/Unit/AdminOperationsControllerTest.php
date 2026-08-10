<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminOperationsController;
use OEMS\App\Services\HealthCheckService;
use OEMS\App\Services\MaintenanceService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakePlatformSettingsRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class AdminOperationsControllerTest extends TestCase
{
    public function testRoutesEnforceRoleCsrfMethodAndExposeNoRestoreSurface(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertTrue(is_string($routes));
        $this->assertTrue(str_contains($routes, "get('/health/live'"));
        $this->assertTrue(str_contains($routes, "get('/health/ready'"));
        $this->assertTrue(str_contains($routes, "get('/admin/operations'"));
        $this->assertTrue(str_contains($routes, "post('/admin/operations/maintenance'"));
        $this->assertTrue(str_contains($routes, "['role:super-admin', 'csrf']"));
        $this->assertFalse(str_contains($routes, '/restore'));
    }

    public function testOperationsPageAndConfirmationBoundToggleAreTruthful(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/operations';
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[9] = ['id' => 9, 'role_id' => 1, 'name' => 'Ops Admin', 'email' => 'ops@example.test', 'password' => password_hash('AdminPass!2026', PASSWORD_DEFAULT), 'status' => 'active'];
        $this->authenticateSession($session, $users, 9);
        $auth = new Auth($session, $users);
        $settings = new FakePlatformSettingsRepository(['maintenance_mode' => '0']);
        $maintenance = new MaintenanceService($settings, sys_get_temp_dir() . '/oems-ops-' . bin2hex(random_bytes(4)) . '.json');
        $pdo = new PDO('sqlite::memory:');
        $health = new HealthCheckService(static fn (): PDO => $pdo, base_path());
        $controller = new AdminOperationsController(new View(base_path('app/Views')), $session, new Security($session), $auth, new Config(['name' => 'OEMS']), $maintenance, $health);

        $page = $controller->index(Request::create('GET', '/admin/operations'));
        $this->assertSame(200, $page->status());
        $this->assertTrue(str_contains($page->body(), 'Operations'));
        $invalid = $controller->updateMaintenance(Request::create('POST', '/admin/operations/maintenance', input: ['enabled' => '1', 'confirmation' => 'wrong']));
        $this->assertSame(422, $invalid->status());
        $this->assertFalse($maintenance->isEnabled());
        $valid = $controller->updateMaintenance(Request::create('POST', '/admin/operations/maintenance', input: ['enabled' => '1', 'confirmation' => 'ENABLE MAINTENANCE']));
        $this->assertSame('/admin/operations', $valid->header('Location'));
        $this->assertTrue($maintenance->isEnabled());
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }
}
