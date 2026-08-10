<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\NotificationService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testEventAnnouncementTypeAcceptsOwnedParticipantRegistrationActions(): void
    {
        $repository = new \OEMS\Tests\Support\FakeNotificationRepository();
        $service = new \OEMS\App\Services\NotificationService($repository);

        $sent = $service->notify(
            7,
            'event_announcement',
            'Doors open earlier',
            'Please arrive at 8:30 AM.',
            '/participant/registrations/41',
            ['event_id' => 11, 'announcement_id' => 9],
        );

        $this->assertTrue($sent);
        $this->assertSame('event_announcement', $repository->notifications[1]['type'] ?? null);
    }

    private FakeNotificationRepository $repository;

    private NotificationService $service;

    private string $logPath;

    protected function setUp(): void
    {
        $this->repository = new FakeNotificationRepository();
        $this->logPath = sys_get_temp_dir() . '/oems-notification-service-' . bin2hex(random_bytes(6)) . '.log';
        $this->service = new NotificationService($this->repository, new Logger($this->logPath));
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testCreatesOnlyAllowListedNotificationsWithBoundedSafePayloads(): void
    {
        $created = $this->service->notify(7, 'ticket_issued', 'Ticket ready', 'Your ticket is available.', '/participant/tickets/44', ['ticket_id' => 44]);
        $unsafe = $this->service->notify(7, 'ticket_issued', 'Ticket ready', 'Your ticket is available.', 'https://attacker.example.test', []);
        $crossRole = $this->service->notify(7, 'ticket_issued', 'Ticket ready', 'Your ticket is available.', '/organizer/dashboard', []);
        $unknown = $this->service->notify(7, 'arbitrary_type', 'Title', 'Message', '/participant/tickets/44', []);
        $oversized = $this->service->notify(7, 'ticket_issued', str_repeat('T', 181), 'Message', '/participant/tickets/44', []);

        $this->assertTrue($created);
        $this->assertFalse($unsafe);
        $this->assertFalse($crossRole);
        $this->assertFalse($unknown);
        $this->assertFalse($oversized);
        $this->assertSame(1, count($this->repository->notifications));
        $this->assertSame('/participant/tickets/44', $this->repository->notifications[1]['action_url']);
        $this->assertSame(['ticket_id' => 44], $this->repository->notifications[1]['data']);
    }

    public function testDeliveryFailureIsContainedAndLoggedWithoutSensitivePayload(): void
    {
        $this->repository->throwOnCreate = true;
        $result = $this->service->notify(7, 'payment_pending', 'Payment received', 'PRIVATE-REFERENCE-123 must stay private.', '/participant/registrations/11', []);

        $this->assertFalse($result);
        $log = (string) file_get_contents($this->logPath);
        $this->assertTrue(str_contains($log, 'notification_dispatch'));
        $this->assertFalse(str_contains($log, 'PRIVATE-REFERENCE-123'));
        $this->assertFalse(str_contains($log, '/participant/registrations/11'));
    }
}
