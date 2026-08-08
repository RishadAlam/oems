<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\RegistrationRepositoryInterface;

final class FakeRegistrationRepository implements RegistrationRepositoryInterface
{
    public ?\ArrayObject $lockTrace = null;

    public array $registrations = [];

    public array $organizerEvents = [];

    public array $organizerPageRequests = [];

    public bool $failReserve = false;

    public bool $failReactivate = false;

    public bool $failConfirm = false;

    public array $eligibleEvents = [];

    public function findEligibleEventForReservation(int $eventId): ?array
    {
        return $this->eligibleEvents[$eventId] ?? null;
    }

    public function lockEventCurrent(int $eventId): bool
    {
        $this->lockTrace?->append('event');

        return isset($this->eligibleEvents[$eventId]);
    }

    public function findForParticipantEvent(int $participantId, int $eventId): ?array
    {
        foreach ($this->registrations as $registration) {
            if ((int) $registration['user_id'] === $participantId
                && (int) $registration['event_id'] === $eventId) {
                return $this->withAliases($registration);
            }
        }

        return null;
    }

    public function findForParticipantEventCurrent(int $participantId, int $eventId): ?array
    {
        return $this->findForParticipantEvent($participantId, $eventId);
    }

    public function findForParticipant(int $participantId, int $registrationId): ?array
    {
        $registration = $this->registrations[$registrationId] ?? null;

        return is_array($registration) && (int) $registration['user_id'] === $participantId
            ? $this->withAliases($registration)
            : null;
    }

    public function findForParticipantCurrent(int $participantId, int $registrationId): ?array
    {
        $this->lockTrace?->append('registration');

        return $this->findForParticipant($participantId, $registrationId);
    }

    public function forParticipant(int $participantId): array
    {
        $registrations = array_values(array_filter(
            $this->registrations,
            static fn (array $registration): bool => (int) $registration['user_id'] === $participantId,
        ));
        usort($registrations, static fn (array $left, array $right): int => [
            $right['registered_at'] ?? '',
            $right['id'],
        ] <=> [
            $left['registered_at'] ?? '',
            $left['id'],
        ]);

        return array_map($this->withAliases(...), $registrations);
    }

    public function findOrganizerEvent(int $organizerUserId, int $eventId): ?array
    {
        $event = $this->organizerEvents[$eventId] ?? null;

        return is_array($event) && (int) ($event['organizer_user_id'] ?? 0) === $organizerUserId
            ? $event
            : null;
    }

    public function forOrganizerEvent(
        int $organizerUserId,
        int $eventId,
        array $filters,
        int $limit,
        int $offset,
    ): array {
        $this->organizerPageRequests[] = ['limit' => $limit, 'offset' => $offset];
        $rows = $this->filteredOrganizerEventRows($organizerUserId, $eventId, $filters);

        return array_map(
            $this->withAliases(...),
            array_slice($rows, max(0, $offset), min(100, max(1, $limit))),
        );
    }

    public function countForOrganizerEvent(int $organizerUserId, int $eventId, array $filters): int
    {
        return count($this->filteredOrganizerEventRows($organizerUserId, $eventId, $filters));
    }

    private function filteredOrganizerEventRows(int $organizerUserId, int $eventId, array $filters): array
    {
        if ($this->findOrganizerEvent($organizerUserId, $eventId) === null) {
            return [];
        }

        $allowed = [
            'registration_status' => ['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'],
            'payment_status' => ['none', 'pending', 'paid', 'failed', 'refunded', 'partially_refunded'],
            'ticket_status' => ['none', 'valid', 'used', 'cancelled'],
            'attendance_status' => ['not_checked_in', 'present', 'absent'],
        ];
        $rows = array_values(array_filter($this->registrations, function (array $row) use ($organizerUserId, $eventId, $filters, $allowed): bool {
            if ((int) ($row['organizer_user_id'] ?? 0) !== $organizerUserId || (int) $row['event_id'] !== $eventId) {
                return false;
            }

            foreach ($allowed as $filter => $values) {
                $value = $filters[$filter] ?? null;
                $actual = $filter === 'registration_status'
                    ? ($row['registration_status'] ?? $row['status'] ?? 'none')
                    : ($row[$filter] ?? 'none');
                if (is_string($value) && in_array($value, $values, true) && $actual !== $value) {
                    return false;
                }
            }

            $search = is_scalar($filters['search'] ?? null) ? trim((string) $filters['search']) : '';
            if ($search === '' || mb_strlen($search) > 120) {
                return true;
            }

            $haystack = implode(' ', [
                $row['participant_name'] ?? '',
                $row['participant_email'] ?? '',
                $row['registration_number'] ?? '',
                $row['ticket_number'] ?? '',
            ]);

            return str_contains(mb_strtolower($haystack), mb_strtolower($search));
        }));
        usort($rows, static fn (array $left, array $right): int => [
            $right['registered_at'] ?? '',
            $right['id'] ?? 0,
        ] <=> [
            $left['registered_at'] ?? '',
            $left['id'] ?? 0,
        ]);

        return $rows;
    }

    public function reserve(int $participantId, int $eventId, array $attributes): ?array
    {
        if ($this->failReserve || $this->findForParticipantEvent($participantId, $eventId) !== null) {
            return null;
        }

        $id = $this->registrations === [] ? 1 : max(array_keys($this->registrations)) + 1;
        $event = $this->eligibleEvents[$eventId] ?? [];
        $this->registrations[$id] = array_merge($attributes, [
            'id' => $id,
            'event_id' => $eventId,
            'user_id' => $participantId,
            'status' => $attributes['status'] ?? 'pending',
            'amount' => (string) ($event['ticket_price'] ?? '0'),
            'currency' => (string) ($event['currency'] ?? 'BDT'),
            'event_title' => (string) ($event['title'] ?? 'Event'),
            'event_slug' => (string) ($event['slug'] ?? ''),
            'event_start_date' => (string) ($event['start_date'] ?? ''),
            'registration_deadline' => (string) ($event['registration_deadline'] ?? ''),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        return $this->withAliases($this->registrations[$id]);
    }

    public function reactivate(int $registrationId, array $attributes): bool
    {
        $registration = $this->registrations[$registrationId] ?? null;

        if ($this->failReactivate
            || !is_array($registration)
            || !in_array($registration['status'], ['cancelled', 'refunded'], true)) {
            return false;
        }

        $this->registrations[$registrationId] = array_merge($registration, $attributes, [
            'status' => $attributes['status'] ?? 'pending',
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        return true;
    }

    public function confirm(int $registrationId): bool
    {
        if ($this->failConfirm || ($this->registrations[$registrationId]['status'] ?? null) !== 'pending') {
            return false;
        }

        $this->registrations[$registrationId]['status'] = 'confirmed';

        return true;
    }

    public function cancel(int $registrationId, string $reason): bool
    {
        if (!isset($this->registrations[$registrationId])
            || !in_array($this->registrations[$registrationId]['status'], ['pending', 'confirmed'], true)) {
            return false;
        }

        $this->registrations[$registrationId]['status'] = 'cancelled';
        $this->registrations[$registrationId]['cancellation_reason'] = $reason;

        return true;
    }

    public function cancelForParticipant(int $participantId, int $registrationId, string $reason): ?array
    {
        $registration = $this->findForParticipant($participantId, $registrationId);

        if ($registration === null || !in_array($registration['status'], ['pending', 'confirmed'], true)) {
            return null;
        }

        $this->registrations[$registrationId]['status'] = 'cancelled';
        $this->registrations[$registrationId]['cancelled_at'] = 'now';
        $this->registrations[$registrationId]['cancellation_reason'] = $reason;

        return $this->withAliases($this->registrations[$registrationId]);
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->statusSummary(
            static fn (array $registration): bool => (int) $registration['user_id'] === $participantId,
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->statusSummary(
            static fn (array $registration): bool => (int) ($registration['organizer_user_id'] ?? 0) === $organizerUserId,
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->statusSummary(static fn (array $registration): bool => true);
    }

    private function statusSummary(callable $scope): array
    {
        $summary = ['active' => 0, 'pending' => 0, 'confirmed' => 0];

        foreach ($this->registrations as $registration) {
            if (!$scope($registration)) {
                continue;
            }

            if ($registration['status'] === 'pending') {
                $summary['pending']++;
                $summary['active']++;
            } elseif ($registration['status'] === 'confirmed') {
                $summary['confirmed']++;
                $summary['active']++;
            }
        }

        return $summary;
    }

    private function withAliases(array $registration): array
    {
        $registration['registration_status'] = $registration['status'];

        return $registration;
    }
}
