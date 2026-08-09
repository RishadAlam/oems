<?php

declare(strict_types=1);

namespace OEMS\App\Mail;

use Closure;
use OEMS\App\Contracts\MailTransportInterface;
use OEMS\Core\Config;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailerTransport implements MailTransportInterface
{
    private readonly Closure $mailerFactory;

    public function __construct(private readonly Config $config, ?Closure $mailerFactory = null)
    {
        $this->mailerFactory = $mailerFactory ?? static fn (): PHPMailer => new PHPMailer(true);
    }

    public function send(EmailMessage $message): ?string
    {
        $mailer = ($this->mailerFactory)();

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) $this->config->get('mail.host', 'localhost');
            $mailer->Port = (int) $this->config->get('mail.port', 2525);
            $mailer->Timeout = 10;
            $mailer->CharSet = PHPMailer::CHARSET_UTF8;
            $username = (string) $this->config->get('mail.username', '');
            $mailer->SMTPAuth = $username !== '';

            if ($mailer->SMTPAuth) {
                $mailer->Username = $username;
                $mailer->Password = (string) $this->config->get('mail.password', '');
            }

            $encryption = strtolower((string) $this->config->get('mail.encryption', 'tls'));

            if ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'none') {
                $mailer->SMTPAutoTLS = false;
                $mailer->SMTPSecure = '';
            } else {
                throw new RuntimeException('Unsupported SMTP encryption setting.');
            }

            $mailer->setFrom(
                (string) $this->config->get('mail.from_address', 'no-reply@oems.local'),
                (string) $this->config->get('mail.from_name', 'OEMS'),
            );
            $mailer->addAddress($message->recipientEmail, $message->recipientName);
            $mailer->isHTML(true);
            $mailer->Subject = $message->subject;
            $mailer->Body = $message->htmlBody;
            $mailer->AltBody = $message->textBody;
            $mailer->send();
            $messageId = trim($mailer->getLastMessageID());

            return $messageId === '' ? null : $messageId;
        } catch (PhpMailerException $exception) {
            throw new RuntimeException('Email delivery failed: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
