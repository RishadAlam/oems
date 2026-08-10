<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Closure;
use PDO;
use Throwable;

final class HealthCheckService
{
    private const REQUIRED_STORAGE = ['storage/cache', 'storage/logs', 'storage/tickets', 'storage/certificates', 'storage/backups'];

    public function __construct(private readonly PDO|Closure $connection, private readonly string $basePath)
    {
    }

    /** @return array{status:string} */
    public function live(): array
    {
        return ['status' => 'ok'];
    }

    /** @return array{status:string,checks:array{database:bool,schema:bool,storage:bool}} */
    public function ready(): array
    {
        $checks = ['database' => false, 'schema' => false, 'storage' => false];

        try {
            $connection = $this->connection instanceof Closure ? ($this->connection)() : $this->connection;
            $checks['database'] = $connection->query('SELECT 1')->fetchColumn() !== false;
        } catch (Throwable) {
            $connection = null;
        }

        if ($checks['database'] && $connection instanceof PDO) {
            try {
                $connection->query('SELECT id, template, status FROM mail_outbox LIMIT 0');
                $connection->query('SELECT id, confirmation_token_hash FROM newsletter LIMIT 0');
                $connection->query('SELECT id, code FROM coupons LIMIT 0');
                $checks['schema'] = true;
            } catch (Throwable) {
            }
        }

        $checks['storage'] = true;
        foreach (self::REQUIRED_STORAGE as $path) {
            $absolute = $this->basePath . '/' . $path;
            if (!is_dir($absolute) || !is_writable($absolute)) {
                $checks['storage'] = false;
                break;
            }
        }

        return ['status' => !in_array(false, $checks, true) ? 'ok' : 'unavailable', 'checks' => $checks];
    }
}
