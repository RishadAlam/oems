<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\EmailLogRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class EmailLogRepositoryTest extends TestCase
{
    public function testRecordPersistsTheFinalDeliveryOutcome(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec(
            'CREATE TABLE email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                recipient_email TEXT NOT NULL,
                template TEXT NOT NULL,
                subject TEXT NOT NULL,
                status TEXT NOT NULL,
                provider_message_id TEXT NULL,
                error_message TEXT NULL,
                sent_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $repository = new EmailLogRepository($connection);

        $repository->record([
            'user_id' => 9,
            'recipient_email' => 'maliha@example.test',
            'template' => 'email_verification',
            'subject' => 'Verify your OEMS email',
            'status' => 'sent',
            'provider_message_id' => '<mailtrap-message-id>',
            'error_message' => null,
            'sent_at' => '2026-08-06 14:30:00',
        ]);

        $row = $connection->query('SELECT * FROM email_logs')->fetch();
        $this->assertSame(9, $row['user_id']);
        $this->assertSame('maliha@example.test', $row['recipient_email']);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('<mailtrap-message-id>', $row['provider_message_id']);
        $this->assertSame('2026-08-06 14:30:00', $row['sent_at']);
    }
}
