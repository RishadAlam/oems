<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\EmailLogRepositoryInterface;
use OEMS\App\Contracts\MailTransportInterface;
use OEMS\App\Mail\EmailMessage;
use OEMS\Core\Config;
use Throwable;

final class AccountMailer
{
    public function __construct(
        private readonly MailTransportInterface $transport,
        private readonly EmailLogRepositoryInterface $logs,
        private readonly Config $config,
    ) {
    }

    public function sendVerification(
        int $userId,
        string $recipient,
        string $name,
        string $token,
    ): bool {
        $url = $this->url('/verify-email/' . rawurlencode($token));
        $safeName = $this->escape($name);
        $safeUrl = $this->escape($url);
        $message = new EmailMessage(
            $recipient,
            $name,
            'Verify your OEMS email',
            '<p>Hello ' . $safeName . ',</p>'
                . '<p>Verify your email address to activate sign-in for your OEMS account.</p>'
                . '<p><a href="' . $safeUrl . '">Verify your email</a></p>'
                . '<p>If you did not create this account, you can ignore this message.</p>',
            "Hello {$name},\n\nVerify your email to activate sign-in for your OEMS account:\n{$url}\n\n"
                . 'If you did not create this account, you can ignore this message.',
        );

        return $this->deliver($userId, 'email_verification', $message, $token);
    }

    public function sendPasswordReset(
        int $userId,
        string $recipient,
        string $name,
        string $token,
    ): bool {
        $url = $this->url('/reset-password/' . rawurlencode($token));
        $safeName = $this->escape($name);
        $safeUrl = $this->escape($url);
        $message = new EmailMessage(
            $recipient,
            $name,
            'Reset your OEMS password',
            '<p>Hello ' . $safeName . ',</p>'
                . '<p>Use the link below to choose a new OEMS password. This link expires in one hour.</p>'
                . '<p><a href="' . $safeUrl . '">Reset your password</a></p>'
                . '<p>If you did not request a reset, you can ignore this message.</p>',
            "Hello {$name},\n\nUse this link to choose a new OEMS password. It expires in one hour:\n{$url}\n\n"
                . 'If you did not request a reset, you can ignore this message.',
        );

        return $this->deliver($userId, 'password_reset', $message, $token);
    }

    private function deliver(int $userId, string $template, EmailMessage $message, string $token): bool
    {
        try {
            $messageId = $this->transport->send($message);
        } catch (Throwable $exception) {
            $this->recordSafely([
                'user_id' => $userId,
                'recipient_email' => $message->recipientEmail,
                'template' => $template,
                'subject' => $message->subject,
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => $this->sanitizeError($exception->getMessage(), $token),
                'sent_at' => null,
            ]);

            return false;
        }

        $this->recordSafely([
            'user_id' => $userId,
            'recipient_email' => $message->recipientEmail,
            'template' => $template,
            'subject' => $message->subject,
            'status' => 'sent',
            'provider_message_id' => $messageId,
            'error_message' => null,
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function recordSafely(array $attributes): void
    {
        try {
            $this->logs->record($attributes);
        } catch (Throwable) {
        }
    }

    private function sanitizeError(string $message, string $token): string
    {
        $sanitized = str_replace($token, '[redacted]', $message);
        $sanitized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $sanitized) ?? 'Email delivery failed.';
        $sanitized = trim(preg_replace('/\s+/u', ' ', $sanitized) ?? 'Email delivery failed.');

        if ($sanitized === '') {
            return 'Email delivery failed.';
        }

        return mb_substr($sanitized, 0, 500);
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
