<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\CertificateRepositoryInterface;
use PDO;

final class CertificateRepository implements CertificateRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function lockEligibleRegistration(int $participantId, int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT registrations.id AS registration_id,
                    registrations.user_id AS participant_id,
                    users.name AS participant_name,
                    events.id AS event_id,
                    events.title AS event_title,
                    events.end_date AS completion_date,
                    tickets.id AS ticket_id,
                    attendance.id AS attendance_id,
                    attendance.scanned_at
             FROM registrations
             INNER JOIN users ON users.id = registrations.user_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN tickets ON tickets.registration_id = registrations.id
             INNER JOIN attendance ON attendance.registration_id = registrations.id
                 AND attendance.ticket_id = tickets.id
             WHERE registrations.id = :registration_id
               AND registrations.user_id = :participant_id
               AND registrations.status = 'confirmed'
               AND users.status = 'active'
               AND users.email_verified_at IS NOT NULL
               AND users.deleted_at IS NULL
               AND events.status = 'completed'
               AND events.deleted_at IS NULL
               AND tickets.status = 'used'
               AND attendance.status = 'present'
             LIMIT 1" . $this->lockingClause(),
        );
        $statement->execute([
            'registration_id' => $registrationId,
            'participant_id' => $participantId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(int $registrationId, int $participantId, array $attributes): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO event_certificates
                (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at)
             VALUES
                (:registration_id, :participant_id, :certificate_number, :verification_token_hash, :pdf_path, 'valid', :issued_at)",
        );
        $statement->execute([
            'registration_id' => $registrationId,
            'participant_id' => $participantId,
            'certificate_number' => (string) ($attributes['certificate_number'] ?? ''),
            'verification_token_hash' => (string) ($attributes['verification_token_hash'] ?? ''),
            'pdf_path' => (string) ($attributes['pdf_path'] ?? ''),
            'issued_at' => (string) ($attributes['issued_at'] ?? ''),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findForRegistration(int $participantId, int $registrationId): ?array
    {
        return $this->participantCertificate('event_certificates.registration_id = :lookup_id', $participantId, $registrationId);
    }

    public function findForParticipant(int $participantId, int $certificateId): ?array
    {
        return $this->participantCertificate('event_certificates.id = :lookup_id', $participantId, $certificateId);
    }

    public function forParticipant(int $participantId): array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE event_certificates.participant_id = :participant_id'
            . ' ORDER BY event_certificates.issued_at DESC, event_certificates.id DESC',
        );
        $statement->execute(['participant_id' => $participantId]);

        return $statement->fetchAll();
    }

    public function findValidByTokenDigest(string $digest): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT users.name AS participant_name,
                    events.title AS event_title,
                    events.end_date AS completion_date,
                    event_certificates.issued_at
             FROM event_certificates
             INNER JOIN registrations ON registrations.id = event_certificates.registration_id
             INNER JOIN users ON users.id = event_certificates.participant_id
                 AND users.id = registrations.user_id
             INNER JOIN events ON events.id = registrations.event_id
             WHERE event_certificates.verification_token_hash = :digest
               AND event_certificates.status = 'valid'
               AND users.status = 'active'
               AND users.email_verified_at IS NOT NULL
               AND users.deleted_at IS NULL
             LIMIT 1",
        );
        $statement->execute(['digest' => $digest]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function participantCertificate(string $lookup, int $participantId, int $lookupId): ?array
    {
        $statement = $this->connection->prepare(
            $this->participantSelect()
            . ' WHERE event_certificates.participant_id = :participant_id AND ' . $lookup . ' LIMIT 1',
        );
        $statement->execute(['participant_id' => $participantId, 'lookup_id' => $lookupId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function participantSelect(): string
    {
        return "SELECT event_certificates.id,
                       event_certificates.registration_id,
                       event_certificates.participant_id,
                       event_certificates.certificate_number,
                       event_certificates.pdf_path,
                       event_certificates.status,
                       event_certificates.issued_at,
                       event_certificates.revoked_at,
                       event_certificates.revocation_reason,
                       events.title AS event_title,
                       events.end_date AS completion_date
                FROM event_certificates
                INNER JOIN registrations ON registrations.id = event_certificates.registration_id
                INNER JOIN events ON events.id = registrations.event_id";
    }

    private function lockingClause(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }
}
