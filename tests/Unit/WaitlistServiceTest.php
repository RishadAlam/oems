<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\WaitlistService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeWaitlistRepository;
use OEMS\Tests\Support\TestCase;

final class WaitlistServiceTest extends TestCase
{
    private FakeUserRepository $users;

    private FakeWaitlistRepository $waitlists;

    private WaitlistService $service;

    private string $logPath;

    protected function setUp(): void
    {
        $this->users = new FakeUserRepository();
        $this->users->users = [
            1 => ['id' => 1, 'role_id' => 3, 'role_slug' => 'participant', 'status' => 'active', 'email_verified_at' => '2026-08-10 00:00:00'],
            2 => ['id' => 2, 'role_id' => 3, 'role_slug' => 'participant', 'status' => 'inactive', 'email_verified_at' => '2026-08-10 00:00:00'],
            3 => ['id' => 3, 'role_id' => 3, 'role_slug' => 'participant', 'status' => 'active', 'email_verified_at' => null],
            4 => ['id' => 4, 'role_id' => 2, 'role_slug' => 'organizer', 'status' => 'active', 'email_verified_at' => '2026-08-10 00:00:00'],
        ];
        $this->waitlists = new FakeWaitlistRepository();
        $this->waitlists->events[10] = ['id' => 10, 'title' => 'Full event', 'slug' => 'full-event', 'ticket_price' => '25.00', 'currency' => 'BDT'];
        $this->logPath = sys_get_temp_dir() . '/oems-waitlist-' . bin2hex(random_bytes(6)) . '.log';
        $this->service = new WaitlistService($this->users, $this->waitlists, new Logger($this->logPath));
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testOnlyActiveVerifiedParticipantsCanJoin(): void
    {
        foreach ([2, 3, 4, 999] as $actorId) {
            $result = $this->service->join($actorId, 10);
            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('account', $result['errors']);
        }
        $this->assertSame([], $this->waitlists->entries);
    }

    public function testJoinUsesDatabaseEventStateAndReturnsTruthfulPosition(): void
    {
        $missing = $this->service->join(1, 999);
        $joined = $this->service->join(1, 10);
        $replayed = $this->service->join(1, 10);

        $this->assertFalse($missing['success']);
        $this->assertArrayHasKey('event', $missing['errors']);
        $this->assertTrue($joined['success']);
        $this->assertSame('waitlisted', $joined['entry']['status']);
        $this->assertSame(1, $joined['position']);
        $this->assertSame($joined['entry']['id'], $replayed['entry']['id']);
        $this->assertSame(1, count($this->waitlists->entries));
    }

    public function testLeaveIsOwnedBoundedAndIdempotent(): void
    {
        $joined = $this->service->join(1, 10);
        $id = (int) $joined['entry']['id'];

        $this->assertFalse($this->service->leave(1, $id, '')['success']);
        $this->assertFalse($this->service->leave(1, $id, str_repeat('x', 501))['success']);
        $this->assertFalse($this->service->leave(2, $id, 'No longer attending')['success']);
        $left = $this->service->leave(1, $id, '  Plans changed  ');
        $repeat = $this->service->leave(1, $id, 'Plans changed');

        $this->assertTrue($left['success']);
        $this->assertSame('Plans changed', $left['entry']['cancellation_reason']);
        $this->assertTrue($repeat['success']);
        $this->assertSame('cancelled', $repeat['entry']['status']);
    }

    public function testParticipantListAddsPositionWithoutLeakingOtherEntries(): void
    {
        $this->waitlists->entries = [
            10 => ['id' => 10, 'event_id' => 10, 'user_id' => 1, 'status' => 'waitlisted', 'waitlisted_at' => '2026-08-10 10:00:00'],
            11 => ['id' => 11, 'event_id' => 10, 'user_id' => 4, 'status' => 'waitlisted', 'waitlisted_at' => '2026-08-10 09:00:00'],
        ];

        $rows = $this->service->forParticipant(1);

        $this->assertSame(1, count($rows));
        $this->assertSame(2, $rows[0]['position']);
        $this->assertSame(10, $rows[0]['id']);
    }

    public function testPersistenceFailureReturnsSafeErrorsAndSanitizedLog(): void
    {
        $this->waitlists->failWrites = true;
        $result = $this->service->join(1, 10);

        $this->assertFalse($result['success']);
        $this->assertSame(['waitlist' => ['The waitlist could not be updated.']], $result['errors']);
        $log = file_get_contents($this->logPath);
        $this->assertTrue(is_string($log) && str_contains($log, 'waitlist_join'));
        $this->assertFalse(is_string($log) && str_contains($log, 'secret'));
    }

    public function testMaintenanceReleasesExpiredClaimsAndReturnsBoundedPromotionEvents(): void
    {
        $this->waitlists->entries[20] = [
            'id' => 20,
            'event_id' => 10,
            'user_id' => 1,
            'status' => 'pending',
            'waitlist_claim_expires_at' => '2026-08-10 09:00:00',
        ];
        $this->waitlists->expired[20] = ['id' => 20, 'event_id' => 10, 'user_id' => 1];
        $this->waitlists->promotableEventIds = [10, 11];

        $result = $this->service->releaseExpiredClaims(new \DateTimeImmutable('2026-08-10 10:00:00'), 25);

        $this->assertSame(1, $result['released']);
        $this->assertSame([10], $result['event_ids']);
        $this->assertSame('cancelled', $this->waitlists->entries[20]['status']);
        $this->assertSame([10], $this->service->promotionEventIds(1));
    }
}
