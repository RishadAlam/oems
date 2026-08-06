<?php

declare(strict_types=1);

namespace OEMS\Core;

final class SensitiveDataRedactor
{
    public static function requestPath(string $path): string
    {
        $redacted = preg_replace(
            '#^/(verify-email|reset-password)/[^/]+/?$#',
            '/$1/[redacted]',
            $path,
        );

        return is_string($redacted) ? $redacted : '[redacted]';
    }
}
