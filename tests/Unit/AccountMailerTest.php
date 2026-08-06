<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\AccountMailer;
use OEMS\Core\Config;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class AccountMailerTest extends TestCase
{
    public function testVerificationMessageUsesAnAbsoluteSingleUseLinkAndLogsSuccess(): void
    {
        $transport = new FakeMailTransport('<mailtrap-message-id>');
        $logs = new FakeEmailLogRepository();
        $mailer = $this->mailer($transport, $logs);
        $token = str_repeat('a', 64);

        $sent = $mailer->sendVerification(9, 'maliha@example.test', 'Maliha Rahman', $token);

        $this->assertTrue($sent);
        $this->assertSame(1, count($transport->messages));
        $message = $transport->messages[0];
        $this->assertSame('maliha@example.test', $message->recipientEmail);
        $this->assertSame('Maliha Rahman', $message->recipientName);
        $this->assertSame('Verify your OEMS email', $message->subject);
        $this->assertTrue(str_contains(
            $message->htmlBody,
            'http://localhost:8000/verify-email/' . $token,
        ));
        $this->assertTrue(str_contains($message->textBody, 'Verify your email'));
        $this->assertSame('email_verification', $logs->records[0]['template']);
        $this->assertSame('sent', $logs->records[0]['status']);
        $this->assertSame('<mailtrap-message-id>', $logs->records[0]['provider_message_id']);
        $this->assertFalse(str_contains((string) ($logs->records[0]['error_message'] ?? ''), $token));
    }

    public function testPasswordResetMessageExplainsTheExpiry(): void
    {
        $transport = new FakeMailTransport();
        $logs = new FakeEmailLogRepository();
        $mailer = $this->mailer($transport, $logs);
        $token = str_repeat('b', 64);

        $sent = $mailer->sendPasswordReset(14, 'raihan@example.test', 'Raihan Ahmed', $token);

        $this->assertTrue($sent);
        $message = $transport->messages[0];
        $this->assertSame('Reset your OEMS password', $message->subject);
        $this->assertTrue(str_contains(
            $message->htmlBody,
            'http://localhost:8000/reset-password/' . $token,
        ));
        $this->assertTrue(str_contains($message->textBody, 'expires in one hour'));
        $this->assertSame('password_reset', $logs->records[0]['template']);
        $this->assertSame(14, $logs->records[0]['user_id']);
    }

    public function testTransportFailureIsSanitizedLoggedAndDoesNotEscape(): void
    {
        $token = str_repeat('c', 64);
        $transport = new FakeMailTransport();
        $transport->failure = new RuntimeException("SMTP failed\nraw-token={$token}\x00retry");
        $logs = new FakeEmailLogRepository();
        $mailer = $this->mailer($transport, $logs);

        $sent = $mailer->sendVerification(22, 'sadika@example.test', 'Sadika Noor', $token);

        $this->assertFalse($sent);
        $this->assertSame('failed', $logs->records[0]['status']);
        $error = (string) $logs->records[0]['error_message'];
        $this->assertFalse(str_contains($error, $token));
        $this->assertFalse(str_contains($error, "\n"));
        $this->assertFalse(str_contains($error, "\x00"));
        $this->assertNull($logs->records[0]['provider_message_id']);
    }

    private function mailer(
        FakeMailTransport $transport,
        FakeEmailLogRepository $logs,
    ): AccountMailer {
        return new AccountMailer(
            $transport,
            $logs,
            new Config([
                'name' => 'OEMS',
                'url' => 'http://localhost:8000',
            ]),
        );
    }
}
