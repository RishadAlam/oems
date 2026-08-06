<?php

declare(strict_types=1);

namespace OEMS\Core;

final class Security
{
    private const CSRF_SESSION_KEY = 'security.csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function csrfToken(): string
    {
        $token = $this->session->get(self::CSRF_SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    public function verifyCsrf(?string $token): bool
    {
        $stored = $this->session->get(self::CSRF_SESSION_KEY);

        return is_string($token)
            && is_string($stored)
            && strlen($token) === strlen($stored)
            && hash_equals($stored, $token);
    }

    public function rotateCsrfToken(): string
    {
        $this->session->forget(self::CSRF_SESSION_KEY);

        return $this->csrfToken();
    }
}

