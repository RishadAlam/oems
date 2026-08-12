<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\DatabaseLifecycleService;
use OEMS\Tests\Support\TestCase;
use PDO;
use RuntimeException;

final class DatabaseLifecycleServiceTest extends TestCase
{
    private string $root;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/oems-database-lifecycle-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/migrations', 0775, true);
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->write('schema.sql', implode("\n", [
            'DROP TABLE IF EXISTS demo_records;',
            'DROP TABLE IF EXISTS migration_effects;',
            'DROP TABLE IF EXISTS roles;',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT NOT NULL);',
            'CREATE TABLE migration_effects (name TEXT PRIMARY KEY);',
            'CREATE TABLE demo_records (id INTEGER PRIMARY KEY, name TEXT NOT NULL);',
        ]));
        $this->write('seed.sql', "INSERT INTO roles (id, name) VALUES (1, 'Administrator');");
        $this->write('demo_seed.sql', "INSERT OR REPLACE INTO demo_records (id, name) VALUES (1, 'Fake participant');");
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }

    public function testFreshMigrationUsesCanonicalSchemaAndRecordsHistoricalFilesAsBaseline(): void
    {
        $up = $this->write('migrations/legacy.sql', "INSERT INTO migration_effects (name) VALUES ('must-not-run');");
        $result = $this->service([
            ['name' => 'legacy', 'up' => $up, 'down' => null],
        ])->migrate();

        $this->assertSame('Initialized the canonical schema. No pending migrations.', $result['message']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM migration_effects')->fetchColumn());
        $history = $this->pdo->query('SELECT migration, batch, reversible FROM oems_migrations')->fetch();
        $this->assertSame('legacy', $history['migration']);
        $this->assertSame(0, (int) $history['batch']);
        $this->assertSame(0, (int) $history['reversible']);
    }

    public function testExistingDatabaseUsesManifestOrderAndRejectsChangedAppliedSql(): void
    {
        $this->pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT NOT NULL); CREATE TABLE migration_effects (name TEXT PRIMARY KEY);');
        $first = $this->write('migrations/z-first.sql', "INSERT INTO migration_effects (name) VALUES ('first');");
        $second = $this->write('migrations/a-second.sql', "INSERT INTO migration_effects (name) VALUES ('second');");
        $service = $this->service([
            ['name' => 'z-first', 'up' => $first, 'down' => null],
            ['name' => 'a-second', 'up' => $second, 'down' => null],
        ]);

        $result = $service->migrate();

        $this->assertSame(['z-first', 'a-second'], $result['migrations']);
        $this->assertSame(
            ['first', 'second'],
            $this->pdo->query('SELECT name FROM migration_effects ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN),
        );
        $this->assertSame([0, 0], array_map('intval', $this->pdo->query('SELECT batch FROM oems_migrations ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN)));

        file_put_contents($first, "INSERT INTO migration_effects (name) VALUES ('changed');");
        $failed = false;
        try {
            $service->migrate();
        } catch (RuntimeException $exception) {
            $failed = str_contains($exception->getMessage(), 'checksum') && !str_contains($exception->getMessage(), 'INSERT');
        }
        $this->assertTrue($failed, 'Changed applied SQL must fail with a safe checksum message.');
    }

    public function testRollbackReversesOnlyTheLatestPositiveBatch(): void
    {
        $this->pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT NOT NULL); CREATE TABLE migration_effects (name TEXT PRIMARY KEY);');
        $firstUp = $this->write('migrations/001-first.up.sql', "INSERT INTO migration_effects (name) VALUES ('first-up');");
        $firstDown = $this->write('migrations/001-first.down.sql', "INSERT INTO migration_effects (name) VALUES ('first-down');");
        $secondUp = $this->write('migrations/002-second.up.sql', "INSERT INTO migration_effects (name) VALUES ('second-up');");
        $secondDown = $this->write('migrations/002-second.down.sql', "INSERT INTO migration_effects (name) VALUES ('second-down');");
        $service = $this->service([
            ['name' => '001-first', 'up' => $firstUp, 'down' => $firstDown],
            ['name' => '002-second', 'up' => $secondUp, 'down' => $secondDown],
        ]);

        $service->migrate();
        $result = $service->rollback();

        $this->assertSame(['002-second', '001-first'], $result['migrations']);
        $this->assertSame(
            ['first-up', 'second-up', 'second-down', 'first-down'],
            $this->pdo->query('SELECT name FROM migration_effects ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN),
        );
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM oems_migrations')->fetchColumn());
        $this->assertSame('No reversible migration batch to roll back.', $service->rollback()['message']);
    }

    public function testRefreshRequiresForceThenRebuildsAndSeedsTheDatabase(): void
    {
        $this->pdo->exec('CREATE TABLE stale_data (id INTEGER PRIMARY KEY); INSERT INTO stale_data (id) VALUES (1);');
        $service = $this->service([]);

        $failed = false;
        try {
            $service->refresh(false);
        } catch (RuntimeException $exception) {
            $failed = str_contains($exception->getMessage(), '--force');
        }
        $this->assertTrue($failed);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM stale_data')->fetchColumn());

        $result = $service->refresh(true);

        $this->assertSame('Database refreshed with base and fake data.', $result['message']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        $this->assertSame('Fake participant', $this->pdo->query('SELECT name FROM demo_records')->fetchColumn());
    }

    public function testLocalSeedLoadsBaseOnceAndProductionSeedNeverWrites(): void
    {
        $local = $this->service([]);
        $local->migrate();
        $local->seedDemo();
        $local->seedDemo();

        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM demo_records')->fetchColumn());

        $this->pdo->exec('DELETE FROM demo_records');
        $failed = false;
        try {
            $this->service([], 'production')->seedDemo();
        } catch (RuntimeException $exception) {
            $failed = str_contains($exception->getMessage(), 'production');
        }
        $this->assertTrue($failed);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM demo_records')->fetchColumn());
    }

    private function service(array $migrations, string $environment = 'local'): DatabaseLifecycleService
    {
        return new DatabaseLifecycleService(
            $this->pdo,
            'sqlite',
            $this->root . '/schema.sql',
            $this->root . '/seed.sql',
            $this->root . '/demo_seed.sql',
            $migrations,
            $environment,
        );
    }

    private function write(string $path, string $contents): string
    {
        $absolute = $this->root . '/' . $path;
        $directory = dirname($absolute);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($absolute, $contents . PHP_EOL);

        return $absolute;
    }
}
