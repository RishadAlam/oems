<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateInterval;
use DateTimeImmutable;
use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\Core\RateLimiter;
use OEMS\Core\Session;

final class AuthService
{
    private const ALLOWED_REGISTRATION_ROLES = ['participant', 'organizer'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Session $session,
        private readonly ?RateLimiter $rateLimiter = null,
    ) {
    }

    public function register(array $attributes): array
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $email = strtolower(trim((string) ($attributes['email'] ?? '')));
        $password = (string) ($attributes['password'] ?? '');
        $roleSlug = (string) ($attributes['role'] ?? 'participant');
        $errors = [];

        if (!in_array($roleSlug, self::ALLOWED_REGISTRATION_ROLES, true)) {
            $errors['role'][] = 'Choose a participant or organizer account.';
        }

        if ($this->users->findByEmail($email) !== null) {
            $errors['email'][] = 'An account already exists for this email.';
        }

        $role = $this->users->findRoleBySlug($roleSlug);

        if ($role === null) {
            $errors['role'][] = 'The selected account type is unavailable.';
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $verificationToken = bin2hex(random_bytes(32));
        $userId = $this->users->create([
            'role_id' => (int) $role['id'],
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verification_token_hash' => hash('sha256', $verificationToken),
        ]);

        return [
            'success' => true,
            'errors' => [],
            'user_id' => $userId,
            'verification_token' => $verificationToken,
        ];
    }

    public function attempt(
        string $email,
        string $password,
        bool $remember = false,
        string $ipAddress = '127.0.0.1',
        string $userAgent = '',
    ): array {
        $normalizedEmail = strtolower(trim($email));
        $rateLimitKey = 'login:' . $normalizedEmail . ':' . $ipAddress;

        if ($this->rateLimiter?->tooManyAttempts($rateLimitKey) === true) {
            return [
                'success' => false,
                'errors' => ['email' => ['Too many sign-in attempts. Try again in a few minutes.']],
            ];
        }

        $user = $this->users->findByEmail($normalizedEmail);
        $passwordHash = $user['password'] ?? '$2y$12$06S5Gr2.KdQ50DsOf9k6kOnrGocFbBbKR2dR6N6qPQ8nD5Q4/3LxW';

        if ($user === null || !password_verify($password, (string) $passwordHash)) {
            $this->rateLimiter?->hit($rateLimitKey);

            return [
                'success' => false,
                'errors' => ['email' => ['The email or password is incorrect.']],
            ];
        }

        if (($user['status'] ?? 'inactive') !== 'active') {
            return [
                'success' => false,
                'errors' => ['email' => ['This account is not active. Contact support.']],
            ];
        }

        if (($user['email_verified_at'] ?? null) === null) {
            return [
                'success' => false,
                'errors' => ['email' => ['Verify your email before signing in.']],
            ];
        }

        $this->rateLimiter?->clear($rateLimitKey);

        $this->session->regenerate();
        $this->session->put('auth.user_id', (int) $user['id']);
        $this->session->put('auth.role', (string) $user['role_slug']);
        $this->users->updateLastLogin((int) $user['id']);

        $rememberCookie = null;

        if ($remember) {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $expiresAt = (new DateTimeImmutable())->add(new DateInterval('P30D'));
            $this->users->storeRememberSession(
                (int) $user['id'],
                $selector,
                hash('sha256', $validator),
                $expiresAt,
                $ipAddress,
                substr($userAgent, 0, 500),
            );
            $rememberCookie = $selector . ':' . $validator;
        }

        return [
            'success' => true,
            'errors' => [],
            'user' => $user,
            'remember_cookie' => $rememberCookie,
        ];
    }

    public function verifyEmail(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        return $this->users->markEmailVerified(hash('sha256', $token)) !== null;
    }

    public function requestPasswordReset(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->users->findByEmail($normalizedEmail);

        if ($user === null || ($user['status'] ?? 'inactive') !== 'active') {
            return [
                'success' => true,
                'reset_token' => null,
                'user_id' => null,
                'name' => null,
                'email' => null,
            ];
        }

        $token = bin2hex(random_bytes(32));
        $this->users->deletePasswordResets($normalizedEmail);
        $this->users->storePasswordReset(
            $normalizedEmail,
            hash('sha256', $token),
            (new DateTimeImmutable())->add(new DateInterval('PT1H')),
        );

        return [
            'success' => true,
            'reset_token' => $token,
            'user_id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ];
    }

    public function resetPassword(string $token, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $reset = $this->users->findValidPasswordReset(hash('sha256', $token), new DateTimeImmutable());

        if ($reset === null) {
            return false;
        }

        $user = $this->users->findByEmail((string) $reset['email']);

        if ($user === null) {
            return false;
        }

        $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->users->deletePasswordResets((string) $reset['email']);
        $this->users->deleteRememberSessionsForUser((int) $user['id']);

        return true;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->users->findById($userId);

        if ($user === null || !password_verify($currentPassword, (string) $user['password'])) {
            return false;
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));
        $this->users->deleteRememberSessionsForUser($userId);

        return true;
    }

    public function consumeRememberCookie(string $cookie, string $ipAddress, string $userAgent): bool
    {
        [$selector, $validator] = array_pad(explode(':', $cookie, 2), 2, '');

        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            return false;
        }

        $rememberSession = $this->users->findRememberSession($selector, new DateTimeImmutable());

        if ($rememberSession === null || !hash_equals(
            (string) $rememberSession['validator_hash'],
            hash('sha256', $validator),
        )) {
            $this->users->deleteRememberSession($selector);

            return false;
        }

        $user = $this->users->findById((int) $rememberSession['user_id']);

        if ($user === null || ($user['status'] ?? 'inactive') !== 'active') {
            $this->users->deleteRememberSession($selector);

            return false;
        }

        $this->session->regenerate();
        $this->session->put('auth.user_id', (int) $user['id']);
        $this->session->put('auth.role', (string) $user['role_slug']);

        return true;
    }

    public function logout(?string $rememberCookie = null): void
    {
        if ($rememberCookie !== null) {
            [$selector] = explode(':', $rememberCookie, 2);

            if (preg_match('/^[a-f0-9]{24}$/', $selector) === 1) {
                $this->users->deleteRememberSession($selector);
            }
        }

        $this->session->invalidate();
    }
}
