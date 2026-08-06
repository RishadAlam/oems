<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\EmailLogRepositoryInterface;
use PDO;

final class EmailLogRepository implements EmailLogRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function record(array $attributes): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO email_logs
                (user_id, recipient_email, template, subject, status, provider_message_id, error_message, sent_at, created_at)
             VALUES
                (:user_id, :recipient_email, :template, :subject, :status, :provider_message_id, :error_message, :sent_at, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'user_id' => $attributes['user_id'] ?? null,
            'recipient_email' => $attributes['recipient_email'],
            'template' => $attributes['template'],
            'subject' => $attributes['subject'],
            'status' => $attributes['status'],
            'provider_message_id' => $attributes['provider_message_id'] ?? null,
            'error_message' => $attributes['error_message'] ?? null,
            'sent_at' => $attributes['sent_at'] ?? null,
        ]);
    }
}
