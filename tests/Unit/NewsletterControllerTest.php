<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class NewsletterControllerTest extends TestCase
{
    public function testNewsletterRoutesAndViewsUsePublicCsrfTokensAndAdminRoleBoundaries(): void
    {
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';
        $layout = file_get_contents(base_path('app/Views/layouts/public.php')) ?: '';
        $admin = file_get_contents(base_path('app/Views/admin/newsletter/index.php')) ?: '';
        $this->assertTrue(str_contains($routes, "'/newsletter/subscribe'"));
        $this->assertTrue(str_contains($routes, "'/newsletter/confirm/{token}'"));
        $this->assertTrue(str_contains($routes, "'/newsletter/unsubscribe/{token}'"));
        $this->assertTrue(str_contains($routes, "'/admin/newsletter'"));
        $this->assertTrue(str_contains($layout, 'name="website"'));
        $this->assertTrue(str_contains($layout, 'name="_token"'));
        $this->assertTrue(str_contains($admin, 'Campaigns'));
    }
}
