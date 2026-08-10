<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\AdminPeopleRepositoryInterface;

final class FakeAdminPeopleRepository implements AdminPeopleRepositoryInterface
{
    public array $users = [];

    public array $organizers = [];

    public array $statusChanges = [];

    public array $approvalChanges = [];

    public bool $forceStale = false;

    public ?array $approvalWinner = null;

    public array $lastUserFilters = [];

    public array $lastOrganizerFilters = [];

    public function users(array $filters, int $page, int $perPage): array
    {
        $this->lastUserFilters = $filters;

        return $this->page(array_values($this->users), $page, $perPage);
    }

    public function findUser(int $userId): ?array
    {
        return $this->users[$userId] ?? null;
    }

    public function organizers(array $filters, int $page, int $perPage): array
    {
        $this->lastOrganizerFilters = $filters;

        return $this->page(array_values($this->organizers), $page, $perPage);
    }

    public function findOrganizer(int $organizerId): ?array
    {
        return $this->organizers[$organizerId] ?? null;
    }

    public function changeUserStatus(
        int $actorId,
        int $userId,
        string $expectedStatus,
        string $status,
        array $context,
    ): bool {
        if ($this->forceStale || ($this->users[$userId]['status'] ?? null) !== $expectedStatus) {
            return false;
        }

        $this->users[$userId]['status'] = $status;
        $this->statusChanges[] = compact('actorId', 'userId', 'expectedStatus', 'status', 'context');

        return true;
    }

    public function changeOrganizerApproval(
        int $actorId,
        int $organizerId,
        string $expectedStatus,
        string $status,
        ?string $reason,
        array $context,
    ): ?array {
        if ($this->forceStale) {
            if ($this->approvalWinner !== null && isset($this->organizers[$organizerId])) {
                $winnerStatus = (string) ($this->approvalWinner['status'] ?? '');
                $this->organizers[$organizerId]['approval_status'] = $winnerStatus;
                $this->organizers[$organizerId]['rejection_reason'] = $winnerStatus === 'rejected'
                    ? ($this->approvalWinner['reason'] ?? null)
                    : null;
            }

            return null;
        }

        if (($this->organizers[$organizerId]['approval_status'] ?? null) !== $expectedStatus) {
            return null;
        }

        $this->organizers[$organizerId]['approval_status'] = $status;
        $this->organizers[$organizerId]['rejection_reason'] = $status === 'rejected' ? $reason : null;
        $this->organizers[$organizerId]['approved_by'] = $actorId;
        $this->organizers[$organizerId]['approved_at'] = '2026-08-10 12:00:00';
        $this->approvalChanges[] = compact(
            'actorId',
            'organizerId',
            'expectedStatus',
            'status',
            'reason',
            'context',
        );

        return $this->organizers[$organizerId];
    }

    private function page(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $lastPage);

        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
