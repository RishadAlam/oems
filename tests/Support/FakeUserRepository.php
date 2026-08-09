<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use DateTimeImmutable;
use OEMS\App\Contracts\UserRepositoryInterface;

final class FakeUserRepository implements UserRepositoryInterface
{
    public array $users = [];

    public array $roles = [
        'super-admin' => ['id' => 1, 'name' => 'Super Admin', 'slug' => 'super-admin'],
        'organizer' => ['id' => 2, 'name' => 'Organizer', 'slug' => 'organizer'],
        'participant' => ['id' => 3, 'name' => 'Participant', 'slug' => 'participant'],
    ];

    public array $passwordResets = [];

    public array $rememberSessions = [];

    public function findByEmail(string $email): ?array
    {
        foreach ($this->users as $user) {
            if (strtolower($user['email']) === strtolower($email)) {
                return $this->withRole($user);
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        return isset($this->users[$id]) ? $this->withRole($this->users[$id]) : null;
    }

    public function findRoleBySlug(string $slug): ?array
    {
        return $this->roles[$slug] ?? null;
    }

    public function create(array $attributes): int
    {
        $id = count($this->users) + 1;
        $this->users[$id] = array_merge($attributes, [
            'id' => $id,
            'status' => $attributes['status'] ?? 'active',
            'email_verified_at' => $attributes['email_verified_at'] ?? null,
        ]);

        return $id;
    }

    public function markEmailVerified(string $tokenHash): ?array
    {
        foreach ($this->users as $id => $user) {
            if (($user['email_verification_token_hash'] ?? null) !== $tokenHash) {
                continue;
            }

            $this->users[$id]['email_verified_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->users[$id]['email_verification_token_hash'] = null;

            return $this->withRole($this->users[$id]);
        }

        return null;
    }

    public function updateLastLogin(int $userId): void
    {
        $this->users[$userId]['last_login_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->users[$userId]['password'] = $passwordHash;
    }

    public function storePasswordReset(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $this->passwordResets[$tokenHash] = [
            'email' => strtolower($email),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ];
    }

    public function resetPasswordUsingToken(
        string $tokenHash,
        DateTimeImmutable $now,
        string $passwordHash,
    ): ?array
    {
        $reset = $this->passwordResets[$tokenHash] ?? null;

        if ($reset === null || $reset['expires_at'] <= $now) {
            return null;
        }

        $user = $this->findByEmail((string) $reset['email']);

        if ($user === null || ($user['status'] ?? 'inactive') !== 'active') {
            return null;
        }

        $this->users[(int) $user['id']]['password'] = $passwordHash;
        $this->deletePasswordResets((string) $reset['email']);
        $this->deleteRememberSessionsForUser((int) $user['id']);

        return ['user_id' => (int) $user['id'], 'email' => (string) $reset['email']];
    }

    public function deletePasswordResets(string $email): void
    {
        foreach ($this->passwordResets as $hash => $reset) {
            if ($reset['email'] === strtolower($email)) {
                unset($this->passwordResets[$hash]);
            }
        }
    }

    public function storeRememberSession(
        int $userId,
        string $selector,
        string $validatorHash,
        DateTimeImmutable $expiresAt,
        string $ipAddress,
        string $userAgent,
    ): void {
        $this->rememberSessions[$selector] = [
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => $validatorHash,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];
    }

    public function rotateRememberSession(
        string $selector,
        string $validatorHash,
        DateTimeImmutable $now,
        string $replacementSelector,
        string $replacementValidatorHash,
        DateTimeImmutable $replacementExpiresAt,
        string $ipAddress,
        string $userAgent,
    ): ?array
    {
        $session = $this->rememberSessions[$selector] ?? null;

        if ($session === null) {
            return null;
        }

        if ($session['expires_at'] <= $now
            || !hash_equals((string) $session['validator_hash'], $validatorHash)) {
            unset($this->rememberSessions[$selector]);

            return null;
        }

        unset($this->rememberSessions[$selector]);
        $this->storeRememberSession(
            (int) $session['user_id'],
            $replacementSelector,
            $replacementValidatorHash,
            $replacementExpiresAt,
            $ipAddress,
            $userAgent,
        );

        return ['user_id' => (int) $session['user_id']];
    }

    public function deleteRememberSession(string $selector): void
    {
        unset($this->rememberSessions[$selector]);
    }

    public function deleteRememberSessionsForUser(int $userId): void
    {
        foreach ($this->rememberSessions as $selector => $session) {
            if ($session['user_id'] === $userId) {
                unset($this->rememberSessions[$selector]);
            }
        }
    }

    private function withRole(array $user): array
    {
        foreach ($this->roles as $role) {
            if ($role['id'] === $user['role_id']) {
                return array_merge($user, [
                    'role_slug' => $role['slug'],
                    'role_name' => $role['name'],
                ]);
            }
        }

        return $user;
    }
}
