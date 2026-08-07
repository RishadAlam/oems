<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\VenueRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class VenueRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE)');
        $this->connection->exec(
            'CREATE TABLE venues (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organizer_id INTEGER NULL,
                name TEXT NOT NULL,
                address_line TEXT NOT NULL,
                city TEXT NOT NULL,
                country TEXT NOT NULL,
                postal_code TEXT NULL,
                latitude REAL NULL,
                longitude REAL NULL,
                map_url TEXT NULL,
                capacity INTEGER NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, venue_id INTEGER NULL, deleted_at TEXT NULL)');
        $this->connection->exec('INSERT INTO organizers (id, user_id) VALUES (1, 10), (2, 20)');
        $this->connection->exec(
            "INSERT INTO venues
                (id, organizer_id, name, address_line, city, country, postal_code, capacity, created_at, updated_at)
             VALUES
                (91, 1, 'Original venue', 'Road 10', 'Dhaka', 'Bangladesh', '1205', 100, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (92, 1, 'Zed venue', 'Road 11', 'Dhaka', 'Bangladesh', '1206', 120, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (93, 2, 'Other organizer venue', 'Road 1', 'Sylhet', 'Bangladesh', '3100', 200, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        );
        $this->connection->exec('INSERT INTO events (id, venue_id, deleted_at) VALUES (1, 92, NULL), (2, 91, CURRENT_TIMESTAMP)');
    }

    public function testForOrganizerUserReturnsOnlyOwnedVenuesInNameOrder(): void
    {
        $repository = new VenueRepository($this->connection);

        $venues = $repository->forOrganizerUser(10);

        $this->assertSame(['Original venue', 'Zed venue'], array_column($venues, 'name'));
        $this->assertSame([], $repository->forOrganizerUser(999));
    }

    public function testFindOwnedReturnsVenueOnlyForTheAuthenticatedOrganizer(): void
    {
        $repository = new VenueRepository($this->connection);

        $venue = $repository->findOwned(10, 91);

        $this->assertNotNull($venue);
        $this->assertSame('Original venue', $venue['name']);
        $this->assertNull($repository->findOwned(20, 91));
    }

    public function testCreateForUserReturnsNewVenueIdOrNullWithoutAnOrganizer(): void
    {
        $repository = new VenueRepository($this->connection);

        $id = $repository->createForUser(10, $this->venueAttributes('New venue'));

        $this->assertNotNull($id);
        $this->assertSame('New venue', $repository->findOwned(10, $id)['name']);
        $this->assertNull($repository->createForUser(999, $this->venueAttributes('No owner')));
    }

    public function testVenueUpdateCannotCrossOrganizerOwnership(): void
    {
        $repository = new VenueRepository($this->connection);
        $updated = $repository->updateOwned(20, 91, [
            'name' => 'Changed venue',
            'address_line' => 'Road 12',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1209',
            'latitude' => null,
            'longitude' => null,
            'map_url' => null,
            'capacity' => 150,
        ]);

        $this->assertFalse($updated);
        $this->assertSame('Original venue', $this->venueName(91));
    }

    public function testUpdateOwnedChangesTheOwnedVenue(): void
    {
        $repository = new VenueRepository($this->connection);

        $this->assertTrue($repository->updateOwned(10, 91, $this->venueAttributes('Changed venue')));
        $venue = $repository->findOwned(10, 91);
        $this->assertSame('Changed venue', $venue['name']);
        $this->assertSame(250, (int) $venue['capacity']);
    }

    public function testDeleteOwnedIfUnusedRefusesLiveEventsButAllowsSoftDeletedEvents(): void
    {
        $repository = new VenueRepository($this->connection);

        $this->assertFalse($repository->deleteOwnedIfUnused(10, 92));
        $this->assertTrue($repository->deleteOwnedIfUnused(10, 91));
        $this->assertNull($repository->findOwned(10, 91));
        $this->assertFalse($repository->deleteOwnedIfUnused(20, 92));
    }

    private function venueName(int $venueId): string
    {
        return (string) $this->connection->query("SELECT name FROM venues WHERE id = {$venueId}")->fetchColumn();
    }

    private function venueAttributes(string $name): array
    {
        return [
            'name' => $name,
            'address_line' => 'Road 12',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1209',
            'latitude' => 23.7806,
            'longitude' => 90.4070,
            'map_url' => 'https://maps.example.test/new-venue',
            'capacity' => 250,
        ];
    }
}
