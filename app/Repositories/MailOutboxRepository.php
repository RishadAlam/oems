<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use JsonException;
use OEMS\App\Contracts\MailOutboxRepositoryInterface;
use PDO;
use PDOException;
use Throwable;

final class MailOutboxRepository implements MailOutboxRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function enqueue(array $job): ?array
    {
        $payload = json_encode($job['payload'] ?? [], JSON_THROW_ON_ERROR);

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO mail_outbox
                    (template, recipient_email, payload, idempotency_key, status, attempts, available_at)
                 VALUES
                    (:template, :recipient_email, :payload, :idempotency_key, \'queued\', 0, :available_at)',
            );
            $statement->execute([
                'template' => (string) $job['template'],
                'recipient_email' => (string) $job['recipient_email'],
                'payload' => $payload,
                'idempotency_key' => (string) $job['idempotency_key'],
                'available_at' => (string) $job['available_at'],
            ]);
        } catch (PDOException $exception) {
            if (!$this->isUniqueViolation($exception)) {
                throw $exception;
            }
        }

        return $this->findByIdempotencyKey((string) $job['idempotency_key']);
    }

    public function claimBatch(int $limit, string $lockToken, DateTimeImmutable $now): array
    {
        $limit = min(100, max(1, $limit));
        $nowValue = $now->format('Y-m-d H:i:s');
        $staleValue = $now->modify('-15 minutes')->format('Y-m-d H:i:s');
        $ownsTransaction = !$this->connection->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->beginWriteTransaction();
            }

            $recover = $this->connection->prepare(
                "UPDATE mail_outbox
                 SET status = 'queued', lock_token = NULL, locked_at = NULL
                 WHERE status = 'processing' AND locked_at IS NOT NULL AND locked_at <= :stale_at",
            );
            $recover->execute(['stale_at' => $staleValue]);

            $sql = "SELECT id FROM mail_outbox
                    WHERE status = 'queued' AND available_at <= :available_at
                    ORDER BY available_at ASC, id ASC
                    LIMIT :limit";
            if ($this->driver() === 'mysql') {
                $sql .= ' FOR UPDATE SKIP LOCKED';
            }

            $select = $this->connection->prepare($sql);
            $select->bindValue('available_at', $nowValue);
            $select->bindValue('limit', $limit, PDO::PARAM_INT);
            $select->execute();
            $ids = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));

            $claimedIds = [];
            $claim = $this->connection->prepare(
                "UPDATE mail_outbox
                 SET status = 'processing', lock_token = :lock_token, locked_at = :locked_at
                 WHERE id = :id AND status = 'queued'",
            );
            foreach ($ids as $id) {
                $claim->execute(['lock_token' => $lockToken, 'locked_at' => $nowValue, 'id' => $id]);
                if ($claim->rowCount() === 1) {
                    $claimedIds[] = $id;
                }
            }

            $rows = $this->findClaimed($claimedIds, $lockToken);
            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $rows;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function markSent(int $id, string $lockToken, ?string $providerId, DateTimeImmutable $sentAt): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE mail_outbox
             SET status = 'sent', sent_at = :sent_at, provider_message_id = :provider_id,
                 lock_token = NULL, locked_at = NULL, last_error = NULL
             WHERE id = :id AND status = 'processing' AND lock_token = :lock_token",
        );
        $statement->execute([
            'sent_at' => $sentAt->format('Y-m-d H:i:s'),
            'provider_id' => $providerId === null ? null : mb_substr(trim($providerId), 0, 190),
            'id' => $id,
            'lock_token' => $lockToken,
        ]);

        return $statement->rowCount() === 1;
    }

    public function releaseFailed(
        int $id,
        string $lockToken,
        int $attempts,
        DateTimeImmutable $availableAt,
        string $error,
        bool $terminal,
    ): bool {
        $statement = $this->connection->prepare(
            "UPDATE mail_outbox
             SET status = :status, attempts = :attempts, available_at = :available_at,
                 last_error = :last_error, lock_token = NULL, locked_at = NULL
             WHERE id = :id AND status = 'processing' AND lock_token = :lock_token",
        );
        $statement->execute([
            'status' => $terminal ? 'failed' : 'queued',
            'attempts' => min(20, max(0, $attempts)),
            'available_at' => $availableAt->format('Y-m-d H:i:s'),
            'last_error' => mb_substr(trim($error), 0, 500),
            'id' => $id,
            'lock_token' => $lockToken,
        ]);

        return $statement->rowCount() === 1;
    }

    private function findByIdempotencyKey(string $key): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM mail_outbox WHERE idempotency_key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    private function findClaimed(array $ids, string $lockToken): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare(
            "SELECT * FROM mail_outbox
             WHERE id IN ({$placeholders}) AND lock_token = ?
             ORDER BY available_at ASC, id ASC",
        );
        $statement->execute([...$ids, $lockToken]);
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->hydrate($row), is_array($rows) ? $rows : []);
    }

    private function hydrate(array $row): array
    {
        $payload = $row['payload'] ?? null;
        try {
            $decoded = is_string($payload) ? json_decode($payload, true, 16, JSON_THROW_ON_ERROR) : [];
        } catch (JsonException) {
            $decoded = [];
        }
        $row['payload'] = is_array($decoded) ? $decoded : [];
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['attempts'] = (int) ($row['attempts'] ?? 0);

        return $row;
    }

    private function driver(): string
    {
        return (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function beginWriteTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    private function isUniqueViolation(PDOException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        return in_array($state, ['23000', '23505', '19'], true);
    }
}
