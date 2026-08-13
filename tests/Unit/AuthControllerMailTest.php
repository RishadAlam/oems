<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AuthController;
use OEMS\App\Services\AccountMailer;
use OEMS\App\Services\AuthService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class AuthControllerMailTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testSuccessfulRegistrationSendsTheVerificationMessage(): void
    {
        [$controller, $transport, $session] = $this->controller();

        $response = $controller->register(Request::create('POST', '/register', input: [
            'name' => 'Maliha Rahman',
            'email' => 'maliha@example.test',
            'password' => 'DemoPass!2026',
            'password_confirmation' => 'DemoPass!2026',
            'role' => 'participant',
            'terms' => '1',
        ]));

        $this->assertSame('/verify-email/resend', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('maliha@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame('Verify your OEMS email', $transport->messages[0]->subject);
        $this->assertNull(
            $session->get('_flash.development_link'),
            'Email ownership tokens must never be exposed in a browser flash message.',
        );
    }

    public function testRegistrationRejectsTooShortNamesAndOversizedPasswordsBeforeCreatingAnAccount(): void
    {
        [$controller, $transport, $session] = $this->controller();

        $response = $controller->register(Request::create('POST', '/register', input: [
            'name' => 'A',
            'email' => 'boundary@example.test',
            'password' => str_repeat('x', 129),
            'password_confirmation' => str_repeat('x', 129),
            'role' => 'participant',
            'terms' => '1',
        ]));

        $errors = $session->get('_flash.errors', []);
        $this->assertSame('/register', $response->header('Location'));
        $this->assertSame(0, count($transport->messages));
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testEligiblePasswordResetSendsTheResetMessage(): void
    {
        $users = new FakeUserRepository();
        $users->create([
            'name' => 'Raihan Ahmed',
            'email' => 'raihan@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
        ]);
        [$controller, $transport, $session] = $this->controller($users);

        $response = $controller->sendResetLink(Request::create('POST', '/forgot-password', input: [
            'email' => 'raihan@example.test',
        ]));

        $this->assertSame('/forgot-password', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('raihan@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame('Reset your OEMS password', $transport->messages[0]->subject);
        $this->assertNull(
            $session->get('_flash.development_link'),
            'Password-reset tokens must never be exposed in a browser flash message.',
        );
    }

    public function testUnknownPasswordResetUsesThePrivacySinkInsteadOfTheSubmittedAddress(): void
    {
        [$controller, $transport] = $this->controller();

        $response = $controller->sendResetLink(Request::create('POST', '/forgot-password', input: [
            'email' => 'unknown@example.test',
        ]));

        $this->assertSame('/forgot-password', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('privacy-sink@example.test', $transport->messages[0]->recipientEmail);
        $this->assertNotSame('unknown@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame('Reset your OEMS password', $transport->messages[0]->subject);
    }

    public function testThrottledPasswordResetDispatchesNoAdditionalMessage(): void
    {
        $users = new FakeUserRepository();
        $users->create([
            'name' => 'Limited User',
            'email' => 'limited-reset@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
        ]);
        $directory = sys_get_temp_dir() . '/oems-controller-reset-rate-' . bin2hex(random_bytes(5));
        [$controller, $transport] = $this->controller(
            $users,
            new RateLimiter($directory, 1, 900),
        );
        $request = static fn (): Request => Request::create(
            'POST',
            '/forgot-password',
            input: ['email' => 'limited-reset@example.test'],
            server: ['REMOTE_ADDR' => '192.0.2.60'],
        );

        $controller->sendResetLink($request());
        $controller->sendResetLink($request());

        $this->assertSame(1, count($transport->messages));

        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    public function testVerificationRecoveryPageAndRoutesExposeTheSecureResendFlow(): void
    {
        [$controller] = $this->controller();

        $response = $controller->showResendVerification(Request::create('GET', '/verify-email/resend'));
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'action="/verify-email/resend"'));
        $this->assertTrue(str_contains($response->body(), 'name="_token"'));
        $this->assertTrue(str_contains($response->body(), 'autocomplete="email"'));
        $this->assertTrue(str_contains($routes, '$router->get(\'/verify-email/resend\''));
        $this->assertTrue(str_contains($routes, '$router->post(\'/verify-email/resend\''));
        $this->assertTrue(str_contains($routes, "['csrf'], 'verification.resend'"));
    }

    public function testEligibleVerificationResendRotatesAndSendsANewLink(): void
    {
        $users = new FakeUserRepository();
        $users->create([
            'name' => 'Resend Owner',
            'email' => 'resend-owner@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
        ]);
        [$controller, $transport, $session] = $this->controller($users);

        $response = $controller->resendVerification(Request::create(
            'POST',
            '/verify-email/resend',
            input: ['email' => ' RESEND-OWNER@example.test '],
            server: ['REMOTE_ADDR' => '192.0.2.71'],
        ));

        $this->assertSame('/verify-email/resend', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('resend-owner@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame('Verify your OEMS email', $transport->messages[0]->subject);
        $this->assertSame(
            'If the address needs verification, a new link is on its way. Use the newest email you receive.',
            $session->get('_flash.success'),
        );
        $this->assertNull($session->get('_flash.development_link'));
    }

    public function testUnknownVerificationResendUsesThePrivacySinkAndTheSameBrowserCopy(): void
    {
        [$controller, $transport, $session] = $this->controller();

        $response = $controller->resendVerification(Request::create(
            'POST',
            '/verify-email/resend',
            input: ['email' => 'unknown@example.test'],
            server: ['REMOTE_ADDR' => '192.0.2.72'],
        ));

        $this->assertSame('/verify-email/resend', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('privacy-sink@example.test', $transport->messages[0]->recipientEmail);
        $this->assertNotSame('unknown@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame(
            'If the address needs verification, a new link is on its way. Use the newest email you receive.',
            $session->get('_flash.success'),
        );
    }

    public function testVerificationResendRejectsNonScalarEmailWithoutEmittingAWarning(): void
    {
        [$controller, $transport, $session] = $this->controller();
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $response = $controller->resendVerification(Request::create(
                'POST',
                '/verify-email/resend',
                input: ['email' => ['crafted@example.test']],
                server: ['REMOTE_ADDR' => '192.0.2.73'],
            ));
        } finally {
            restore_error_handler();
        }

        $this->assertSame('/verify-email/resend', $response->header('Location'));
        $this->assertSame(0, count($transport->messages));
        $this->assertArrayHasKey('email', $session->get('_flash.errors', []));
    }

    public function testInvalidVerificationLinkRecoversAtTheResendPage(): void
    {
        [$controller] = $this->controller();

        $request = Request::create('GET', '/verify-email/invalid')
            ->withRouteParameters(['token' => 'invalid']);
        $response = $controller->verifyEmail($request);

        $this->assertSame('/verify-email/resend', $response->header('Location'));
    }

    public function testLoginPreservesOnlyAllowListedPublicReturnDestinationsThroughTheWholeFlow(): void
    {
        $users = new FakeUserRepository();
        $users->create([
            'name' => 'Returning Participant',
            'email' => 'returning@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ]);
        [$controller] = $this->controller($users);

        $form = $controller->showLogin(Request::create('GET', '/login', query: [
            'return_to' => '/events/future-craft',
        ]));
        $validationFailure = $controller->login(Request::create('POST', '/login', input: [
            'email' => 'returning@example.test',
            'password' => '',
            'return_to' => '/events/future-craft',
        ]));
        $success = $controller->login(Request::create('POST', '/login', input: [
            'email' => 'returning@example.test',
            'password' => 'DemoPass!2026',
            'return_to' => '/events/future-craft',
        ]));

        $this->assertTrue(str_contains($form->body(), 'name="return_to" value="/events/future-craft"'));
        $this->assertSame('/login?return_to=%2Fevents%2Ffuture-craft', $validationFailure->header('Location'));
        $this->assertSame('/events/future-craft', $success->header('Location'));
    }

    public function testLoginRejectsExternalProtocolRelativeQueryAndFragmentReturnDestinations(): void
    {
        foreach ([
            'https://evil.example/login',
            '//evil.example/login',
            '/events/future-craft?next=evil',
            '/events/future-craft#fragment',
        ] as $candidate) {
            $users = new FakeUserRepository();
            $users->create([
                'name' => 'Safe Participant',
                'email' => 'safe@example.test',
                'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
                'role_id' => 3,
                'status' => 'active',
                'email_verified_at' => '2026-08-01 09:00:00',
            ]);
            [$controller] = $this->controller($users);

            $form = $controller->showLogin(Request::create('GET', '/login', query: ['return_to' => $candidate]));
            $success = $controller->login(Request::create('POST', '/login', input: [
                'email' => 'safe@example.test',
                'password' => 'DemoPass!2026',
                'return_to' => $candidate,
            ]));

            $this->assertFalse(str_contains($form->body(), 'name="return_to"'));
            $this->assertSame('/dashboard', $success->header('Location'));
        }
    }

    public function testSuccessfulLoginRotatesThePreAuthenticationCsrfToken(): void
    {
        $users = new FakeUserRepository();
        $users->create([
            'name' => 'Token Rotation Participant',
            'email' => 'token-rotation@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ]);
        [$controller] = $this->controller($users);
        $security = new Security(new Session(false));
        $preAuthenticationToken = $security->csrfToken();

        $controller->login(Request::create('POST', '/login', input: [
            'email' => 'token-rotation@example.test',
            'password' => 'DemoPass!2026',
        ]));

        $this->assertFalse($security->verifyCsrf($preAuthenticationToken));
        $this->assertNotSame($preAuthenticationToken, $security->csrfToken());
    }

    public function testRememberCookieCanBeForcedSecureBehindTlsTermination(): void
    {
        $users = new FakeUserRepository();
        $users->create([
            'name' => 'Secure Cookie Participant',
            'email' => 'secure-cookie@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'role_id' => 3,
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ]);
        [$controller] = $this->controller($users, config: ['secure_cookies' => true]);

        $response = $controller->login(Request::create('POST', '/login', input: [
            'email' => 'secure-cookie@example.test',
            'password' => 'DemoPass!2026',
            'remember' => '1',
        ]));

        $cookie = (string) $response->header('Set-Cookie');
        $this->assertTrue(str_contains($cookie, '; Secure'));
        $this->assertTrue(str_contains($cookie, '; HttpOnly'));
        $this->assertTrue(str_contains($cookie, '; SameSite=Lax'));
    }

    private function controller(
        ?FakeUserRepository $users = null,
        ?RateLimiter $rateLimiter = null,
        array $config = [],
    ): array
    {
        $users ??= new FakeUserRepository();
        $session = new Session(false);
        $config = new Config(array_merge([
            'name' => 'OEMS',
            'url' => 'http://localhost:8000',
            'debug' => true,
            'remember_cookie' => 'OEMS_REMEMBER',
            'mail' => [
                'privacy_sink_address' => 'privacy-sink@example.test',
                'from_address' => 'no-reply@example.test',
                'from_name' => 'OEMS',
            ],
        ], $config));
        $transport = new FakeMailTransport('<mailtrap-message-id>');
        $accountMailer = new AccountMailer($transport, new FakeEmailLogRepository(), $config);
        $auth = new Auth($session, $users);
        $controller = new AuthController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            $auth,
            $config,
            new AuthService($users, $session, $rateLimiter),
            $accountMailer,
        );

        return [$controller, $transport, $session];
    }
}
