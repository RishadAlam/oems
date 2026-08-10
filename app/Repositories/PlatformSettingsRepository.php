<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\PlatformSettingsRepositoryInterface;
use PDO;
use Throwable;

final class PlatformSettingsRepository implements PlatformSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function valuesForKeys(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $statement = $this->connection->prepare(
            "SELECT `key`, `value` FROM settings WHERE `key` IN ({$placeholders}) AND is_public = 1",
        );
        $statement->execute($keys);
        $values = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row) && is_string($row['key'] ?? null)) {
                $values[$row['key']] = $row['value'] ?? null;
            }
        }

        return $values;
    }

    public function updateMany(array $values): void
    {
        $started = !$this->connection->inTransaction();
        if ($started) {
            $this->connection->beginTransaction();
        }

        try {
            $find = $this->connection->prepare('SELECT id FROM settings WHERE `key` = :key LIMIT 1');
            $update = $this->connection->prepare(
                'UPDATE settings
                 SET `group` = :group_name, `value` = :value, value_type = :value_type,
                     is_public = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE `key` = :key',
            );
            $insert = $this->connection->prepare(
                'INSERT INTO settings (`group`, `key`, `value`, value_type, is_public, created_at, updated_at)
                 VALUES (:group_name, :key, :value, :value_type, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );

            foreach ($values as $key => $value) {
                $parameters = [
                    'group_name' => str_starts_with((string) $key, 'home_') ? 'home' : 'general',
                    'key' => (string) $key,
                    'value' => (string) $value,
                    'value_type' => 'string',
                ];
                $find->execute(['key' => $parameters['key']]);
                ($find->fetchColumn() === false ? $insert : $update)->execute($parameters);
            }

            if ($started) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
