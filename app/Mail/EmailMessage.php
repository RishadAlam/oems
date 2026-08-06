<?php

declare(strict_types=1);

namespace OEMS\App\Mail;

final readonly class EmailMessage
{
    public function __construct(
        public string $recipientEmail,
        public string $recipientName,
        public string $subject,
        public string $htmlBody,
        public string $textBody,
    ) {
    }
}
