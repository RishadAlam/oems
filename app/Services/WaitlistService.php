<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\App\Contracts\WaitlistRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class WaitlistService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly WaitlistRepositoryInterface $waitlists,
        private readonly ?Logger $logger = null,
        private readonly ?NotificationService $notifications = null,
    ) {
    }

    public function join(int $actorId, int $eventId): array
    {
        if (!$this->eligibleParticipant($actorId)) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }
        $existing = $this->waitlists->findParticipantEntry($actorId, $eventId);
        if (($existing['status'] ?? null) === 'waitlisted') {
            return $this->joined($existing);
        }
        if ($this->waitlists->findJoinableEvent($eventId) === null) {
            return $this->failure(['event' => ['This event is not available for waitlisting.']]);
        }
        try {
            $entry = $this->waitlists->join($actorId, $eventId, [
                'registration_number' => 'OEMS-WAIT-' . strtoupper(bin2hex(random_bytes(12))),
                'waitlisted_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('waitlist_join', $actorId, $eventId, null, $exception);
            return $this->failure(['waitlist' => ['The waitlist could not be updated.']]);
        }

        if ($entry === null) {
            return $this->failure(['event' => ['This event can no longer accept a waitlist entry.']]);
        }
        $this->notifications?->notify(
            $actorId,
            'waitlist_joined',
            'Waitlist joined',
            'You joined the waitlist for ' . (string) ($entry['event_title'] ?? 'this event') . '.',
            '/participant/waitlist',
            ['registration_id' => (int) $entry['id'], 'event_id' => $eventId],
        );
        return $this->joined($entry);
    }

    public function leave(int $actorId, int $registrationId, mixed $reason): array
    {
        if (!$this->eligibleParticipant($actorId)) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }
        if (!is_scalar($reason)) {
            return $this->failure(['reason' => ['Enter a reason of no more than 500 characters.']]);
        }
        $reason = trim((string) $reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            return $this->failure(['reason' => ['Enter a reason of no more than 500 characters.']]);
        }
        $current = $this->waitlists->findParticipantEntryById($actorId, $registrationId);
        if ($current === null) {
            return $this->failure(['waitlist' => ['Waitlist entry not found.']]);
        }
        if (($current['status'] ?? null) === 'cancelled') {
            return $this->success($current);
        }
        if (($current['status'] ?? null) !== 'waitlisted') {
            return $this->failure(['waitlist' => ['This waitlist entry can no longer be left.']]);
        }
        try {
            $entry = $this->waitlists->leave($actorId, $registrationId, $reason, new DateTimeImmutable());
        } catch (Throwable $exception) {
            $this->logFailure('waitlist_leave', $actorId, (int) ($current['event_id'] ?? 0), $registrationId, $exception);
            return $this->failure(['waitlist' => ['The waitlist could not be updated.']]);
        }

        return $entry === null
            ? $this->failure(['waitlist' => ['This waitlist entry can no longer be left.']])
            : $this->success($entry);
    }

    public function forParticipant(int $actorId): array
    {
        if (!$this->eligibleParticipant($actorId)) {
            return [];
        }

        return array_map(function (array $entry): array {
            $entry['position'] = $this->waitlists->position((int) $entry['id']);
            return $entry;
        }, $this->waitlists->forParticipant($actorId));
    }

    public function releaseExpiredClaims(?DateTimeImmutable $now = null, int $limit = 100): array
    {
        $now ??= new DateTimeImmutable();
        $released = 0;
        $eventIds = [];

        foreach ($this->waitlists->expiredClaims($now, min(100, max(1, $limit))) as $claim) {
            $registrationId = (int) ($claim['id'] ?? 0);
            if ($registrationId <= 0) {
                continue;
            }
            try {
                $entry = $this->waitlists->releaseExpiredClaim($registrationId, $now);
            } catch (Throwable $exception) {
                $this->logFailure(
                    'waitlist_expiry',
                    (int) ($claim['user_id'] ?? 0),
                    (int) ($claim['event_id'] ?? 0),
                    $registrationId,
                    $exception,
                );
                continue;
            }
            if ($entry === null) {
                continue;
            }
            $released++;
            $eventId = (int) ($entry['event_id'] ?? 0);
            if ($eventId > 0) {
                $eventIds[$eventId] = $eventId;
            }
            $this->notifications?->notify(
                (int) ($entry['user_id'] ?? 0),
                'waitlist_expired',
                'Waitlist claim expired',
                'Your promoted seat expired before payment details were submitted.',
                '/participant/waitlist',
                ['registration_id' => $registrationId, 'event_id' => $eventId],
            );
        }

        return ['released' => $released, 'event_ids' => array_values($eventIds)];
    }

    public function promotionEventIds(int $limit = 100): array
    {
        return $this->waitlists->eventsWithAvailableSeats(min(100, max(1, $limit)));
    }

    private function joined(array $entry): array
    {
        return [
            'success' => true,
            'entry' => $entry,
            'position' => $this->waitlists->position((int) $entry['id']),
            'errors' => [],
        ];
    }

    private function success(array $entry): array
    {
        return ['success' => true, 'entry' => $entry, 'errors' => []];
    }

    private function failure(array $errors): array
    {
        return ['success' => false, 'entry' => null, 'errors' => $errors];
    }

    private function eligibleParticipant(int $actorId): bool
    {
        $user = $this->users->findById($actorId);
        $role = (string) ($user['role_slug'] ?? match ((int) ($user['role_id'] ?? 0)) {
            3 => 'participant',
            default => '',
        });

        return is_array($user)
            && $role === 'participant'
            && ($user['status'] ?? null) === 'active'
            && !empty($user['email_verified_at']);
    }

    private function logFailure(string $operation, int $actorId, int $eventId, ?int $registrationId, Throwable $exception): void
    {
        try {
            $this->logger?->error('Waitlist persistence failed.', [
                'operation' => $operation,
                'actor_id' => $actorId,
                'event_id' => $eventId,
                'registration_id' => $registrationId,
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging must not escape the waitlist boundary.
        }
    }
}
