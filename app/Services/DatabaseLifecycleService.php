<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use PDO;
use Throwable;

final class DatabaseLifecycleService
{
    /** @var list<array{name: string, up: string, down: ?string}> */
    private array $migrations;

    /**
     * @param list<array{name: string, up: string, down: ?string}> $migrations
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $driver,
        private readonly string $schemaPath,
        private readonly string $baseSeedPath,
        private readonly string $demoSeedPath,
        array $migrations,
        private readonly string $environment,
    ) {
        $names = [];
        foreach ($migrations as $migration) {
            $name = trim((string) ($migration['name'] ?? ''));
            $up = (string) ($migration['up'] ?? '');
            $down = $migration['down'] ?? null;
            if ($name === '' || $up === '' || ($down !== null && !is_string($down))) {
                throw new DatabaseLifecycleException('Migration manifest contains an invalid entry.');
            }
            if (isset($names[$name])) {
                throw new DatabaseLifecycleException('Migration manifest contains a duplicate name.');
            }
            $names[$name] = true;
        }

        $this->migrations = $migrations;
    }

    /** @return array{message: string, migrations: list<string>} */
    public function migrate(): array
    {
        $isFresh = $this->applicationTables() === [];
        $this->ensureHistoryTable();

        if ($isFresh) {
            $this->executeFile($this->schemaPath);
            $this->ensureHistoryTable();
            foreach ($this->migrations as $migration) {
                $this->recordMigration($migration, 0);
            }

            return ['message' => 'Initialized the canonical schema. No pending migrations.', 'migrations' => []];
        }

        $applied = $this->appliedMigrations();
        $this->verifyChecksums($applied);
        $batch = $this->nextBatch();
        $completed = [];

        foreach ($this->migrations as $migration) {
            if (isset($applied[$migration['name']])) {
                continue;
            }

            $this->executeFile($migration['up']);
            $this->recordMigration($migration, $migration['down'] === null ? 0 : $batch);
            $completed[] = $migration['name'];
        }

        return [
            'message' => $completed === []
                ? 'No pending migrations.'
                : sprintf('Applied %d migration%s.', count($completed), count($completed) === 1 ? '' : 's'),
            'migrations' => $completed,
        ];
    }

    /** @return array{message: string, migrations: list<string>} */
    public function rollback(bool $force = false): array
    {
        if ($this->isProduction() && !$force) {
            throw new DatabaseLifecycleException('Production rollback requires --force.');
        }

        $this->ensureHistoryTable();
        $applied = $this->appliedMigrations();
        $this->verifyChecksums($applied);
        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) FROM oems_migrations WHERE batch > 0')->fetchColumn();

        if ($batch === 0) {
            return ['message' => 'No reversible migration batch to roll back.', 'migrations' => []];
        }

        $batchRows = $this->pdo->prepare('SELECT migration FROM oems_migrations WHERE batch = :batch');
        $batchRows->execute(['batch' => $batch]);
        $names = array_fill_keys($batchRows->fetchAll(PDO::FETCH_COLUMN), true);
        $rolledBack = [];

        foreach (array_reverse($this->migrations) as $migration) {
            if (!isset($names[$migration['name']])) {
                continue;
            }
            if ($migration['down'] === null) {
                throw new DatabaseLifecycleException('The latest migration batch is not reversible.');
            }

            $this->executeFile($migration['down']);
            $statement = $this->pdo->prepare('DELETE FROM oems_migrations WHERE migration = :migration AND batch = :batch');
            $statement->execute(['migration' => $migration['name'], 'batch' => $batch]);
            $rolledBack[] = $migration['name'];
        }

        if (count($rolledBack) !== count($names)) {
            throw new DatabaseLifecycleException('Migration history does not match the current manifest.');
        }

        return [
            'message' => sprintf('Rolled back %d migration%s.', count($rolledBack), count($rolledBack) === 1 ? '' : 's'),
            'migrations' => $rolledBack,
        ];
    }

    /** @return array{message: string, migrations: list<string>} */
    public function refresh(bool $force): array
    {
        if (!$force) {
            throw new DatabaseLifecycleException('Database refresh is destructive. Re-run with --force.');
        }

        $this->dropAllTables();
        $this->executeFile($this->schemaPath);
        $this->ensureHistoryTable();
        foreach ($this->migrations as $migration) {
            $this->recordMigration($migration, 0);
        }
        $this->executeFile($this->baseSeedPath);
        $this->executeFile($this->demoSeedPath);

        return ['message' => 'Database refreshed with base and fake data.', 'migrations' => []];
    }

    /** @return array{message: string, migrations: list<string>} */
    public function seedDemo(): array
    {
        if ($this->isProduction()) {
            throw new DatabaseLifecycleException('Fake data cannot be seeded in production.');
        }
        if (!$this->tableExists('roles')) {
            throw new DatabaseLifecycleException('Database schema is missing. Run db:migrate first.');
        }

        $baseLoaded = (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() > 0;
        if (!$baseLoaded) {
            $this->executeFile($this->baseSeedPath);
        }
        $this->executeFile($this->demoSeedPath);

        return [
            'message' => $baseLoaded ? 'Fake data seeded.' : 'Base and fake data seeded.',
            'migrations' => [],
        ];
    }

    private function ensureHistoryTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS oems_migrations ('
            . 'migration VARCHAR(255) PRIMARY KEY, '
            . 'batch INTEGER NOT NULL, '
            . 'checksum CHAR(64) NOT NULL, '
            . 'reversible INTEGER NOT NULL DEFAULT 0, '
            . 'applied_at VARCHAR(19) NOT NULL'
            . ')',
        );
    }

    /** @return array<string, array{checksum: string, batch: int}> */
    private function appliedMigrations(): array
    {
        $rows = $this->pdo->query('SELECT migration, checksum, batch FROM oems_migrations')->fetchAll();
        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['migration']] = [
                'checksum' => (string) $row['checksum'],
                'batch' => (int) $row['batch'],
            ];
        }

        return $applied;
    }

    /** @param array<string, array{checksum: string, batch: int}> $applied */
    private function verifyChecksums(array $applied): void
    {
        $manifest = [];
        foreach ($this->migrations as $migration) {
            $manifest[$migration['name']] = $migration;
        }

        foreach ($applied as $name => $row) {
            if (!isset($manifest[$name])) {
                throw new DatabaseLifecycleException('Applied migration is missing from the manifest.');
            }
            if (!hash_equals($row['checksum'], $this->checksum($manifest[$name]['up']))) {
                throw new DatabaseLifecycleException(sprintf('Applied migration checksum changed: %s.', $name));
            }
        }
    }

    /** @param array{name: string, up: string, down: ?string} $migration */
    private function recordMigration(array $migration, int $batch): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO oems_migrations (migration, batch, checksum, reversible, applied_at) '
            . 'VALUES (:migration, :batch, :checksum, :reversible, :applied_at)',
        );
        $statement->execute([
            'migration' => $migration['name'],
            'batch' => $batch,
            'checksum' => $this->checksum($migration['up']),
            'reversible' => $migration['down'] === null ? 0 : 1,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function checksum(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new DatabaseLifecycleException('Migration SQL file is missing or unreadable.');
        }
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum)) {
            throw new DatabaseLifecycleException('Migration SQL checksum could not be calculated.');
        }

        return $checksum;
    }

    private function executeFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new DatabaseLifecycleException('Database SQL file is missing or unreadable.');
        }
        $sql = file_get_contents($path);
        if (!is_string($sql) || trim($sql) === '') {
            throw new DatabaseLifecycleException('Database SQL file is empty.');
        }

        try {
            $this->pdo->exec($sql);
        } catch (Throwable $exception) {
            throw new DatabaseLifecycleException('Database SQL execution failed for ' . basename($path) . '.', previous: $exception);
        }
    }

    /** @return list<string> */
    private function applicationTables(): array
    {
        if ($this->driver === 'sqlite') {
            $statement = $this->pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'oems_migrations'",
            );
        } else {
            $statement = $this->pdo->query(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'oems_migrations'",
            );
        }

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, $this->applicationTables(), true);
    }

    private function nextBatch(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM oems_migrations WHERE batch > 0')->fetchColumn();
    }

    private function dropAllTables(): void
    {
        $tables = $this->applicationTables();
        if ($this->tableExists('oems_migrations') || $this->historyTableExists()) {
            $tables[] = 'oems_migrations';
        }

        try {
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = OFF');
                foreach (array_unique($tables) as $table) {
                    $this->pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '"');
                }
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                return;
            }

            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (array_unique($tables) as $table) {
                $this->pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $exception) {
            throw new DatabaseLifecycleException('Database refresh could not clear the existing schema.', previous: $exception);
        }
    }

    private function historyTableExists(): bool
    {
        if ($this->driver === 'sqlite') {
            $statement = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'oems_migrations'");
        } else {
            $statement = $this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oems_migrations'");
        }

        return (int) $statement->fetchColumn() > 0;
    }

    private function isProduction(): bool
    {
        return strtolower(trim($this->environment)) === 'production';
    }
}
