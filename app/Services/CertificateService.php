<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\CertificateRepositoryInterface;
use OEMS\Core\Logger;
use PDO;
use Throwable;

final class CertificateService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly CertificateRepositoryInterface $certificates,
        private readonly CertificateArtifactService $artifacts,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function issue(int $participantId, int $registrationId): array
    {
        if ($participantId <= 0 || $registrationId <= 0) {
            return $this->failure();
        }
        $ownsTransaction = !$this->connection->inTransaction();
        $artifact = null;
        try {
            if ($ownsTransaction) {
                $this->connection->beginTransaction();
            }
            $eligibility = $this->certificates->lockEligibleRegistration($participantId, $registrationId);
            if ($eligibility === null) {
                if ($ownsTransaction) {
                    $this->connection->rollBack();
                }

                return $this->failure('A certificate is available only after confirmed attendance at a completed event.');
            }
            $existing = $this->certificates->findForRegistration($participantId, $registrationId);
            if ($existing !== null) {
                if ($ownsTransaction) {
                    $this->connection->commit();
                }

                return ['success' => true, 'created' => false, 'certificate' => $existing, 'verification_token' => null, 'errors' => []];
            }
            $issuedAt = new DateTimeImmutable('now');
            $artifact = $this->artifacts->generate([
                'participant_name' => $eligibility['participant_name'] ?? '',
                'event_title' => $eligibility['event_title'] ?? '',
                'completion_date' => $this->displayDate($eligibility['completion_date'] ?? null),
                'issued_at' => $issuedAt->format('F j, Y'),
            ]);
            $this->certificates->create($registrationId, $participantId, [
                'certificate_number' => $artifact['certificate_number'],
                'verification_token_hash' => $artifact['verification_token_hash'],
                'pdf_path' => $artifact['pdf_path'],
                'issued_at' => $issuedAt->format('Y-m-d H:i:s'),
            ]);
            $certificate = $this->certificates->findForRegistration($participantId, $registrationId);
            if ($certificate === null) {
                throw new \RuntimeException('The issued certificate could not be read.');
            }
            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return [
                'success' => true,
                'created' => true,
                'certificate' => $certificate,
                'verification_token' => $artifact['raw_token'],
                'errors' => [],
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if (is_array($artifact)) {
                $this->artifacts->delete($artifact['pdf_path'] ?? null);
            }
            $winner = $this->certificates->findForRegistration($participantId, $registrationId);
            if ($winner !== null) {
                return ['success' => true, 'created' => false, 'certificate' => $winner, 'verification_token' => null, 'errors' => []];
            }
            $this->logger?->error('Certificate issuance failed.', [
                'participant_id' => $participantId,
                'registration_id' => $registrationId,
                'exception' => $exception::class,
            ]);

            return $this->failure('The certificate could not be issued. Please try again.');
        }
    }

    public function forParticipant(int $participantId): array
    {
        return $participantId > 0 ? $this->certificates->forParticipant($participantId) : [];
    }

    public function downloadPath(int $participantId, int $certificateId): ?string
    {
        return $this->download($participantId, $certificateId)['path'] ?? null;
    }

    public function download(int $participantId, int $certificateId): ?array
    {
        if ($participantId <= 0 || $certificateId <= 0) {
            return null;
        }
        $certificate = $this->certificates->findForParticipant($participantId, $certificateId);
        if ($certificate === null || (string) ($certificate['status'] ?? '') !== 'valid') {
            return null;
        }
        $path = $this->artifacts->resolvePath((string) ($certificate['pdf_path'] ?? ''));

        return $path === null ? null : ['path' => $path, 'certificate' => $certificate];
    }

    public function verify(string $rawToken): ?array
    {
        $token = strtolower(trim($rawToken));
        if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            return null;
        }
        $certificate = $this->certificates->findValidByTokenDigest(hash('sha256', $token));
        if ($certificate === null) {
            return null;
        }

        return [
            'valid' => true,
            'participant_name' => (string) ($certificate['participant_name'] ?? ''),
            'event_title' => (string) ($certificate['event_title'] ?? ''),
            'completion_date' => (string) ($certificate['completion_date'] ?? ''),
            'issued_at' => (string) ($certificate['issued_at'] ?? ''),
        ];
    }

    private function failure(string $message = 'The certificate request is invalid.'): array
    {
        return ['success' => false, 'created' => false, 'certificate' => null, 'verification_token' => null, 'errors' => ['certificate' => [$message]]];
    }

    private function displayDate(mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return 'Date unavailable';
        }
        try {
            return (new DateTimeImmutable((string) $value))->format('F j, Y');
        } catch (Throwable) {
            return 'Date unavailable';
        }
    }
}
