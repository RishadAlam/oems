<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class CmsFeatureSurfaceTest extends TestCase
{
    public function testRoutesExposeFixedPublicPagesAndProtectedPostOnlyAdminWrites(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertTrue(is_string($routes));
        foreach (['/about', '/contact', '/privacy', '/terms', '/faq'] as $path) $this->assertTrue(str_contains($routes, "get('{$path}'"));
        foreach (['/admin/settings', '/admin/cms/pages/{slug}', '/admin/cms/faqs', '/admin/cms/banners'] as $path) $this->assertTrue(str_contains($routes, $path));
        $this->assertTrue(str_contains($routes, "['role:super-admin', 'csrf']"));
        $this->assertFalse(str_contains($routes, "delete('/admin/cms"));
    }

    public function testLayoutsAndHomeConsumePublicContentWithoutSecretFieldNames(): void
    {
        $surfaces = file_get_contents(base_path('app/Views/layouts/public.php'))
            . file_get_contents(base_path('app/Views/layouts/auth.php'))
            . file_get_contents(base_path('app/Views/layouts/dashboard.php'))
            . file_get_contents(base_path('app/Views/home/index.php'));
        foreach (['site_name', 'footer_blurb', 'footer_location', 'home_hero_kicker', 'home_hero_title', 'home_hero_copy'] as $key) $this->assertTrue(str_contains($surfaces, $key));
        foreach (['smtp_password', 'mail_driver', 'maintenance_mode', 'trusted_proxies'] as $secret) $this->assertFalse(str_contains($surfaces, $secret));
        $this->assertTrue(str_contains($surfaces, 'href="/about"'));
        $this->assertTrue(str_contains($surfaces, 'href="/privacy"'));
        $this->assertTrue(str_contains($surfaces, 'href="/terms"'));
        $this->assertTrue(str_contains($surfaces, 'href="/faq"'));
    }

    public function testFreshAndForwardSchemaProvideCmsIndexesAndPlainTextDefaults(): void
    {
        $schema = file_get_contents(base_path('database/schema.sql'));
        $migration = file_get_contents(base_path('database/migrations/2026-08-10-spec-completion.sql'));
        $seed = file_get_contents(base_path('database/seed.sql'));
        foreach (['idx_pages_status_published', 'idx_faqs_active_sort', 'idx_banners_home_schedule'] as $index) {
            $this->assertTrue(str_contains((string) $schema, $index));
            $this->assertTrue(str_contains((string) $migration, $index));
        }
        foreach (['contact_email', 'support_phone', 'footer_blurb', 'footer_location', 'home_hero_kicker', 'home_hero_title', 'home_hero_copy', 'default_seo_description'] as $key) $this->assertTrue(str_contains((string) $seed, "'{$key}'"));
        $this->assertTrue(str_contains((string) $seed, "'Contact', 'contact'"));
        $this->assertFalse(str_contains((string) $seed, "'<p>"));
        $this->assertTrue(str_contains((string) $migration, "content = '<p>OEMS connects"));
    }
}
