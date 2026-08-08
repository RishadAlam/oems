<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\TicketRepositoryInterface;
use PDO;
use RuntimeException;
use Throwable;

final class TicketService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketArtifactService $artifacts,
        private readonly string $expectedCheckInUrl = '/organizer/check-in',
    ) {
    }

    public function issue(array $registration, array $participant, array $event): array
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $issuance = null;

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $issuance = $this->issueWithinTransaction($registration, $participant, $event);

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $issuance;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if (is_array($issuance)) {
                $this->cleanupCreated($issuance);
            }

            throw $exception;
        }
    }

    private function issueWithinTransaction(array $registration, array $participant, array $event): array
    {
        $registrationId = (int) ($registration['id'] ?? 0);
        $existing = $this->tickets->findForRegistration($registrationId);

        if ($existing !== null && in_array((string) ($existing['ticket_status'] ?? ''), ['valid', 'used'], true)) {
            return [
                'ticket' => $existing,
                'created' => false,
                'created_paths' => [],
                'replaced_paths' => [],
            ];
        }

        $artifact = $this->artifacts->generate([
            'event_title' => $event['title'] ?? $event['event_title'] ?? '',
            'event_starts_at' => $event['start_date'] ?? $event['event_start_date'] ?? '',
            'venue_name' => $event['venue_name'] ?? '',
            'participant_name' => $participant['name'] ?? $participant['participant_name'] ?? '',
        ]);
        $createdPaths = [$artifact['qr_path'], $artifact['pdf_path']];
        $attributes = [
            'ticket_number' => $artifact['ticket_number'],
            'qr_payload_hash' => $artifact['qr_payload_hash'],
            'qr_path' => $artifact['qr_path'],
            'pdf_path' => $artifact['pdf_path'],
            'status' => 'valid',
            'issued_at' => date('Y-m-d H:i:s'),
        ];
        $replacedPaths = [];

        try {
            if ($existing !== null && (string) ($existing['ticket_status'] ?? '') === 'cancelled') {
                $replacedPaths = array_values(array_filter([
                    $existing['qr_path'] ?? null,
                    $existing['pdf_path'] ?? null,
                ], 'is_string'));

                if (!$this->tickets->reactivateForRegistration($registrationId, $attributes)) {
                    throw new RuntimeException('The ticket could not be reactivated.');
                }
            } elseif ($this->tickets->createForRegistration($registrationId, $attributes) <= 0) {
                throw new RuntimeException('The ticket could not be issued.');
            }

            $ticket = $this->tickets->findForRegistration($registrationId);

            if ($ticket === null) {
                throw new RuntimeException('The issued ticket could not be read.');
            }
        } catch (Throwable $exception) {
            $this->deletePaths($createdPaths);

            throw $exception;
        }

        return [
            'ticket' => $ticket,
            'created' => true,
            'created_paths' => $createdPaths,
            'replaced_paths' => $replacedPaths,
        ];
    }

    public function cleanupCreated(array $issuance): void
    {
        $this->deletePaths(is_array($issuance['created_paths'] ?? null) ? $issuance['created_paths'] : []);
    }

    public function cleanupReplaced(array $issuance): void
    {
        $this->deletePaths(is_array($issuance['replaced_paths'] ?? null) ? $issuance['replaced_paths'] : []);
    }

    public function checkInByToken(
        int $organizerId,
        string $rawToken,
        int $scannerId,
        ?string $scannerIp,
    ): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
            return null;
        }

        $ticket = $this->tickets->findForOrganizerByTokenDigest(
            $organizerId,
            hash('sha256', strtolower($rawToken)),
        );

        if ($ticket === null) {
            return null;
        }

        $ownsTransaction = !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $attendance = $this->tickets->recordAttendance(
                $organizerId,
                (int) $ticket['id'],
                $scannerId,
                $scannerIp,
            );
            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $attendance;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function checkIn(
        int $organizerId,
        int $eventId,
        string $submittedValue,
        int $scannerId,
        ?string $scannerIp,
    ): ?array {
        if ($organizerId <= 0 || $eventId <= 0 || $scannerId <= 0) {
            return null;
        }

        $value = trim($submittedValue);
        if ($value === '' || strlen($value) > 512) {
            return null;
        }

        $ticket = null;
        $rawToken = $this->rawTokenFromCheckInValue($value);

        if ($rawToken !== null) {
            $ticket = $this->tickets->findForOrganizerEventByTokenDigest(
                $organizerId,
                $eventId,
                hash('sha256', strtolower($rawToken)),
            );
            $rawToken = null;
            $value = '';
        } elseif (preg_match('/\AOEMS-[A-Z0-9-]{4,35}\z/i', $value) === 1) {
            $ticket = $this->tickets->findForOrganizerEventByNumber(
                $organizerId,
                $eventId,
                strtoupper($value),
            );
        }

        if ($ticket === null) {
            return null;
        }

        $duplicate = !empty($ticket['attendance_id']) || (string) ($ticket['ticket_status'] ?? '') === 'used';
        $ownsTransaction = !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $attendance = $this->tickets->recordAttendanceForEvent(
                $organizerId,
                $eventId,
                (int) $ticket['id'],
                $scannerId,
                $scannerIp,
            );

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $attendance === null ? null : array_merge($attendance, [
                'duplicate' => (bool) ($attendance['duplicate'] ?? $duplicate),
            ]);
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function downloadPath(int $participantId, int $ticketId, string $format): ?string
    {
        $column = ['qr' => 'qr_path', 'pdf' => 'pdf_path'][$format] ?? null;

        if ($column === null) {
            return null;
        }

        $ticket = $this->tickets->findForParticipant($participantId, $ticketId);
        $path = $ticket[$column] ?? null;

        return is_string($path) ? $this->artifacts->resolvePublicPath($path) : null;
    }

    public function forRegistration(int $registrationId): ?array
    {
        return $this->tickets->findForRegistration($registrationId);
    }

    public function forRegistrationCurrent(int $registrationId): ?array
    {
        return $this->tickets->findForRegistrationCurrent($registrationId);
    }

    public function voidForRegistration(int $registrationId): bool
    {
        return $this->tickets->voidForRegistration($registrationId);
    }

    private function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            $this->artifacts->delete(is_string($path) ? $path : null);
        }
    }

    private function rawTokenFromCheckInValue(string $value): ?string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/i', $value) === 1) {
            return strtolower($value);
        }

        if ($value === '' || str_contains($value, "\0") || str_starts_with($value, '//')) {
            return null;
        }

        $parts = parse_url($value);
        $expected = parse_url($this->expectedCheckInUrl);
        if (!is_array($parts)
            || !is_array($expected)
            || ($parts['path'] ?? '') !== ($expected['path'] ?? '/organizer/check-in')
            || !isset($parts['query'])) {
            return null;
        }

        $isAbsolute = isset($parts['scheme']) || isset($parts['host']);
        if ($isAbsolute && (
            !isset($expected['scheme'], $expected['host'])
            || strtolower((string) ($parts['scheme'] ?? '')) !== strtolower((string) $expected['scheme'])
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) $expected['host'])
            || $this->urlPort($parts) !== $this->urlPort($expected)
            || isset($parts['user'])
            || isset($parts['pass'])
        )) {
            return null;
        }

        parse_str((string) $parts['query'], $query);
        if (array_keys($query) !== ['token'] || !is_string($query['token'])) {
            return null;
        }

        return preg_match('/\A[a-f0-9]{64}\z/i', $query['token']) === 1
            ? strtolower($query['token'])
            : null;
    }

    private function urlPort(array $parts): ?int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return match (strtolower((string) ($parts['scheme'] ?? ''))) {
            'https' => 443,
            'http' => 80,
            default => null,
        };
    }
}
