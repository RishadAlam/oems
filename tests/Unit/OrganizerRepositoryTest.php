<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\OrganizerRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class OrganizerRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec(
            'CREATE TABLE organizers (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL UNIQUE,
                organization_name TEXT NOT NULL,
                approval_status TEXT NOT NULL
            )',
        );
        $this->connection->exec(
            "INSERT INTO organizers (id, user_id, organization_name, approval_status) VALUES
                (1, 10, 'Approved Organizer', 'approved'),
                (2, 20, 'Pending Organizer', 'pending')",
        );
    }

    public function testApprovalStatusIsScopedToTheOrganizerUser(): void
    {
        $repository = new OrganizerRepository($this->connection);

        $this->assertSame('approved', $repository->approvalStatusForUser(10));
        $this->assertSame('pending', $repository->approvalStatusForUser(20));
        $this->assertNull($repository->approvalStatusForUser(999));
    }
}
