<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ProfileController;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeProfileRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ProfileControllerTest extends TestCase
{
    private Session $session;

    private FakeProfileRepository $profiles;

    private ProfileController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/profile';
        $this->session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Nusrat Jahan',
            'email' => 'nusrat@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-06 10:00:00',
        ];
        $this->session->put('auth.user_id', 7);
        $this->profiles = new FakeProfileRepository();
        $this->profiles->profiles[7] = $this->profileFixture();
        $auth = new Auth($this->session, $users);
        $config = new Config([
            'name' => 'OEMS',
            'debug' => true,
            'remember_cookie' => 'OEMS_REMEMBER',
        ]);
        $this->controller = new ProfileController(
            new View(base_path('app/Views')),
            $this->session,
            new Security($this->session),
            $auth,
            $config,
            $this->profiles,
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testEditRendersTheAuthenticatedUsersProfile(): void
    {
        $response = $this->controller->edit(Request::create('GET', '/profile'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Nusrat Jahan'));
        $this->assertTrue(str_contains($response->body(), 'nusrat@example.test'));
        $this->assertTrue(str_contains($response->body(), 'dashboard-nav-link--active'));
    }

    public function testUpdateRejectsAMissingNameWithoutPersisting(): void
    {
        $input = $this->validInput();
        $input['name'] = '   ';

        $response = $this->controller->update(Request::create('POST', '/profile', input: $input));

        $errors = $this->session->get('_flash.errors', []);
        $this->assertSame('/profile', $response->header('Location'));
        $this->assertArrayHasKey('name', $errors);
        $this->assertSame([], $this->profiles->updates);
    }

    public function testUpdateUsesTheAuthenticatedIdAndNormalizesOptionalValues(): void
    {
        $input = $this->validInput();
        $input['user_id'] = '8';
        $input['name'] = '  Nusrat Sultana  ';
        $input['phone'] = '   ';

        $response = $this->controller->update(Request::create('POST', '/profile', input: $input));

        $this->assertSame('/profile', $response->header('Location'));
        $this->assertArrayHasKey(7, $this->profiles->updates);
        $this->assertFalse(array_key_exists(8, $this->profiles->updates));
        $this->assertSame('Nusrat Sultana', $this->profiles->updates[7]['name']);
        $this->assertNull($this->profiles->updates[7]['phone']);
        $this->assertSame('Profile updated successfully.', $this->session->get('_flash.success'));
    }

    private function profileFixture(): array
    {
        return [
            'id' => 7,
            'name' => 'Nusrat Jahan',
            'email' => 'nusrat@example.test',
            'phone' => null,
            'status' => 'active',
            'role_name' => 'Participant',
            'role_slug' => 'participant',
            'bio' => 'Community event volunteer.',
            'date_of_birth' => '1997-04-13',
            'gender' => 'female',
            'address_line' => 'Dhanmondi 8A',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1209',
            'website' => 'https://example.test/nusrat',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ];
    }

    private function validInput(): array
    {
        return [
            'name' => 'Nusrat Sultana',
            'phone' => '+8801712345678',
            'bio' => 'Community event volunteer.',
            'date_of_birth' => '1997-04-13',
            'gender' => 'female',
            'address_line' => 'Dhanmondi 8A',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1209',
            'website' => 'https://example.test/nusrat',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ];
    }
}
