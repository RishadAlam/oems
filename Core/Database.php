<?php

declare(strict_types=1);

namespace OEMS\Core;

use PDO;
use Throwable;

final class Database
{
    private ?PDO $pdo;

    public function __construct(private readonly array $config, ?PDO $connection = null)
    {
        $this->pdo = $connection;
    }

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['driver'],
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'],
        );

        $this->pdo = new PDO(
            $dsn,
            (string) $this->config['username'],
            (string) $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );

        return $this->pdo;
    }

    public function transaction(callable $operation): mixed
    {
        $connection = $this->connection();
        $isSqlite = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if ($isSqlite) {
            $connection->exec('BEGIN IMMEDIATE');
        } else {
            $connection->beginTransaction();
        }

        try {
            $result = $operation($connection);

            if ($isSqlite) {
                $connection->exec('COMMIT');
            } else {
                $connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($isSqlite) {
                try {
                    $connection->exec('ROLLBACK');
                } catch (Throwable) {
                    // The operation may have ended the transaction before failing.
                }
            } elseif ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
