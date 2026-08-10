<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use OEMS\App\Contracts\NewsletterRepositoryInterface;
use PDO;
use PDOException;

final class NewsletterRepository implements NewsletterRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function savePending(string $email, string $confirmationHash, string $unsubscribeHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): ?array
    {
        $existing = $this->findByEmail($email, true);
        if (($existing['status'] ?? null) === 'subscribed') return $existing;
        if ($existing === null) {
            $statement = $this->connection->prepare(
                "INSERT INTO newsletter (email, status, confirmation_token_hash, confirmation_expires_at, unsubscribe_token_hash, subscribed_at)
                 VALUES (:email, 'pending', :confirmation_hash, :confirmation_expires_at, :unsubscribe_hash, :subscribed_at)",
            );
            try {
                $statement->execute(['email' => $email, 'confirmation_hash' => $confirmationHash, 'confirmation_expires_at' => $expiresAt->format('Y-m-d H:i:s'), 'unsubscribe_hash' => $unsubscribeHash, 'subscribed_at' => $now->format('Y-m-d H:i:s')]);
            } catch (PDOException $exception) {
                if ($this->uniqueViolation($exception)) return $this->findByEmail($email, true);
                throw $exception;
            }
        } else {
            $statement = $this->connection->prepare(
                "UPDATE newsletter SET status = 'pending', confirmation_token_hash = :confirmation_hash,
                    confirmation_expires_at = :confirmation_expires_at, unsubscribe_token_hash = :unsubscribe_hash,
                    subscribed_at = :subscribed_at, confirmed_at = NULL, unsubscribed_at = NULL
                 WHERE id = :subscriber_id AND status IN ('pending', 'unsubscribed')",
            );
            $statement->execute(['confirmation_hash' => $confirmationHash, 'confirmation_expires_at' => $expiresAt->format('Y-m-d H:i:s'), 'unsubscribe_hash' => $unsubscribeHash, 'subscribed_at' => $now->format('Y-m-d H:i:s'), 'subscriber_id' => (int) $existing['id']]);
        }
        return $this->findByEmail($email, false);
    }

    public function confirm(string $tokenHash, DateTimeImmutable $now): ?array
    {
        $candidate = $this->connection->prepare(
            'SELECT id FROM newsletter
             WHERE confirmation_token_hash = :lookup_hash AND status = \'pending\' AND confirmation_expires_at >= :lookup_expiry
             LIMIT 1' . ($this->driver() === 'mysql' && $this->connection->inTransaction() ? ' FOR UPDATE' : ''),
        );
        $formatted = $now->format('Y-m-d H:i:s');
        $candidate->execute(['lookup_hash' => $tokenHash, 'lookup_expiry' => $formatted]);
        $subscriberId = $candidate->fetchColumn();
        if ($subscriberId === false) return null;
        $statement = $this->connection->prepare(
            "UPDATE newsletter SET status = 'subscribed', confirmation_token_hash = NULL,
                confirmation_expires_at = NULL, confirmed_at = :confirmed_at, unsubscribed_at = NULL
             WHERE id = :subscriber_id AND confirmation_token_hash = :token_hash
               AND status = 'pending' AND confirmation_expires_at >= :expires_at",
        );
        $statement->execute(['confirmed_at' => $formatted, 'subscriber_id' => (int) $subscriberId, 'token_hash' => $tokenHash, 'expires_at' => $formatted]);
        if ($statement->rowCount() !== 1) return null;
        $find = $this->connection->prepare('SELECT * FROM newsletter WHERE id = :subscriber_id AND status = \'subscribed\' LIMIT 1');
        $find->execute(['subscriber_id' => (int) $subscriberId]); $row = $find->fetch();
        return is_array($row) ? $row : null;
    }

    public function unsubscribe(string $tokenHash, DateTimeImmutable $now): ?array
    {
        $statement = $this->connection->prepare(
            "UPDATE newsletter SET status = 'unsubscribed', unsubscribed_at = :unsubscribed_at
             WHERE unsubscribe_token_hash = :token_hash AND status = 'subscribed'",
        );
        $formatted = $now->format('Y-m-d H:i:s'); $statement->execute(['unsubscribed_at' => $formatted, 'token_hash' => $tokenHash]);
        if ($statement->rowCount() !== 1) return null;
        $find = $this->connection->prepare('SELECT * FROM newsletter WHERE unsubscribe_token_hash = :token_hash LIMIT 1');
        $find->execute(['token_hash' => $tokenHash]); $row = $find->fetch();
        return is_array($row) ? $row : null;
    }

    public function confirmedSubscribers(int $limit, int $offset): array
    {
        $statement = $this->connection->prepare("SELECT id, email FROM newsletter WHERE status = 'subscribed' ORDER BY id LIMIT :row_limit OFFSET :row_offset");
        $statement->bindValue('row_limit', max(1, min(500, $limit)), PDO::PARAM_INT); $statement->bindValue('row_offset', max(0, $offset), PDO::PARAM_INT); $statement->execute();
        $rows = $statement->fetchAll(); return is_array($rows) ? $rows : [];
    }

    public function rotateUnsubscribeToken(int $id, string $tokenHash): bool
    {
        $statement = $this->connection->prepare("UPDATE newsletter SET unsubscribe_token_hash = :token_hash WHERE id = :subscriber_id AND status = 'subscribed'");
        $statement->execute(['token_hash' => $tokenHash, 'subscriber_id' => $id]); return $statement->rowCount() === 1;
    }

    public function campaigns(): array
    {
        $rows = $this->connection->query('SELECT id, subject, message, status, created_by, recipient_count, queued_count, scheduled_at, queued_at, sent_at, created_at FROM newsletter_campaigns ORDER BY created_at DESC, id DESC')->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findCampaign(int $id, bool $lock = false): ?array
    {
        $locking = $lock && $this->driver() === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare('SELECT * FROM newsletter_campaigns WHERE id = :campaign_id LIMIT 1' . $locking); $statement->execute(['campaign_id' => $id]); $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function createCampaign(int $administratorId, array $attributes): ?array
    {
        $statement = $this->connection->prepare("INSERT INTO newsletter_campaigns (subject, message, status, created_by, request_key, scheduled_at) VALUES (:subject, :message, 'draft', :created_by, :request_key, :scheduled_at)");
        try { $statement->execute(['subject' => (string) $attributes['subject'], 'message' => (string) $attributes['message'], 'created_by' => $administratorId, 'request_key' => (string) $attributes['request_key'], 'scheduled_at' => $attributes['scheduled_at'] ?? null]); }
        catch (PDOException $exception) { if ($this->uniqueViolation($exception)) return null; throw $exception; }
        return $this->findCampaign((int) $this->connection->lastInsertId());
    }

    public function markCampaignQueued(int $id, int $recipientCount, int $queuedCount, DateTimeImmutable $queuedAt): bool
    {
        $statement = $this->connection->prepare("UPDATE newsletter_campaigns SET status = 'queued', recipient_count = :recipient_count, queued_count = :queued_count, queued_at = :queued_at WHERE id = :campaign_id AND status = 'draft'");
        $statement->execute(['recipient_count' => $recipientCount, 'queued_count' => $queuedCount, 'queued_at' => $queuedAt->format('Y-m-d H:i:s'), 'campaign_id' => $id]); return $statement->rowCount() === 1;
    }

    private function findByEmail(string $email, bool $lock): ?array
    {
        $locking = $lock && $this->driver() === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare('SELECT * FROM newsletter WHERE email = :email LIMIT 1' . $locking); $statement->execute(['email' => $email]); $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private function driver(): string { return (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME); }
    private function uniqueViolation(PDOException $exception): bool { return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505', '19'], true); }
}
