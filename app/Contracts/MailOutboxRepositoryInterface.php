<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface MailOutboxRepositoryInterface
{
    public function enqueue(array $job): ?array;

    public function claimBatch(int $limit, string $lockToken, DateTimeImmutable $now): array;

    public function markSent(int $id, string $lockToken, ?string $providerId, DateTimeImmutable $sentAt): bool;

    public function releaseFailed(
        int $id,
        string $lockToken,
        int $attempts,
        DateTimeImmutable $availableAt,
        string $error,
        bool $terminal,
    ): bool;
}
