<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use OEMS\App\Mail\EmailMessage;

interface MailTransportInterface
{
    public function send(EmailMessage $message): ?string;
}
