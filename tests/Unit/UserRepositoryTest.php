<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\UserRepository;
use OEMS\Core\Database;
use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use PDOStatement;

final class UserRepositoryRecordingPdo extends PDO
{
    public array $preparedQueries = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedQueries[] = $query;

        return parent::prepare($query, $options);
    }
}

final class UserRepositoryTest extends TestCase
{
    private UserRepositoryRecordingPdo $connection;

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        $this->connection = new UserRepositoryRecordingPdo('sqlite::memory:');
        $this->configureConnection($this->connection);
        $this->createSchema($this->connection);
        $this->seedUser($this->connection);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testPasswordResetConsumptionUpdatesAndRevokesExactlyOnce(): void
    {
        $tokenHash = hash('sha256', 'reset-token');
        $this->insertReset($this->connection, $tokenHash);
        $this->insertRememberSession($this->connection, 'aaaaaaaaaaaaaaaaaaaaaaaa');
        $repository = $this->repository($this->connection);
        $newPasswordHash = password_hash('new-secure-password', PASSWORD_DEFAULT);

        $first = $repository->resetPasswordUsingToken(
            $tokenHash,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            $newPasswordHash,
        );
        $replay = $repository->resetPasswordUsingToken(
            $tokenHash,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            password_hash('replayed-password', PASSWORD_DEFAULT),
        );

        $this->assertSame(1, (int) ($first['user_id'] ?? 0));
        $this->assertNull($replay);
        $this->assertSame($newPasswordHash, $this->passwordForUser($this->connection));
        $this->assertSame(0, $this->countRows($this->connection, 'password_resets'));
        $this->assertSame(0, $this->countRows($this->connection, 'sessions'));
    }

    public function testPasswordResetRollbackRestoresTokenPasswordAndRememberSessions(): void
    {
        $tokenHash = hash('sha256', 'rollback-token');
        $this->insertReset($this->connection, $tokenHash);
        $this->insertRememberSession($this->connection, 'bbbbbbbbbbbbbbbbbbbbbbbb');
        $oldPasswordHash = $this->passwordForUser($this->connection);
        $this->connection->exec(
            "CREATE TRIGGER reject_password_update
             BEFORE UPDATE OF password ON users
             BEGIN SELECT RAISE(ABORT, 'password update rejected'); END",
        );
        $thrown = false;

        try {
            $this->repository($this->connection)->resetPasswordUsingToken(
                $tokenHash,
                new DateTimeImmutable('2026-08-10 12:00:00'),
                password_hash('new-secure-password', PASSWORD_DEFAULT),
            );
        } catch (PDOException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame($oldPasswordHash, $this->passwordForUser($this->connection));
        $this->assertSame(1, $this->countRows($this->connection, 'password_resets'));
        $this->assertSame(1, $this->countRows($this->connection, 'sessions'));
    }

    public function testPasswordResetQueriesUseUniqueBindingsWithNativePrepares(): void
    {
        $tokenHash = hash('sha256', 'native-token');
        $this->insertReset($this->connection, $tokenHash);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $before = count($this->connection->preparedQueries);

        $this->repository($this->connection)->resetPasswordUsingToken(
            $tokenHash,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            password_hash('new-secure-password', PASSWORD_DEFAULT),
        );

        foreach (array_slice($this->connection->preparedQueries, $before) as $query) {
            preg_match_all('/:(\w+)/', $query, $bindings);
            $this->assertSame(count($bindings[1]), count(array_unique($bindings[1])));
        }
    }

    public function testRememberSessionRotationReplacesTheTokenAndRejectsReplay(): void
    {
        $oldSelector = 'cccccccccccccccccccccccc';
        $oldValidatorHash = hash('sha256', 'old-validator');
        $newSelector = 'dddddddddddddddddddddddd';
        $this->insertRememberSession($this->connection, $oldSelector, $oldValidatorHash);
        $repository = $this->repository($this->connection);

        $first = $repository->rotateRememberSession(
            $oldSelector,
            $oldValidatorHash,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            $newSelector,
            hash('sha256', 'new-validator'),
            new DateTimeImmutable('2026-09-09 12:00:00'),
            '192.0.2.80',
            'OEMS Repository Test',
        );
        $replay = $repository->rotateRememberSession(
            $oldSelector,
            $oldValidatorHash,
            new DateTimeImmutable('2026-08-10 12:00:00'),
            'eeeeeeeeeeeeeeeeeeeeeeee',
            hash('sha256', 'replay-validator'),
            new DateTimeImmutable('2026-09-09 12:00:00'),
            '192.0.2.81',
            'OEMS Replay Test',
        );

        $this->assertSame(1, (int) ($first['user_id'] ?? 0));
        $this->assertNull($replay);
        $this->assertSame(0, $this->countWhere($this->connection, 'sessions', 'selector', $oldSelector));
        $this->assertSame(1, $this->countWhere($this->connection, 'sessions', 'selector', $newSelector));
    }

    public function testConcurrentPasswordResetConsumersAllowOnlyOneCommit(): void
    {
        $path = $this->createFileDatabase('reset');
        $tokenHash = hash('sha256', 'concurrent-reset-token');
        $connection = $this->openConnection($path);
        $this->insertReset($connection, $tokenHash);
        $connection = null;
        $markerDirectory = $path . '.markers';
        mkdir($markerDirectory, 0775, true);
        $this->temporaryPaths[] = $markerDirectory . '/one-ready';
        $this->temporaryPaths[] = $markerDirectory . '/two-ready';
        $this->temporaryPaths[] = $markerDirectory . '/release';
        $this->temporaryPaths[] = $markerDirectory;
        $one = $this->spawnRepositoryWorker('reset', $path, $tokenHash, 'one', $markerDirectory);
        $two = $this->spawnRepositoryWorker('reset', $path, $tokenHash, 'two', $markerDirectory);
        $this->waitForFile($markerDirectory . '/one-ready');
        $this->waitForFile($markerDirectory . '/two-ready');
        touch($markerDirectory . '/release');
        $successes = (int) $this->finishWorker($one) + (int) $this->finishWorker($two);
        $verification = $this->openConnection($path);

        $this->assertSame(1, $successes);
        $this->assertSame(0, $this->countRows($verification, 'password_resets'));
    }

    public function testConcurrentRememberConsumersAllowOnlyOneReplacement(): void
    {
        $path = $this->createFileDatabase('remember');
        $selector = 'ffffffffffffffffffffffff';
        $validatorHash = hash('sha256', 'concurrent-validator');
        $connection = $this->openConnection($path);
        $this->insertRememberSession($connection, $selector, $validatorHash);
        $connection = null;
        $markerDirectory = $path . '.markers';
        mkdir($markerDirectory, 0775, true);
        $this->temporaryPaths[] = $markerDirectory . '/one-ready';
        $this->temporaryPaths[] = $markerDirectory . '/two-ready';
        $this->temporaryPaths[] = $markerDirectory . '/release';
        $this->temporaryPaths[] = $markerDirectory;
        $one = $this->spawnRepositoryWorker('remember', $path, $selector, 'one', $markerDirectory, $validatorHash);
        $two = $this->spawnRepositoryWorker('remember', $path, $selector, 'two', $markerDirectory, $validatorHash);
        $this->waitForFile($markerDirectory . '/one-ready');
        $this->waitForFile($markerDirectory . '/two-ready');
        touch($markerDirectory . '/release');
        $successes = (int) $this->finishWorker($one) + (int) $this->finishWorker($two);
        $verification = $this->openConnection($path);

        $this->assertSame(1, $successes);
        $this->assertSame(1, $this->countRows($verification, 'sessions'));
        $this->assertSame(0, $this->countWhere($verification, 'sessions', 'selector', $selector));
    }

    private function repository(PDO $connection): UserRepository
    {
        return new UserRepository(new Database([], $connection));
    }

    private function createFileDatabase(string $label): string
    {
        $path = sys_get_temp_dir() . '/oems-user-repository-' . $label . '-' . bin2hex(random_bytes(5)) . '.sqlite';
        $this->temporaryPaths[] = $path;
        $connection = $this->openConnection($path);
        $this->createSchema($connection);
        $this->seedUser($connection);

        return $path;
    }

    private function openConnection(string $path): PDO
    {
        $connection = new PDO('sqlite:' . $path);
        $this->configureConnection($connection);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA busy_timeout = 5000');

        return $connection;
    }

    private function configureConnection(PDO $connection): void
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    private function createSchema(PDO $connection): void
    {
        $connection->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                role_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                status TEXT NOT NULL,
                deleted_at TEXT NULL,
                updated_at TEXT NULL
            )',
        );
        $connection->exec(
            'CREATE TABLE password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
        $connection->exec(
            'CREATE TABLE sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                selector TEXT NOT NULL UNIQUE,
                validator_hash TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                last_activity_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )',
        );
    }

    private function seedUser(PDO $connection): void
    {
        $statement = $connection->prepare(
            'INSERT INTO users (id, role_id, name, email, password, status, updated_at)
             VALUES (1, 3, :name, :email, :password, :status, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'name' => 'Repository User',
            'email' => 'repository@example.com',
            'password' => password_hash('old-secure-password', PASSWORD_DEFAULT),
            'status' => 'active',
        ]);
    }

    private function insertReset(PDO $connection, string $tokenHash): void
    {
        $statement = $connection->prepare(
            'INSERT INTO password_resets (email, token_hash, expires_at)
             VALUES (:email, :token_hash, :expires_at)',
        );
        $statement->execute([
            'email' => 'repository@example.com',
            'token_hash' => $tokenHash,
            'expires_at' => '2026-08-10 13:00:00',
        ]);
    }

    private function insertRememberSession(
        PDO $connection,
        string $selector,
        ?string $validatorHash = null,
    ): void {
        $statement = $connection->prepare(
            'INSERT INTO sessions
                (user_id, selector, validator_hash, ip_address, user_agent, last_activity_at, expires_at)
             VALUES
                (1, :selector, :validator_hash, :ip_address, :user_agent, :last_activity_at, :expires_at)',
        );
        $statement->execute([
            'selector' => $selector,
            'validator_hash' => $validatorHash ?? hash('sha256', 'validator'),
            'ip_address' => '192.0.2.70',
            'user_agent' => 'OEMS Test',
            'last_activity_at' => '2026-08-10 12:00:00',
            'expires_at' => '2026-09-09 12:00:00',
        ]);
    }

    private function passwordForUser(PDO $connection): string
    {
        return (string) $connection->query('SELECT password FROM users WHERE id = 1')->fetchColumn();
    }

    private function countRows(PDO $connection, string $table): int
    {
        return (int) $connection->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function countWhere(PDO $connection, string $table, string $column, string $value): int
    {
        $statement = $connection->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = :value');
        $statement->execute(['value' => $value]);

        return (int) $statement->fetchColumn();
    }

    private function spawnRepositoryWorker(
        string $mode,
        string $path,
        string $credential,
        string $label,
        string $markerDirectory,
        string $validatorHash = '',
    ): array {
        $code = <<<'PHP'
require $argv[1];
$connection = new PDO('sqlite:' . $argv[2]);
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$connection->exec('PRAGMA busy_timeout = 5000');
$repository = new OEMS\App\Repositories\UserRepository(new OEMS\Core\Database([], $connection));
$mode = $argv[3];
$credential = $argv[4];
$label = $argv[5];
$markers = $argv[6];
touch($markers . '/' . $label . '-ready');
$deadline = microtime(true) + 5;
while (!is_file($markers . '/release') && microtime(true) < $deadline) {
    usleep(1000);
}
if ($mode === 'reset') {
    $result = $repository->resetPasswordUsingToken(
        $credential,
        new DateTimeImmutable('2026-08-10 12:00:00'),
        password_hash('new-' . $label . '-password', PASSWORD_DEFAULT),
    );
} else {
    $result = $repository->rotateRememberSession(
        $credential,
        $argv[7],
        new DateTimeImmutable('2026-08-10 12:00:00'),
        str_repeat($label === 'one' ? '1' : '2', 24),
        hash('sha256', 'replacement-' . $label),
        new DateTimeImmutable('2026-09-09 12:00:00'),
        '192.0.2.90',
        'OEMS Concurrent Test',
    );
}
echo $result === null ? '0' : '1';
PHP;
        $process = proc_open(
            [
                PHP_BINARY,
                '-r',
                $code,
                '--',
                base_path('vendor/autoload.php'),
                $path,
                $mode,
                $credential,
                $label,
                $markerDirectory,
                $validatorHash,
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertTrue(is_resource($process));
        fclose($pipes[0]);

        return [$process, $pipes[1], $pipes[2]];
    }

    private function finishWorker(array $worker): string
    {
        [$process, $stdout, $stderr] = $worker;
        $output = trim((string) stream_get_contents($stdout));
        $error = trim((string) stream_get_contents($stderr));
        fclose($stdout);
        fclose($stderr);
        $status = proc_close($process);
        $this->assertSame(0, $status, $error);

        return $output;
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 5;

        while (!is_file($path) && microtime(true) < $deadline) {
            usleep(1000);
        }

        $this->assertTrue(is_file($path), 'Timed out waiting for repository worker coordination.');
    }
}
