<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\TicketRepositoryInterface;

final class FakeTicketRepository implements TicketRepositoryInterface
{
    public array $tickets = [];

    public array $attendance = [];

    public bool $failCreate = false;

    public bool $failAttendance = false;

    public function createForRegistration(int $registrationId, array $attributes): int
    {
        if ($this->failCreate) {
            return 0;
        }

        $id = $this->tickets === [] ? 1 : max(array_keys($this->tickets)) + 1;
        $this->tickets[$id] = array_merge($attributes, [
            'id' => $id,
            'registration_id' => $registrationId,
            'status' => $attributes['status'] ?? 'valid',
        ]);

        return $id;
    }

    public function reactivateForRegistration(int $registrationId, array $attributes): bool
    {
        foreach ($this->tickets as $id => $ticket) {
            if ((int) $ticket['registration_id'] === $registrationId && $ticket['status'] === 'cancelled') {
                $this->tickets[$id] = array_merge($ticket, $attributes, ['status' => 'valid']);

                return true;
            }
        }

        return false;
    }

    public function findForRegistration(int $registrationId): ?array
    {
        foreach ($this->tickets as $ticket) {
            if ((int) $ticket['registration_id'] === $registrationId) {
                return $this->publicTicket($ticket);
            }
        }

        return null;
    }

    public function findForRegistrationCurrent(int $registrationId): ?array
    {
        return $this->findForRegistration($registrationId);
    }

    public function forParticipant(int $participantId): array
    {
        $tickets = array_values(array_filter(
            $this->tickets,
            static fn (array $ticket): bool => (int) $ticket['participant_id'] === $participantId,
        ));
        usort($tickets, static fn (array $left, array $right): int => [
            $right['issued_at'] ?? '',
            $right['id'],
        ] <=> [
            $left['issued_at'] ?? '',
            $left['id'],
        ]);

        return array_map($this->publicTicket(...), $tickets);
    }

    public function findForParticipant(int $participantId, int $ticketId): ?array
    {
        $ticket = $this->tickets[$ticketId] ?? null;

        return is_array($ticket) && (int) $ticket['participant_id'] === $participantId
            ? $this->publicTicket($ticket)
            : null;
    }

    public function findForOrganizerByTokenDigest(int $organizerId, string $tokenDigest): ?array
    {
        return $this->findForOrganizer($organizerId, 'qr_payload_hash', $tokenDigest);
    }

    public function findForOrganizerByNumber(int $organizerId, string $ticketNumber): ?array
    {
        return $this->findForOrganizer($organizerId, 'ticket_number', $ticketNumber);
    }

    public function voidForRegistration(int $registrationId): bool
    {
        foreach ($this->tickets as $id => $ticket) {
            if ((int) $ticket['registration_id'] === $registrationId && $ticket['status'] === 'valid') {
                $this->tickets[$id]['status'] = 'cancelled';

                return true;
            }
        }

        return false;
    }

    public function recordAttendance(int $organizerId, int $ticketId, int $scannerId, ?string $scannerIp): ?array
    {
        $ticket = $this->tickets[$ticketId] ?? null;

        if ($this->failAttendance
            || !is_array($ticket)
            || (int) $ticket['organizer_user_id'] !== $organizerId
            || ($ticket['registration_status'] ?? null) !== 'confirmed') {
            return null;
        }

        if (isset($this->attendance[$ticketId])) {
            return in_array($ticket['status'], ['valid', 'used'], true)
                ? $this->attendance[$ticketId]
                : null;
        }

        if ($ticket['status'] !== 'valid') {
            return null;
        }

        $attendance = [
            'id' => count($this->attendance) + 1,
            'registration_id' => $ticket['registration_id'],
            'ticket_id' => $ticketId,
            'scanned_by' => $scannerId,
            'status' => 'present',
            'attendance_status' => 'present',
            'scanned_at' => 'now',
            'scanner_ip' => $scannerIp,
        ];
        $this->attendance[$ticketId] = $attendance;
        $this->tickets[$ticketId]['status'] = 'used';

        return $attendance;
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->ticketSummary(
            static fn (array $ticket): bool => (int) ($ticket['participant_id'] ?? 0) === $participantId,
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->ticketSummary(
            static fn (array $ticket): bool => (int) ($ticket['organizer_user_id'] ?? 0) === $organizerUserId,
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->ticketSummary(static fn (array $ticket): bool => true);
    }

    private function ticketSummary(callable $scope): array
    {
        $summary = ['issued' => 0, 'checked_in' => 0];

        foreach ($this->tickets as $ticketId => $ticket) {
            if (!$scope($ticket)
                || ($ticket['registration_status'] ?? null) !== 'confirmed'
                || !in_array($ticket['status'], ['valid', 'used'], true)) {
                continue;
            }

            $summary['issued']++;

            if (($this->attendance[$ticketId]['status'] ?? null) === 'present' && $ticket['status'] === 'used') {
                $summary['checked_in']++;
            }
        }

        return $summary;
    }

    private function findForOrganizer(int $organizerUserId, string $field, string $value): ?array
    {
        foreach ($this->tickets as $ticket) {
            if ((int) $ticket['organizer_user_id'] === $organizerUserId
                && ($ticket[$field] ?? null) === $value
                && ($ticket['registration_status'] ?? null) === 'confirmed'
                && in_array($ticket['status'], ['valid', 'used'], true)) {
                return $this->publicTicket($ticket);
            }
        }

        return null;
    }

    private function publicTicket(array $ticket): array
    {
        unset($ticket['qr_payload_hash']);
        $ticket['ticket_status'] = $ticket['status'];

        return $ticket;
    }
}
