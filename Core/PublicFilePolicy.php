<?php

declare(strict_types=1);

namespace OEMS\Core;

final class PublicFilePolicy
{
    public static function mayServe(string $documentRoot, string $requestPath): bool
    {
        $path = parse_url($requestPath, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        if (str_contains($path, "\0")
            || $path === '/uploads/tickets'
            || str_starts_with($path, '/uploads/tickets/')) {
            return false;
        }

        return $path !== '/' && is_file(rtrim($documentRoot, DIRECTORY_SEPARATOR) . $path);
    }
}
