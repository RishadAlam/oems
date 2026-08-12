<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\PublicContactController;
use OEMS\App\Services\ContactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCmsRepository;
use OEMS\Tests\Support\FakeContactRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ContactControllerTest extends TestCase
{
    public function testContactPageRendersPublishedCmsCopyThroughTheController(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $cms = new FakeCmsRepository();
        $cms->pages['contact']['status'] = 'published';
        $cms->pages['contact']['content'] = 'Reach our support team without exposing private account details.';
        $controller = new PublicContactController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config(['name' => 'OEMS']),
            $cms,
            new ContactService(new FakeContactRepository()),
            new RateLimiter(sys_get_temp_dir() . '/oems-contact-' . bin2hex(random_bytes(5))),
        );

        $body = $controller->index(Request::create('GET', '/contact'))->body();

        $this->assertTrue(str_contains(
            $body,
            'Reach our support team without exposing private account details.',
        ));
        $this->assertFalse(str_contains($body, 'Undefined variable'));
    }

    public function testContactRoutesAndViewsProvidePublicCsrfRateLimitAndAdminEvidence(): void
    {
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';
        $public = (new View(base_path('app/Views')))->render('pages/contact', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'contact-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => ['email' => ['Enter a valid email address.']],
            'old' => ['email' => 'invalid'],
            'pageTitle' => 'Contact',
            'page' => ['title' => 'Contact OEMS'],
            'copy' => '',
        ], 'public');
        $admin = file_get_contents(base_path('app/Views/admin/contact/show.php')) ?: '';
        $this->assertTrue(str_contains($routes, "'/contact'"));
        $this->assertTrue(str_contains($routes, "'/contact/submit'"));
        $this->assertTrue(str_contains($routes, "['csrf']"));
        $this->assertTrue(str_contains($routes, "'/admin/contact'"));
        $this->assertTrue(str_contains($routes, "['role:super-admin', 'csrf']"));
        $this->assertTrue(str_contains($public, 'name="_token" value="contact-token"'));
        $this->assertTrue(str_contains($public, 'aria-describedby'));
        $this->assertTrue(str_contains($public, 'href="#contact-email"'));
        $this->assertTrue(str_contains($admin, 'Reply by email'));
    }
}
