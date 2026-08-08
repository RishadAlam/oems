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
    ) {
    }

    public function issue(array $registration, array $participant, array $event): array
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

        $this->connection->beginTransaction();

        try {
            $attendance = $this->tickets->recordAttendance(
                $organizerId,
                (int) $ticket['id'],
                $scannerId,
                $scannerIp,
            );
            $this->connection->commit();

            return $attendance;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
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
}
