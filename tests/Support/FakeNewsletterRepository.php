<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use DateTimeImmutable;
use OEMS\App\Contracts\NewsletterRepositoryInterface;

final class FakeNewsletterRepository implements NewsletterRepositoryInterface
{
    public array $subscribers = [];
    public array $campaigns = [];
    public int $nextSubscriberId = 1;
    public int $nextCampaignId = 1;

    public function savePending(string $email, string $confirmationHash, string $unsubscribeHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): ?array
    {
        foreach ($this->subscribers as $row) if ($row['email'] === $email && $row['status'] === 'subscribed') return $row;
        $id = $this->nextSubscriberId++;
        return $this->subscribers[$id] = ['id' => $id, 'email' => $email, 'status' => 'pending', 'confirmation_token_hash' => $confirmationHash, 'unsubscribe_token_hash' => $unsubscribeHash, 'confirmation_expires_at' => $expiresAt->format('Y-m-d H:i:s')];
    }

    public function confirm(string $tokenHash, DateTimeImmutable $now): ?array
    {
        foreach ($this->subscribers as $id => $row) if ($row['confirmation_token_hash'] === $tokenHash && $row['status'] === 'pending' && $row['confirmation_expires_at'] >= $now->format('Y-m-d H:i:s')) { $this->subscribers[$id]['status'] = 'subscribed'; $this->subscribers[$id]['confirmation_token_hash'] = null; return $this->subscribers[$id]; }
        return null;
    }

    public function unsubscribe(string $tokenHash, DateTimeImmutable $now): ?array
    {
        foreach ($this->subscribers as $id => $row) if (hash_equals((string) $row['unsubscribe_token_hash'], $tokenHash) && $row['status'] === 'subscribed') { $this->subscribers[$id]['status'] = 'unsubscribed'; return $this->subscribers[$id]; }
        return null;
    }

    public function confirmedSubscribers(int $limit, int $offset): array { return array_slice(array_values(array_filter($this->subscribers, static fn (array $row): bool => $row['status'] === 'subscribed')), $offset, $limit); }
    public function rotateUnsubscribeToken(int $id, string $tokenHash): bool { if (!isset($this->subscribers[$id])) return false; $this->subscribers[$id]['unsubscribe_token_hash'] = $tokenHash; return true; }
    public function campaigns(): array { return array_values($this->campaigns); }
    public function findCampaign(int $id, bool $lock = false): ?array { return $this->campaigns[$id] ?? null; }
    public function createCampaign(int $administratorId, array $attributes): ?array { $id = $this->nextCampaignId++; return $this->campaigns[$id] = ['id' => $id, 'status' => 'draft', 'created_by' => $administratorId, ...$attributes]; }
    public function markCampaignQueued(int $id, int $recipientCount, int $queuedCount, DateTimeImmutable $queuedAt): bool { if (($this->campaigns[$id]['status'] ?? null) !== 'draft') return false; $this->campaigns[$id] = [...$this->campaigns[$id], 'status' => 'queued', 'recipient_count' => $recipientCount, 'queued_count' => $queuedCount]; return true; }
}
