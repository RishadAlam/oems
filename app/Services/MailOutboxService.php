<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\MailOutboxRepositoryInterface;
use Throwable;

final class MailOutboxService
{
    private const TEMPLATES = [
        'registration_confirmation' => ['user_id', 'participant_name', 'registration_id', 'event_title', 'action_url'],
        'payment_pending' => ['user_id', 'participant_name', 'registration_id', 'event_title', 'action_url'],
        'payment_paid' => ['user_id', 'participant_name', 'registration_id', 'event_title', 'action_url'],
        'payment_rejected' => ['user_id', 'participant_name', 'registration_id', 'event_title', 'action_url'],
        'registration_cancelled' => ['user_id', 'participant_name', 'registration_id', 'event_title', 'action_url'],
        'ticket_issued' => ['user_id', 'participant_name', 'registration_id', 'event_title', 'action_url'],
        'event_reminder' => ['user_id', 'event_id', 'registration_id', 'event_title', 'starts_at', 'calendar_url'],
        'event_announcement' => ['event_id', 'recipient_name', 'event_title', 'subject', 'message', 'action_url'],
        'contact_reply' => ['contact_id', 'name', 'reply'],
        'newsletter_confirmation' => ['subscription_id', 'confirmation_url'],
        'newsletter_campaign' => ['campaign_id', 'subject', 'message', 'unsubscribe_url'],
        'newsletter_unsubscribe' => ['subscription_id'],
    ];

    public function __construct(private readonly MailOutboxRepositoryInterface $outbox)
    {
    }

    public function enqueue(
        string $template,
        string $recipient,
        array $payload,
        string $idempotencyMaterial,
        ?DateTimeImmutable $availableAt = null,
    ): array {
        $errors = [];
        $template = trim($template);
        $recipient = mb_strtolower(trim($recipient));
        $idempotencyMaterial = trim($idempotencyMaterial);

        if (!array_key_exists($template, self::TEMPLATES)) {
            $errors['template'] = 'Choose a supported email template.';
        }
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false || mb_strlen($recipient) > 190) {
            $errors['recipient_email'] = 'Enter a valid recipient email address.';
        }
        if ($idempotencyMaterial === '' || mb_strlen($idempotencyMaterial) > 500) {
            $errors['idempotency_key'] = 'The delivery request identifier is invalid.';
        }
        if (isset(self::TEMPLATES[$template]) && !$this->validPayload($template, $payload)) {
            $errors['payload'] = 'The email content is incomplete or invalid.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'job' => null, 'errors' => $errors];
        }

        try {
            $job = $this->outbox->enqueue([
                'template' => $template,
                'recipient_email' => $recipient,
                'payload' => $payload,
                'idempotency_key' => hash('sha256', $idempotencyMaterial),
                'available_at' => ($availableAt ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'job' => null, 'errors' => ['outbox' => 'Email delivery could not be queued.']];
        }

        return $job === null
            ? ['ok' => false, 'job' => null, 'errors' => ['outbox' => 'Email delivery could not be queued.']]
            : ['ok' => true, 'created' => (bool) ($job['was_created'] ?? false), 'job' => $job, 'errors' => []];
    }

    private function validPayload(string $template, array $payload): bool
    {
        $expected = self::TEMPLATES[$template];
        $actual = array_keys($payload);
        sort($expected);
        sort($actual);
        if ($actual !== $expected) {
            return false;
        }

        foreach ($payload as $value) {
            if (!is_scalar($value) || is_bool($value)) {
                return false;
            }
        }

        return match ($template) {
            'registration_confirmation', 'payment_pending', 'payment_paid', 'payment_rejected',
            'registration_cancelled', 'ticket_issued' => $this->positiveIds($payload, ['user_id', 'registration_id'])
                && $this->bounded($payload['participant_name'], 160)
                && $this->bounded($payload['event_title'], 180)
                && $this->relativeUrl($payload['action_url']),
            'event_reminder' => $this->positiveIds($payload, ['user_id', 'event_id', 'registration_id'])
                && $this->bounded($payload['event_title'], 180)
                && $this->bounded($payload['starts_at'], 64)
                && $this->relativeUrl($payload['calendar_url']),
            'event_announcement' => $this->positiveIds($payload, ['event_id'])
                && $this->bounded($payload['recipient_name'], 160)
                && $this->bounded($payload['event_title'], 180)
                && $this->bounded($payload['subject'], 180)
                && $this->bounded($payload['message'], 1000)
                && $this->relativeUrl($payload['action_url']),
            'contact_reply' => $this->positiveIds($payload, ['contact_id'])
                && $this->bounded($payload['name'], 100)
                && $this->bounded($payload['reply'], 4000),
            'newsletter_confirmation' => $this->positiveIds($payload, ['subscription_id'])
                && $this->relativeUrl($payload['confirmation_url']),
            'newsletter_campaign' => $this->positiveIds($payload, ['campaign_id'])
                && $this->bounded($payload['subject'], 180)
                && $this->bounded($payload['message'], 4000)
                && $this->relativeUrl($payload['unsubscribe_url']),
            'newsletter_unsubscribe' => $this->positiveIds($payload, ['subscription_id']),
            default => false,
        };
    }

    private function positiveIds(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                return false;
            }
        }

        return true;
    }

    private function bounded(mixed $value, int $max): bool
    {
        return is_scalar($value) && trim((string) $value) !== '' && mb_strlen(trim((string) $value)) <= $max;
    }

    private function relativeUrl(mixed $value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }
        $url = trim((string) $value);

        return $url !== '' && mb_strlen($url) <= 500 && preg_match('#\A/(?!/)#', $url) === 1;
    }
}
