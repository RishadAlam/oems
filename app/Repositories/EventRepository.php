<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\EventRepositoryInterface;
use PDO;
use Throwable;

final class EventRepository implements EventRepositoryInterface
{
    private const STATUSES = ['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'];

    private const SORTS = [
        'soonest' => 'events.start_date ASC, events.id ASC',
        'latest' => 'events.start_date DESC, events.id DESC',
        'price_low' => 'events.ticket_price ASC, events.start_date ASC, events.id ASC',
        'price_high' => 'events.ticket_price DESC, events.start_date ASC, events.id ASC',
    ];

    private const ORGANIZER_TRANSITIONS = [
        'pending' => ['draft'],
        'published' => ['approved'],
        'cancelled' => ['approved', 'published'],
    ];

    private const ADMIN_TRANSITIONS = [
        'approved' => ['pending'],
        'rejected' => ['pending'],
        'published' => ['approved'],
        'completed' => ['published'],
        'cancelled' => ['approved', 'published'],
    ];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function featured(int $limit): array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect()
            . ' WHERE events.status = :status
                  AND events.deleted_at IS NULL
                  AND events.start_date >= CURRENT_TIMESTAMP
                  AND categories.is_active = 1
                ORDER BY events.is_featured DESC, events.start_date ASC, events.id ASC
                LIMIT :limit',
        );
        $statement->bindValue('status', 'published');
        $statement->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $this->decodeEvents($statement->fetchAll());
    }

    public function publicSearch(array $filters): array
    {
        $clauses = [
            'events.status = :published_status',
            'events.deleted_at IS NULL',
            'events.start_date >= CURRENT_TIMESTAMP',
            'categories.is_active = 1',
        ];
        $parameters = ['published_status' => 'published'];

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $searchClauses = [];
            $searchValue = '%' . strtolower($search) . '%';

            foreach ([
                'title' => 'events.title',
                'description' => 'events.description',
                'speaker' => "COALESCE(events.speaker, '')",
                'organizer' => 'organizers.organization_name',
                'category' => 'categories.name',
                'venue' => "CASE WHEN events.location_visibility = 'public' THEN COALESCE(venues.name, '') ELSE '' END",
                'city' => "COALESCE(venues.city, '')",
            ] as $name => $column) {
                $parameter = 'search_' . $name;
                $searchClauses[] = 'LOWER(' . $column . ') LIKE :' . $parameter;
                $parameters[$parameter] = $searchValue;
            }

            $clauses[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        if (($category = trim((string) ($filters['category'] ?? ''))) !== '') {
            $clauses[] = 'categories.slug = :category';
            $parameters['category'] = $category;
        }

        if (($city = trim((string) ($filters['city'] ?? ''))) !== '') {
            $clauses[] = 'venues.city = :city';
            $parameters['city'] = $city;
        }

        $date = (string) ($filters['date'] ?? 'upcoming');

        if ($date === 'today') {
            [$start, $end] = $this->dateRange('today');
            $clauses[] = 'events.start_date >= :date_start AND events.start_date < :date_end';
            $parameters['date_start'] = $start;
            $parameters['date_end'] = $end;
        } elseif ($date === 'this_week') {
            [$start, $end] = $this->dateRange('this_week');
            $clauses[] = 'events.start_date >= :date_start AND events.start_date < :date_end';
            $parameters['date_start'] = $start;
            $parameters['date_end'] = $end;
        } elseif ($date === 'this_month') {
            [$start, $end] = $this->dateRange('this_month');
            $clauses[] = 'events.start_date >= :date_start AND events.start_date < :date_end';
            $parameters['date_start'] = $start;
            $parameters['date_end'] = $end;
        }

        $price = (string) ($filters['price'] ?? '');
        if ($price === 'free') {
            $clauses[] = 'events.ticket_price = 0';
        } elseif ($price === 'paid') {
            $clauses[] = 'events.ticket_price > 0';
        }

        $nearby = $this->nearbyParameters($filters);

        if ($nearby !== null) {
            $clauses[] = 'venues.latitude IS NOT NULL';
            $clauses[] = 'venues.longitude IS NOT NULL';
            $clauses[] = 'venues.latitude >= :latitude_min AND venues.latitude <= :latitude_max';
            $nearbyParameters = $nearby['parameters'];

            if ($nearby['all_longitudes']) {
                unset($nearbyParameters['longitude_min'], $nearbyParameters['longitude_max']);
            }

            $parameters = array_merge($parameters, $nearbyParameters);

            if ($nearby['longitude_wraps']) {
                $clauses[] = '(venues.longitude >= :longitude_min OR venues.longitude <= :longitude_max)';
            } elseif (!$nearby['all_longitudes']) {
                $clauses[] = 'venues.longitude >= :longitude_min AND venues.longitude <= :longitude_max';
            }
        }

        $sort = self::SORTS[(string) ($filters['sort'] ?? '')] ?? self::SORTS['soonest'];

        if ($nearby !== null && ($filters['sort'] ?? '') === 'distance') {
            $sort = 'distance_km ASC, start_date ASC, id ASC';
        } else {
            $sort = str_replace('events.', '', $sort);
        }

        $select = $this->eventSelect($nearby === null ? null : $this->distanceExpression());

        if ($nearby !== null) {
            $statement = $this->connection->prepare(
                'SELECT * FROM (' . $select
                . ' WHERE ' . implode(' AND ', $clauses)
                . ') AS nearby_events WHERE distance_km <= (:distance_radius + 0)'
                . ' ORDER BY ' . $sort,
            );
            $statement->execute($parameters);

            return $this->decodeEvents($statement->fetchAll());
        }

        $statement = $this->connection->prepare(
            $select
            . ' WHERE ' . implode(' AND ', $clauses)
            . ' ORDER BY ' . $sort,
        );
        $statement->execute($parameters);

        return $this->decodeEvents($statement->fetchAll());
    }

    public function publicCities(): array
    {
        $statement = $this->connection->prepare(
            'SELECT DISTINCT venues.city
             FROM events
             INNER JOIN venues ON venues.id = events.venue_id
             INNER JOIN categories ON categories.id = events.category_id
             WHERE events.status = :status
               AND events.deleted_at IS NULL
               AND events.start_date >= CURRENT_TIMESTAMP
               AND categories.is_active = 1
               AND venues.city IS NOT NULL
             ORDER BY venues.city ASC',
        );
        $statement->execute(['status' => 'published']);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect()
            . ' WHERE events.slug = :slug
                  AND events.status IN (\'published\', \'completed\')
                  AND events.deleted_at IS NULL
                  AND categories.is_active = 1
                LIMIT 1',
        );
        $statement->execute(['slug' => $slug]);

        return $this->decodeEvent($statement->fetch());
    }

    public function gallery(int $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_gallery.id, event_gallery.event_id, event_gallery.image_path,
                    event_gallery.alt_text, event_gallery.sort_order, event_gallery.created_at
             FROM event_gallery
             INNER JOIN events ON events.id = event_gallery.event_id
             INNER JOIN categories ON categories.id = events.category_id
             WHERE event_gallery.event_id = :event_id
               AND events.status IN (\'published\', \'completed\')
               AND events.deleted_at IS NULL
               AND categories.is_active = 1
             ORDER BY event_gallery.sort_order ASC, event_gallery.id ASC',
        );
        $statement->execute(['event_id' => $eventId]);
        $gallery = $statement->fetchAll();

        return is_array($gallery) ? $gallery : [];
    }

    public function galleryForOwned(int $userId, int $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_gallery.id, event_gallery.event_id, event_gallery.image_path,
                    event_gallery.alt_text, event_gallery.sort_order, event_gallery.created_at
             FROM event_gallery
             INNER JOIN events ON events.id = event_gallery.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE organizers.user_id = :user_id
               AND event_gallery.event_id = :event_id
               AND events.deleted_at IS NULL
             ORDER BY event_gallery.sort_order ASC, event_gallery.id ASC',
        );
        $statement->execute(['user_id' => $userId, 'event_id' => $eventId]);
        $gallery = $statement->fetchAll();

        return is_array($gallery) ? $gallery : [];
    }

    public function organizerSummary(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN events.status = \'draft\' THEN 1 ELSE 0 END) AS draft,
                    SUM(CASE WHEN events.status = \'pending\' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN events.status = \'approved\' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN events.status = \'rejected\' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN events.status = \'published\' THEN 1 ELSE 0 END) AS published,
                    SUM(CASE WHEN events.status = \'completed\' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN events.status = \'cancelled\' THEN 1 ELSE 0 END) AS cancelled
             FROM events
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE organizers.user_id = :user_id AND events.deleted_at IS NULL',
        );
        $statement->execute(['user_id' => $userId]);
        $summary = $statement->fetch();
        $summary = is_array($summary) ? $summary : [];

        foreach (['total', ...self::STATUSES] as $key) {
            $summary[$key] = (int) ($summary[$key] ?? 0);
        }

        return $summary;
    }

    public function forOrganizerUser(int $userId, ?string $status): array
    {
        $query = $this->eventSelect()
            . ' INNER JOIN organizers AS owner_organizers ON owner_organizers.id = events.organizer_id
                WHERE owner_organizers.user_id = :user_id AND events.deleted_at IS NULL';
        $parameters = ['user_id' => $userId];

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $query .= ' AND events.status = :status';
            $parameters['status'] = $status;
        }

        $query .= ' ORDER BY events.start_date DESC, events.id DESC';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $this->decodeEvents($statement->fetchAll());
    }

    public function recentForOrganizerUser(int $userId, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $statement = $this->connection->prepare(
            $this->eventSelect()
            . ' INNER JOIN organizers AS owner_organizers ON owner_organizers.id = events.organizer_id
                WHERE owner_organizers.user_id = :user_id
                  AND events.deleted_at IS NULL
                ORDER BY events.updated_at DESC, events.created_at DESC, events.id DESC
                LIMIT :limit',
        );
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->decodeEvents($statement->fetchAll());
    }

    public function findOwned(int $userId, int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect()
            . ' INNER JOIN organizers AS owner_organizers ON owner_organizers.id = events.organizer_id
                WHERE owner_organizers.user_id = :user_id
                  AND events.id = :event_id
                  AND events.deleted_at IS NULL
                LIMIT 1',
        );
        $statement->execute(['user_id' => $userId, 'event_id' => $eventId]);

        return $this->decodeEvent($statement->fetch());
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        $query = 'SELECT id FROM events WHERE slug = :slug';
        $parameters = ['slug' => $slug];

        if ($exceptId !== null) {
            $query .= ' AND id != :except_id';
            $parameters['except_id'] = $exceptId;
        }

        $statement = $this->connection->prepare($query . ' LIMIT 1');
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function createForUser(int $userId, array $attributes): ?int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO events
                (organizer_id, category_id, venue_id, title, slug, description, banner, map_url, location_visibility, arrival_notes, speaker,
                 start_date, end_date, registration_deadline, capacity, available_seats, ticket_price, currency,
                 tags, status, is_featured, waitlist_enabled, created_at, updated_at)
             SELECT organizers.id, :category_id, :venue_id, :title, :slug, :description, :banner, :map_url, :location_visibility, :arrival_notes, :speaker,
                    :start_date, :end_date, :registration_deadline, :capacity, :available_seats, :ticket_price, :currency,
                    :tags, \'draft\', :is_featured, :waitlist_enabled, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM organizers
             WHERE organizers.user_id = :user_id
               AND EXISTS (SELECT 1 FROM categories WHERE categories.id = :category_id_guard)
               AND (:venue_id_nullable_guard IS NULL OR EXISTS (
                   SELECT 1 FROM venues WHERE venues.id = :venue_id_ownership_guard AND venues.organizer_id = organizers.id
               ))',
        );
        $statement->execute($this->eventParameters($userId, $attributes));

        return $statement->rowCount() === 1 ? (int) $this->connection->lastInsertId() : null;
    }

    public function createWithGalleryForUser(int $userId, array $attributes, array $images): ?int
    {
        return $this->transactional(function () use ($userId, $attributes, $images): ?int {
            $eventId = $this->createForUser($userId, $attributes);

            if ($eventId === null) {
                return null;
            }

            $this->replaceGallery($eventId, $images);

            return $eventId;
        });
    }

    public function updateOwned(int $userId, int $eventId, array $attributes): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE events
             SET category_id = :category_id,
                 venue_id = :venue_id,
                 title = :title,
                 slug = :slug,
                 description = :description,
                 banner = :banner,
                 map_url = :map_url,
                 location_visibility = :location_visibility,
                 arrival_notes = :arrival_notes,
                 speaker = :speaker,
                 start_date = :start_date,
                 end_date = :end_date,
                 registration_deadline = :registration_deadline,
                 capacity = :capacity,
                 available_seats = :available_seats,
                 ticket_price = :ticket_price,
                 currency = :currency,
                 tags = :tags,
                 is_featured = :is_featured,
                 waitlist_enabled = :waitlist_enabled,
                 rejection_reason = CASE WHEN status = \'rejected\' THEN NULL ELSE rejection_reason END,
                 status = CASE WHEN status = \'rejected\' THEN \'draft\' ELSE status END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE events.id = :event_id
               AND events.deleted_at IS NULL
               AND events.status IN (\'draft\', \'rejected\')
               AND events.organizer_id IN (SELECT id FROM organizers WHERE user_id = :user_id)
               AND EXISTS (SELECT 1 FROM categories WHERE categories.id = :category_id_guard)
               AND (:venue_id_nullable_guard IS NULL OR EXISTS (
                   SELECT 1 FROM venues
                   WHERE venues.id = :venue_id_ownership_guard AND venues.organizer_id = events.organizer_id
               ))',
        );
        $parameters = $this->eventParameters($userId, $attributes);
        $parameters['event_id'] = $eventId;
        $statement->execute($parameters);

        if ($statement->rowCount() > 0) {
            return true;
        }

        return $this->ownedEventUpdateEligible($userId, $eventId, $attributes);
    }

    public function updateWithGalleryOwned(
        int $userId,
        int $eventId,
        array $attributes,
        ?array $images,
    ): ?array {
        return $this->transactional(function () use ($userId, $eventId, $attributes, $images): ?array {
            $event = $this->connection->prepare(
                'SELECT events.banner
                 FROM events
                 INNER JOIN organizers ON organizers.id = events.organizer_id
                 WHERE organizers.user_id = :user_id
                   AND events.id = :event_id
                   AND events.deleted_at IS NULL
                   AND events.status IN (\'draft\', \'rejected\')
                 LIMIT 1',
            );
            $event->execute(['user_id' => $userId, 'event_id' => $eventId]);
            $priorBanner = $event->fetchColumn();

            if ($priorBanner === false) {
                return null;
            }

            $gallery = $this->connection->prepare(
                'SELECT event_gallery.image_path
                 FROM event_gallery
                 WHERE event_gallery.event_id = :event_id
                 ORDER BY event_gallery.sort_order ASC, event_gallery.id ASC',
            );
            $gallery->execute(['event_id' => $eventId]);
            $priorGallery = array_values(array_filter($gallery->fetchAll(PDO::FETCH_COLUMN), 'is_string'));

            if (!$this->updateOwned($userId, $eventId, $attributes)) {
                return null;
            }

            if ($images !== null) {
                $this->replaceGallery($eventId, $images);
            }

            return [
                'banner' => is_string($priorBanner) ? $priorBanner : null,
                'gallery' => $priorGallery,
            ];
        });
    }

    public function softDeleteOwned(int $userId, int $eventId, array $context): bool
    {
        return $this->transactional(function () use ($userId, $eventId, $context): bool {
            $statement = $this->connection->prepare(
                'UPDATE events
                 SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE events.id = :event_id
                   AND events.deleted_at IS NULL
                   AND events.status IN (\'draft\', \'rejected\', \'cancelled\')
                   AND events.organizer_id IN (SELECT id FROM organizers WHERE user_id = :user_id)
                   AND NOT EXISTS (SELECT 1 FROM registrations WHERE registrations.event_id = events.id)',
            );
            $statement->execute(['user_id' => $userId, 'event_id' => $eventId]);

            if ($statement->rowCount() !== 1) {
                return false;
            }

            $this->writeActivity($userId, $eventId, 'deleted', $context, null);

            return true;
        });
    }

    public function softDeleteAdmin(int $userId, int $eventId, array $context): bool
    {
        return $this->transactional(function () use ($userId, $eventId, $context): bool {
            $statement = $this->connection->prepare(
                'UPDATE events
                 SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE events.id = :event_id
                   AND events.deleted_at IS NULL
                   AND events.status IN (\'draft\', \'rejected\', \'cancelled\')
                   AND NOT EXISTS (SELECT 1 FROM registrations WHERE registrations.event_id = events.id)',
            );
            $statement->execute(['event_id' => $eventId]);

            if ($statement->rowCount() !== 1) {
                return false;
            }

            $this->writeActivity($userId, $eventId, 'deleted', $context, null);

            return true;
        });
    }

    public function transitionOwned(int $userId, int $eventId, array $context, string $status): bool
    {
        $currentStatuses = self::ORGANIZER_TRANSITIONS[$status] ?? null;

        if ($currentStatuses === null) {
            return false;
        }

        return $this->transactional(function () use (
            $userId,
            $eventId,
            $context,
            $status,
            $currentStatuses,
        ): bool {
            [$statusSql, $statusParameters] = $this->statusPredicate($currentStatuses);
            $approvalSql = '';
            $approvalParameters = [];

            if ($status === 'pending') {
                $approvalSql = ' AND EXISTS (
                    SELECT 1 FROM organizers
                    WHERE organizers.id = events.organizer_id
                      AND organizers.user_id = :approval_user_id
                      AND organizers.approval_status = :organizer_approval_status
                )';
                $approvalParameters = [
                    'approval_user_id' => $userId,
                    'organizer_approval_status' => 'approved',
                ];
            }

            if ($status === 'published') {
                $publishNow = date('Y-m-d H:i:s');
                $approvalSql = ' AND EXISTS (
                    SELECT 1 FROM organizers
                    WHERE organizers.id = events.organizer_id
                      AND organizers.user_id = :publish_user_id
                      AND organizers.approval_status = :publish_organizer_approval_status
                )
                AND EXISTS (
                    SELECT 1 FROM categories
                    WHERE categories.id = events.category_id
                      AND categories.is_active = :publish_category_active
                )
                AND events.start_date > :publish_now
                AND events.registration_deadline > :publish_now_deadline
                AND events.registration_deadline < events.start_date';
                $approvalParameters = [
                    'publish_user_id' => $userId,
                    'publish_organizer_approval_status' => 'approved',
                    'publish_category_active' => 1,
                    'publish_now' => $publishNow,
                    'publish_now_deadline' => $publishNow,
                ];
            }

            $publishedAt = $status === 'published' ? 'CURRENT_TIMESTAMP' : 'published_at';

            $statement = $this->connection->prepare(
                'UPDATE events
                 SET status = :status,
                     rejection_reason = CASE WHEN :status_reason = \'rejected\' THEN rejection_reason ELSE NULL END,
                     published_at = ' . $publishedAt . ',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE events.id = :event_id
                   AND events.deleted_at IS NULL
                   AND events.organizer_id IN (SELECT id FROM organizers WHERE user_id = :user_id)
                   AND events.status IN (' . $statusSql . ')'
                   . $approvalSql,
            );
            $statement->execute(array_merge([
                'status' => $status,
                'status_reason' => $status,
                'event_id' => $eventId,
                'user_id' => $userId,
            ], $statusParameters, $approvalParameters));

            if ($statement->rowCount() === 0) {
                return false;
            }

            if ($status === 'cancelled') {
                $this->cancelParticipantFulfillment($eventId);
            }

            $this->writeActivity($userId, $eventId, $status, $context, null);

            return true;
        });
    }

    public function participantIdsForEventCancellation(int $eventId): array
    {
        $statement = $this->connection->prepare(
            "SELECT DISTINCT registrations.user_id
             FROM registrations
             INNER JOIN users
                     ON users.id = registrations.user_id
                    AND users.deleted_at IS NULL
             WHERE registrations.event_id = :event_id
               AND registrations.status = 'cancelled'
               AND registrations.cancellation_reason = 'Event cancelled'
             ORDER BY registrations.user_id ASC",
        );
        $statement->execute(['event_id' => $eventId]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function publishOwned(int $userId, int $eventId, array $context): bool
    {
        return $this->transitionOwned($userId, $eventId, $context, 'published');
    }

    public function forAdmin(?string $status): array
    {
        $query = $this->eventSelect() . ' WHERE events.deleted_at IS NULL';
        $parameters = [];

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $query .= ' AND events.status = :status';
            $parameters['status'] = $status;
        }

        $query .= ' ORDER BY events.created_at DESC, events.id DESC';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $this->decodeEvents($statement->fetchAll());
    }

    public function countPendingForAdmin(): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*)
             FROM events
             WHERE events.status = :status AND events.deleted_at IS NULL',
        );
        $statement->execute(['status' => 'pending']);

        return (int) $statement->fetchColumn();
    }

    public function findForAdmin(int $eventId): ?array
    {
        $statement = $this->connection->prepare(
            $this->eventSelect() . ' WHERE events.id = :event_id AND events.deleted_at IS NULL LIMIT 1',
        );
        $statement->execute(['event_id' => $eventId]);

        return $this->decodeEvent($statement->fetch());
    }

    public function galleryForAdmin(int $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_gallery.id, event_gallery.event_id, event_gallery.image_path,
                    event_gallery.alt_text, event_gallery.sort_order, event_gallery.created_at
             FROM event_gallery
             INNER JOIN events ON events.id = event_gallery.event_id
             WHERE event_gallery.event_id = :event_id
               AND events.deleted_at IS NULL
             ORDER BY event_gallery.sort_order ASC, event_gallery.id ASC',
        );
        $statement->execute(['event_id' => $eventId]);
        $gallery = $statement->fetchAll();

        return is_array($gallery) ? $gallery : [];
    }

    public function transitionAdmin(int $userId, int $eventId, array $context, string $status, ?string $reason): bool
    {
        $currentStatuses = self::ADMIN_TRANSITIONS[$status] ?? null;

        if ($currentStatuses === null) {
            return false;
        }

        return $this->transactional(function () use (
            $userId,
            $eventId,
            $context,
            $status,
            $reason,
            $currentStatuses,
        ): bool {
            $approvedBy = match ($status) {
                'approved' => ':approved_by',
                'rejected' => 'NULL',
                default => 'approved_by',
            };
            $approvedAt = match ($status) {
                'approved' => 'CURRENT_TIMESTAMP',
                'rejected' => 'NULL',
                default => 'approved_at',
            };
            $publishedAt = match ($status) {
                'published' => 'CURRENT_TIMESTAMP',
                'rejected' => 'NULL',
                default => 'published_at',
            };
            $rejectionReason = $status === 'rejected' ? ':rejection_reason' : 'NULL';
            [$statusSql, $statusParameters] = $this->statusPredicate($currentStatuses);
            $statement = $this->connection->prepare(
                'UPDATE events
                 SET status = :status,
                     rejection_reason = ' . $rejectionReason . ',
                     approved_by = ' . $approvedBy . ',
                     approved_at = ' . $approvedAt . ',
                     published_at = ' . $publishedAt . ',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE events.id = :event_id
                   AND events.deleted_at IS NULL
                   AND events.status IN (' . $statusSql . ')',
            );
            $parameters = array_merge([
                'status' => $status,
                'event_id' => $eventId,
            ], $statusParameters);

            if ($status === 'approved') {
                $parameters['approved_by'] = $userId;
            }

            if ($status === 'rejected') {
                $parameters['rejection_reason'] = $reason;
            }

            $statement->execute($parameters);

            if ($statement->rowCount() === 0) {
                return false;
            }

            if ($status === 'cancelled') {
                $this->cancelParticipantFulfillment($eventId);
            }

            $this->writeActivity($userId, $eventId, $status, $context, $reason);

            return true;
        });
    }

    public function replaceGallery(int $eventId, array $images): void
    {
        $this->transactional(function () use ($eventId, $images): bool {
            $delete = $this->connection->prepare('DELETE FROM event_gallery WHERE event_id = :event_id');
            $delete->execute(['event_id' => $eventId]);

            $insert = $this->connection->prepare(
                'INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order, created_at)
                 VALUES (:event_id, :image_path, :alt_text, :sort_order, CURRENT_TIMESTAMP)',
            );

            foreach (array_slice($images, 0, 6) as $sortOrder => $image) {
                $path = is_array($image) ? ($image['image_path'] ?? $image['path'] ?? null) : $image;

                if (!is_string($path) || $path === '') {
                    continue;
                }

                $insert->execute([
                    'event_id' => $eventId,
                    'image_path' => $path,
                    'alt_text' => is_array($image) ? ($image['alt_text'] ?? null) : null,
                    'sort_order' => $sortOrder,
                ]);
            }

            return true;
        });
    }

    public function deleteGalleryImageOwned(int $userId, int $eventId, int $imageId): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT event_gallery.image_path
             FROM event_gallery
             INNER JOIN events ON events.id = event_gallery.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE organizers.user_id = :user_id
               AND events.id = :event_id
               AND events.deleted_at IS NULL
               AND event_gallery.id = :image_id
             LIMIT 1',
        );
        $statement->execute(['user_id' => $userId, 'event_id' => $eventId, 'image_id' => $imageId]);
        $path = $statement->fetchColumn();

        if (!is_string($path)) {
            return null;
        }

        $delete = $this->connection->prepare(
            'DELETE FROM event_gallery
             WHERE id = :image_id
               AND event_id = :event_id
               AND EXISTS (
                   SELECT 1 FROM events
                   INNER JOIN organizers ON organizers.id = events.organizer_id
                   WHERE events.id = :event_id_guard
                     AND events.deleted_at IS NULL
                     AND organizers.user_id = :user_id
               )',
        );
        $delete->execute([
            'image_id' => $imageId,
            'event_id' => $eventId,
            'event_id_guard' => $eventId,
            'user_id' => $userId,
        ]);

        return $delete->rowCount() === 1 ? $path : null;
    }

    private function eventSelect(?string $distanceExpression = null): string
    {
        return 'SELECT events.*, categories.name AS category_name, categories.slug AS category_slug,
                       venues.name AS venue_name, venues.address_line AS venue_address_line,
                       venues.city AS venue_city, venues.country AS venue_country,
                       venues.postal_code AS venue_postal_code, venues.latitude AS venue_latitude,
                       venues.longitude AS venue_longitude, venues.map_url AS venue_map_url,
                       organizers.organization_name, organizers.user_id AS organizer_user_id,
                       ' . ($distanceExpression ?? 'NULL') . ' AS distance_km
                FROM events
                INNER JOIN categories ON categories.id = events.category_id
                LEFT JOIN venues ON venues.id = events.venue_id
                INNER JOIN organizers ON organizers.id = events.organizer_id';
    }

    /**
     * @return array{parameters: array<string, float|int>, longitude_wraps: bool, all_longitudes: bool}|null
     */
    private function nearbyParameters(array $filters): ?array
    {
        $keys = [
            'latitude',
            'longitude',
            'latitude_min',
            'latitude_max',
            'longitude_min',
            'longitude_max',
        ];

        foreach ($keys as $key) {
            if (!isset($filters[$key]) || !is_numeric($filters[$key]) || !is_finite((float) $filters[$key])) {
                return null;
            }
        }

        $latitude = (float) $filters['latitude'];
        $longitude = (float) $filters['longitude'];
        $latitudeMin = (float) $filters['latitude_min'];
        $latitudeMax = (float) $filters['latitude_max'];
        $longitudeMin = (float) $filters['longitude_min'];
        $longitudeMax = (float) $filters['longitude_max'];

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180
            || $latitudeMin < -90 || $latitudeMax > 90 || $latitudeMin > $latitudeMax
            || $longitudeMin < -180 || $longitudeMin > 180 || $longitudeMax < -180 || $longitudeMax > 180) {
            return null;
        }

        $radius = is_numeric($filters['radius'] ?? null) ? (int) $filters['radius'] : 25;
        $radius = in_array($radius, [5, 10, 25, 50, 100], true) ? $radius : 25;
        $longitudeWraps = ($filters['longitude_wraps'] ?? false) === true;

        return [
            'parameters' => [
                'latitude_min' => $latitudeMin,
                'latitude_max' => $latitudeMax,
                'longitude_min' => $longitudeMin,
                'longitude_max' => $longitudeMax,
                'distance_latitude' => $latitude,
                'origin_latitude' => $latitude,
                'distance_longitude' => $longitude,
                'distance_radius' => $radius,
            ],
            'longitude_wraps' => $longitudeWraps,
            'all_longitudes' => !$longitudeWraps && $longitudeMin <= -180.0 && $longitudeMax >= 180.0,
        ];
    }

    private function distanceExpression(): string
    {
        return '6371.0088 * 2 * ASIN(
            SQRT(
                POWER(SIN(RADIANS(venues.latitude - :distance_latitude) / 2), 2)
                + COS(RADIANS(:origin_latitude))
                * COS(RADIANS(venues.latitude))
                * POWER(SIN(RADIANS(venues.longitude - :distance_longitude) / 2), 2)
            )
        )';
    }

    private function eventParameters(int $userId, array $attributes): array
    {
        $tags = $attributes['tags'] ?? [];

        if (is_array($tags)) {
            $tags = json_encode($tags, JSON_THROW_ON_ERROR);
        }

        return [
            'user_id' => $userId,
            'category_id' => $attributes['category_id'],
            'category_id_guard' => $attributes['category_id'],
            'venue_id' => $attributes['venue_id'] ?? null,
            'venue_id_nullable_guard' => $attributes['venue_id'] ?? null,
            'venue_id_ownership_guard' => $attributes['venue_id'] ?? null,
            'title' => $attributes['title'],
            'slug' => $attributes['slug'],
            'description' => $attributes['description'],
            'banner' => $attributes['banner'] ?? null,
            'map_url' => $attributes['map_url'] ?? null,
            'location_visibility' => $attributes['location_visibility'] ?? 'public',
            'arrival_notes' => $attributes['arrival_notes'] ?? null,
            'speaker' => $attributes['speaker'] ?? null,
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'registration_deadline' => $attributes['registration_deadline'],
            'capacity' => $attributes['capacity'],
            'available_seats' => $attributes['available_seats'],
            'ticket_price' => $attributes['ticket_price'] ?? 0,
            'currency' => $attributes['currency'] ?? 'BDT',
            'tags' => $tags,
            'is_featured' => !empty($attributes['is_featured']) ? 1 : 0,
            'waitlist_enabled' => !empty($attributes['waitlist_enabled']) ? 1 : 0,
        ];
    }

    private function ownedEventUpdateEligible(int $userId, int $eventId, array $attributes): bool
    {
        $statement = $this->connection->prepare(
            'SELECT EXISTS (
                SELECT 1
                FROM events
                INNER JOIN organizers ON organizers.id = events.organizer_id
                WHERE events.id = :eligibility_event_id
                  AND organizers.user_id = :eligibility_user_id
                  AND events.deleted_at IS NULL
                  AND events.status IN (\'draft\', \'rejected\')
                  AND EXISTS (
                      SELECT 1 FROM categories
                      WHERE categories.id = :eligibility_category_id
                  )
                  AND (:eligibility_venue_nullable IS NULL OR EXISTS (
                      SELECT 1 FROM venues
                      WHERE venues.id = :eligibility_venue_id
                        AND venues.organizer_id = events.organizer_id
                  ))
            )',
        );
        $venueId = $attributes['venue_id'] ?? null;
        $statement->execute([
            'eligibility_event_id' => $eventId,
            'eligibility_user_id' => $userId,
            'eligibility_category_id' => $attributes['category_id'],
            'eligibility_venue_nullable' => $venueId,
            'eligibility_venue_id' => $venueId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function dateRange(string $date): array
    {
        $today = new \DateTimeImmutable('today');

        if ($date === 'this_week') {
            $start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
            $end = $start->modify('+7 days');
        } elseif ($date === 'this_month') {
            $start = $today->modify('first day of this month');
            $end = $start->modify('first day of next month');
        } else {
            $start = $today;
            $end = $start->modify('+1 day');
        }

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    private function decodeEvents(mixed $events): array
    {
        if (!is_array($events)) {
            return [];
        }

        return array_map(fn (array $event): array => $this->decodeEvent($event) ?? [], $events);
    }

    private function decodeEvent(mixed $event): ?array
    {
        if (!is_array($event)) {
            return null;
        }

        $tags = $event['tags'] ?? null;
        $decoded = is_string($tags) ? json_decode($tags, true) : null;
        $event['tags'] = is_array($decoded) ? $decoded : [];

        return $event;
    }

    private function writeActivity(int $userId, int $eventId, string $status, array $context, ?string $reason): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO activity_logs
                (user_id, action, subject_type, subject_id, description, properties, ip_address, user_agent, created_at)
             VALUES
                (:user_id, :action, :subject_type, :subject_id, :description, :properties, :ip_address, :user_agent, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => 'event.' . $status,
            'subject_type' => 'event',
            'subject_id' => $eventId,
            'description' => 'Event status changed to ' . $status . '.',
            'properties' => json_encode(['status' => $status, 'reason' => $reason], JSON_THROW_ON_ERROR),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ]);
    }

    private function cancelParticipantFulfillment(int $eventId): void
    {
        $tickets = $this->connection->prepare(
            "UPDATE tickets
             SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
             WHERE status = 'valid'
               AND registration_id IN (
                   SELECT id FROM registrations
                   WHERE event_id = :ticket_event_id
                     AND status IN ('pending', 'confirmed')
               )",
        );
        $tickets->execute(['ticket_event_id' => $eventId]);

        $payments = $this->connection->prepare(
            "UPDATE payments
             SET status = 'failed', updated_at = CURRENT_TIMESTAMP
             WHERE status = 'pending'
               AND registration_id IN (
                   SELECT id FROM registrations
                   WHERE event_id = :payment_event_id
                     AND status IN ('pending', 'confirmed')
               )",
        );
        $payments->execute(['payment_event_id' => $eventId]);

        $registrations = $this->connection->prepare(
            "UPDATE registrations
             SET status = 'cancelled',
                 cancelled_at = CURRENT_TIMESTAMP,
                 cancellation_reason = 'Event cancelled',
                 updated_at = CURRENT_TIMESTAMP
             WHERE event_id = :registration_event_id
               AND status IN ('pending', 'confirmed')",
        );
        $registrations->execute(['registration_event_id' => $eventId]);

        $event = $this->connection->prepare(
            'UPDATE events SET available_seats = capacity, updated_at = CURRENT_TIMESTAMP WHERE id = :capacity_event_id',
        );
        $event->execute(['capacity_event_id' => $eventId]);
    }

    private function statusPredicate(array $statuses): array
    {
        $placeholders = [];
        $parameters = [];

        foreach (array_values($statuses) as $index => $status) {
            $name = 'current_status_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $status;
        }

        return [implode(', ', $placeholders), $parameters];
    }

    private function transactional(callable $callback): mixed
    {
        $started = !$this->connection->inTransaction();

        if ($started) {
            $this->connection->beginTransaction();
        }

        try {
            $result = $callback();

            if ($started) {
                $this->connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
