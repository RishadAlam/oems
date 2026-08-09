<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\NotificationRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class NotificationRepositoryTest extends TestCase
{
    private PDO $connection;

    private NotificationRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, action_url TEXT NULL, data TEXT NULL, read_at TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->repository = new NotificationRepository($this->connection);
    }

    public function testListsOnlyOwnedNotificationsAndTracksUnreadState(): void
    {
        $first = $this->repository->createForUser(7, [
            'type' => 'payment_pending', 'title' => 'Payment received', 'message' => 'We are reviewing your payment.', 'action_url' => '/participant/registrations/11', 'data' => ['registration_id' => 11],
        ]);
        $this->repository->createForUser(8, [
            'type' => 'payment_pending', 'title' => 'Private', 'message' => 'Another participant notice.', 'action_url' => '/participant/registrations/12', 'data' => [],
        ]);
        $second = $this->repository->createForUser(7, [
            'type' => 'ticket_issued', 'title' => 'Ticket ready', 'message' => 'Your ticket is ready.', 'action_url' => '/participant/tickets/17', 'data' => ['ticket_id' => 17],
        ]);

        $page = $this->repository->forUser(7, 1, 1);

        $this->assertSame(2, $this->repository->unreadCountForUser(7));
        $this->assertSame(2, $page['pagination']['total']);
        $this->assertSame($second, $page['items'][0]['id']);
        $this->assertTrue($this->repository->markReadForUser(7, $first));
        $this->assertFalse($this->repository->markReadForUser(7, 2));
        $this->assertSame(1, $this->repository->unreadCountForUser(7));
        $this->assertSame(1, $this->repository->markAllReadForUser(7));
        $this->assertSame(0, $this->repository->unreadCountForUser(7));
    }
}
