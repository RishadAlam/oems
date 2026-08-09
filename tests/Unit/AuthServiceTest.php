<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\AuthService;
use OEMS\Core\Auth;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\RateLimiter;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class AuthServiceTest extends TestCase
{
    private FakeUserRepository $users;

    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->users = new FakeUserRepository();
        $this->session = new Session(false);
    }

    public function testRegistrationHashesPasswordAndUsesAnAllowedRole(): void
    {
        $service = new AuthService($this->users, $this->session);

        $result = $service->register([
            'name' => 'Nafisa Rahman',
            'email' => 'Nafisa@Example.com',
            'password' => 'secure-password',
            'role' => 'organizer',
        ]);

        $created = $this->users->findById((int) $result['user_id']);
        $this->assertTrue($result['success']);
        $this->assertSame('nafisa@example.com', $created['email']);
        $this->assertTrue(password_verify('secure-password', $created['password']));
        $this->assertNotSame('secure-password', $created['password']);
        $this->assertSame('organizer', $created['role_slug']);
        $this->assertSame(64, strlen($created['email_verification_token_hash']));
        $this->assertNotSame($result['verification_token'], $created['email_verification_token_hash']);
    }

    public function testDuplicateEmailIsRejectedWithoutCreatingAnotherUser(): void
    {
        $this->users->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
        ]);
        $service = new AuthService($this->users, $this->session);

        $result = $service->register([
            'name' => 'Second User',
            'email' => 'EXISTING@example.com',
            'password' => 'another-password',
            'role' => 'participant',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertSame(1, count($this->users->users));
    }

    public function testSuperAdminSelfRegistrationIsRejected(): void
    {
        $service = new AuthService($this->users, $this->session);

        $result = $service->register([
            'name' => 'Unsafe Admin',
            'email' => 'unsafe@example.com',
            'password' => 'secure-password',
            'role' => 'super-admin',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('role', $result['errors']);
    }

    public function testVerifiedUserCanLoginAndSessionStoresCanonicalIdentity(): void
    {
        $id = $this->users->create([
            'name' => 'Imran Hossain',
            'email' => 'imran@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $service = new AuthService($this->users, $this->session);

        $result = $service->attempt(' IMRAN@example.com ', 'secure-password');

        $this->assertTrue($result['success']);
        $this->assertSame($id, $this->session->get('auth.user_id'));
        $this->assertSame('participant', $this->session->get('auth.role'));
    }

    public function testUnverifiedUserCannotLogin(): void
    {
        $this->users->create([
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => null,
        ]);
        $service = new AuthService($this->users, $this->session);

        $result = $service->attempt('unverified@example.com', 'secure-password');

        $this->assertFalse($result['success']);
        $this->assertSame('Verify your email before signing in.', $result['errors']['email'][0]);
        $this->assertNull($this->session->get('auth.user_id'));
    }

    public function testInactiveUserCannotLogin(): void
    {
        $this->users->create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'inactive',
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $service = new AuthService($this->users, $this->session);

        $result = $service->attempt('inactive@example.com', 'secure-password');

        $this->assertFalse($result['success']);
        $this->assertSame('This account is not active. Contact support.', $result['errors']['email'][0]);
        $this->assertNull($this->session->get('auth.user_id'));
    }

    public function testInvalidPasswordCannotCreateAnAuthenticatedSession(): void
    {
        $this->users->create([
            'name' => 'Valid User',
            'email' => 'valid@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $service = new AuthService($this->users, $this->session);

        $result = $service->attempt('valid@example.com', 'wrong-password');

        $this->assertFalse($result['success']);
        $this->assertNull($this->session->get('auth.user_id'));
    }

    public function testRepeatedInvalidPasswordsTriggerLoginLimit(): void
    {
        $this->users->create([
            'name' => 'Rate Limited User',
            'email' => 'limited@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $directory = sys_get_temp_dir() . '/oems-auth-rate-' . bin2hex(random_bytes(5));
        $limiter = new RateLimiter($directory, 2, 900);
        $service = new AuthService($this->users, $this->session, $limiter);

        $service->attempt('limited@example.com', 'wrong-one', false, '192.0.2.1');
        $service->attempt('limited@example.com', 'wrong-two', false, '192.0.2.1');
        $blocked = $service->attempt('limited@example.com', 'secure-password', false, '192.0.2.1');

        $this->assertFalse($blocked['success']);
        $this->assertSame(
            'Too many sign-in attempts. Try again in a few minutes.',
            $blocked['errors']['email'][0],
        );

        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    public function testRegistrationRateLimitStopsRepeatedAccountCreationFromOneIp(): void
    {
        $directory = sys_get_temp_dir() . '/oems-registration-rate-' . bin2hex(random_bytes(5));
        $service = new AuthService(
            $this->users,
            $this->session,
            new RateLimiter($directory, 1, 900),
        );

        try {
            $first = $service->register([
                'name' => 'First Registration',
                'email' => 'first-registration@example.com',
                'password' => 'secure-password',
                'role' => 'participant',
            ], '192.0.2.70');
            $second = $service->register([
                'name' => 'Second Registration',
                'email' => 'second-registration@example.com',
                'password' => 'secure-password',
                'role' => 'participant',
            ], '192.0.2.70');

            $this->assertTrue($first['success']);
            $this->assertFalse($second['success']);
            $this->assertSame(
                'Too many account creation attempts. Try again in a few minutes.',
                $second['errors']['email'][0] ?? null,
            );
            $this->assertSame(1, count($this->users->users));
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testRememberCookieAuthenticationInvalidatesTheGuestCsrfToken(): void
    {
        $userId = $this->users->create([
            'name' => 'Remembered Participant',
            'email' => 'remembered@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $selector = str_repeat('a', 24);
        $validator = str_repeat('b', 64);
        $this->users->storeRememberSession(
            $userId,
            $selector,
            hash('sha256', $validator),
            (new \DateTimeImmutable())->modify('+1 hour'),
            '192.0.2.71',
            'OEMS Test',
        );
        $security = new Security($this->session);
        $guestToken = $security->csrfToken();
        $service = new AuthService($this->users, $this->session);

        $authenticated = $service->consumeRememberCookie(
            $selector . ':' . $validator,
            '192.0.2.71',
            'OEMS Test',
        );

        $this->assertTrue($authenticated);
        $this->assertFalse($security->verifyCsrf($guestToken));
        $this->assertSame($userId, $this->session->get('auth.user_id'));
    }

    public function testPasswordResetMakesAnExistingAuthenticatedSessionUnusable(): void
    {
        $this->users->create([
            'name' => 'Reset Session Participant',
            'email' => 'reset-session@example.com',
            'password' => password_hash('old-secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $service = new AuthService($this->users, $this->session);
        $login = $service->attempt('reset-session@example.com', 'old-secure-password');
        $reset = $service->requestPasswordReset('reset-session@example.com');

        $this->assertTrue($login['success']);
        $this->assertTrue($service->resetPassword((string) $reset['reset_token'], 'new-secure-password'));

        $auth = new Auth($this->session, $this->users);
        $this->assertTrue($auth->guest());
        $this->assertNull($this->session->get('auth.user_id'));
    }

    public function testPasswordChangeRefreshesTheCurrentAuthenticatedSessionCredential(): void
    {
        $userId = $this->users->create([
            'name' => 'Password Change Participant',
            'email' => 'password-change@example.com',
            'password' => password_hash('old-secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $service = new AuthService($this->users, $this->session);
        $service->attempt('password-change@example.com', 'old-secure-password');

        $changed = $service->changePassword(
            $userId,
            'old-secure-password',
            'new-secure-password',
        );

        $this->assertTrue($changed);
        $this->assertTrue((new Auth($this->session, $this->users))->check());
        $this->assertTrue(password_verify(
            'new-secure-password',
            (string) $this->users->findById($userId)['password'],
        ));
    }

    public function testPasswordResetReturnsTheEligibleMailRecipient(): void
    {
        $id = $this->users->create([
            'name' => 'Raihan Ahmed',
            'email' => 'raihan@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
        ]);
        $service = new AuthService($this->users, $this->session);

        $result = $service->requestPasswordReset(' RAIHAN@example.com ');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame($id, $result['user_id']);
        $this->assertSame('Raihan Ahmed', $result['name']);
        $this->assertSame('raihan@example.com', $result['email']);
        $this->assertSame('reset', $result['mail_dispatch']);
        $this->assertSame(64, strlen((string) $result['reset_token']));
    }

    public function testPasswordResetKeepsAnUnknownAccountGeneric(): void
    {
        $service = new AuthService($this->users, $this->session);

        $result = $service->requestPasswordReset('unknown@example.com');

        $this->assertTrue($result['success']);
        $this->assertNull($result['reset_token']);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertNull($result['user_id']);
        $this->assertNull($result['name']);
        $this->assertNull($result['email']);
        $this->assertSame('probe', $result['mail_dispatch']);
    }

    public function testRepeatedPasswordResetRequestsAreRateLimitedWithoutReplacingTheToken(): void
    {
        $this->users->create([
            'name' => 'Reset Limited User',
            'email' => 'reset-limited@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
        ]);
        $directory = sys_get_temp_dir() . '/oems-reset-rate-' . bin2hex(random_bytes(5));
        $limiter = new RateLimiter($directory, 1, 900);
        $service = new AuthService($this->users, $this->session, $limiter);

        $first = $service->requestPasswordReset('reset-limited@example.com', '192.0.2.30');
        $firstResets = $this->users->passwordResets;
        $second = $service->requestPasswordReset('reset-limited@example.com', '192.0.2.30');

        $this->assertTrue(is_string($first['reset_token']));
        $this->assertNull($second['reset_token']);
        $this->assertSame($firstResets, $this->users->passwordResets);

        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    public function testPasswordResetRateLimitFollowsTheEmailAcrossIpAddresses(): void
    {
        $this->users->create([
            'name' => 'Distributed Target',
            'email' => 'distributed-target@example.com',
            'password' => password_hash('secure-password', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
        ]);
        $directory = sys_get_temp_dir() . '/oems-reset-email-rate-' . bin2hex(random_bytes(5));
        $service = new AuthService(
            $this->users,
            $this->session,
            new RateLimiter($directory, 1, 900),
        );

        $first = $service->requestPasswordReset('distributed-target@example.com', '192.0.2.31');
        $second = $service->requestPasswordReset('distributed-target@example.com', '192.0.2.32');

        $this->assertTrue(is_string($first['reset_token']));
        $this->assertNull($second['reset_token']);
        $this->assertSame('none', $second['mail_dispatch']);

        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    public function testPasswordResetRateLimitFollowsTheIpAcrossEmailAddresses(): void
    {
        foreach (['first@example.com', 'second@example.com'] as $email) {
            $this->users->create([
                'name' => 'IP Limited User',
                'email' => $email,
                'password' => password_hash('secure-password', PASSWORD_DEFAULT),
                'role_id' => 3,
                'status' => 'active',
            ]);
        }
        $directory = sys_get_temp_dir() . '/oems-reset-ip-rate-' . bin2hex(random_bytes(5));
        $service = new AuthService(
            $this->users,
            $this->session,
            new RateLimiter($directory, 1, 900),
        );

        $first = $service->requestPasswordReset('first@example.com', '192.0.2.33');
        $second = $service->requestPasswordReset('second@example.com', '192.0.2.33');

        $this->assertTrue(is_string($first['reset_token']));
        $this->assertNull($second['reset_token']);
        $this->assertSame('none', $second['mail_dispatch']);

        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
