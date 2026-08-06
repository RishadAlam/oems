<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class DashboardMetricsRepositoryTest extends TestCase
{
    public function testReturnsVisiblePlatformTotalsAsIntegers(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec("INSERT INTO users (deleted_at) VALUES (NULL), (NULL), ('2026-08-01 09:00:00')");
        $connection->exec('INSERT INTO organizers DEFAULT VALUES');
        $connection->exec('INSERT INTO organizers DEFAULT VALUES');
        $connection->exec("INSERT INTO events (deleted_at) VALUES (NULL), (NULL), (NULL), ('2026-08-02 09:00:00')");

        $repository = new DashboardMetricsRepository($connection);

        $this->assertSame([
            'users' => 2,
            'organizers' => 2,
            'events' => 3,
        ], $repository->totals());
    }
}
