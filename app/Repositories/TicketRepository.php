<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\TicketRepositoryInterface;
use PDO;
use RuntimeException;

final class TicketRepository implements TicketRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function createForRegistration(int $registrationId, array $attributes): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO tickets
                (registration_id, ticket_number, qr_payload_hash, qr_path, pdf_path, status, issued_at)
             VALUES
                (:registration_id, :ticket_number, :qr_payload_hash, :qr_path, :pdf_path, :ticket_status, :issued_at)',
        );
        $statement->execute([
            'registration_id' => $registrationId,
            'ticket_number' => (string) $attributes['ticket_number'],
            'qr_payload_hash' => (string) $attributes['qr_payload_hash'],
            'qr_path' => $attributes['qr_path'] ?? null,
            'pdf_path' => $attributes['pdf_path'] ?? null,
            'ticket_status' => (string) ($attributes['status'] ?? 'valid'),
            'issued_at' => (string) $attributes['issued_at'],
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function reactivateForRegistration(int $registrationId, array $attributes): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE tickets
             SET ticket_number = :ticket_number,
                 qr_payload_hash = :qr_payload_hash,
                 qr_path = :qr_path,
                 pdf_path = :pdf_path,
                 status = 'valid',
                 issued_at = :issued_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE registration_id = :registration_id
               AND status = 'cancelled'",
        );
        $statement->execute([
            'ticket_number' => (string) $attributes['ticket_number'],
            'qr_payload_hash' => (string) $attributes['qr_payload_hash'],
            'qr_path' => $attributes['qr_path'] ?? null,
            'pdf_path' => $attributes['pdf_path'] ?? null,
            'issued_at' => (string) $attributes['issued_at'],
            'registration_id' => $registrationId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function findForRegistration(int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            $this->ticketSelect() . ' WHERE tickets.registration_id = :registration_id LIMIT 1',
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $this->rowOrNull($statement->fetch());
    }

    public function findForRegistrationCurrent(int $registrationId): ?array
    {
        $lockingClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            $this->ticketSelect()
            . ' WHERE tickets.registration_id = :registration_id LIMIT 1'
            . $lockingClause,
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $this->rowOrNull($statement->fetch());
    }

    public function forParticipant(int $participantId): array
    {
        $statement = $this->connection->prepare(
            $this->ticketSelect()
            . ' WHERE registrations.user_id = :user_id'
            . ' ORDER BY tickets.issued_at DESC, tickets.id DESC',
        );
        $statement->execute(['user_id' => $participantId]);

        return $statement->fetchAll();
    }

    public function findForParticipant(int $participantId, int $ticketId): ?array
    {
        $statement = $this->connection->prepare(
            $this->ticketSelect()
            . ' WHERE registrations.user_id = :user_id AND tickets.id = :ticket_id LIMIT 1',
        );
        $statement->execute([
            'user_id' => $participantId,
            'ticket_id' => $ticketId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    public function findForOrganizerByTokenDigest(int $organizerId, string $tokenDigest): ?array
    {
        return $this->findForOrganizer($organizerId, 'tickets.qr_payload_hash', $tokenDigest);
    }

    public function findForOrganizerByNumber(int $organizerId, string $ticketNumber): ?array
    {
        return $this->findForOrganizer($organizerId, 'tickets.ticket_number', $ticketNumber);
    }

    public function voidForRegistration(int $registrationId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE tickets
             SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
             WHERE registration_id = :registration_id AND status = 'valid'",
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $statement->rowCount() === 1;
    }

    public function recordAttendance(int $organizerId, int $ticketId, int $scannerId, ?string $scannerIp): ?array
    {
        $existing = $this->findAttendance($organizerId, $ticketId);

        if ($existing !== null) {
            return $existing;
        }

        $eligibleTicket = $this->eligibleTicketForAttendance($organizerId, $ticketId);

        if ($eligibleTicket === null) {
            return $this->findAttendance($organizerId, $ticketId, true);
        }

        $markUsed = $this->connection->prepare(
            "UPDATE tickets
             SET status = 'used', updated_at = CURRENT_TIMESTAMP
             WHERE id = :ticket_id
               AND status = 'valid'
               AND EXISTS (
                   SELECT 1
                   FROM registrations
                   INNER JOIN events ON events.id = registrations.event_id
                   INNER JOIN organizers ON organizers.id = events.organizer_id
                   WHERE registrations.id = tickets.registration_id
                     AND registrations.status = 'confirmed'
                     AND organizers.user_id = :organizer_user_id
               )",
        );
        $markUsed->execute([
            'ticket_id' => $ticketId,
            'organizer_user_id' => $organizerId,
        ]);

        if ($markUsed->rowCount() !== 1) {
            $winnerAttendance = $this->findAttendance($organizerId, $ticketId, true);

            if ($winnerAttendance !== null) {
                return $winnerAttendance;
            }

            throw new RuntimeException('Ticket state changed before attendance could be recorded.');
        }

        $statement = $this->connection->prepare(
            "INSERT INTO attendance
                (registration_id, ticket_id, scanned_by, status, scanned_at, scanner_ip)
             VALUES
                (:registration_id, :ticket_id, :scanned_by, 'present', CURRENT_TIMESTAMP, :scanner_ip)",
        );
        $statement->execute([
            'registration_id' => (int) $eligibleTicket['registration_id'],
            'ticket_id' => $ticketId,
            'scanned_by' => $scannerId,
            'scanner_ip' => $scannerIp,
        ]);

        return $this->findAttendance($organizerId, $ticketId);
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->ticketSummary(
            '',
            'WHERE registrations.user_id = :participant_user_id',
            ['participant_user_id' => $participantId],
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->ticketSummary(
            'INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id',
            'WHERE organizers.user_id = :organizer_user_id',
            ['organizer_user_id' => $organizerUserId],
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->ticketSummary('', '', []);
    }

    private function ticketSelect(): string
    {
        return 'SELECT tickets.id,
                       tickets.registration_id,
                       tickets.ticket_number,
                       tickets.qr_path,
                       tickets.pdf_path,
                       tickets.status,
                       tickets.status AS ticket_status,
                       tickets.issued_at,
                       tickets.created_at,
                       tickets.updated_at,
                       registrations.user_id AS participant_id,
                       registrations.registration_number,
                       registrations.status AS registration_status,
                       events.id AS event_id,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       events.status AS event_status,
                       attendance.id AS attendance_id,
                       attendance.status AS attendance_status,
                       attendance.scanned_at
                FROM tickets
                INNER JOIN registrations ON registrations.id = tickets.registration_id
                INNER JOIN events ON events.id = registrations.event_id
                LEFT JOIN attendance ON attendance.ticket_id = tickets.id';
    }

    private function organizerTicketSelect(): string
    {
        return $this->ticketSelect()
            . ' INNER JOIN organizers ON organizers.id = events.organizer_id';
    }

    private function findForOrganizer(int $organizerUserId, string $lookupColumn, string $lookupValue): ?array
    {
        $lookupColumns = [
            'tickets.qr_payload_hash' => true,
            'tickets.ticket_number' => true,
        ];

        if (!isset($lookupColumns[$lookupColumn])) {
            return null;
        }

        $statement = $this->connection->prepare(
            $this->organizerTicketSelect()
            . " WHERE organizers.user_id = :organizer_user_id
                  AND {$lookupColumn} = :lookup_value
                  AND registrations.status = 'confirmed'
                  AND tickets.status IN ('valid', 'used')
                LIMIT 1",
        );
        $statement->execute([
            'organizer_user_id' => $organizerUserId,
            'lookup_value' => $lookupValue,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    private function findAttendance(int $organizerUserId, int $ticketId, bool $currentRead = false): ?array
    {
        $lockingClause = $currentRead && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            "SELECT attendance.id,
                    attendance.registration_id,
                    attendance.ticket_id,
                    attendance.scanned_by,
                    attendance.status,
                    attendance.status AS attendance_status,
                    attendance.scanned_at,
                    attendance.scanner_ip,
                    attendance.created_at,
                    tickets.ticket_number,
                    registrations.registration_number,
                    registrations.status AS registration_status,
                    events.id AS event_id,
                    events.title AS event_title
             FROM attendance
             INNER JOIN tickets ON tickets.id = attendance.ticket_id
             INNER JOIN registrations ON registrations.id = attendance.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE attendance.ticket_id = :ticket_id
               AND organizers.user_id = :organizer_user_id
               AND registrations.status = 'confirmed'
               AND tickets.status IN ('valid', 'used')
             LIMIT 1" . $lockingClause,
        );
        $statement->execute([
            'ticket_id' => $ticketId,
            'organizer_user_id' => $organizerUserId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    private function eligibleTicketForAttendance(int $organizerUserId, int $ticketId): ?array
    {
        $lockingClause = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->connection->prepare(
            "SELECT tickets.id, tickets.registration_id
             FROM tickets
             INNER JOIN registrations ON registrations.id = tickets.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             WHERE tickets.id = :ticket_id
               AND organizers.user_id = :organizer_user_id
               AND tickets.status = 'valid'
               AND registrations.status = 'confirmed'
             LIMIT 1" . $lockingClause,
        );
        $statement->execute([
            'ticket_id' => $ticketId,
            'organizer_user_id' => $organizerUserId,
        ]);

        return $this->rowOrNull($statement->fetch());
    }

    private function ticketSummary(string $joins, string $where, array $bindings): array
    {
        $statement = $this->connection->prepare(
            "SELECT COALESCE(SUM(CASE
                        WHEN tickets.status IN ('valid', 'used') AND registrations.status = 'confirmed' THEN 1
                        ELSE 0
                    END), 0) AS issued,
                    COALESCE(SUM(CASE
                        WHEN attendance.status = 'present' AND tickets.status = 'used' AND registrations.status = 'confirmed' THEN 1
                        ELSE 0
                    END), 0) AS checked_in
             FROM tickets
             INNER JOIN registrations ON registrations.id = tickets.registration_id
             LEFT JOIN attendance ON attendance.ticket_id = tickets.id
             {$joins}
             {$where}",
        );
        $statement->execute($bindings);
        $summary = $statement->fetch();

        return [
            'issued' => (int) ($summary['issued'] ?? 0),
            'checked_in' => (int) ($summary['checked_in'] ?? 0),
        ];
    }

    private function rowOrNull(mixed $row): ?array
    {
        return is_array($row) ? $row : null;
    }
}
