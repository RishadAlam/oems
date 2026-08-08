<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\FavoriteRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class FavoriteRepositoryTest extends TestCase
{
    private PDO $connection;

    private mixed $repository = null;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, category_id INTEGER NOT NULL, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, ticket_price TEXT NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT NULL, banner TEXT NULL)');
        $this->connection->exec('CREATE TABLE favorites (user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, event_id))');
        $this->connection->exec("INSERT INTO categories (id, name, is_active) VALUES (1, 'Technology', 1), (2, 'Archived', 0)");
        $this->connection->exec("INSERT INTO events (id, category_id, title, slug, start_date, ticket_price, currency, status, deleted_at, banner) VALUES
            (101, 1, 'Published event', 'published-event', '2026-10-20 10:00:00', '0.00', 'BDT', 'published', NULL, '/uploads/published.jpg'),
            (102, 1, 'Draft event', 'draft-event', '2026-10-21 10:00:00', '0.00', 'BDT', 'draft', NULL, NULL),
            (103, 2, 'Inactive category event', 'inactive-category-event', '2026-10-22 10:00:00', '100.00', 'BDT', 'published', NULL, NULL),
            (104, 1, 'Deleted event', 'deleted-event', '2026-10-23 10:00:00', '100.00', 'BDT', 'published', '2026-08-01 10:00:00', NULL)");
        $this->connection->exec("INSERT INTO favorites (user_id, event_id, created_at) VALUES (7, 103, '2026-08-05 09:00:00'), (7, 104, '2026-08-06 09:00:00'), (8, 101, '2026-08-07 09:00:00')");

        if (class_exists(FavoriteRepository::class)) {
            $this->repository = new FavoriteRepository($this->connection);
        }
    }

    public function testAddIsEligibilityGuardedAndDuplicateSafe(): void
    {
        $repository = $this->repository();

        $this->assertTrue($repository->addForParticipant(7, 101));
        $this->assertTrue($repository->addForParticipant(7, 101));
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM favorites WHERE user_id = 7 AND event_id = 101')->fetchColumn());
        $this->assertFalse($repository->addForParticipant(7, 102));
        $this->assertFalse($repository->addForParticipant(7, 103));
        $this->assertFalse($repository->addForParticipant(7, 104));
    }

    public function testRemoveAndExistsAreStrictlyParticipantScopedAndIdempotent(): void
    {
        $repository = $this->repository();

        $this->assertTrue($repository->removeForParticipant(7, 101));
        $this->assertTrue($repository->removeForParticipant(7, 101));
        $this->assertTrue($repository->existsForParticipant(8, 101));
        $this->assertFalse($repository->existsForParticipant(7, 101));
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM favorites WHERE user_id = 8 AND event_id = 101')->fetchColumn());
    }

    public function testBulkStatesAndSavedHistoryAreBoundedDeterministicAndSafe(): void
    {
        $repository = $this->repository();
        $this->connection->exec("INSERT INTO favorites (user_id, event_id, created_at) VALUES (7, 101, '2026-08-08 09:00:00')");

        $states = $repository->statesForParticipant(7, [101, 102, 104]);
        $firstPage = $repository->forParticipant(7, 1, 1);
        $boundedPage = $repository->forParticipant(7, -9, 999);

        $this->assertSame([101 => true, 104 => true], $states);
        $this->assertSame([101], array_column($firstPage['items'], 'event_id'));
        $this->assertSame(3, $firstPage['pagination']['total']);
        $this->assertSame(3, $firstPage['pagination']['last_page']);
        $this->assertSame(1, $boundedPage['pagination']['page']);
        $this->assertSame(50, $boundedPage['pagination']['per_page']);
        $this->assertSame([101, 104, 103], array_column($boundedPage['items'], 'event_id'));
        $this->assertFalse($boundedPage['items'][1]['is_available']);
        $this->assertSame('Deleted event', $boundedPage['items'][1]['title']);
        $this->assertFalse(array_key_exists('organization_name', $boundedPage['items'][1]));
    }

    private function repository(): FavoriteRepository
    {
        $this->assertTrue($this->repository instanceof FavoriteRepository, 'Favorite repository is missing.');

        return $this->repository;
    }
}
