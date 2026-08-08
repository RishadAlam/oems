<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface TicketRepositoryInterface
{
    public function createForRegistration(int $registrationId, array $attributes): int;

    public function reactivateForRegistration(int $registrationId, array $attributes): bool;

    public function findForRegistration(int $registrationId): ?array;

    public function forParticipant(int $participantId): array;

    public function findForParticipant(int $participantId, int $ticketId): ?array;

    public function findForOrganizerByTokenDigest(int $organizerId, string $tokenDigest): ?array;

    public function findForOrganizerByNumber(int $organizerId, string $ticketNumber): ?array;

    public function voidForRegistration(int $registrationId): bool;

    public function recordAttendance(int $organizerId, int $ticketId, int $scannerId, ?string $scannerIp): ?array;

    public function summaryForParticipant(int $participantId): array;

    public function summaryForOrganizer(int $organizerUserId): array;

    public function summaryForAdmin(): array;
}
