<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\OrganizerRepositoryInterface;
use PDO;

final class OrganizerRepository implements OrganizerRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function approvalStatusForUser(int $userId): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT approval_status FROM organizers WHERE user_id = :user_id LIMIT 1',
        );
        $statement->execute(['user_id' => $userId]);
        $status = $statement->fetchColumn();

        return is_string($status) ? $status : null;
    }
}
