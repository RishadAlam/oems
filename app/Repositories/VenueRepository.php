<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\VenueRepositoryInterface;
use PDO;

final class VenueRepository implements VenueRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function forOrganizerUser(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT venues.id, venues.organizer_id, venues.name, venues.address_line, venues.city,
                    venues.country, venues.postal_code, venues.latitude, venues.longitude, venues.map_url,
                    venues.capacity, venues.created_at, venues.updated_at
             FROM venues
             INNER JOIN organizers ON organizers.id = venues.organizer_id
             WHERE organizers.user_id = :user_id
             ORDER BY venues.name ASC, venues.id ASC',
        );
        $statement->execute(['user_id' => $userId]);
        $venues = $statement->fetchAll();

        return is_array($venues) ? $venues : [];
    }

    public function findOwned(int $userId, int $venueId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT venues.id, venues.organizer_id, venues.name, venues.address_line, venues.city,
                    venues.country, venues.postal_code, venues.latitude, venues.longitude, venues.map_url,
                    venues.capacity, venues.created_at, venues.updated_at
             FROM venues
             INNER JOIN organizers ON organizers.id = venues.organizer_id
             WHERE organizers.user_id = :user_id AND venues.id = :venue_id
             LIMIT 1',
        );
        $statement->execute([
            'user_id' => $userId,
            'venue_id' => $venueId,
        ]);
        $venue = $statement->fetch();

        return is_array($venue) ? $venue : null;
    }

    public function createForUser(int $userId, array $attributes): ?int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO venues
                (organizer_id, name, address_line, city, country, postal_code, latitude, longitude, map_url, capacity, created_at, updated_at)
             SELECT
                organizers.id, :name, :address_line, :city, :country, :postal_code, :latitude, :longitude, :map_url, :capacity,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM organizers
             WHERE organizers.user_id = :user_id',
        );
        $statement->execute($this->venueParameters($userId, $attributes));

        return $statement->rowCount() === 1 ? (int) $this->connection->lastInsertId() : null;
    }

    public function updateOwned(int $userId, int $venueId, array $attributes): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE venues
             SET name = :name,
                 address_line = :address_line,
                 city = :city,
                 country = :country,
                 postal_code = :postal_code,
                 latitude = :latitude,
                 longitude = :longitude,
                 map_url = :map_url,
                 capacity = :capacity,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :venue_id
               AND organizer_id IN (SELECT id FROM organizers WHERE user_id = :user_id)',
        );
        $parameters = $this->venueParameters($userId, $attributes);
        $parameters['venue_id'] = $venueId;
        $statement->execute($parameters);

        return $statement->rowCount() > 0;
    }

    public function deleteOwnedIfUnused(int $userId, int $venueId): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM venues
             WHERE id = :venue_id
               AND organizer_id IN (SELECT id FROM organizers WHERE user_id = :user_id)
               AND NOT EXISTS (
                   SELECT 1
                   FROM events
                   WHERE events.venue_id = venues.id AND events.deleted_at IS NULL
               )',
        );
        $statement->execute([
            'user_id' => $userId,
            'venue_id' => $venueId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function venueParameters(int $userId, array $attributes): array
    {
        return [
            'user_id' => $userId,
            'name' => $attributes['name'],
            'address_line' => $attributes['address_line'],
            'city' => $attributes['city'],
            'country' => $attributes['country'],
            'postal_code' => $attributes['postal_code'] ?? null,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'map_url' => $attributes['map_url'] ?? null,
            'capacity' => $attributes['capacity'] ?? null,
        ];
    }
}
