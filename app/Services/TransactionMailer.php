<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\EmailLogRepositoryInterface;
use OEMS\App\Contracts\MailTransportInterface;
use OEMS\App\Mail\EmailMessage;
use OEMS\Core\Config;
use OEMS\Core\Logger;
use Throwable;

final class TransactionMailer
{
    private const SUBJECT_LIMIT = 190;

    private const DISPLAY_LIMIT = 160;

    public function __construct(
        private readonly MailTransportInterface $transport,
        private readonly EmailLogRepositoryInterface $logs,
        private readonly Config $config,
        private readonly ?Logger $logger = null,
        private readonly ?MailOutboxService $outbox = null,
    ) {
    }

    public function sendConfirmation(array $participant, array $registration): bool
    {
        return $this->send(
            'registration_confirmation',
            'Registration confirmed',
            'Your registration is confirmed.',
            $participant,
            $registration,
        );
    }

    public function sendPending(array $participant, array $registration): bool
    {
        return $this->send(
            'payment_pending',
            'Payment review pending',
            'Your payment reference was received and is awaiting review.',
            $participant,
            $registration,
        );
    }

    public function sendPaid(array $participant, array $registration): bool
    {
        return $this->send(
            'payment_paid',
            'Payment approved',
            'Your payment was approved and your registration is confirmed.',
            $participant,
            $registration,
        );
    }

    public function sendRejected(array $participant, array $registration): bool
    {
        return $this->send(
            'payment_rejected',
            'Payment rejected',
            'Your payment could not be verified and the reserved seat was released.',
            $participant,
            $registration,
        );
    }

    public function sendCancelled(array $participant, array $registration): bool
    {
        return $this->send(
            'registration_cancelled',
            'Registration cancelled',
            'Your registration was cancelled.',
            $participant,
            $registration,
        );
    }

    public function sendTicket(array $participant, array $registration, array $ticket): bool
    {
        $ticketId = (int) ($ticket['id'] ?? 0);

        return $this->send(
            'ticket_issued',
            'Your event ticket is ready',
            'Your ticket is ready. Open the secure ticket detail page to view or download it.',
            $participant,
            $registration,
            '/participant/tickets/' . $ticketId,
        );
    }

    private function send(
        string $template,
        string $subject,
        string $copy,
        array $participant,
        array $registration,
        ?string $path = null,
    ): bool {
        $userId = (int) ($participant['id'] ?? $participant['participant_id'] ?? 0);
        $recipient = trim((string) ($participant['email'] ?? $participant['participant_email'] ?? ''));
        $name = $this->display($participant['name'] ?? $participant['participant_name'] ?? 'Participant');
        $event = $this->display($registration['event_title'] ?? 'your event');
        $path ??= '/participant/registrations/' . (int) ($registration['id'] ?? 0);
        $url = $this->url($path);

        if ($this->outbox !== null) {
            $queued = $this->outbox->enqueue(
                $template,
                $recipient,
                [
                    'user_id' => $userId,
                    'participant_name' => $name,
                    'registration_id' => (int) ($registration['id'] ?? 0),
                    'event_title' => $event,
                    'action_url' => $path,
                ],
                "transaction:{$template}:user:{$userId}:registration:" . (int) ($registration['id'] ?? 0),
            );

            return (bool) ($queued['ok'] ?? false);
        }

        $safeName = $this->escape($name);
        $safeEvent = $this->escape($event);
        $safeUrl = $this->escape($url);
        $message = new EmailMessage(
            $recipient,
            $name,
            mb_substr($subject, 0, self::SUBJECT_LIMIT),
            '<p>Hello ' . $safeName . ',</p>'
                . '<p>' . $this->escape($copy) . '</p>'
                . '<p>Event: ' . $safeEvent . '</p>'
                . '<p><a href="' . $safeUrl . '">View details</a></p>',
            "Hello {$name},\n\n{$copy}\nEvent: {$event}\n{$url}",
        );

        try {
            $messageId = $this->transport->send($message);
            $attributes = $this->logAttributes($userId, $template, $message, 'sent');
            $attributes['provider_message_id'] = $messageId;
            $attributes['sent_at'] = date('Y-m-d H:i:s');
        } catch (Throwable) {
            $attributes = $this->logAttributes($userId, $template, $message, 'failed');
            $attributes['error_message'] = 'Email delivery failed.';
            $messageId = false;
        }

        try {
            $this->logs->record($attributes);
        } catch (Throwable $exception) {
            try {
                $this->logger?->error('Transaction email outcome could not be recorded.', [
                    'operation' => 'transaction_mail_log',
                    'actor_id' => $userId,
                    'exception_class' => $exception::class,
                ]);
            } catch (Throwable) {
                // Domain state and mail delivery have already completed.
            }
        }

        return $messageId !== false;
    }

    private function logAttributes(
        int $userId,
        string $template,
        EmailMessage $message,
        string $status,
    ): array {
        return [
            'user_id' => $userId > 0 ? $userId : null,
            'recipient_email' => $message->recipientEmail,
            'template' => $template,
            'subject' => $message->subject,
            'status' => $status,
            'provider_message_id' => null,
            'error_message' => null,
            'sent_at' => null,
        ];
    }

    private function display(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $value = strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, self::DISPLAY_LIMIT);
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/') . $path;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
