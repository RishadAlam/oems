<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

use DateTimeImmutable;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?array;

    public function findById(int $id): ?array;

    public function findRoleBySlug(string $slug): ?array;

    public function create(array $attributes): int;

    public function markEmailVerified(string $tokenHash): ?array;

    public function updateLastLogin(int $userId): void;

    public function updatePassword(int $userId, string $passwordHash): void;

    public function storePasswordReset(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void;

    public function findValidPasswordReset(string $tokenHash, DateTimeImmutable $now): ?array;

    public function deletePasswordResets(string $email): void;

    public function storeRememberSession(
        int $userId,
        string $selector,
        string $validatorHash,
        DateTimeImmutable $expiresAt,
        string $ipAddress,
        string $userAgent,
    ): void;

    public function findRememberSession(string $selector, DateTimeImmutable $now): ?array;

    public function deleteRememberSession(string $selector): void;

    public function deleteRememberSessionsForUser(int $userId): void;
}

