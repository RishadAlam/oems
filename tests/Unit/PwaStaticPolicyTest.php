<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\PublicFilePolicy;
use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;

final class PwaStaticPolicyTest extends TestCase
{
    public function testManifestUsesExplicitStableScopeAndLocalInstallIcons(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('public/manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('OEMS Event Management', $manifest['name'] ?? null);
        $this->assertSame('OEMS', $manifest['short_name'] ?? null);
        $this->assertSame('/', $manifest['start_url'] ?? null);
        $this->assertSame('/', $manifest['scope'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertSame('#f5f7fb', $manifest['background_color'] ?? null);
        $this->assertSame('#3157d5', $manifest['theme_color'] ?? null);
        $icons = $manifest['icons'] ?? [];
        $this->assertSame(['/assets/icons/oems-192.png', '/assets/icons/oems-512.png'], array_column($icons, 'src'));
        $this->assertSame(['192x192', '512x512'], array_column($icons, 'sizes'));
        foreach ($icons as $icon) {
            $path = base_path('public' . $icon['src']);
            $size = getimagesize($path);
            $this->assertSame('image/png', $size['mime'] ?? null);
            $this->assertSame((int) explode('x', $icon['sizes'])[0], $size[0] ?? null);
            $this->assertTrue(str_contains((string) ($icon['purpose'] ?? ''), 'maskable'));
        }
    }

    public function testOfflineShellAndPwaAssetsArePublicStaticAndContainNoPrivateState(): void
    {
        foreach (['/manifest.webmanifest', '/service-worker.js', '/offline.html', '/assets/js/pwa.js', '/assets/icons/oems-192.png', '/assets/icons/oems-512.png'] as $path) {
            $this->assertTrue(PublicFilePolicy::mayServe(base_path('public'), $path), $path . ' must be a readable public static asset.');
        }
        $offline = (string) file_get_contents(base_path('public/offline.html'));
        $this->assertTrue(str_contains($offline, '<html lang="en">'));
        $this->assertTrue(str_contains($offline, 'You are offline'));
        foreach (['csrf', 'participant', 'email', 'ticket', 'certificate', 'dashboard'] as $privateWord) {
            $this->assertFalse(str_contains(strtolower($offline), $privateWord));
        }
    }

    public function testLayoutsLoadManifestAndExternalRegistrationWithoutHidingServerContent(): void
    {
        $view = new View(base_path('app/Views'));
        $shared = ['app' => ['name' => 'OEMS'], 'currentUser' => null, 'csrfToken' => 'safe', 'flash' => [], 'errors' => [], 'old' => []];
        $public = $view->render('errors/404', $shared + ['pageTitle' => 'Not found'], 'public');
        $auth = $view->render('auth/login', $shared + ['pageTitle' => 'Sign in'], 'auth');
        $dashboard = $view->render('errors/404', $shared + [
            'pageTitle' => 'Workspace',
            'currentUser' => ['name' => 'Admin', 'email' => 'admin@example.test', 'role_slug' => 'super-admin', 'role_name' => 'Super Admin'],
        ], 'dashboard');

        foreach ([$public, $auth, $dashboard] as $html) {
            $this->assertTrue(str_contains($html, '<link rel="manifest" href="/manifest.webmanifest">'));
            $this->assertTrue(str_contains($html, '<link rel="stylesheet" href="/assets/css/app.css?v=20260811-form-controls-fix">'));
            $this->assertTrue(str_contains($html, '<script src="/assets/js/pwa.js?v=20260811-form-controls-fix" defer></script>'));
            $this->assertFalse(str_contains($html, 'navigator.serviceWorker'));
        }
        $this->assertTrue(str_contains($public, 'data-pwa-install'));
        $this->assertTrue(str_contains($public, 'hidden'));
        $this->assertTrue(str_contains($public, 'This page is not here.'));
    }

    public function testProductionNginxServesPwaTypesWithoutStaleImmutableAssets(): void
    {
        $nginx = (string) file_get_contents(base_path('deploy/nginx/oems.conf'));

        $this->assertTrue(str_contains($nginx, 'location = /service-worker.js'));
        $this->assertTrue(str_contains($nginx, 'default_type application/javascript'));
        $this->assertTrue(str_contains($nginx, 'location = /manifest.webmanifest'));
        $this->assertTrue(str_contains($nginx, 'default_type application/manifest+json'));
        $this->assertTrue(str_contains($nginx, 'Cache-Control "no-cache, no-store, must-revalidate"'));
        $this->assertFalse(str_contains($nginx, 'immutable'));
        $this->assertTrue(str_contains($nginx, 'X-Content-Type-Options "nosniff"'));
    }
}
