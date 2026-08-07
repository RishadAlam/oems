<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\OrganizerRepositoryInterface;

final class FakeOrganizerRepository implements OrganizerRepositoryInterface
{
    public array $approvalStatuses = [
        10 => 'approved',
        11 => 'pending',
    ];

    public function approvalStatusForUser(int $userId): ?string
    {
        return $this->approvalStatuses[$userId] ?? null;
    }
}
