<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\AdminPeopleService;
use OEMS\App\Services\NotificationService;
use OEMS\Tests\Support\FakeAdminPeopleRepository;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\TestCase;

final class AdminPeopleServiceTest extends TestCase
{
    private FakeAdminPeopleRepository $people;

    private FakeNotificationRepository $notifications;

    private AdminPeopleService $service;

    protected function setUp(): void
    {
        $this->people = new FakeAdminPeopleRepository();
        $this->people->users = [
            1 => ['id' => 1, 'name' => 'Root Admin', 'email' => 'root@example.test', 'role_slug' => 'super-admin', 'status' => 'active'],
            10 => ['id' => 10, 'name' => 'Participant', 'email' => 'participant@example.test', 'role_slug' => 'participant', 'status' => 'active'],
            11 => ['id' => 11, 'name' => 'Organizer', 'email' => 'organizer@example.test', 'role_slug' => 'organizer', 'status' => 'suspended'],
            12 => ['id' => 12, 'name' => 'Inactive Participant', 'email' => 'inactive@example.test', 'role_slug' => 'participant', 'status' => 'inactive'],
        ];
        $this->people->organizers = [
            20 => [
                'id' => 20,
                'user_id' => 11,
                'organization_name' => 'Open Community Events',
                'approval_status' => 'pending',
                'user_status' => 'active',
                'email_verified_at' => '2026-08-01 09:00:00',
                'role_slug' => 'organizer',
            ],
            21 => [
                'id' => 21,
                'user_id' => 10,
                'organization_name' => 'Ineligible Events',
                'approval_status' => 'pending',
                'user_status' => 'active',
                'email_verified_at' => null,
                'role_slug' => 'organizer',
            ],
            23 => [
                'id' => 23,
                'user_id' => 11,
                'organization_name' => 'Approved Events',
                'approval_status' => 'approved',
                'rejection_reason' => null,
                'user_status' => 'active',
                'email_verified_at' => '2026-08-01 09:00:00',
                'role_slug' => 'organizer',
            ],
        ];
        $this->notifications = new FakeNotificationRepository();
        $this->service = new AdminPeopleService(
            $this->people,
            new NotificationService($this->notifications),
        );
    }

    public function testListInputIsScalarBoundedAndAllowlisted(): void
    {
        $this->service->users([
            'search' => str_repeat('x', 120),
            'role' => 'participant',
            'status' => 'suspended',
            'page' => '-8',
            'per_page' => '500',
        ]);
        $this->service->organizers([
            'search' => 'events',
            'approval_status' => 'pending',
            'page' => '2',
            'per_page' => '25',
        ]);

        $this->assertSame(100, mb_strlen($this->people->lastUserFilters['search']));
        $this->assertSame('participant', $this->people->lastUserFilters['role']);
        $this->assertSame('suspended', $this->people->lastUserFilters['status']);
        $this->assertSame('events', $this->people->lastOrganizerFilters['search']);
        $this->assertSame('pending', $this->people->lastOrganizerFilters['approval_status']);
    }

    public function testMalformedOrUnknownPeopleFiltersFailClosedWithoutARepositoryQuery(): void
    {
        $malformedUsers = $this->service->users(['search' => ['participant']]);
        $unknownUsers = $this->service->users(['role' => 'system-owner']);
        $malformedOrganizers = $this->service->organizers(['approval_status' => ['pending']]);
        $unknownOrganizers = $this->service->organizers(['approval_status' => 'archived']);

        $this->assertSame([], $malformedUsers['items']);
        $this->assertSame([], $unknownUsers['items']);
        $this->assertSame([], $malformedOrganizers['items']);
        $this->assertSame([], $unknownOrganizers['items']);
        $this->assertSame([], $this->people->lastUserFilters);
        $this->assertSame([], $this->people->lastOrganizerFilters);
    }

    public function testSuspendAndReactivateProtectSelfAndEverySuperAdministrator(): void
    {
        $self = $this->service->suspend(1, 1, []);
        $superAdmin = $this->service->suspend(9, 1, []);
        $suspended = $this->service->suspend(1, 10, ['ip_address' => '203.0.113.9']);
        $reactivated = $this->service->reactivate(1, 11, []);

        $this->assertFalse($self['success']);
        $this->assertSame('forbidden', $self['code']);
        $this->assertFalse($superAdmin['success']);
        $this->assertSame('forbidden', $superAdmin['code']);
        $this->assertTrue($suspended['success']);
        $this->assertTrue($reactivated['success']);
        $this->assertSame('suspended', $this->people->users[10]['status']);
        $this->assertSame('active', $this->people->users[11]['status']);
        $this->assertSame('203.0.113.9', $this->people->statusChanges[0]['context']['ip_address']);
    }

    public function testDeactivateAndReactivateInactiveParticipantUseCasStatusChanges(): void
    {
        $deactivated = $this->service->deactivate(1, 10, []);
        $reactivated = $this->service->reactivate(1, 12, []);

        $this->assertTrue($deactivated['success']);
        $this->assertSame('inactive', $this->people->users[10]['status']);
        $this->assertTrue($reactivated['success']);
        $this->assertSame('active', $this->people->users[12]['status']);
    }

    public function testStaleUserStatusActionReturnsConflictWithoutASecondWrite(): void
    {
        $this->people->forceStale = true;

        $result = $this->service->suspend(1, 10, []);

        $this->assertFalse($result['success']);
        $this->assertSame('conflict', $result['code']);
        $this->assertSame([], $this->people->statusChanges);
    }

    public function testOrganizerTransitionsValidateEligibilityAndReasonThenNotifyAfterCommit(): void
    {
        $missingReason = $this->service->rejectOrganizer(1, 20, '');
        $longReason = $this->service->rejectOrganizer(1, 20, str_repeat('r', 501));
        $ineligible = $this->service->approveOrganizer(1, 21);
        $approved = $this->service->approveOrganizer(1, 20);

        $this->assertArrayHasKey('reason', $missingReason['errors']);
        $this->assertArrayHasKey('reason', $longReason['errors']);
        $this->assertSame('conflict', $ineligible['code']);
        $this->assertTrue($approved['success']);
        $this->assertSame('approved', $this->people->organizers[20]['approval_status']);
        $notification = array_values($this->notifications->notifications)[0] ?? [];
        $this->assertSame(11, $notification['user_id'] ?? null);
        $this->assertSame('organizer_application_approved', $notification['type'] ?? null);
        $this->assertSame('/organizer/dashboard', $notification['action_url'] ?? null);
    }

    public function testRejectionDoesNotRequireApprovalEligibility(): void
    {
        $result = $this->service->rejectOrganizer(1, 21, 'Email verification is required before approval.');

        $this->assertTrue($result['success']);
        $this->assertSame('rejected', $this->people->organizers[21]['approval_status']);
    }

    public function testNotificationFailureDoesNotUndoCommittedApproval(): void
    {
        $this->notifications->throwOnCreate = true;

        $result = $this->service->approveOrganizer(1, 20);

        $this->assertTrue($result['success']);
        $this->assertSame('approved', $this->people->organizers[20]['approval_status']);
    }

    public function testApprovedOrganizerCanBeRejectedAndIdenticalReplayIsIdempotent(): void
    {
        $rejected = $this->service->rejectOrganizer(1, 23, 'Policy evidence is incomplete.');
        $replayed = $this->service->rejectOrganizer(1, 23, 'Policy evidence is incomplete.');

        $this->assertTrue($rejected['success']);
        $this->assertTrue($replayed['success']);
        $this->assertSame('rejected', $this->people->organizers[23]['approval_status']);
        $this->assertSame(1, count($this->people->approvalChanges));
        $this->assertSame(1, count($this->notifications->notifications));
    }

    public function testAlreadyApprovedOrganizerApprovalIsIdempotentWithoutDuplicateSideEffects(): void
    {
        $result = $this->service->approveOrganizer(1, 23);

        $this->assertTrue($result['success']);
        $this->assertSame([], $this->people->approvalChanges);
        $this->assertSame([], $this->notifications->notifications);
    }

    public function testConcurrentIdenticalOrganizerCasLoserRefetchesWinnerAsIdempotent(): void
    {
        $this->people->forceStale = true;
        $this->people->approvalWinner = ['status' => 'approved', 'reason' => null];
        $approved = $this->service->approveOrganizer(1, 20);

        $this->people->approvalWinner = [
            'status' => 'rejected',
            'reason' => 'Policy evidence is incomplete.',
        ];
        $rejected = $this->service->rejectOrganizer(1, 23, 'Policy evidence is incomplete.');

        $this->assertTrue($approved['success']);
        $this->assertTrue($rejected['success']);
        $this->assertSame([], $this->people->approvalChanges);
        $this->assertSame([], $this->notifications->notifications);
    }
}
