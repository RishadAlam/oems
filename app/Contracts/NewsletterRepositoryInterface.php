<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface NewsletterRepositoryInterface
{
    public function savePending(string $email, string $confirmationHash, string $unsubscribeHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): ?array;
    public function confirm(string $tokenHash, DateTimeImmutable $now): ?array;
    public function unsubscribe(string $tokenHash, DateTimeImmutable $now): ?array;
    public function confirmedSubscribers(int $limit, int $offset): array;
    public function rotateUnsubscribeToken(int $id, string $tokenHash): bool;
    public function campaigns(): array;
    public function findCampaign(int $id, bool $lock = false): ?array;
    public function createCampaign(int $administratorId, array $attributes): ?array;
    public function markCampaignQueued(int $id, int $recipientCount, int $queuedCount, DateTimeImmutable $queuedAt): bool;
}
