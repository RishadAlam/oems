<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\MailOutboxService;
use OEMS\Tests\Support\FakeMailOutboxRepository;
use OEMS\Tests\Support\TestCase;

final class MailOutboxServiceTest extends TestCase
{
    private FakeMailOutboxRepository $repository;
    private MailOutboxService $service;

    protected function setUp(): void
    {
        $this->repository = new FakeMailOutboxRepository();
        $this->service = new MailOutboxService($this->repository);
    }

    public function testEnqueueNormalizesRecipientHashesIdempotencyAndReturnsTheStoredJob(): void
    {
        $result = $this->service->enqueue(
            'event_reminder',
            ' Person@Example.COM ',
            [
                'user_id' => 9,
                'event_id' => 42,
                'registration_id' => 71,
                'event_title' => 'Dhaka Product Night',
                'starts_at' => '2026-08-11T09:00:00+06:00',
                'calendar_url' => '/participant/registrations/71/calendar.ics',
            ],
            'reminder:event:42:registration:71:24h',
            new DateTimeImmutable('2026-08-10 09:00:00'),
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('person@example.com', $result['job']['recipient_email']);
        $this->assertSame(64, strlen($result['job']['idempotency_key']));
        $this->assertSame('2026-08-10 09:00:00', $result['job']['available_at']);
        $this->assertSame(1, count($this->repository->jobs));

        $replay = $this->service->enqueue(
            'event_reminder',
            'person@example.com',
            $result['job']['payload'],
            'reminder:event:42:registration:71:24h',
            new DateTimeImmutable('2026-08-10 09:00:00'),
        );
        $this->assertSame($result['job']['id'], $replay['job']['id']);
        $this->assertSame(1, count($this->repository->jobs));
    }

    public function testEnqueueRejectsUnknownTemplatesInvalidRecipientsAndUnexpectedPayloadFields(): void
    {
        $unknown = $this->service->enqueue('raw_php', 'person@example.com', [], 'unknown-job');
        $invalidEmail = $this->service->enqueue('event_reminder', 'not-an-email', [], 'bad-email');
        $unexpected = $this->service->enqueue('event_reminder', 'person@example.com', [
            'user_id' => 9,
            'event_id' => 42,
            'registration_id' => 71,
            'event_title' => 'Dhaka Product Night',
            'starts_at' => '2026-08-11T09:00:00+06:00',
            'calendar_url' => '/participant/registrations/71/calendar.ics',
            'smtp_password' => 'must-not-enter-outbox',
        ], 'unexpected-field');

        $this->assertFalse($unknown['ok']);
        $this->assertArrayHasKey('template', $unknown['errors']);
        $this->assertFalse($invalidEmail['ok']);
        $this->assertArrayHasKey('recipient_email', $invalidEmail['errors']);
        $this->assertFalse($unexpected['ok']);
        $this->assertArrayHasKey('payload', $unexpected['errors']);
        $this->assertSame([], $this->repository->jobs);
    }

    public function testEnqueueRequiresTemplateFieldsAndBoundsPayloadAndIdempotencyMaterial(): void
    {
        $missing = $this->service->enqueue('contact_reply', 'person@example.com', [
            'contact_id' => 7,
            'name' => 'Samira',
        ], 'contact:7:reply');
        $long = $this->service->enqueue('newsletter_campaign', 'person@example.com', [
            'campaign_id' => 5,
            'subject' => str_repeat('S', 181),
            'message' => 'Upcoming events',
            'unsubscribe_url' => '/newsletter/unsubscribe/token',
        ], str_repeat('x', 501));

        $this->assertFalse($missing['ok']);
        $this->assertArrayHasKey('payload', $missing['errors']);
        $this->assertFalse($long['ok']);
        $this->assertArrayHasKey('payload', $long['errors']);
        $this->assertArrayHasKey('idempotency_key', $long['errors']);
    }

    public function testPersistenceFailureReturnsStableErrorsWithoutLeakingTheException(): void
    {
        $this->repository->throwOnEnqueue = true;
        $result = $this->service->enqueue('newsletter_confirmation', 'person@example.com', [
            'subscription_id' => 8,
            'confirmation_url' => '/newsletter/confirm/token',
        ], 'newsletter:confirm:8');

        $this->assertFalse($result['ok']);
        $this->assertSame(['outbox' => 'Email delivery could not be queued.'], $result['errors']);
    }
}
