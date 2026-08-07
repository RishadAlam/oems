<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface OrganizerRepositoryInterface
{
    public function approvalStatusForUser(int $userId): ?string;
}
