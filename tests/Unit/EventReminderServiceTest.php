<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\EventReminderService;
use OEMS\App\Services\MailOutboxService;
use OEMS\Tests\Support\FakeMailOutboxRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\TestCase;

final class EventReminderServiceTest extends TestCase
{
    public function testQueuesOneIdempotentReminderPerEligibleConfirmedRegistration(): void
    {
        $registrations = new FakeRegistrationRepository();
        $registrations->reminderCandidates = [
            $this->candidate(11, 21, 31, 'active', 'confirmed', 'published', '2026-08-11 09:30:00'),
            $this->candidate(12, 22, 32, 'inactive', 'confirmed', 'published', '2026-08-11 09:40:00'),
            $this->candidate(13, 23, 33, 'active', 'cancelled', 'published', '2026-08-11 09:50:00'),
            $this->candidate(14, 24, 34, 'active', 'confirmed', 'approved', '2026-08-11 10:00:00'),
        ];
        $outbox = new FakeMailOutboxRepository();
        $service = new EventReminderService($registrations, new MailOutboxService($outbox), 'Asia/Dhaka');
        $now = new DateTimeImmutable('2026-08-10 10:00:00+06:00');

        $first = $service->queueDue($now, 10);
        $second = $service->queueDue($now, 10);

        $this->assertSame(1, $first['queued']);
        $this->assertSame(0, $second['queued']);
        $this->assertSame(1, count($outbox->jobs));
        $this->assertSame('event_reminder', $outbox->jobs[0]['template']);
        $this->assertSame('/participant/registrations/11/calendar.ics', $outbox->jobs[0]['payload']['calendar_url']);
        $this->assertFalse(array_key_exists('location', $outbox->jobs[0]['payload']));
    }

    public function testBatchLimitIsBoundedAndTimezoneWindowEndsAtTwentyFourHours(): void
    {
        $registrations = new FakeRegistrationRepository();
        $registrations->reminderCandidates = [
            $this->candidate(11, 21, 31, 'active', 'confirmed', 'published', '2026-08-11 09:59:59'),
            $this->candidate(12, 22, 32, 'active', 'confirmed', 'published', '2026-08-11 10:00:00'),
        ];
        $service = new EventReminderService(
            $registrations,
            new MailOutboxService(new FakeMailOutboxRepository()),
            'Asia/Dhaka',
        );

        $result = $service->queueDue(new DateTimeImmutable('2026-08-10 10:00:00+06:00'), 1);

        $this->assertSame(1, $result['queued']);
        $this->assertSame(1, $result['limit']);
        $this->assertSame('2026-08-11 10:00:00', $result['window_ends_at']);
    }

    private function candidate(
        int $registrationId,
        int $eventId,
        int $userId,
        string $userStatus,
        string $registrationStatus,
        string $eventStatus,
        string $startsAt,
    ): array {
        return [
            'registration_id' => $registrationId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'recipient_email' => "participant{$userId}@example.test",
            'participant_name' => 'Participant',
            'event_title' => 'Dhaka Product Night',
            'start_date' => $startsAt,
            'user_status' => $userStatus,
            'email_verified_at' => '2026-01-01 00:00:00',
            'user_deleted_at' => null,
            'registration_status' => $registrationStatus,
            'event_status' => $eventStatus,
            'event_deleted_at' => null,
        ];
    }
}
