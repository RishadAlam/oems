<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use DateTimeImmutable;
use OEMS\App\Contracts\MailOutboxRepositoryInterface;
use RuntimeException;

final class FakeMailOutboxRepository implements MailOutboxRepositoryInterface
{
    public array $jobs = [];
    public bool $throwOnEnqueue = false;

    public function enqueue(array $job): ?array
    {
        if ($this->throwOnEnqueue) {
            throw new RuntimeException('Database credentials and SMTP payload must not escape.');
        }

        foreach ($this->jobs as $existing) {
            if ($existing['idempotency_key'] === $job['idempotency_key']) {
                return $existing;
            }
        }

        $job = array_merge($job, [
            'id' => count($this->jobs) + 1,
            'status' => 'queued',
            'attempts' => 0,
            'lock_token' => null,
            'locked_at' => null,
            'sent_at' => null,
            'provider_message_id' => null,
            'last_error' => null,
        ]);
        $this->jobs[] = $job;

        return $job;
    }

    public function claimBatch(int $limit, string $lockToken, DateTimeImmutable $now): array
    {
        return [];
    }

    public function markSent(int $id, string $lockToken, ?string $providerId, DateTimeImmutable $sentAt): bool
    {
        return false;
    }

    public function releaseFailed(
        int $id,
        string $lockToken,
        int $attempts,
        DateTimeImmutable $availableAt,
        string $error,
        bool $terminal,
    ): bool {
        return false;
    }
}
