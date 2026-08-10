<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\DatabaseBackupService;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class BackupScriptTest extends TestCase
{
    public function testDeploymentAssetsUseReplacementMarkersAndNoEmbeddedSecrets(): void
    {
        $paths = [
            'deploy/nginx/oems.conf', 'deploy/php-fpm/oems.conf',
            'deploy/systemd/oems-mail-outbox.service', 'deploy/systemd/oems-mail-outbox.timer',
            'deploy/systemd/oems-reminders.service', 'deploy/systemd/oems-reminders.timer',
            'deploy/systemd/oems-waitlists.service', 'deploy/systemd/oems-waitlists.timer',
            'deploy/systemd/oems-backup.service', 'deploy/systemd/oems-backup.timer',
        ];
        foreach ($paths as $path) {
            $contents = file_get_contents(base_path($path));
            $this->assertTrue(is_string($contents) && $contents !== '');
            $this->assertFalse(str_contains(strtolower($contents), 'password='));
        }
        $this->assertTrue(str_contains((string) file_get_contents(base_path('deploy/nginx/oems.conf')), '__APP_ROOT__'));
    }

    public function testBackupIsConfinedPrivateNonEmptyAndRetainedWithoutPasswordArguments(): void
    {
        $root = sys_get_temp_dir() . '/oems-backup-' . bin2hex(random_bytes(5));
        mkdir($root . '/storage/backups', 0775, true);
        $captured = [];
        $runner = static function (array $command, array $environment, string $destination) use (&$captured): void {
            $captured = [$command, $environment];
            $stream = gzopen($destination, 'wb9');
            gzwrite($stream, 'CREATE TABLE demo (id INT);');
            gzclose($stream);
        };
        $service = new DatabaseBackupService($root, $runner);
        putenv('MAIL_PASSWORD=must-not-reach-mysqldump');
        for ($index = 0; $index < 3; $index++) {
            $service->backup(['host' => '127.0.0.1', 'port' => 3306, 'database' => 'oems', 'username' => 'oems', 'password' => 'top-secret'], 2, new DateTimeImmutable('2026-08-10 12:00:0' . $index));
        }
        $files = glob($root . '/storage/backups/*.sql.gz') ?: [];
        $this->assertSame(2, count($files));
        $this->assertTrue(filesize($files[0]) > 0);
        $this->assertSame(0600, fileperms($files[0]) & 0777);
        $this->assertFalse(str_contains(implode(' ', $captured[0]), 'top-secret'));
        $this->assertTrue(in_array('--set-gtid-purged=OFF', $captured[0], true));
        $this->assertSame('top-secret', $captured[1]['MYSQL_PWD']);
        $this->assertFalse(array_key_exists('MAIL_PASSWORD', $captured[1]));
        putenv('MAIL_PASSWORD');
    }

    public function testBackupRejectsUnsafeDatabaseRetentionAndCleansFailedOutput(): void
    {
        $root = sys_get_temp_dir() . '/oems-backup-' . bin2hex(random_bytes(5));
        mkdir($root . '/storage/backups', 0775, true);
        $service = new DatabaseBackupService($root, static function (array $command, array $environment, string $destination): void {
            file_put_contents($destination, 'partial');
            throw new RuntimeException('dump failed');
        });
        $failed = false;
        try { $service->backup(['database' => '../other', 'password' => 'secret'], 31); } catch (RuntimeException) { $failed = true; }
        $this->assertTrue($failed);
        $this->assertSame([], glob($root . '/storage/backups/*') ?: []);

        try { $service->backup(['database' => 'oems', 'password' => 'secret'], 2); } catch (RuntimeException) { $failed = true; }
        $this->assertSame([], glob($root . '/storage/backups/*') ?: []);
    }

    public function testEmptyCompressedDumpIsRejectedAndRemoved(): void
    {
        $root = sys_get_temp_dir() . '/oems-backup-' . bin2hex(random_bytes(5));
        mkdir($root . '/storage/backups', 0775, true);
        $service = new DatabaseBackupService($root, static function (array $command, array $environment, string $destination): void {
            $stream = gzopen($destination, 'wb9');
            gzclose($stream);
        });
        $failed = false;
        try { $service->backup(['database' => 'oems', 'password' => 'secret'], 2); } catch (RuntimeException) { $failed = true; }
        $this->assertTrue($failed);
        $this->assertSame([], glob($root . '/storage/backups/*') ?: []);
    }
}
