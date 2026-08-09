<?php

declare(strict_types=1);

namespace OEMS\Core;

use OEMS\App\Contracts\UserRepositoryInterface;

final class Auth
{
    private ?array $cachedUser = null;

    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            if ($this->cachedUser !== null || $this->session->get('auth.user_id') === null) {
                return $this->cachedUser;
            }

            $this->resolved = false;
        }

        $this->resolved = true;
        $userId = $this->session->get('auth.user_id');

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if ($user === null || ($user['status'] ?? 'inactive') !== 'active') {
            $this->session->forget('auth');

            return null;
        }

        $sessionSignature = $this->session->get('auth.password_signature');

        if (!is_string($sessionSignature)
            || !preg_match('/^[a-f0-9]{64}$/', $sessionSignature)
            || !hash_equals($sessionSignature, hash('sha256', (string) ($user['password'] ?? '')))) {
            $this->session->forget('auth');

            return null;
        }

        $this->session->put('auth.role', (string) $user['role_slug']);
        $this->cachedUser = $user;

        return $this->cachedUser;
    }

    public function hasRole(string ...$roles): bool
    {
        $user = $this->user();

        return $user !== null && in_array((string) $user['role_slug'], $roles, true);
    }
}
