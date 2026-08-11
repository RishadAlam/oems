<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;

final class ContactControllerTest extends TestCase
{
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
