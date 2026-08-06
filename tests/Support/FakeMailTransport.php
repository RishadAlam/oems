<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\MailTransportInterface;
use OEMS\App\Mail\EmailMessage;
use Throwable;

final class FakeMailTransport implements MailTransportInterface
{
    public array $messages = [];

    public ?Throwable $failure = null;

    public function __construct(private readonly ?string $messageId = null)
    {
    }

    public function send(EmailMessage $message): ?string
    {
        $this->messages[] = $message;

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->messageId;
    }
}
