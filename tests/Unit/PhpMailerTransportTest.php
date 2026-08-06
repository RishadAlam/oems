<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Mail\EmailMessage;
use OEMS\App\Mail\PhpMailerTransport;
use OEMS\Core\Config;
use OEMS\Tests\Support\TestCase;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailerTransportTest extends TestCase
{
    public function testAppliesSmtpConfigurationAndMessageContent(): void
    {
        $phpMailer = new StubPhpMailer();
        $transport = new PhpMailerTransport($this->config(), static fn (): PHPMailer => $phpMailer);

        $messageId = $transport->send(new EmailMessage(
            'recipient@example.test',
            'Recipient Name',
            'Account subject',
            '<p>HTML body</p>',
            'Text body',
        ));

        $this->assertSame('<stub-message-id>', $messageId);
        $this->assertSame('smtp.example.test', $phpMailer->Host);
        $this->assertSame(2525, $phpMailer->Port);
        $this->assertTrue($phpMailer->SMTPAuth);
        $this->assertSame('smtp-user', $phpMailer->Username);
        $this->assertSame('smtp-password', $phpMailer->Password);
        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $phpMailer->SMTPSecure);
        $this->assertSame('no-reply@example.test', $phpMailer->From);
        $this->assertSame('OEMS', $phpMailer->FromName);
        $this->assertSame('recipient@example.test', $phpMailer->getToAddresses()[0][0]);
        $this->assertSame('Account subject', $phpMailer->Subject);
        $this->assertSame('<p>HTML body</p>', $phpMailer->Body);
        $this->assertSame('Text body', $phpMailer->AltBody);
    }

    public function testWrapsPhpMailerFailuresInAnApplicationException(): void
    {
        $phpMailer = new StubPhpMailer(true);
        $transport = new PhpMailerTransport($this->config(), static fn (): PHPMailer => $phpMailer);

        try {
            $transport->send(new EmailMessage(
                'recipient@example.test',
                'Recipient Name',
                'Account subject',
                '<p>HTML body</p>',
                'Text body',
            ));
            $this->assertTrue(false, 'Expected an application mail exception.');
        } catch (RuntimeException $exception) {
            $this->assertTrue(str_contains($exception->getMessage(), 'Email delivery failed'));
            $this->assertTrue($exception->getPrevious() instanceof PhpMailerException);
        }
    }

    private function config(): Config
    {
        return new Config([
            'mail' => [
                'host' => 'smtp.example.test',
                'port' => 2525,
                'username' => 'smtp-user',
                'password' => 'smtp-password',
                'encryption' => 'tls',
                'from_address' => 'no-reply@example.test',
                'from_name' => 'OEMS',
            ],
        ]);
    }
}

final class StubPhpMailer extends PHPMailer
{
    public function __construct(private readonly bool $fail = false)
    {
        parent::__construct(true);
    }

    public function send(): bool
    {
        if ($this->fail) {
            throw new PhpMailerException('simulated transport failure');
        }

        return true;
    }

    public function getLastMessageID(): string
    {
        return '<stub-message-id>';
    }
}
