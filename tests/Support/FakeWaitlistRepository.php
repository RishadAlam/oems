<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use DateTimeImmutable;
use OEMS\App\Contracts\WaitlistRepositoryInterface;

final class FakeWaitlistRepository implements WaitlistRepositoryInterface
{
    public array $events = [];

    public array $entries = [];

    public bool $failWrites = false;

    public array $expired = [];

    public array $promotableEventIds = [];

    public function findJoinableEvent(int $eventId): ?array
    {
        return $this->events[$eventId] ?? null;
    }

    public function findParticipantEntry(int $participantId, int $eventId): ?array
    {
        foreach ($this->entries as $entry) {
            if ((int) $entry['user_id'] === $participantId && (int) $entry['event_id'] === $eventId) {
                return $entry;
            }
        }

        return null;
    }

    public function findParticipantEntryById(int $participantId, int $registrationId): ?array
    {
        $entry = $this->entries[$registrationId] ?? null;

        return is_array($entry) && (int) $entry['user_id'] === $participantId ? $entry : null;
    }

    public function forParticipant(int $participantId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => (int) $entry['user_id'] === $participantId
                && ($entry['status'] ?? null) === 'waitlisted',
        ));
    }

    public function join(int $participantId, int $eventId, array $attributes): ?array
    {
        if ($this->failWrites) {
            throw new \RuntimeException('waitlist persistence path and secret');
        }
        $existing = $this->findParticipantEntry($participantId, $eventId);
        if (is_array($existing) && ($existing['status'] ?? null) === 'waitlisted') {
            return $existing;
        }
        if (is_array($existing) && !in_array(($existing['status'] ?? null), ['cancelled', 'refunded'], true)) {
            return null;
        }
        $id = is_array($existing) ? (int) $existing['id'] : ($this->entries === [] ? 1 : max(array_keys($this->entries)) + 1);
        $event = $this->events[$eventId] ?? [];
        $this->entries[$id] = array_merge($existing ?? [], $attributes, [
            'id' => $id,
            'event_id' => $eventId,
            'user_id' => $participantId,
            'status' => 'waitlisted',
            'event_title' => $event['title'] ?? 'Event',
            'event_slug' => $event['slug'] ?? 'event',
            'amount' => $event['ticket_price'] ?? '0.00',
            'currency' => $event['currency'] ?? 'BDT',
        ]);

        return $this->entries[$id];
    }

    public function leave(int $participantId, int $registrationId, string $reason, DateTimeImmutable $leftAt): ?array
    {
        if ($this->failWrites) {
            throw new \RuntimeException('waitlist leave failed');
        }
        $entry = $this->findParticipantEntryById($participantId, $registrationId);
        if (!is_array($entry) || ($entry['status'] ?? null) !== 'waitlisted') {
            return null;
        }
        $this->entries[$registrationId]['status'] = 'cancelled';
        $this->entries[$registrationId]['cancelled_at'] = $leftAt->format('Y-m-d H:i:s');
        $this->entries[$registrationId]['cancellation_reason'] = $reason;

        return $this->entries[$registrationId];
    }

    public function position(int $registrationId): ?int
    {
        $entry = $this->entries[$registrationId] ?? null;
        if (!is_array($entry) || ($entry['status'] ?? null) !== 'waitlisted') {
            return null;
        }
        $rows = array_values(array_filter($this->entries, static fn (array $row): bool =>
            (int) $row['event_id'] === (int) $entry['event_id'] && ($row['status'] ?? null) === 'waitlisted'));
        usort($rows, static fn (array $left, array $right): int => [
            $left['waitlisted_at'] ?? '', $left['id'],
        ] <=> [$right['waitlisted_at'] ?? '', $right['id']]);
        foreach ($rows as $index => $row) {
            if ((int) $row['id'] === $registrationId) {
                return $index + 1;
            }
        }

        return null;
    }

    public function claimOldest(int $eventId, DateTimeImmutable $promotedAt, DateTimeImmutable $claimExpiresAt): ?array
    {
        return null;
    }

    public function completeClaim(int $registrationId): bool
    {
        if (!isset($this->entries[$registrationId])) {
            return false;
        }
        $this->entries[$registrationId]['waitlist_claim_expires_at'] = null;
        return true;
    }

    public function expiredClaims(DateTimeImmutable $now, int $limit): array
    {
        return array_slice(array_values($this->expired), 0, $limit);
    }

    public function releaseExpiredClaim(int $registrationId, DateTimeImmutable $expiredAt): ?array
    {
        $entry = $this->entries[$registrationId] ?? null;
        if (!is_array($entry) || !isset($this->expired[$registrationId])) {
            return null;
        }
        unset($this->expired[$registrationId]);
        $this->entries[$registrationId]['status'] = 'cancelled';
        $this->entries[$registrationId]['cancellation_reason'] = 'Waitlist payment window expired';
        $this->entries[$registrationId]['waitlist_claim_expires_at'] = null;

        return $this->entries[$registrationId];
    }

    public function eventsWithAvailableSeats(int $limit): array
    {
        return array_slice($this->promotableEventIds, 0, $limit);
    }
}
