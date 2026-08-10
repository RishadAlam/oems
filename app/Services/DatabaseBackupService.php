<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Closure;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class DatabaseBackupService
{
    private readonly Closure $runner;

    public function __construct(private readonly string $basePath, ?Closure $runner = null)
    {
        $this->runner = $runner ?? Closure::fromCallable([$this, 'runDump']);
    }

    public function backup(array $database, int $retention, ?DateTimeImmutable $now = null): string
    {
        $name = (string) ($database['database'] ?? '');
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $name) !== 1 || $retention < 1 || $retention > 30) {
            throw new RuntimeException('The backup configuration is invalid.');
        }

        $directory = $this->basePath . '/storage/backups';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('The backup directory is unavailable.');
        }
        @chmod($directory, 0700);
        $now ??= new DateTimeImmutable();
        $destination = $directory . '/oems-' . $now->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql.gz';
        $command = [
            'mysqldump', '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF',
            '--host=' . (string) ($database['host'] ?? '127.0.0.1'),
            '--port=' . (int) ($database['port'] ?? 3306),
            '--user=' . (string) ($database['username'] ?? ''),
            '--default-character-set=utf8mb4', '--', $name,
        ];
        $environment = ['MYSQL_PWD' => (string) ($database['password'] ?? '')];
        foreach (['PATH', 'HOME', 'TMPDIR', 'LANG', 'LC_ALL'] as $key) {
            $value = getenv($key);
            if (is_string($value) && $value !== '') {
                $environment[$key] = $value;
            }
        }

        try {
            ($this->runner)($command, $environment, $destination);
            clearstatcache(true, $destination);
            if (!is_file($destination) || !is_readable($destination) || filesize($destination) < 20) {
                throw new RuntimeException('The database backup was empty.');
            }
            $archive = @gzopen($destination, 'rb');
            $firstByte = $archive === false ? false : @gzread($archive, 1);
            if (is_resource($archive)) {
                gzclose($archive);
            }
            if (!is_string($firstByte) || $firstByte === '') {
                throw new RuntimeException('The database backup contained no SQL data.');
            }
            chmod($destination, 0600);
            $this->retain($directory, $retention);
        } catch (Throwable $exception) {
            @unlink($destination);
            throw new RuntimeException('The database backup failed.', 0, $exception);
        }

        return $destination;
    }

    private function runDump(array $command, array $environment, string $destination): void
    {
        $errorPath = $destination . '.error';
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errorPath, 'w']], $pipes, null, $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('The database dump could not start.');
        }
        fclose($pipes[0]);
        $archive = gzopen($destination, 'wb9');
        if ($archive === false) {
            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
            @unlink($errorPath);
            throw new RuntimeException('The backup archive could not be opened.');
        }
        try {
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk === false) throw new RuntimeException('The database dump could not be read.');
                if ($chunk !== '' && gzwrite($archive, $chunk) === false) throw new RuntimeException('The backup archive could not be written.');
            }
            fclose($pipes[1]);
            gzclose($archive);
            $status = proc_close($process);
            if ($status !== 0) throw new RuntimeException('The database dump command failed.');
        } finally {
            @unlink($errorPath);
        }
    }

    private function retain(string $directory, int $retention): void
    {
        $files = glob($directory . '/oems-*.sql.gz') ?: [];
        usort($files, static fn (string $left, string $right): int => strcmp(basename($right), basename($left)));
        foreach (array_slice($files, $retention) as $file) {
            if (is_file($file)) @unlink($file);
        }
    }
}
