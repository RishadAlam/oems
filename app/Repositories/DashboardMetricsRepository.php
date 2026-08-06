<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use PDO;

final class DashboardMetricsRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function totals(): array
    {
        $row = $this->connection->query(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS users,
                (SELECT COUNT(*) FROM organizers) AS organizers,
                (SELECT COUNT(*) FROM events WHERE deleted_at IS NULL) AS events',
        )->fetch();

        return [
            'users' => (int) $row['users'],
            'organizers' => (int) $row['organizers'],
            'events' => (int) $row['events'],
        ];
    }
}
