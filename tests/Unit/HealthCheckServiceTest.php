<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\HealthCheckService;
use OEMS\Tests\Support\TestCase;
use PDO;

final class HealthCheckServiceTest extends TestCase
{
    public function testHttpEntrypointSkipsAuthenticationForBothHealthEndpoints(): void
    {
        $entrypoint = file_get_contents(base_path('public/index.php'));
        $this->assertTrue(is_string($entrypoint));
        $guard = strpos($entrypoint, '$healthRequest');
        $auth = strpos($entrypoint, '$auth =');
        $this->assertTrue(is_int($guard) && is_int($auth) && $guard < $auth);
        $this->assertTrue(str_contains($entrypoint, "['/health/live', '/health/ready']"));
        $this->assertTrue(str_contains($entrypoint, 'if (!$healthRequest)'));
    }

    public function testLivenessIsProcessOnlyAndReadinessIsSanitized(): void
    {
        $root = sys_get_temp_dir() . '/oems-health-' . bin2hex(random_bytes(5));
        foreach (['storage/cache', 'storage/logs', 'storage/tickets', 'storage/backups'] as $path) {
            mkdir($root . '/' . $path, 0775, true);
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE mail_outbox (id INTEGER, template TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE newsletter (id INTEGER, confirmation_token_hash TEXT)');
        $pdo->exec('CREATE TABLE coupons (id INTEGER, code TEXT)');
        $connectionCalls = 0;
        $service = new HealthCheckService(static function () use ($pdo, &$connectionCalls): PDO {
            $connectionCalls++;
            return $pdo;
        }, $root);

        $this->assertSame(['status' => 'ok'], $service->live());
        $this->assertSame(0, $connectionCalls);
        $ready = $service->ready();
        $this->assertSame(1, $connectionCalls);
        $this->assertSame('ok', $ready['status']);
        $this->assertSame(['database' => true, 'schema' => true, 'storage' => true], $ready['checks']);
        $this->assertFalse(str_contains(json_encode($ready, JSON_THROW_ON_ERROR), $root));

        $pdo->exec('DROP TABLE mail_outbox');
        $failed = $service->ready();
        $this->assertSame('unavailable', $failed['status']);
        $this->assertFalse($failed['checks']['schema']);
    }
}
