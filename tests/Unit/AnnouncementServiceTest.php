<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\AnnouncementService;
use OEMS\Tests\Support\FakeAnnouncementRepository;
use OEMS\Tests\Support\TestCase;

final class AnnouncementServiceTest extends TestCase
{
    private FakeAnnouncementRepository $announcements;

    private AnnouncementService $service;

    protected function setUp(): void
    {
        $this->announcements = new FakeAnnouncementRepository();
        $this->announcements->events = [
            11 => $this->event(11, 10, 'published', 'approved'),
            12 => $this->event(12, 20, 'published', 'approved'),
            13 => $this->event(13, 10, 'draft', 'approved'),
            14 => $this->event(14, 10, 'completed', 'rejected'),
        ];
        $this->service = new AnnouncementService($this->announcements);
    }

    public function testWorkspaceIsOwnerScopedAndHistoryIsBounded(): void
    {
        $this->announcements->announcements = [
            1 => ['id' => 1, 'event_id' => 11, 'subject' => 'Earlier', 'sent_at' => '2026-08-10 09:00:00'],
            2 => ['id' => 2, 'event_id' => 11, 'subject' => 'Latest', 'sent_at' => '2026-08-10 10:00:00'],
        ];

        $workspace = $this->service->workspace(10, 11);

        $this->assertSame('Published event', $workspace['event']['title'] ?? null);
        $this->assertSame('Latest', $workspace['announcements'][0]['subject'] ?? null);
        $this->assertTrue($workspace['can_send'] ?? false);
        $this->assertSame(25, $this->announcements->historyLimit);
        $this->assertNull($this->service->workspace(10, 12));
    }

    public function testReviewRequiresScalarTrimmedBoundedPlainText(): void
    {
        foreach ([
            [['nested'], 'Message'],
            ['Subject', ['nested']],
            ['', 'Message'],
            ['Subject', ''],
            [str_repeat('s', 181), 'Message'],
            ['Subject', str_repeat('m', 1001)],
        ] as [$subject, $message]) {
            $result = $this->service->review(10, 11, $subject, $message);
            $this->assertFalse($result['success']);
        }

        $valid = $this->service->review(10, 11, '  Doors open  ', "  Arrive at 8:30.\nBring your ticket.  ");

        $this->assertTrue($valid['success']);
        $this->assertSame('Doors open', $valid['data']['subject'] ?? null);
        $this->assertSame("Arrive at 8:30.\nBring your ticket.", $valid['data']['message'] ?? null);
    }

    public function testReviewRejectsForeignDraftAndUnapprovedOrganizerEvents(): void
    {
        $foreign = $this->service->review(10, 12, 'Notice', 'A complete message.');
        $foreignMalformed = $this->service->review(10, 12, ['nested'], ['nested']);
        $draft = $this->service->review(10, 13, 'Notice', 'A complete message.');
        $unapproved = $this->service->review(10, 14, 'Notice', 'A complete message.');

        $this->assertSame('not_found', $foreign['code'] ?? null);
        $this->assertSame('not_found', $foreignMalformed['code'] ?? null);
        $this->assertSame('ineligible', $draft['code'] ?? null);
        $this->assertSame('ineligible', $unapproved['code'] ?? null);
    }

    public function testSendRequiresAValidRequestKeyAndForwardsNormalizedContext(): void
    {
        foreach (['', 'abc', str_repeat('G', 64), str_repeat('a', 63), ['nested']] as $key) {
            $result = $this->service->send(10, 11, 'Notice', 'A complete message.', $key, []);
            $this->assertFalse($result['success']);
            $this->assertSame([], $this->announcements->deliveries);
        }

        $sent = $this->service->send(
            10,
            11,
            '  Notice  ',
            '  A complete message.  ',
            str_repeat('a', 64),
            ['ip_address' => '203.0.113.40'],
        );

        $this->assertTrue($sent['success']);
        $this->assertSame('Notice', $this->announcements->deliveries[0]['subject'] ?? null);
        $this->assertSame('A complete message.', $this->announcements->deliveries[0]['message'] ?? null);
        $this->assertSame('203.0.113.40', $this->announcements->deliveries[0]['context']['ip_address'] ?? null);
    }

    public function testSendMapsReplayZeroRecipientIneligibleAndPersistenceOutcomesTruthfully(): void
    {
        $this->announcements->forcedDeliveryResult = [
            'status' => 'replayed',
            'id' => 4,
            'recipient_count' => 9,
            'subject' => 'Original notice',
        ];
        $replay = $this->service->send(10, 11, 'Notice', 'Message', str_repeat('b', 64), []);

        $this->announcements->forcedDeliveryResult = ['status' => 'no_recipients'];
        $empty = $this->service->send(10, 11, 'Notice', 'Message', str_repeat('c', 64), []);

        $this->announcements->forcedDeliveryResult = ['status' => 'ineligible'];
        $ineligible = $this->service->send(10, 11, 'Notice', 'Message', str_repeat('d', 64), []);

        $this->announcements->throwOnDelivery = true;
        $failed = $this->service->send(10, 11, 'Notice', 'Message', str_repeat('e', 64), []);

        $this->assertTrue($replay['success']);
        $this->assertTrue($replay['replayed'] ?? false);
        $this->assertSame(9, $replay['announcement']['recipient_count'] ?? null);
        $this->assertFalse($empty['success']);
        $this->assertSame('no_recipients', $empty['code'] ?? null);
        $this->assertFalse($ineligible['success']);
        $this->assertSame('ineligible', $ineligible['code'] ?? null);
        $this->assertFalse($failed['success']);
        $this->assertSame('persistence', $failed['code'] ?? null);
        $this->assertFalse(str_contains(json_encode($failed), 'secret-example'));
    }

    private function event(int $id, int $userId, string $status, string $approval): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'title' => $id === 11 ? 'Published event' : 'Event ' . $id,
            'status' => $status,
            'organizer_approval_status' => $approval,
            'organizer_user_status' => 'active',
            'organizer_email_verified_at' => '2026-08-01 09:00:00',
            'organizer_deleted_at' => null,
            'organizer_role' => 'organizer',
            'deleted_at' => null,
        ];
    }
}
