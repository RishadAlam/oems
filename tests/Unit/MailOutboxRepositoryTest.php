<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\MailOutboxRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class MailOutboxRepositoryTest extends TestCase
{
    private PDO $connection;
    private MailOutboxRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec(
            "CREATE TABLE mail_outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template TEXT NOT NULL,
                recipient_email TEXT NOT NULL,
                payload TEXT NOT NULL,
                idempotency_key TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'queued',
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL,
                lock_token TEXT NULL,
                locked_at TEXT NULL,
                sent_at TEXT NULL,
                provider_message_id TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
        );
        $this->repository = new MailOutboxRepository($this->connection);
    }

    public function testEnqueueIsIdempotentAndHydratesTheStoredPayload(): void
    {
        $attributes = [
            'template' => 'event_reminder',
            'recipient_email' => 'person@example.com',
            'payload' => ['event_id' => 42, 'event_title' => 'Dhaka Product Night'],
            'idempotency_key' => str_repeat('a', 64),
            'available_at' => '2026-08-10 09:00:00',
        ];

        $first = $this->repository->enqueue($attributes);
        $second = $this->repository->enqueue($attributes);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(['event_id' => 42, 'event_title' => 'Dhaka Product Night'], $first['payload']);
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn());
    }

    public function testClaimBatchSelectsOnlyDueJobsInDeterministicOrderAndRecoversStaleLocks(): void
    {
        $this->seed('future@example.com', str_repeat('f', 64), '2026-08-10 11:00:00');
        $firstId = $this->seed('first@example.com', str_repeat('a', 64), '2026-08-10 08:00:00');
        $secondId = $this->seed('second@example.com', str_repeat('b', 64), '2026-08-10 08:30:00');
        $staleId = $this->seed('stale@example.com', str_repeat('c', 64), '2026-08-10 08:45:00');
        $this->connection->exec(
            "UPDATE mail_outbox SET status = 'processing', lock_token = 'dead-worker', locked_at = '2026-08-10 08:00:00'
             WHERE id = {$staleId}",
        );

        $claimed = $this->repository->claimBatch(3, 'worker-1', new DateTimeImmutable('2026-08-10 10:00:00'));

        $this->assertSame([$firstId, $secondId, $staleId], array_column($claimed, 'id'));
        $this->assertSame(['processing', 'processing', 'processing'], array_column($claimed, 'status'));
        $this->assertSame(['worker-1', 'worker-1', 'worker-1'], array_column($claimed, 'lock_token'));
    }

    public function testSettlementRequiresTheClaimTokenAndRetriesOrTerminatesTruthfully(): void
    {
        $id = $this->seed('person@example.com', str_repeat('d', 64), '2026-08-10 08:00:00');
        $this->repository->claimBatch(1, 'worker-1', new DateTimeImmutable('2026-08-10 09:00:00'));

        $this->assertFalse($this->repository->markSent($id, 'worker-2', 'provider-1', new DateTimeImmutable('2026-08-10 09:01:00')));
        $this->assertTrue($this->repository->releaseFailed($id, 'worker-1', 1, new DateTimeImmutable('2026-08-10 09:05:00'), 'Temporary failure', false));

        $retry = $this->connection->query("SELECT * FROM mail_outbox WHERE id = {$id}")->fetch();
        $this->assertSame('queued', $retry['status']);
        $this->assertSame(1, (int) $retry['attempts']);
        $this->assertSame('Temporary failure', $retry['last_error']);

        $this->repository->claimBatch(1, 'worker-3', new DateTimeImmutable('2026-08-10 09:05:00'));
        $this->assertTrue($this->repository->releaseFailed($id, 'worker-3', 5, new DateTimeImmutable('2026-08-10 10:00:00'), 'Final failure', true));
        $terminal = $this->connection->query("SELECT * FROM mail_outbox WHERE id = {$id}")->fetch();
        $this->assertSame('failed', $terminal['status']);
        $this->assertSame(5, (int) $terminal['attempts']);
        $this->assertNull($terminal['lock_token']);
    }

    public function testSuccessfulSettlementPersistsProviderEvidenceOnce(): void
    {
        $id = $this->seed('person@example.com', str_repeat('e', 64), '2026-08-10 08:00:00');
        $this->repository->claimBatch(1, 'worker-1', new DateTimeImmutable('2026-08-10 09:00:00'));

        $this->assertTrue($this->repository->markSent($id, 'worker-1', 'provider-message-7', new DateTimeImmutable('2026-08-10 09:01:00')));
        $this->assertFalse($this->repository->markSent($id, 'worker-1', 'provider-message-8', new DateTimeImmutable('2026-08-10 09:02:00')));

        $row = $this->connection->query("SELECT * FROM mail_outbox WHERE id = {$id}")->fetch();
        $this->assertSame('sent', $row['status']);
        $this->assertSame('provider-message-7', $row['provider_message_id']);
        $this->assertSame('2026-08-10 09:01:00', $row['sent_at']);
    }

    private function seed(string $email, string $key, string $availableAt): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO mail_outbox
                (template, recipient_email, payload, idempotency_key, status, attempts, available_at)
             VALUES ('event_reminder', :email, '{}', :idempotency_key, 'queued', 0, :available_at)",
        );
        $statement->execute(['email' => $email, 'idempotency_key' => $key, 'available_at' => $availableAt]);

        return (int) $this->connection->lastInsertId();
    }
}
