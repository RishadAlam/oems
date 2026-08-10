<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use InvalidArgumentException;
use OEMS\Core\Config;

final class QueuedMailTemplateService
{
    private const REQUIRED = [
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

    public function __construct(private readonly Config $config)
    {
    }

    public function render(string $template, array $payload): array
    {
        $this->assertPayload($template, $payload);
        $event = $this->plain($payload['event_title'] ?? '', 180);
        $name = $this->plain($payload['participant_name'] ?? $payload['recipient_name'] ?? $payload['name'] ?? 'Participant', 160);
        $action = null;
        $subject = '';
        $copy = '';

        switch ($template) {
            case 'registration_confirmation':
                [$subject, $copy, $action] = ['Registration confirmed', "Your registration for {$event} is confirmed.", $payload['action_url']];
                break;
            case 'payment_pending':
                [$subject, $copy, $action] = ['Payment review pending', "Your payment reference for {$event} is awaiting review.", $payload['action_url']];
                break;
            case 'payment_paid':
                [$subject, $copy, $action] = ['Payment approved', "Your payment for {$event} was approved and your registration is confirmed.", $payload['action_url']];
                break;
            case 'payment_rejected':
                [$subject, $copy, $action] = ['Payment rejected', "Your payment for {$event} could not be verified and the reserved seat was released.", $payload['action_url']];
                break;
            case 'registration_cancelled':
                [$subject, $copy, $action] = ['Registration cancelled', "Your registration for {$event} was cancelled.", $payload['action_url']];
                break;
            case 'ticket_issued':
                [$subject, $copy, $action] = ['Your event ticket is ready', "Your secure ticket for {$event} is ready.", $payload['action_url']];
                break;
            case 'event_reminder':
                $startsAt = $this->plain($payload['starts_at'], 64);
                [$subject, $copy, $action] = ["Reminder: {$event}", "{$event} starts at {$startsAt}.", $payload['calendar_url']];
                break;
            case 'event_announcement':
                $subject = $this->plain($payload['subject'], 180);
                $copy = $this->plain($payload['message'], 1000);
                $action = $payload['action_url'];
                break;
            case 'contact_reply':
                $subject = 'Reply from OEMS support';
                $copy = $this->plain($payload['reply'], 4000);
                break;
            case 'newsletter_confirmation':
                [$subject, $copy, $action] = ['Confirm your OEMS subscription', 'Confirm your email to receive OEMS event updates.', $payload['confirmation_url']];
                break;
            case 'newsletter_campaign':
                $subject = $this->plain($payload['subject'], 180);
                $copy = $this->plain($payload['message'], 4000);
                $action = $payload['unsubscribe_url'];
                break;
            case 'newsletter_unsubscribe':
                $subject = 'OEMS subscription ended';
                $copy = 'You will no longer receive OEMS newsletter updates.';
                break;
        }

        $text = "Hello {$name},\n\n{$copy}";
        $html = '<p>Hello ' . $this->escape($name) . ',</p><p>' . nl2br($this->escape($copy), false) . '</p>';
        if (is_string($action)) {
            $absolute = $this->absolute($action);
            $text .= "\n\n{$absolute}";
            $html .= '<p><a href="' . $this->escape($absolute) . '">Open details</a></p>';
        }

        return [
            'subject' => mb_substr($subject, 0, 190),
            'text' => $text,
            'html' => $html,
        ];
    }

    private function assertPayload(string $template, array $payload): void
    {
        $required = self::REQUIRED[$template] ?? null;
        if ($required === null) {
            throw new InvalidArgumentException('Unsupported queued email template.');
        }
        $keys = array_keys($payload);
        sort($required);
        sort($keys);
        if ($keys !== $required) {
            throw new InvalidArgumentException('Invalid queued email payload.');
        }
        foreach ($payload as $value) {
            if (!is_scalar($value) || is_bool($value)) {
                throw new InvalidArgumentException('Invalid queued email payload value.');
            }
        }
    }

    private function absolute(mixed $path): string
    {
        if (!is_string($path) || mb_strlen($path) > 500 || preg_match('#\A/(?!/)#', $path) !== 1) {
            throw new InvalidArgumentException('Invalid queued email action URL.');
        }

        return rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/') . $path;
    }

    private function plain(mixed $value, int $limit): string
    {
        $value = is_scalar($value) ? strip_tags((string) $value) : '';
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/[ \t]+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, $limit);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
