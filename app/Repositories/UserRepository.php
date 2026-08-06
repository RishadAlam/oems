<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use DateTimeImmutable;
use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\Core\Database;
use PDO;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email AND users.deleted_at IS NULL
             LIMIT 1',
        );
        $statement->execute(['email' => strtolower($email)]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id AND users.deleted_at IS NULL
             LIMIT 1',
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function findRoleBySlug(string $slug): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id, name, slug FROM roles WHERE slug = :slug LIMIT 1',
        );
        $statement->execute(['slug' => $slug]);
        $role = $statement->fetch();

        return is_array($role) ? $role : null;
    }

    public function create(array $attributes): int
    {
        return $this->database->transaction(function (PDO $connection) use ($attributes): int {
            $statement = $connection->prepare(
                'INSERT INTO users
                    (role_id, name, email, password, status, email_verification_token_hash, created_at, updated_at)
                 VALUES
                    (:role_id, :name, :email, :password, :status, :verification_hash, NOW(), NOW())',
            );
            $statement->execute([
                'role_id' => $attributes['role_id'],
                'name' => $attributes['name'],
                'email' => strtolower((string) $attributes['email']),
                'password' => $attributes['password'],
                'status' => $attributes['status'] ?? 'active',
                'verification_hash' => $attributes['email_verification_token_hash'] ?? null,
            ]);
            $userId = (int) $connection->lastInsertId();

            $profile = $connection->prepare(
                'INSERT INTO profiles (user_id, created_at, updated_at) VALUES (:user_id, NOW(), NOW())',
            );
            $profile->execute(['user_id' => $userId]);

            $organizerRole = $this->findRoleBySlug('organizer');

            if ($organizerRole !== null && (int) $organizerRole['id'] === (int) $attributes['role_id']) {
                $organizer = $connection->prepare(
                    'INSERT INTO organizers (user_id, organization_name, approval_status, created_at, updated_at)
                     VALUES (:user_id, :organization_name, :approval_status, NOW(), NOW())',
                );
                $organizer->execute([
                    'user_id' => $userId,
                    'organization_name' => $attributes['name'],
                    'approval_status' => 'pending',
                ]);
            }

            return $userId;
        });
    }

    public function markEmailVerified(string $tokenHash): ?array
    {
        return $this->database->transaction(function (PDO $connection) use ($tokenHash): ?array {
            $lookup = $connection->prepare(
                'SELECT id
                 FROM users
                 WHERE email_verification_token_hash = :token_hash AND email_verified_at IS NULL
                 LIMIT 1
                 FOR UPDATE',
            );
            $lookup->execute(['token_hash' => $tokenHash]);
            $userId = $lookup->fetchColumn();

            if ($userId === false) {
                return null;
            }

            $update = $connection->prepare(
                'UPDATE users
                 SET email_verified_at = NOW(), email_verification_token_hash = NULL, updated_at = NOW()
                 WHERE id = :id AND email_verified_at IS NULL',
            );
            $update->execute(['id' => (int) $userId]);

            if ($update->rowCount() !== 1) {
                return null;
            }

            $userLookup = $connection->prepare(
                'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
                 FROM users
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE users.id = :id AND users.deleted_at IS NULL
                 LIMIT 1',
            );
            $userLookup->execute(['id' => (int) $userId]);
            $user = $userLookup->fetch();

            return is_array($user) ? $user : null;
        });
    }

    public function updateLastLogin(int $userId): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id',
        );
        $statement->execute(['id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id',
        );
        $statement->execute(['password' => $passwordHash, 'id' => $userId]);
    }

    public function storePasswordReset(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO password_resets (email, token_hash, expires_at, created_at)
             VALUES (:email, :token_hash, :expires_at, NOW())',
        );
        $statement->execute([
            'email' => strtolower($email),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findValidPasswordReset(string $tokenHash, DateTimeImmutable $now): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT email, token_hash, expires_at
             FROM password_resets
             WHERE token_hash = :token_hash AND expires_at > :now
             LIMIT 1',
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);
        $reset = $statement->fetch();

        return is_array($reset) ? $reset : null;
    }

    public function deletePasswordResets(string $email): void
    {
        $statement = $this->database->connection()->prepare('DELETE FROM password_resets WHERE email = :email');
        $statement->execute(['email' => strtolower($email)]);
    }

    public function storeRememberSession(
        int $userId,
        string $selector,
        string $validatorHash,
        DateTimeImmutable $expiresAt,
        string $ipAddress,
        string $userAgent,
    ): void {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO sessions
                (user_id, selector, validator_hash, ip_address, user_agent, last_activity_at, expires_at, created_at)
             VALUES
                (:user_id, :selector, :validator_hash, :ip_address, :user_agent, NOW(), :expires_at, NOW())',
        );
        $statement->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => $validatorHash,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findRememberSession(string $selector, DateTimeImmutable $now): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT user_id, selector, validator_hash, expires_at
             FROM sessions
             WHERE selector = :selector AND expires_at > :now
             LIMIT 1',
        );
        $statement->execute([
            'selector' => $selector,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);
        $session = $statement->fetch();

        return is_array($session) ? $session : null;
    }

    public function deleteRememberSession(string $selector): void
    {
        $statement = $this->database->connection()->prepare('DELETE FROM sessions WHERE selector = :selector');
        $statement->execute(['selector' => $selector]);
    }

    public function deleteRememberSessionsForUser(int $userId): void
    {
        $statement = $this->database->connection()->prepare('DELETE FROM sessions WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
    }
}
