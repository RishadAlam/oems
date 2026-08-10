<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Middleware\MaintenanceMiddleware;
use OEMS\App\Services\MaintenanceService;
use OEMS\Core\Auth;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakePlatformSettingsRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class MaintenanceMiddlewareTest extends TestCase
{
    public function testMaintenanceBlocksPublicButAllowsHealthLoginAndSuperAdmin(): void
    {
        $_SESSION = [];
        $cache = sys_get_temp_dir() . '/oems-maintenance-' . bin2hex(random_bytes(5)) . '.json';
        $settings = new FakePlatformSettingsRepository(['maintenance_mode' => '1']);
        $service = new MaintenanceService($settings, $cache, static fn (): int => 1000);
        $session = new Session(false);
        $users = new FakeUserRepository();
        $auth = new Auth($session, $users);
        $middleware = new MaintenanceMiddleware($service, $auth, new View(base_path('app/Views')));
        $next = static fn (): Response => Response::text('next');

        $this->assertSame(200, $middleware->handle(Request::create('GET', '/health/live'), $next)->status());
        $this->assertSame(0, $settings->privateReadCalls);
        $blocked = $middleware->handle(Request::create('GET', '/events'), $next);
        $this->assertSame(503, $blocked->status());
        $this->assertSame('300', $blocked->header('Retry-After'));
        $this->assertTrue(str_contains($blocked->body(), 'temporarily unavailable'));
        $this->assertSame(200, $middleware->handle(Request::create('GET', '/login'), $next)->status());

        $users->users[1] = ['id' => 1, 'role_id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => password_hash('AdminPass!2026', PASSWORD_DEFAULT), 'status' => 'active'];
        $this->authenticateSession($session, $users, 1);
        $this->assertSame(200, $middleware->handle(Request::create('GET', '/admin/operations'), $next)->status());
        @unlink($cache);
        $_SESSION = [];
    }

    public function testStateUpdatesInvalidateThePrivateCacheAndAreAudited(): void
    {
        $cache = sys_get_temp_dir() . '/oems-maintenance-' . bin2hex(random_bytes(5)) . '.json';
        $settings = new FakePlatformSettingsRepository(['maintenance_mode' => '0']);
        $service = new MaintenanceService($settings, $cache, static fn (): int => 1000);
        $this->assertFalse($service->isEnabled());
        $service->setEnabled(true, 77);
        $this->assertTrue($service->isEnabled());
        $this->assertSame([77, true], $settings->maintenanceUpdates[0]);
        @unlink($cache);
    }

    public function testExpiredEnabledCacheFailsClosedWhenSettingsAreTemporarilyUnavailable(): void
    {
        $now = 1000;
        $cache = sys_get_temp_dir() . '/oems-maintenance-' . bin2hex(random_bytes(5)) . '.json';
        $settings = new FakePlatformSettingsRepository(['maintenance_mode' => '1']);
        $service = new MaintenanceService($settings, $cache, static function () use (&$now): int { return $now; });
        $this->assertTrue($service->isEnabled());
        $settings->failRead = true;
        $now = 1010;
        $this->assertTrue($service->isEnabled());
        @unlink($cache);
    }
}
