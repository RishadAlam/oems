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
            || str_contains($path, '\\')
            || str_contains($path, '//')) {
            return false;
        }

        $segments = explode('/', $path);
        if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return false;
        }

        $foldedPath = strtolower($path);
        if ($foldedPath === '/uploads/tickets'
            || str_starts_with($foldedPath, '/uploads/tickets/')) {
            return false;
        }

        $root = realpath($documentRoot);
        $target = $root === false ? false : realpath($root . $path);
        $legacyTicketRoot = $root === false ? false : realpath($root . '/uploads/tickets');

        if ($target !== false
            && $legacyTicketRoot !== false
            && ($target === $legacyTicketRoot
                || str_starts_with($target, $legacyTicketRoot . DIRECTORY_SEPARATOR))) {
            return false;
        }

        return $path !== '/'
            && $target !== false
            && str_starts_with($target, $root . DIRECTORY_SEPARATOR)
            && is_file($target);
    }
}
