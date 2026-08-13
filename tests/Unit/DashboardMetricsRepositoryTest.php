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
        $connection->exec("CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, approval_status TEXT NOT NULL DEFAULT 'pending')");
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec("INSERT INTO users (deleted_at) VALUES (NULL), (NULL), ('2026-08-01 09:00:00')");
        $connection->exec('INSERT INTO organizers (user_id) VALUES (1), (3)');
        $connection->exec("INSERT INTO events (deleted_at) VALUES (NULL), (NULL), (NULL), ('2026-08-02 09:00:00')");

        $repository = new DashboardMetricsRepository($connection);

        $this->assertSame([
            'users' => 2,
            'organizers' => 1,
            'events' => 3,
            'pending_organizers' => 1,
        ], $repository->totals());
    }

    public function testOrganizerApprovalProjectionsAreScopedOrderedAndExcludeDeletedAccounts(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL, status TEXT NOT NULL, email_verified_at TEXT NULL, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL, rejection_reason TEXT NULL, created_at TEXT NOT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec("INSERT INTO users (id, name, email, status, email_verified_at, deleted_at) VALUES
            (7, 'New Organizer', 'new@example.test', 'active', NULL, NULL),
            (8, 'Ready Organizer', 'ready@example.test', 'active', '2026-08-09 09:00:00', NULL),
            (9, 'Deleted Organizer', 'deleted@example.test', 'active', '2026-08-08 09:00:00', '2026-08-11 09:00:00'),
            (10, 'Approved Organizer', 'approved@example.test', 'active', '2026-08-07 09:00:00', NULL)");
        $connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status, rejection_reason, created_at) VALUES
            (10, 7, 'New Community', 'pending', NULL, '2026-08-12 09:00:00'),
            (12, 8, 'Ready Community', 'pending', NULL, '2026-08-10 09:00:00'),
            (13, 9, 'Deleted Community', 'pending', NULL, '2026-08-09 09:00:00'),
            (14, 10, 'Approved Community', 'approved', NULL, '2026-08-08 09:00:00')");

        $repository = new DashboardMetricsRepository($connection);
        $approval = $repository->organizerApprovalForUser(7);
        $queue = $repository->pendingOrganizerApplications(2);

        $this->assertSame('pending', $approval['approval_status']);
        $this->assertSame('New Community', $approval['organization_name']);
        $this->assertSame(null, $approval['email_verified_at']);
        $this->assertSame(2, $repository->totals()['pending_organizers']);
        $this->assertSame([12, 10], array_column($queue, 'id'));
        $this->assertSame('Ready Organizer', $queue[0]['contact_name']);
        $this->assertSame([], $repository->organizerApprovalForUser(999));
    }

    public function testReviewSummariesUseNarrowSqlAggregatesWithParticipantOrganizerAndAdminScope(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, organizer_reply TEXT NULL)');
        $connection->exec("INSERT INTO users (id, deleted_at) VALUES (1, NULL), (2, NULL), (3, NULL), (4, '2026-08-01 00:00:00')");
        $connection->exec('INSERT INTO organizers (id, user_id) VALUES (10, 2), (11, 3)');
        $connection->exec("INSERT INTO events (id, organizer_id, deleted_at) VALUES (20, 10, NULL), (21, 11, NULL), (22, 10, '2026-08-02 00:00:00')");
        $connection->exec("INSERT INTO reviews (id, event_id, user_id, status, organizer_reply) VALUES
            (1, 20, 1, 'pending', NULL),
            (2, 20, 1, 'published', NULL),
            (3, 20, 3, 'published', 'Thanks'),
            (4, 21, 1, 'published', NULL),
            (5, 22, 1, 'pending', NULL),
            (6, 20, 4, 'published', NULL)");

        $repository = new DashboardMetricsRepository($connection);

        $this->assertSame(
            ['submitted' => 3, 'pending' => 1, 'published' => 2],
            $repository->reviewsForParticipant(1),
        );
        $this->assertSame(
            ['published' => 2, 'awaiting_reply' => 1],
            $repository->reviewsForOrganizer(2),
        );
        $this->assertSame(
            ['total' => 4, 'pending' => 1, 'published' => 3],
            $repository->reviewsForAdmin(),
        );
        $this->assertSame(
            ['published' => 1, 'awaiting_reply' => 1],
            $repository->reviewsForOrganizer(3),
        );
        $this->assertSame(
            ['submitted' => 0, 'pending' => 0, 'published' => 0],
            $repository->reviewsForParticipant(999),
        );
    }

    public function testParticipantWorkspaceUsesScopedUpcomingFavoritesAndReviewActionQueries(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, end_date TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, status TEXT NOT NULL, registration_number TEXT NOT NULL)');
        $connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $connection->exec('CREATE TABLE favorites (user_id INTEGER NOT NULL, event_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, status TEXT NOT NULL, organizer_reply TEXT NULL)');
        $connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, ticket_number TEXT NOT NULL, status TEXT NOT NULL, issued_at TEXT NOT NULL)');
        $connection->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, action_url TEXT NULL, read_at TEXT NULL, created_at TEXT NOT NULL)');
        $connection->exec("INSERT INTO users (id, deleted_at) VALUES (7, NULL), (8, NULL), (9, '2026-08-01 00:00:00')");
        $connection->exec("INSERT INTO events (id, title, slug, start_date, end_date, status, deleted_at) VALUES
            (11, 'Owned future event', 'owned-future', datetime('now', '+5 days'), datetime('now', '+5 days', '+2 hours'), 'published', NULL),
            (12, 'Owned completed event', 'owned-completed', datetime('now', '-5 days'), datetime('now', '-5 days', '+2 hours'), 'published', NULL),
            (13, 'Foreign future event', 'foreign-future', datetime('now', '+2 days'), datetime('now', '+2 days', '+2 hours'), 'published', NULL),
            (14, 'Owned ongoing event', 'owned-ongoing', datetime('now', '-2 hours'), datetime('now', '+2 hours'), 'published', NULL),
            (15, 'Deleted event', 'deleted-event', datetime('now', '+8 days'), datetime('now', '+8 days', '+2 hours'), 'published', '2026-08-01 00:00:00'),
            (16, 'Cancelled event', 'cancelled-event', datetime('now', '+6 days'), datetime('now', '+6 days', '+2 hours'), 'cancelled', NULL)");
        $connection->exec("INSERT INTO registrations (id, user_id, event_id, status, registration_number) VALUES
            (21, 7, 11, 'confirmed', 'REG-OWNED'), (22, 7, 12, 'confirmed', 'REG-REVIEW'), (23, 8, 13, 'confirmed', 'REG-FOREIGN'), (24, 7, 14, 'confirmed', 'REG-ONGOING'), (25, 7, 15, 'confirmed', 'REG-DELETED-EVENT'), (26, 9, 11, 'confirmed', 'REG-DELETED-USER'), (27, 7, 16, 'confirmed', 'REG-CANCELLED-EVENT')");
        $connection->exec("INSERT INTO payments (registration_id, status) VALUES (21, 'paid'), (21, 'pending'), (22, 'paid'), (23, 'paid')");
        $connection->exec('INSERT INTO favorites (user_id, event_id) VALUES (7, 11), (7, 12), (8, 13)');
        $connection->exec("INSERT INTO reviews (id, user_id, event_id, status, organizer_reply) VALUES (1, 8, 13, 'published', NULL)");
        $connection->exec("INSERT INTO tickets (id, registration_id, ticket_number, status, issued_at) VALUES
            (30, 21, 'TICKET-30', 'valid', '2026-08-01 09:00:00'),
            (31, 22, 'TICKET-31', 'used', '2026-08-02 09:00:00'),
            (32, 24, 'TICKET-32', 'valid', '2026-08-03 09:00:00'),
            (33, 21, 'TICKET-33', 'valid', '2026-08-04 09:00:00'),
            (34, 25, 'TICKET-34', 'valid', '2026-08-05 09:00:00'),
            (35, 26, 'TICKET-35', 'valid', '2026-08-06 09:00:00')");
        $connection->exec("INSERT INTO notifications (id, user_id, type, title, message, action_url, read_at, created_at) VALUES
            (40, 7, 'ticket_issued', 'Old ticket', 'Old ticket message', '/participant/tickets/30', NULL, '2026-08-01 09:00:00'),
            (41, 7, 'payment_verified', 'Verified', 'Payment verified', '/participant/registrations/21', NULL, '2026-08-02 09:00:00'),
            (42, 7, 'ticket_issued', 'Latest ticket', 'Ticket ready', '/participant/tickets/33', NULL, '2026-08-03 09:00:00'),
            (43, 7, 'review_published', 'Review published', 'Your review is live', '/participant/reviews', '2026-08-04 10:00:00', '2026-08-04 09:00:00'),
            (44, 8, 'ticket_issued', 'Foreign', 'Foreign message', '/participant/tickets/99', NULL, '2026-08-05 09:00:00'),
            (45, 9, 'ticket_issued', 'Deleted user', 'Hidden', '/participant/tickets/35', NULL, '2026-08-06 09:00:00')");

        $workspace = (new DashboardMetricsRepository($connection))->participantWorkspace(7);

        $this->assertSame(2, $workspace['favorite_count']);
        $this->assertSame(1, $workspace['review_actions']);
        $this->assertSame(1, count($workspace['upcoming']));
        $this->assertSame('Owned future event', $workspace['upcoming'][0]['event_title']);
        $this->assertSame('pending', $workspace['upcoming'][0]['payment_status']);
        $this->assertSame([33, 32, 31], array_column($workspace['tickets'], 'id'));
        $this->assertSame('TICKET-33', $workspace['tickets'][0]['ticket_number']);
        $this->assertSame([43, 42, 41], array_column($workspace['recent_notifications'], 'id'));
        $this->assertSame('/participant/tickets/33', $workspace['recent_notifications'][1]['action_url']);
        $this->assertSame([], (new DashboardMetricsRepository($connection))->participantWorkspace(9)['tickets']);
        $this->assertSame([], (new DashboardMetricsRepository($connection))->participantWorkspace(9)['recent_notifications']);
    }
}
