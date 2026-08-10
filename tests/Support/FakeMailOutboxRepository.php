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
        $limit = min(100, max(1, $limit));
        $claimed = [];
        foreach ($this->jobs as &$job) {
            if (count($claimed) >= $limit) break;
            if (($job['status'] ?? null) !== 'queued' || (string) $job['available_at'] > $now->format('Y-m-d H:i:s')) continue;
            $job['status'] = 'processing';
            $job['lock_token'] = $lockToken;
            $job['locked_at'] = $now->format('Y-m-d H:i:s');
            $claimed[] = $job;
        }
        unset($job);

        return $claimed;
    }

    public function markSent(int $id, string $lockToken, ?string $providerId, DateTimeImmutable $sentAt): bool
    {
        foreach ($this->jobs as &$job) {
            if ($job['id'] !== $id || $job['status'] !== 'processing' || $job['lock_token'] !== $lockToken) continue;
            $job['status'] = 'sent';
            $job['sent_at'] = $sentAt->format('Y-m-d H:i:s');
            $job['provider_message_id'] = $providerId;
            $job['lock_token'] = null;
            $job['locked_at'] = null;
            unset($job);
            return true;
        }
        unset($job);
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
        foreach ($this->jobs as &$job) {
            if ($job['id'] !== $id || $job['status'] !== 'processing' || $job['lock_token'] !== $lockToken) continue;
            $job['status'] = $terminal ? 'failed' : 'queued';
            $job['attempts'] = $attempts;
            $job['available_at'] = $availableAt->format('Y-m-d H:i:s');
            $job['last_error'] = $error;
            $job['lock_token'] = null;
            $job['locked_at'] = null;
            unset($job);
            return true;
        }
        unset($job);
        return false;
    }
}
