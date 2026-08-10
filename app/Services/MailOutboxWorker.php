<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateInterval;
use DateTimeImmutable;
use OEMS\App\Contracts\EmailLogRepositoryInterface;
use OEMS\App\Contracts\MailOutboxRepositoryInterface;
use OEMS\App\Contracts\MailTransportInterface;
use OEMS\App\Mail\EmailMessage;
use OEMS\Core\Logger;
use Throwable;

final class MailOutboxWorker
{
    public function __construct(
        private readonly MailOutboxRepositoryInterface $outbox,
        private readonly QueuedMailTemplateService $templates,
        private readonly MailTransportInterface $transport,
        private readonly EmailLogRepositoryInterface $logs,
        private readonly ?Logger $logger = null,
        private readonly int $maxAttempts = 5,
    ) {
    }

    public function run(int $limit, DateTimeImmutable $now): array
    {
        $limit = min(100, max(1, $limit));
        $lockToken = bin2hex(random_bytes(32));
        $jobs = $this->outbox->claimBatch($limit, $lockToken, $now);
        $result = ['claimed' => count($jobs), 'sent' => 0, 'retried' => 0, 'failed' => 0];

        foreach ($jobs as $job) {
            $this->process($job, $lockToken, $now, $result);
        }

        return $result;
    }

    private function process(array $job, string $lockToken, DateTimeImmutable $now, array &$result): void
    {
        $attempts = min(20, max(0, (int) ($job['attempts'] ?? 0)) + 1);
        $id = (int) ($job['id'] ?? 0);
        $template = (string) ($job['template'] ?? 'unknown');
        $recipient = (string) ($job['recipient_email'] ?? '');

        try {
            $rendered = $this->templates->render($template, is_array($job['payload'] ?? null) ? $job['payload'] : []);
            $message = new EmailMessage(
                $recipient,
                $this->recipientName($job['payload'] ?? []),
                $rendered['subject'],
                (string) ($rendered['html'] ?? ''),
                $rendered['text'],
            );
            $providerId = $this->transport->send($message);
        } catch (Throwable $exception) {
            $terminal = $attempts >= min(20, max(1, $this->maxAttempts)) || $exception instanceof \InvalidArgumentException;
            $delay = min(3600, 60 * (2 ** max(0, $attempts - 1)));
            $availableAt = $now->add(new DateInterval('PT' . $delay . 'S'));
            if ($this->outbox->releaseFailed($id, $lockToken, $attempts, $availableAt, 'Email delivery failed.', $terminal)) {
                $result[$terminal ? 'failed' : 'retried']++;
            }
            $this->record($job, '', 'failed', null, 'Email delivery failed.');
            $this->logFailure($id, $template, $attempts, $terminal, $exception);
            return;
        }

        if ($this->outbox->markSent($id, $lockToken, $providerId, $now)) {
            $result['sent']++;
            $this->record($job, $rendered['subject'], 'sent', $providerId, null, $now);
        }
    }

    private function record(
        array $job,
        string $subject,
        string $status,
        ?string $providerId,
        ?string $error,
        ?DateTimeImmutable $sentAt = null,
    ): void {
        try {
            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            $this->logs->record([
                'user_id' => filter_var($payload['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
                'recipient_email' => (string) ($job['recipient_email'] ?? ''),
                'template' => (string) ($job['template'] ?? 'unknown'),
                'subject' => $subject === '' ? 'Queued email delivery' : mb_substr($subject, 0, 190),
                'status' => $status,
                'provider_message_id' => $providerId,
                'error_message' => $error,
                'sent_at' => $sentAt?->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            try {
                $this->logger?->error('Queued email outcome could not be recorded.', [
                    'outbox_id' => (int) ($job['id'] ?? 0),
                    'template' => (string) ($job['template'] ?? 'unknown'),
                    'exception_class' => $exception::class,
                ]);
            } catch (Throwable) {
            }
        }
    }

    private function recipientName(mixed $payload): string
    {
        if (!is_array($payload)) return 'OEMS participant';
        $name = $payload['participant_name'] ?? $payload['recipient_name'] ?? $payload['name'] ?? 'OEMS participant';
        $name = is_scalar($name) ? strip_tags((string) $name) : 'OEMS participant';
        return mb_substr(trim($name), 0, 160);
    }

    private function logFailure(int $id, string $template, int $attempts, bool $terminal, Throwable $exception): void
    {
        try {
            $this->logger?->error('Queued email delivery failed.', [
                'outbox_id' => $id,
                'template' => $template,
                'attempts' => $attempts,
                'terminal' => $terminal,
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
        }
    }
}
