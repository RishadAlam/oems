<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\EventRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use PDOStatement;

final class EventRepositoryRecordingPdo extends PDO
{
    public array $preparedQueries = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedQueries[] = $query;

        return parent::prepare($query, $options);
    }
}

final class EventRepositoryZeroChangedRowsStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function rowCount(): int
    {
        return 0;
    }
}

final class EventRepositoryTest extends TestCase
{
    private PDO $connection;

    private EventRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new EventRepositoryRecordingPdo('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedEvents();
        $this->repository = new EventRepository($this->connection);
    }

    public function testPublicSearchCombinesCategoryCityFreeAndSoonestFilters(): void
    {
        $events = $this->repository->publicSearch([
            'search' => 'summit',
            'category' => 'technology',
            'city' => 'Dhaka',
            'date' => 'upcoming',
            'price' => 'free',
            'sort' => 'soonest',
        ]);

        $this->assertSame(['free-dhaka-summit'], array_column($events, 'slug'));
    }

    public function testPublicTextSearchCoversEveryJoinedDiscoveryField(): void
    {
        foreach ([
            'Needle Title',
            'Needle Description',
            'Needle Speaker',
            'Needle Organization',
            'Needle Category',
            'Needle Venue',
            'Needle City',
        ] as $search) {
            $this->assertSame(
                ['needle-search-event'],
                array_column($this->repository->publicSearch(['search' => $search]), 'slug'),
            );
        }
    }

    public function testTextSearchUsesUniqueBindingsUnderNativePrepareContract(): void
    {
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $events = $this->repository->publicSearch(['search' => 'Needle Venue']);
        $query = $this->connection->preparedQueries[array_key_last($this->connection->preparedQueries)];
        preg_match_all('/:(search_[a-z]+)/', $query, $matches);

        $this->assertSame(['needle-search-event'], array_column($events, 'slug'));
        $this->assertSame(
            [
                'search_title',
                'search_description',
                'search_speaker',
                'search_organizer',
                'search_category',
                'search_venue',
                'search_city',
            ],
            $matches[1],
        );
        $this->assertSame(count($matches[1]), count(array_unique($matches[1])));
    }

    public function testEventWritesUseUniqueBindingsUnderNativePrepareContract(): void
    {
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $this->repository->createForUser(10, $this->eventAttributes('native-create'));
        $createQuery = $this->connection->preparedQueries[array_key_last($this->connection->preparedQueries)];
        preg_match_all('/:(\w+)/', $createQuery, $createBindings);

        $this->repository->updateOwned(10, 502, $this->eventAttributes('native-update'));
        $updateQuery = $this->connection->preparedQueries[array_key_last($this->connection->preparedQueries)];
        preg_match_all('/:(\w+)/', $updateQuery, $updateBindings);

        $this->assertSame(count($createBindings[1]), count(array_unique($createBindings[1])));
        $this->assertSame(count($updateBindings[1]), count(array_unique($updateBindings[1])));
    }

    public function testPublicDateFiltersImplementTodayWeekMonthAndUpcomingFallback(): void
    {
        $today = array_column($this->repository->publicSearch(['date' => 'today']), 'slug');
        $thisWeek = array_column($this->repository->publicSearch(['date' => 'this_week']), 'slug');
        $thisMonth = array_column($this->repository->publicSearch(['date' => 'this_month']), 'slug');
        $fallback = array_column($this->repository->publicSearch(['date' => 'not-supported']), 'slug');

        $this->assertTrue(in_array('today-event', $today, true));
        $this->assertTrue(in_array('today-event', $thisWeek, true));
        $this->assertFalse(in_array('next-week-event', $thisWeek, true));
        $this->assertTrue(in_array('today-event', $thisMonth, true));
        $this->assertFalse(in_array('next-month-event', $thisMonth, true));
        $this->assertTrue(in_array('free-dhaka-summit', $fallback, true));
        $this->assertFalse(in_array('past-dhaka-summit', $fallback, true));
    }

    public function testPublicQueriesExcludeUnpublishedDeletedAndPastEventsAndDecodeTags(): void
    {
        $featured = $this->repository->featured(4);
        $cities = $this->repository->publicCities();
        $event = $this->repository->findPublishedBySlug('free-dhaka-summit');

        $this->assertSame(
            ['free-dhaka-summit', 'paid-chittagong-summit', 'today-event', 'needle-search-event'],
            array_column($featured, 'slug'),
        );
        $this->assertSame(['Chittagong', 'Dhaka', 'Needle City'], $cities);
        $this->assertNotNull($event);
        $this->assertSame(['php', 'community'], $event['tags']);
        $this->assertNull($this->repository->findPublishedBySlug('draft-dhaka-summit'));
        $this->assertSame(
            ['today-event', 'free-dhaka-summit', 'paid-chittagong-summit', 'needle-search-event', 'next-week-event', 'next-month-event'],
            array_column($this->repository->publicSearch(['sort' => 'injected SQL']), 'slug'),
        );
    }

    public function testPublicQueriesExcludeInactiveCategoriesAndFeaturedFallsBackWithoutDuplicates(): void
    {
        $this->assertSame(
            ['free-dhaka-summit', 'paid-chittagong-summit', 'today-event'],
            array_column($this->repository->featured(3), 'slug'),
        );

        $this->connection->exec('UPDATE categories SET is_active = 0 WHERE id = 1');

        $this->assertSame(['needle-search-event'], array_column($this->repository->featured(3), 'slug'));
        $this->assertSame(['needle-search-event'], array_column($this->repository->publicSearch([]), 'slug'));
        $this->assertSame(['Needle City'], $this->repository->publicCities());
        $this->assertNull($this->repository->findPublishedBySlug('free-dhaka-summit'));
        $this->assertSame([], $this->repository->gallery(501));
    }

    public function testGalleryReturnsImagesOnlyForPublishedNonDeletedEventsInStoredOrder(): void
    {
        $gallery = $this->repository->gallery(501);

        $this->assertSame(['summit-cover.jpg', 'summit-stage.jpg'], array_column($gallery, 'image_path'));
        $this->assertSame([], $this->repository->gallery(502));
        $this->assertSame([], $this->repository->gallery(505));
        $this->assertSame([], $this->repository->gallery(999));
    }

    public function testOrganizerListsSummaryAndOwnershipAreScopedToAuthenticatedUser(): void
    {
        $summary = $this->repository->organizerSummary(10);
        $events = $this->repository->forOrganizerUser(10, 'draft');
        $owned = $this->repository->findOwned(10, 502);

        $this->assertSame(7, $summary['total']);
        $this->assertSame(1, $summary['draft']);
        $this->assertSame(['draft-dhaka-summit'], array_column($events, 'slug'));
        $this->assertNotNull($owned);
        $this->assertSame('draft-dhaka-summit', $owned['slug']);
        $this->assertNull($this->repository->findOwned(20, 502));
    }

    public function testRecentOrganizerEventsUseUpdatedOrderLimitAndUserScope(): void
    {
        $this->connection->exec(
            "UPDATE events
             SET updated_at = CASE id
                 WHEN 501 THEN '2026-08-01 10:00:00'
                 WHEN 502 THEN '2026-08-04 10:00:00'
                 WHEN 503 THEN '2026-08-10 10:00:00'
                 WHEN 504 THEN '2026-08-03 10:00:00'
                 WHEN 505 THEN '2026-08-09 10:00:00'
                 WHEN 506 THEN '2026-08-02 10:00:00'
                 WHEN 507 THEN '2026-08-05 10:00:00'
                 WHEN 508 THEN '2026-08-01 10:00:00'
                 WHEN 509 THEN '2026-08-05 10:00:00'
                 ELSE updated_at
             END",
        );

        $events = $this->repository->recentForOrganizerUser(10, 3);

        $this->assertSame([509, 507, 502], array_column($events, 'id'));
        $this->assertSame(3, count($events));
        $this->assertSame([], $this->repository->recentForOrganizerUser(20, 0));
    }

    public function testOrganizerCannotLoadOrUpdateAnotherOrganizersEvent(): void
    {
        $this->assertNull($this->repository->findOwned(20, 502));
        $this->assertFalse($this->repository->updateOwned(20, 502, $this->eventAttributes()));
        $this->assertSame('Draft Dhaka Summit', $this->eventTitle(502));
    }

    public function testOrganizerCanCreateUpdateTransitionAndSoftDeleteOnlyOwnedEvents(): void
    {
        $id = $this->repository->createForUser(10, $this->eventAttributes('created-event'));

        $this->assertNotNull($id);
        $this->assertTrue($this->repository->slugExists('created-event', null));
        $this->assertFalse($this->repository->slugExists('created-event', $id));
        $this->assertNull($this->repository->createForUser(999, $this->eventAttributes('missing-organizer')));
        $this->assertTrue($this->repository->updateOwned(10, $id, $this->eventAttributes('updated-event', 'Updated Event')));
        $this->assertSame('Updated Event', $this->eventTitle($id));
        $this->assertTrue($this->repository->transitionOwned(10, $id, $this->auditContext(), 'pending'));
        $this->assertSame('pending', $this->eventValue($id, 'status'));
        $this->assertFalse($this->repository->softDeleteOwned(10, $id, $this->auditContext()));
        $this->connection->exec("UPDATE events SET status = 'cancelled' WHERE id = " . (int) $id);
        $this->assertTrue($this->repository->softDeleteOwned(10, $id, $this->auditContext()));
        $this->assertNull($this->repository->findOwned(10, $id));
        $this->assertSame(1, $this->activityCountFor($id));
    }

    public function testSoftDeleteUsesEligibleStatusAsAnAtomicGuard(): void
    {
        $this->connection->exec("UPDATE events SET status = 'pending' WHERE id = 502");

        $this->assertFalse($this->repository->softDeleteOwned(10, 502, $this->auditContext()));
        $this->assertNull($this->eventValue(502, 'deleted_at'));

        $this->connection->exec("UPDATE events SET status = 'rejected' WHERE id = 502");

        $this->assertTrue($this->repository->softDeleteOwned(10, 502, $this->auditContext()));
        $this->assertNotNull($this->eventValue(502, 'deleted_at'));
    }

    public function testRejectedEventMustBeSavedAsDraftBeforeResubmission(): void
    {
        $this->connection->exec(
            "UPDATE events SET status = 'rejected', rejection_reason = 'Clarify the schedule.' WHERE id = 502",
        );

        $this->assertFalse($this->repository->transitionOwned(10, 502, $this->auditContext(), 'pending'));
        $this->assertTrue($this->repository->updateOwned(10, 502, $this->eventAttributes()));
        $this->assertSame('draft', $this->eventValue(502, 'status'));
        $this->assertNull($this->eventValue(502, 'rejection_reason'));
        $this->assertTrue($this->repository->transitionOwned(10, 502, $this->auditContext(), 'pending'));
    }

    public function testSubmissionAtomicallyRequiresAnApprovedOrganizer(): void
    {
        $this->connection->exec("UPDATE organizers SET approval_status = 'pending' WHERE user_id = 10");

        $this->assertFalse($this->repository->transitionOwned(10, 502, $this->auditContext(), 'pending'));
        $this->assertSame('draft', $this->eventValue(502, 'status'));
        $this->assertSame(0, $this->activityCountFor(502));

        $query = $this->connection->preparedQueries[array_key_last($this->connection->preparedQueries)];
        preg_match_all('/:(\w+)/', $query, $bindings);

        $this->assertTrue(str_contains($query, 'organizers.approval_status = :organizer_approval_status'));
        $this->assertSame(count($bindings[1]), count(array_unique($bindings[1])));

        $this->connection->exec("UPDATE organizers SET approval_status = 'approved' WHERE user_id = 10");

        $this->assertTrue($this->repository->transitionOwned(10, 502, $this->auditContext(), 'pending'));
        $this->assertSame('pending', $this->eventValue(502, 'status'));
    }

    public function testIdenticalOwnedEventUpdateSucceedsWhenDriverReportsZeroChangedRows(): void
    {
        $attributes = $this->eventAttributes('no-op-event', 'No-op Event');
        $eventId = $this->repository->createForUser(10, $attributes);
        $this->assertNotNull($eventId);
        $this->connection->setAttribute(
            PDO::ATTR_STATEMENT_CLASS,
            [EventRepositoryZeroChangedRowsStatement::class],
        );

        $this->assertTrue($this->repository->updateOwned(10, (int) $eventId, $attributes));
        $this->assertFalse($this->repository->updateOwned(20, (int) $eventId, $attributes));

        $this->connection->exec("UPDATE events SET status = 'pending' WHERE id = " . (int) $eventId);
        $this->assertFalse($this->repository->updateOwned(10, (int) $eventId, $attributes));
    }

    public function testAtomicCreatePersistsTheEventAndGalleryTogether(): void
    {
        $id = $this->repository->createWithGalleryForUser(
            10,
            $this->eventAttributes('atomic-event', 'Atomic Event'),
            ['atomic-one.jpg', 'atomic-two.jpg'],
        );

        $this->assertNotNull($id);
        $this->assertSame('Atomic Event', $this->eventTitle((int) $id));
        $this->assertSame(['atomic-one.jpg', 'atomic-two.jpg'], $this->storedGalleryPaths((int) $id));
    }

    public function testAtomicCreateRollsBackTheEventAndSlugWhenGalleryInsertFails(): void
    {
        $this->connection->exec(
            "CREATE TRIGGER fail_atomic_create_gallery
             BEFORE INSERT ON event_gallery
             WHEN NEW.image_path = 'explode.jpg'
             BEGIN SELECT RAISE(ABORT, 'gallery failed'); END",
        );

        try {
            $this->repository->createWithGalleryForUser(
                10,
                $this->eventAttributes('rolled-back-event', 'Rolled Back Event'),
                ['safe.jpg', 'explode.jpg'],
            );
            $this->assertTrue(false, 'Expected the gallery insert to fail.');
        } catch (\PDOException) {
            $this->assertFalse($this->repository->slugExists('rolled-back-event', null));
            $this->assertSame([], $this->storedGalleryPathsForSlug('rolled-back-event'));
        }
    }

    public function testAtomicUpdateReturnsPriorMediaOnlyAfterReplacingEventAndGallery(): void
    {
        $this->connection->exec("UPDATE events SET banner = 'old-banner.jpg' WHERE id = 502");
        $attributes = $this->eventAttributes('atomic-update', 'Atomic Update');
        $attributes['banner'] = 'new-banner.jpg';

        $prior = $this->repository->updateWithGalleryOwned(
            10,
            502,
            $attributes,
            ['draft-only.jpg', 'new-gallery.jpg'],
        );

        $this->assertSame([
            'banner' => 'old-banner.jpg',
            'gallery' => ['draft-only.jpg'],
        ], $prior);
        $this->assertSame('Atomic Update', $this->eventTitle(502));
        $this->assertSame('new-banner.jpg', $this->eventValue(502, 'banner'));
        $this->assertSame(['draft-only.jpg', 'new-gallery.jpg'], $this->storedGalleryPaths(502));
    }

    public function testAtomicUpdateLeavesOldEventAndGalleryUntouchedWhenGalleryInsertFails(): void
    {
        $this->connection->exec("UPDATE events SET banner = 'old-banner.jpg' WHERE id = 502");
        $this->connection->exec(
            "CREATE TRIGGER fail_atomic_update_gallery
             BEFORE INSERT ON event_gallery
             WHEN NEW.image_path = 'explode.jpg'
             BEGIN SELECT RAISE(ABORT, 'gallery failed'); END",
        );
        $attributes = $this->eventAttributes('failed-atomic-update', 'Failed Atomic Update');
        $attributes['banner'] = 'new-banner.jpg';

        try {
            $this->repository->updateWithGalleryOwned(10, 502, $attributes, ['explode.jpg']);
            $this->assertTrue(false, 'Expected the gallery insert to fail.');
        } catch (\PDOException) {
            $this->assertSame('Draft Dhaka Summit', $this->eventTitle(502));
            $this->assertSame('old-banner.jpg', $this->eventValue(502, 'banner'));
            $this->assertSame(['draft-only.jpg'], $this->storedGalleryPaths(502));
            $this->assertFalse($this->repository->slugExists('failed-atomic-update', null));
        }
    }

    public function testTransitionsUseCurrentStatusAsAnAtomicCompareAndSetGuard(): void
    {
        $this->connection->exec("UPDATE events SET status = 'pending' WHERE id = 502");
        $this->connection->exec("UPDATE events SET status = 'approved' WHERE id = 509");

        $owned = $this->repository->transitionOwned(10, 502, $this->auditContext(), 'pending');
        $admin = $this->repository->transitionAdmin(77, 509, $this->auditContext(), 'approved', null);

        $this->assertFalse($owned);
        $this->assertFalse($admin);
        $this->assertSame('pending', $this->eventValue(502, 'status'));
        $this->assertSame('approved', $this->eventValue(509, 'status'));
        $this->assertSame(0, $this->activityCountFor(502));
        $this->assertSame(0, $this->activityCountFor(509));
    }

    public function testAdminTransitionsPreserveAndClearLifecycleMetadataAsAppropriate(): void
    {
        $events = $this->repository->forAdmin('pending');
        $this->connection->exec("UPDATE events SET status = 'pending' WHERE id = 502");
        $event = $this->repository->findForAdmin(502);

        $this->assertSame(['pending-review-event'], array_column($events, 'slug'));
        $this->assertNotNull($event);
        $this->assertTrue($this->repository->transitionAdmin(77, 502, $this->auditContext(), 'approved', null));
        $approvedAt = $this->eventValue(502, 'approved_at');
        $this->assertSame('approved', $this->eventValue(502, 'status'));
        $this->assertSame(77, $this->eventValue(502, 'approved_by'));
        $this->assertNotNull($approvedAt);
        $this->assertNull($this->eventValue(502, 'published_at'));
        $this->assertTrue($this->repository->transitionAdmin(88, 502, $this->auditContext(), 'published', null));
        $this->assertSame('published', $this->eventValue(502, 'status'));
        $this->assertSame(77, $this->eventValue(502, 'approved_by'));
        $this->assertSame($approvedAt, $this->eventValue(502, 'approved_at'));
        $this->assertNotNull($this->eventValue(502, 'published_at'));
        $this->assertTrue($this->repository->transitionAdmin(88, 502, $this->auditContext(), 'completed', null));
        $this->assertSame(77, $this->eventValue(502, 'approved_by'));
        $this->assertSame($approvedAt, $this->eventValue(502, 'approved_at'));
        $this->assertNotNull($this->eventValue(502, 'published_at'));
        $this->connection->exec("UPDATE events SET approved_by = 77, approved_at = '2026-01-01 10:00:00', published_at = '2026-01-02 10:00:00' WHERE id = 509");
        $this->assertTrue($this->repository->transitionAdmin(88, 509, $this->auditContext(), 'rejected', 'Incomplete venue details'));
        $this->assertSame('Incomplete venue details', $this->eventValue(509, 'rejection_reason'));
        $this->assertNull($this->eventValue(509, 'approved_by'));
        $this->assertNull($this->eventValue(509, 'approved_at'));
        $this->assertNull($this->eventValue(509, 'published_at'));
        $this->assertTrue($this->repository->transitionAdmin(88, 503, $this->auditContext(), 'cancelled', null));
        $this->assertSame(55, $this->eventValue(503, 'approved_by'));
        $this->assertSame('2026-01-03 10:00:00', $this->eventValue(503, 'approved_at'));
        $this->assertSame('2026-01-04 10:00:00', $this->eventValue(503, 'published_at'));
    }

    public function testAdminTransitionRollsBackWhenAuditWriteFails(): void
    {
        $this->connection->exec("CREATE TRIGGER reject_event_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit failed'); END");

        try {
            $this->repository->transitionAdmin(77, 509, $this->auditContext(), 'approved', null);
            $this->assertTrue(false, 'Expected the audit insert to fail.');
        } catch (\PDOException) {
            $this->assertSame('pending', $this->eventValue(509, 'status'));
            $this->assertNull($this->eventValue(509, 'approved_by'));
            $this->assertNull($this->eventValue(509, 'approved_at'));
        }
    }

    public function testGalleryReplacementCapsImagesAndDeletionRequiresOwnership(): void
    {
        $this->connection->exec("UPDATE events SET status = 'published' WHERE id = 502");
        $this->repository->replaceGallery(502, [
            ['image_path' => 'one.jpg', 'alt_text' => 'One'],
            ['image_path' => 'two.jpg', 'alt_text' => 'Two'],
            ['image_path' => 'three.jpg', 'alt_text' => 'Three'],
            ['image_path' => 'four.jpg', 'alt_text' => 'Four'],
            ['image_path' => 'five.jpg', 'alt_text' => 'Five'],
            ['image_path' => 'six.jpg', 'alt_text' => 'Six'],
            ['image_path' => 'seven.jpg', 'alt_text' => 'Seven'],
        ]);

        $gallery = $this->repository->gallery(502);
        $imageId = (int) $gallery[1]['id'];

        $this->assertSame(['one.jpg', 'two.jpg', 'three.jpg', 'four.jpg', 'five.jpg', 'six.jpg'], array_column($gallery, 'image_path'));
        $this->assertNull($this->repository->deleteGalleryImageOwned(20, 502, $imageId));
        $this->assertSame('two.jpg', $this->repository->deleteGalleryImageOwned(10, 502, $imageId));
        $this->assertSame(['one.jpg', 'three.jpg', 'four.jpg', 'five.jpg', 'six.jpg'], array_column($this->repository->gallery(502), 'image_path'));
    }

    public function testAdministratorGalleryIncludesPendingMediaButExcludesDeletedEvents(): void
    {
        $this->assertSame(
            ['draft-only.jpg'],
            array_column($this->repository->galleryForAdmin(502), 'image_path'),
        );
        $this->assertSame([], $this->repository->galleryForAdmin(505));
        $this->assertSame([], $this->repository->galleryForAdmin(999));
    }

    public function testOrganizerGalleryIsOwnerScopedAndExcludesDeletedEvents(): void
    {
        $this->assertSame(
            ['draft-only.jpg'],
            array_column($this->repository->galleryForOwned(10, 502), 'image_path'),
        );
        $this->assertSame([], $this->repository->galleryForOwned(20, 502));
        $this->assertSame([], $this->repository->galleryForOwned(10, 505));
    }

    public function testPendingModerationCountUsesAnAggregateAndExcludesDeletedEvents(): void
    {
        $this->assertSame(1, $this->repository->countPendingForAdmin());
        $query = $this->connection->preparedQueries[array_key_last($this->connection->preparedQueries)];
        $this->assertTrue(str_contains($query, 'SELECT COUNT(*)'));
        $this->assertFalse(str_contains($query, 'events.title'));
        $this->connection->exec("UPDATE events SET deleted_at = CURRENT_TIMESTAMP WHERE id = 509");
        $this->assertSame(0, $this->repository->countPendingForAdmin());
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL DEFAULT "pending")');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, is_active INTEGER NOT NULL DEFAULT 1)');
        $this->connection->exec('CREATE TABLE venues (id INTEGER PRIMARY KEY, organizer_id INTEGER NULL, name TEXT NOT NULL, city TEXT NOT NULL, country TEXT NOT NULL)');
        $this->connection->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organizer_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                venue_id INTEGER NULL,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT NOT NULL,
                banner TEXT NULL,
                map_url TEXT NULL,
                speaker TEXT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                registration_deadline TEXT NOT NULL,
                capacity INTEGER NOT NULL,
                available_seats INTEGER NOT NULL,
                ticket_price REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT "BDT",
                tags TEXT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                rejection_reason TEXT NULL,
                approved_by INTEGER NULL,
                approved_at TEXT NULL,
                published_at TEXT NULL,
                is_featured INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
        $this->connection->exec('CREATE TABLE event_gallery (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, image_path TEXT NOT NULL, alt_text TEXT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action TEXT NOT NULL, subject_type TEXT NULL, subject_id INTEGER NULL, description TEXT NOT NULL, properties TEXT NULL, ip_address TEXT NULL, user_agent TEXT NULL, created_at TEXT NOT NULL)');
    }

    private function seedEvents(): void
    {
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status) VALUES (1, 10, 'First organization', 'approved'), (2, 20, 'Second organization', 'approved'), (3, 30, 'Needle Organization', 'approved')");
        $this->connection->exec("INSERT INTO categories (id, name, slug, is_active) VALUES (1, 'Technology', 'technology', 1), (2, 'Arts', 'arts', 1), (3, 'Needle Category', 'needle-category', 1)");
        $this->connection->exec("INSERT INTO venues (id, organizer_id, name, city, country) VALUES (1, 1, 'Dhaka Hall', 'Dhaka', 'Bangladesh'), (2, 2, 'Chittagong Hall', 'Chittagong', 'Bangladesh'), (3, 3, 'Needle Venue', 'Needle City', 'Bangladesh')");
        $this->connection->exec(
            "INSERT INTO events
                (id, organizer_id, category_id, venue_id, title, slug, description, speaker, start_date, end_date, registration_deadline, capacity, available_seats, ticket_price, currency, tags, status, is_featured, created_at, updated_at, deleted_at)
             VALUES
                (501, 1, 1, 1, 'Free Dhaka Summit', 'free-dhaka-summit', 'A free summit about PHP.', 'Ada', datetime('now', '+2 days'), datetime('now', '+2 days', '+2 hours'), datetime('now', '+1 day'), 100, 100, 0, 'BDT', '[\"php\",\"community\"]', 'published', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (502, 1, 1, 1, 'Draft Dhaka Summit', 'draft-dhaka-summit', 'Waiting for moderation.', 'Bea', datetime('now', '+4 days'), datetime('now', '+4 days', '+2 hours'), datetime('now', '+3 days'), 100, 99, 0, 'BDT', '{bad json', 'draft', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (503, 2, 1, 2, 'Paid Chittagong Summit', 'paid-chittagong-summit', 'A paid summit about PHP.', 'Cam', datetime('now', '+3 days'), datetime('now', '+3 days', '+2 hours'), datetime('now', '+2 days'), 50, 50, 500, 'BDT', '[]', 'published', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (504, 1, 2, 1, 'Past Dhaka Summit', 'past-dhaka-summit', 'Already happened.', 'Dan', datetime('now', '-3 days'), datetime('now', '-3 days', '+2 hours'), datetime('now', '-4 days'), 60, 60, 0, 'BDT', '[]', 'published', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (505, 1, 1, 1, 'Deleted Dhaka Summit', 'deleted-dhaka-summit', 'No longer available.', 'Eve', datetime('now', '+5 days'), datetime('now', '+5 days', '+2 hours'), datetime('now', '+4 days'), 60, 60, 0, 'BDT', '[]', 'published', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (506, 1, 1, 1, 'Today Event', 'today-event', 'Happening later today.', 'Fay', datetime('now', 'start of day', '+23 hours'), datetime('now', 'start of day', '+23 hours', '+1 hour'), datetime('now', 'start of day', '+22 hours'), 60, 60, 0, 'BDT', '[]', 'published', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (507, 1, 1, 1, 'Next Week Event', 'next-week-event', 'Future weekly boundary.', 'Gia', datetime('now', '+10 days'), datetime('now', '+10 days', '+1 hour'), datetime('now', '+9 days'), 60, 60, 0, 'BDT', '[]', 'published', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (508, 1, 1, 1, 'Next Month Event', 'next-month-event', 'Future monthly boundary.', 'Hal', datetime('now', '+45 days'), datetime('now', '+45 days', '+1 hour'), datetime('now', '+44 days'), 60, 60, 0, 'BDT', '[]', 'published', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (509, 1, 1, 1, 'Pending Review Event', 'pending-review-event', 'Needs review.', 'Ian', datetime('now', '+7 days'), datetime('now', '+7 days', '+1 hour'), datetime('now', '+6 days'), 60, 60, 0, 'BDT', '[]', 'pending', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
                (510, 3, 3, 3, 'Needle Title', 'needle-search-event', 'Needle Description', 'Needle Speaker', datetime('now', '+6 days'), datetime('now', '+6 days', '+1 hour'), datetime('now', '+5 days'), 60, 60, 0, 'BDT', '[]', 'published', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)"
        );
        $this->connection->exec("UPDATE events SET approved_by = 55, approved_at = '2026-01-03 10:00:00', published_at = '2026-01-04 10:00:00' WHERE id = 503");
        $this->connection->exec("INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order, created_at) VALUES (501, 'summit-stage.jpg', 'Stage', 2, CURRENT_TIMESTAMP), (501, 'summit-cover.jpg', 'Cover', 1, CURRENT_TIMESTAMP), (502, 'draft-only.jpg', 'Draft', 1, CURRENT_TIMESTAMP), (505, 'deleted-only.jpg', 'Deleted', 1, CURRENT_TIMESTAMP)");
    }

    private function eventAttributes(string $slug = 'new-event', string $title = 'New Event'): array
    {
        return [
            'category_id' => 1,
            'venue_id' => 1,
            'title' => $title,
            'slug' => $slug,
            'description' => 'A test event.',
            'banner' => 'banner.jpg',
            'map_url' => 'https://maps.example.test/event',
            'speaker' => 'Test Speaker',
            'start_date' => '2030-01-10 10:00:00',
            'end_date' => '2030-01-10 12:00:00',
            'registration_deadline' => '2030-01-09 10:00:00',
            'capacity' => 120,
            'available_seats' => 120,
            'ticket_price' => 250,
            'currency' => 'BDT',
            'tags' => ['php', 'testing'],
            'is_featured' => false,
        ];
    }

    private function auditContext(): array
    {
        return [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'OEMS repository test',
        ];
    }

    private function eventTitle(int $eventId): string
    {
        return (string) $this->eventValue($eventId, 'title');
    }

    private function eventValue(int $eventId, string $column): mixed
    {
        return $this->connection->query("SELECT {$column} FROM events WHERE id = {$eventId}")->fetchColumn();
    }

    private function activityCountFor(int $eventId): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM activity_logs WHERE subject_id = {$eventId}")->fetchColumn();
    }

    private function storedGalleryPaths(int $eventId): array
    {
        $statement = $this->connection->query(
            "SELECT image_path FROM event_gallery WHERE event_id = {$eventId} ORDER BY sort_order ASC, id ASC",
        );

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function storedGalleryPathsForSlug(string $slug): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_gallery.image_path
             FROM event_gallery
             INNER JOIN events ON events.id = event_gallery.event_id
             WHERE events.slug = :slug
             ORDER BY event_gallery.sort_order ASC, event_gallery.id ASC',
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}
