<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\AnnouncementRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use Throwable;

final class AnnouncementRepositoryTest extends TestCase
{
    private PDO $connection;

    private AnnouncementRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedEligibleJourney();
        $this->repository = new AnnouncementRepository($this->connection);
    }

    public function testOwnedEventLookupDoesNotExposeForeignOrDeletedEvents(): void
    {
        $owned = $this->repository->findOwnedEvent(10, 11);

        $this->assertSame('Published event', $owned['title'] ?? null);
        $this->assertSame('approved', $owned['organizer_approval_status'] ?? null);
        $this->assertNull($this->repository->findOwnedEvent(99, 11));
        $this->connection->exec("UPDATE events SET deleted_at = '2026-08-10 10:00:00' WHERE id = 11");
        $this->assertNull($this->repository->findOwnedEvent(10, 11));
    }

    public function testDeliveryPersistsOnlyEligibleRecipientNotificationsCountAndAuditAtomically(): void
    {
        $result = $this->repository->deliverToConfirmedParticipants(
            10,
            11,
            'Doors open earlier',
            'Please arrive at 8:30 AM.',
            str_repeat('a', 64),
            ['ip_address' => '203.0.113.18', 'user_agent' => 'Organizer browser'],
        );

        $this->assertSame('sent', $result['status'] ?? null);
        $this->assertSame(1, $result['recipient_count'] ?? null);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM event_announcements')->fetchColumn());
        $notification = $this->connection->query('SELECT * FROM notifications')->fetch();
        $this->assertSame(20, (int) ($notification['user_id'] ?? 0));
        $this->assertSame('event_announcement', $notification['type'] ?? null);
        $this->assertSame('/participant/registrations/101', $notification['action_url'] ?? null);
        $this->assertSame('Doors open earlier', $notification['title'] ?? null);
        $this->assertSame('Please arrive at 8:30 AM.', $notification['message'] ?? null);
        $data = json_decode((string) ($notification['data'] ?? ''), true);
        $this->assertSame(11, $data['event_id'] ?? null);
        $this->assertSame((int) ($result['id'] ?? 0), $data['announcement_id'] ?? null);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn());
        $activity = $this->connection->query('SELECT * FROM activity_logs')->fetch();
        $this->assertSame('announcement.sent', $activity['action'] ?? null);
        $this->assertSame('203.0.113.18', $activity['ip_address'] ?? null);
    }

    public function testIdenticalRequestKeyReplayReturnsOriginalWithoutDuplicateSideEffects(): void
    {
        $first = $this->repository->deliverToConfirmedParticipants(
            10,
            11,
            'First subject',
            'First message',
            str_repeat('b', 64),
            [],
        );
        $replay = $this->repository->deliverToConfirmedParticipants(
            10,
            11,
            'Changed retry subject',
            'Changed retry message',
            str_repeat('b', 64),
            [],
        );

        $this->assertSame('sent', $first['status'] ?? null);
        $this->assertSame('replayed', $replay['status'] ?? null);
        $this->assertSame($first['id'] ?? null, $replay['id'] ?? null);
        $this->assertSame('First subject', $replay['subject'] ?? null);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM event_announcements')->fetchColumn());
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn());
    }

    public function testIneligibleEventAndUnapprovedOrganizerDoNotSend(): void
    {
        $this->connection->exec("UPDATE events SET status = 'draft' WHERE id = 11");
        $draft = $this->repository->deliverToConfirmedParticipants(
            10,
            11,
            'Draft notice',
            'This event is not public.',
            str_repeat('c', 64),
            [],
        );
        $this->connection->exec("UPDATE events SET status = 'published'");
        $this->connection->exec("UPDATE organizers SET approval_status = 'rejected' WHERE id = 2");
        $rejected = $this->repository->deliverToConfirmedParticipants(
            10,
            11,
            'Rejected notice',
            'This organizer is not approved.',
            str_repeat('d', 64),
            [],
        );

        $this->assertSame('ineligible', $draft['status'] ?? null);
        $this->assertSame('ineligible', $rejected['status'] ?? null);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM event_announcements')->fetchColumn());
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn());
    }

    public function testZeroEligibleRecipientsCreatesNoAnnouncementOrAudit(): void
    {
        $this->connection->exec("UPDATE registrations SET status = 'cancelled'");

        $result = $this->repository->deliverToConfirmedParticipants(
            10,
            11,
            'No audience',
            'No eligible participant should receive this.',
            str_repeat('e', 64),
            [],
        );

        $this->assertSame('no_recipients', $result['status'] ?? null);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM event_announcements')->fetchColumn());
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn());
    }

    public function testNotificationPersistenceFailureRollsBackAnnouncementAndAudit(): void
    {
        $this->connection->exec("CREATE TRIGGER fail_notification BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT, 'notification failure'); END");
        $thrown = false;

        try {
            $this->repository->deliverToConfirmedParticipants(
                10,
                11,
                'Atomic notice',
                'Every write must roll back.',
                str_repeat('f', 64),
                [],
            );
        } catch (Throwable) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM event_announcements')->fetchColumn());
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn());
    }

    public function testHistoryIsOwnerScopedBoundedAndDeterministic(): void
    {
        $insert = $this->connection->prepare(
            'INSERT INTO event_announcements (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at)
             VALUES (11, 10, :subject, :message, \'confirmed\', :count, :request_key, :sent_at)',
        );
        foreach ([
            ['First', 'Older', 3, str_repeat('1', 64), '2026-08-10 09:00:00'],
            ['Second', '<script>alert(1)</script>', 4, str_repeat('2', 64), '2026-08-10 10:00:00'],
            ['Third', 'Same time, higher id', 5, str_repeat('3', 64), '2026-08-10 10:00:00'],
        ] as [$subject, $message, $count, $key, $sentAt]) {
            $insert->execute([
                'subject' => $subject,
                'message' => $message,
                'count' => $count,
                'request_key' => $key,
                'sent_at' => $sentAt,
            ]);
        }

        $history = $this->repository->historyForOwnedEvent(10, 11, 2);

        $this->assertSame(2, count($history));
        $this->assertSame('Third', $history[0]['subject'] ?? null);
        $this->assertSame('Second', $history[1]['subject'] ?? null);
        $this->assertSame(5, $history[0]['recipient_count'] ?? null);
        $this->assertSame([], $this->repository->historyForOwnedEvent(99, 11, 50));
    }

    private function createSchema(): void
    {
        $this->connection->exec("CREATE TABLE roles (id INTEGER PRIMARY KEY, slug TEXT NOT NULL)");
        $this->connection->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, role_id INTEGER NOT NULL, name TEXT NOT NULL, status TEXT NOT NULL, email_verified_at TEXT NULL, deleted_at TEXT NULL)");
        $this->connection->exec("CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL)");
        $this->connection->exec("CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT NULL)");
        $this->connection->exec("CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, cancelled_at TEXT NULL)");
        $this->connection->exec("CREATE TABLE event_announcements (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, sent_by INTEGER NULL, subject TEXT NOT NULL, message TEXT NOT NULL, audience TEXT NOT NULL, recipient_count INTEGER NOT NULL DEFAULT 0, request_key TEXT NOT NULL UNIQUE, sent_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->connection->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, action_url TEXT NULL, data TEXT NULL, read_at TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->connection->exec("CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action TEXT NOT NULL, subject_type TEXT NULL, subject_id INTEGER NULL, description TEXT NOT NULL, properties TEXT NULL, ip_address TEXT NULL, user_agent TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    }

    private function seedEligibleJourney(): void
    {
        $this->connection->exec("INSERT INTO roles (id, slug) VALUES (1, 'organizer'), (2, 'participant')");
        $insertUser = $this->connection->prepare('INSERT INTO users (id, role_id, name, status, email_verified_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([
            [10, 1, 'Approved organizer', 'active', '2026-08-01 09:00:00', null],
            [20, 2, 'Eligible participant', 'active', '2026-08-01 09:00:00', null],
            [21, 2, 'Inactive participant', 'inactive', '2026-08-01 09:00:00', null],
            [22, 2, 'Unverified participant', 'active', null, null],
            [23, 2, 'Deleted participant', 'active', '2026-08-01 09:00:00', '2026-08-02 09:00:00'],
            [24, 1, 'Organizer account', 'active', '2026-08-01 09:00:00', null],
            [25, 2, 'Cancelled participant', 'active', '2026-08-01 09:00:00', null],
            [26, 2, 'Pending participant', 'active', '2026-08-01 09:00:00', null],
        ] as $user) {
            $insertUser->execute($user);
        }
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status) VALUES (2, 10, 'Trusted Events', 'approved')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, title, status, deleted_at) VALUES (11, 2, 'Published event', 'published', NULL)");
        $insertRegistration = $this->connection->prepare('INSERT INTO registrations (id, event_id, user_id, status, cancelled_at) VALUES (?, 11, ?, ?, ?)');
        foreach ([
            [101, 20, 'confirmed', null],
            [102, 21, 'confirmed', null],
            [103, 22, 'confirmed', null],
            [104, 23, 'confirmed', null],
            [105, 24, 'confirmed', null],
            [106, 25, 'confirmed', '2026-08-09 09:00:00'],
            [107, 26, 'pending', null],
        ] as $registration) {
            $insertRegistration->execute($registration);
        }
    }
}
