<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface RegistrationRepositoryInterface
{
    public function findEligibleEventForReservation(int $eventId): ?array;

    public function lockEventCurrent(int $eventId): bool;

    public function findForParticipantEvent(int $participantId, int $eventId): ?array;

    public function findForParticipantEventCurrent(int $participantId, int $eventId): ?array;

    public function findForParticipant(int $participantId, int $registrationId): ?array;

    public function findForParticipantCurrent(int $participantId, int $registrationId): ?array;

    public function forParticipant(int $participantId): array;

    public function dueReminderRecipients(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit,
        int $offset = 0,
    ): array;

    public function findCalendarForParticipant(int $participantId, int $registrationId): ?array;

    public function findOrganizerEvent(int $organizerUserId, int $eventId): ?array;

    public function forOrganizerEvent(
        int $organizerUserId,
        int $eventId,
        array $filters,
        int $limit,
        int $offset,
    ): array;

    public function countForOrganizerEvent(int $organizerUserId, int $eventId, array $filters): int;

    public function reserve(int $participantId, int $eventId, array $attributes): ?array;

    public function reactivate(int $registrationId, array $attributes): bool;

    public function confirm(int $registrationId): bool;

    public function cancel(int $registrationId, string $reason): bool;

    public function cancelForParticipant(int $participantId, int $registrationId, string $reason): ?array;

    public function summaryForParticipant(int $participantId): array;

    public function summaryForOrganizer(int $organizerUserId): array;

    public function summaryForAdmin(): array;
}
