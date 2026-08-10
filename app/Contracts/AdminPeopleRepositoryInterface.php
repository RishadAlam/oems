<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface AdminPeopleRepositoryInterface
{
    public function users(array $filters, int $page, int $perPage): array;

    public function findUser(int $userId): ?array;

    public function organizers(array $filters, int $page, int $perPage): array;

    public function findOrganizer(int $organizerId): ?array;

    public function changeUserStatus(
        int $actorId,
        int $userId,
        string $expectedStatus,
        string $status,
        array $context,
    ): bool;

    public function changeOrganizerApproval(
        int $actorId,
        int $organizerId,
        string $expectedStatus,
        string $status,
        ?string $reason,
        array $context,
    ): ?array;
}
