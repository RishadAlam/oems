<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface PaymentRepositoryInterface
{
    public function findActiveMethodBySlug(string $slug): ?array;

    public function createForRegistration(int $registrationId, array $attributes): int;

    public function findForRegistration(int $registrationId): ?array;

    public function pendingForAdmin(): array;

    public function findForAdmin(int $paymentId): ?array;

    public function review(int $paymentId, int $administratorId, string $status, ?string $note): ?array;

    public function cancelForRegistration(int $registrationId): bool;

    public function summaryForParticipant(int $participantId): array;

    public function summaryForOrganizer(int $organizerUserId): array;

    public function summaryForAdmin(): array;
}
