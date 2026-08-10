<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\MailOutboxWorker;
use OEMS\App\Services\QueuedMailTemplateService;
use OEMS\Core\Config;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailOutboxRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class MailOutboxWorkerTest extends TestCase
{
    public function testClaimsABoundedBatchSendsOnceAndPersistsSafeProviderEvidence(): void
    {
        $outbox = new FakeMailOutboxRepository();
        $outbox->enqueue($this->job('event_reminder', str_repeat('a', 64), '2026-08-10 09:00:00'));
        $outbox->enqueue($this->job('event_reminder', str_repeat('b', 64), '2026-08-10 09:00:00'));
        $transport = new FakeMailTransport('<provider-7>');
        $logs = new FakeEmailLogRepository();
        $worker = $this->worker($outbox, $transport, $logs);

        $result = $worker->run(1, new DateTimeImmutable('2026-08-10 10:00:00'));

        $this->assertSame(['claimed' => 1, 'sent' => 1, 'retried' => 0, 'failed' => 0], $result);
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('sent', $outbox->jobs[0]['status']);
        $this->assertSame('<provider-7>', $outbox->jobs[0]['provider_message_id']);
        $this->assertSame('queued', $outbox->jobs[1]['status']);
        $this->assertSame('sent', $logs->records[0]['status']);
        $this->assertFalse(array_key_exists('payload', $logs->records[0]));
    }

    public function testTransportFailureUsesBoundedBackoffThenTerminatesAtMaximumAttempts(): void
    {
        $outbox = new FakeMailOutboxRepository();
        $outbox->enqueue($this->job('event_reminder', str_repeat('c', 64), '2026-08-10 09:00:00'));
        $transport = new FakeMailTransport();
        $transport->failure = new RuntimeException('SMTP password=SECRET recipient=private@example.test');
        $logs = new FakeEmailLogRepository();
        $path = sys_get_temp_dir() . '/oems-week3-worker-' . bin2hex(random_bytes(5)) . '.log';
        $worker = $this->worker($outbox, $transport, $logs, new Logger($path), 2);

        $first = $worker->run(1, new DateTimeImmutable('2026-08-10 10:00:00'));
        $this->assertSame(['claimed' => 1, 'sent' => 0, 'retried' => 1, 'failed' => 0], $first);
        $this->assertSame('queued', $outbox->jobs[0]['status']);
        $this->assertSame(1, $outbox->jobs[0]['attempts']);
        $this->assertSame('2026-08-10 10:01:00', $outbox->jobs[0]['available_at']);

        $second = $worker->run(1, new DateTimeImmutable('2026-08-10 10:01:00'));
        $this->assertSame(['claimed' => 1, 'sent' => 0, 'retried' => 0, 'failed' => 1], $second);
        $this->assertSame('failed', $outbox->jobs[0]['status']);
        $this->assertSame(2, $outbox->jobs[0]['attempts']);
        $this->assertSame('Email delivery failed.', $outbox->jobs[0]['last_error']);
        $this->assertSame(['failed', 'failed'], array_column($logs->records, 'status'));

        $contents = is_file($path) ? (file_get_contents($path) ?: '') : '';
        $this->assertFalse(str_contains($contents, 'SECRET'));
        $this->assertFalse(str_contains($contents, 'private@example.test'));
        if (is_file($path)) unlink($path);
    }

    public function testMalformedStoredJobFailsTerminallyWithoutCallingTheProvider(): void
    {
        $outbox = new FakeMailOutboxRepository();
        $outbox->enqueue([
            'template' => 'raw_php',
            'recipient_email' => 'person@example.test',
            'payload' => ['body' => 'unsafe'],
            'idempotency_key' => str_repeat('d', 64),
            'available_at' => '2026-08-10 09:00:00',
        ]);
        $transport = new FakeMailTransport();

        $result = $this->worker($outbox, $transport, new FakeEmailLogRepository())
            ->run(1, new DateTimeImmutable('2026-08-10 10:00:00'));

        $this->assertSame(['claimed' => 1, 'sent' => 0, 'retried' => 0, 'failed' => 1], $result);
        $this->assertSame([], $transport->messages);
        $this->assertSame('failed', $outbox->jobs[0]['status']);
    }

    private function worker(
        FakeMailOutboxRepository $outbox,
        FakeMailTransport $transport,
        FakeEmailLogRepository $logs,
        ?Logger $logger = null,
        int $maxAttempts = 5,
    ): MailOutboxWorker {
        return new MailOutboxWorker(
            $outbox,
            new QueuedMailTemplateService(new Config(['url' => 'https://events.example.test'])),
            $transport,
            $logs,
            $logger,
            $maxAttempts,
        );
    }

    private function job(string $template, string $key, string $availableAt): array
    {
        return [
            'template' => $template,
            'recipient_email' => 'person@example.test',
            'payload' => [
                'user_id' => 5,
                'event_id' => 6,
                'registration_id' => 7,
                'event_title' => 'Dhaka Product Night',
                'starts_at' => '2026-08-11T09:00:00+06:00',
                'calendar_url' => '/participant/registrations/7/calendar.ics',
            ],
            'idempotency_key' => $key,
            'available_at' => $availableAt,
        ];
    }
}
