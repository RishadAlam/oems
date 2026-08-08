<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\RegistrationRepositoryInterface;

final class FakeRegistrationRepository implements RegistrationRepositoryInterface
{
    public array $registrations = [];

    public bool $failReserve = false;

    public bool $failReactivate = false;

    public bool $failConfirm = false;

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

    public function findForParticipant(int $participantId, int $registrationId): ?array
    {
        $registration = $this->registrations[$registrationId] ?? null;

        return is_array($registration) && (int) $registration['user_id'] === $participantId
            ? $this->withAliases($registration)
            : null;
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

    public function reserve(int $participantId, int $eventId, array $attributes): ?array
    {
        if ($this->failReserve || $this->findForParticipantEvent($participantId, $eventId) !== null) {
            return null;
        }

        $id = $this->registrations === [] ? 1 : max(array_keys($this->registrations)) + 1;
        $this->registrations[$id] = array_merge($attributes, [
            'id' => $id,
            'event_id' => $eventId,
            'user_id' => $participantId,
            'status' => $attributes['status'] ?? 'pending',
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

    private function withAliases(array $registration): array
    {
        $registration['registration_status'] = $registration['status'];

        return $registration;
    }
}
