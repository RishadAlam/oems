<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AuthController;
use OEMS\App\Services\AccountMailer;
use OEMS\App\Services\AuthService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
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
        [$controller, $transport] = $this->controller();

        $response = $controller->register(Request::create('POST', '/register', input: [
            'name' => 'Maliha Rahman',
            'email' => 'maliha@example.test',
            'password' => 'DemoPass!2026',
            'password_confirmation' => 'DemoPass!2026',
            'role' => 'participant',
            'terms' => '1',
        ]));

        $this->assertSame('/login', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('maliha@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame('Verify your OEMS email', $transport->messages[0]->subject);
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
        [$controller, $transport] = $this->controller($users);

        $response = $controller->sendResetLink(Request::create('POST', '/forgot-password', input: [
            'email' => 'raihan@example.test',
        ]));

        $this->assertSame('/forgot-password', $response->header('Location'));
        $this->assertSame(1, count($transport->messages));
        $this->assertSame('raihan@example.test', $transport->messages[0]->recipientEmail);
        $this->assertSame('Reset your OEMS password', $transport->messages[0]->subject);
    }

    public function testUnknownPasswordResetAddressDoesNotSendAMessage(): void
    {
        [$controller, $transport] = $this->controller();

        $response = $controller->sendResetLink(Request::create('POST', '/forgot-password', input: [
            'email' => 'unknown@example.test',
        ]));

        $this->assertSame('/forgot-password', $response->header('Location'));
        $this->assertSame(0, count($transport->messages));
    }

    private function controller(?FakeUserRepository $users = null): array
    {
        $users ??= new FakeUserRepository();
        $session = new Session(false);
        $config = new Config([
            'name' => 'OEMS',
            'url' => 'http://localhost:8000',
            'debug' => true,
            'remember_cookie' => 'OEMS_REMEMBER',
        ]);
        $transport = new FakeMailTransport('<mailtrap-message-id>');
        $accountMailer = new AccountMailer($transport, new FakeEmailLogRepository(), $config);
        $auth = new Auth($session, $users);
        $controller = new AuthController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            $auth,
            $config,
            new AuthService($users, $session),
            $accountMailer,
        );

        return [$controller, $transport];
    }
}
