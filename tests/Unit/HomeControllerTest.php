<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\HomeController;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class HomeControllerTest extends TestCase
{
    private HomeController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $this->controller = new HomeController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config(['name' => 'OEMS']),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testEventSearchFiltersTheCuratedPreviews(): void
    {
        $response = $this->controller->events(Request::create('GET', '/events', ['search' => 'music']));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Rooftop sessions'));
        $this->assertFalse(str_contains($response->body(), 'Designing for public life'));
    }

    public function testEventSearchExplainsWhenNoPreviewMatches(): void
    {
        $response = $this->controller->events(Request::create('GET', '/events', ['search' => 'astronomy']));

        $this->assertTrue(str_contains($response->body(), 'No preview events match'));
        $this->assertTrue(str_contains($response->body(), 'Clear search'));
    }
}
