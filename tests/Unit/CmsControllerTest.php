<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminCmsController;
use OEMS\App\Controllers\AdminSettingsController;
use OEMS\App\Controllers\PublicContentController;
use OEMS\App\Services\CmsService;
use OEMS\App\Services\ImageUploadService;
use OEMS\App\Services\PlatformSettingsService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCmsRepository;
use OEMS\Tests\Support\FakePlatformSettingsRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class CmsControllerTest extends TestCase
{
    private Session $session;
    private Security $security;
    private Auth $auth;
    private FakeCmsRepository $cms;
    private string $uploadRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/cms';
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $users = new FakeUserRepository();
        $users->users[99] = ['id' => 99, 'role_id' => 1, 'name' => 'CMS Administrator', 'email' => 'admin@example.test', 'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT), 'status' => 'active', 'email_verified_at' => '2026-08-10 10:00:00'];
        $this->authenticateSession($this->session, $users, 99);
        $this->auth = new Auth($this->session, $users);
        $this->cms = new FakeCmsRepository();
        $this->uploadRoot = sys_get_temp_dir() . '/oems-controller-cms-' . bin2hex(random_bytes(5));
        mkdir($this->uploadRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadRoot . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
        if (is_dir($this->uploadRoot)) rmdir($this->uploadRoot);
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testPublicFixedPageRequiresPublicationEscapesContentAndSetsMetadata(): void
    {
        $this->cms->pages['about']['content'] = "A trusted platform.\n\n<script>alert(1)</script>";
        $controller = new PublicContentController($this->view(), $this->session, $this->security, $this->auth, $this->config(), $this->cms);

        $about = $controller->about(Request::create('GET', '/about'));
        $contact = $controller->contact(Request::create('GET', '/contact'));

        $this->assertSame(200, $about->status());
        $this->assertTrue(str_contains($about->body(), '<p>A trusted platform.</p>'));
        $this->assertTrue(str_contains($about->body(), '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertFalse(str_contains($about->body(), '<script>alert(1)</script>'));
        $this->assertSame(404, $contact->status());
    }

    public function testPublicFaqIncludesOnlyActiveItemsUsingNativeDisclosures(): void
    {
        $this->cms->faqs[2] = ['id' => 2, 'question' => 'Hidden?', 'answer' => 'No.', 'category' => null, 'sort_order' => 1, 'is_active' => 0];
        $controller = new PublicContentController($this->view(), $this->session, $this->security, $this->auth, $this->config(), $this->cms);

        $response = $controller->faq(Request::create('GET', '/faq'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), '<details'));
        $this->assertTrue(str_contains($response->body(), 'How do tickets work?'));
        $this->assertFalse(str_contains($response->body(), 'Hidden?'));
    }

    public function testAdminSettingsRejectsArraysAndFlashesOnlyFixedFields(): void
    {
        $repository = new FakePlatformSettingsRepository();
        $controller = new AdminSettingsController($this->view(), $this->session, $this->security, $this->auth, $this->config(), new PlatformSettingsService($repository));
        $response = $controller->update(Request::create('POST', '/admin/settings', input: [
            'site_name' => ['unsafe'], 'contact_email' => 'invalid', 'smtp_password' => 'secret',
        ]));
        $old = $this->session->get('_flash.old', []);

        $this->assertSame('/admin/settings', $response->header('Location'));
        $this->assertArrayHasKey('site_name', $this->session->get('_flash.errors', []));
        $this->assertFalse(array_key_exists('site_name', $old));
        $this->assertFalse(array_key_exists('smtp_password', $old));
    }

    public function testAdminCmsRejectsMalformedIdsAndUnknownSlugsWithoutMutation(): void
    {
        $controller = new AdminCmsController($this->view(), $this->session, $this->security, $this->auth, $this->config(), $this->cms, $this->cmsService());

        $faq = $controller->editFaq($this->routed('GET', '/admin/cms/faqs/0/edit', ['id' => '0']));
        $banner = $controller->updateBanner($this->routed('POST', '/admin/cms/banners/nope', ['id' => 'nope']));
        $page = $controller->editPage($this->routed('GET', '/admin/cms/pages/new-page', ['slug' => 'new-page']));

        $this->assertSame(404, $faq->status());
        $this->assertSame(404, $banner->status());
        $this->assertSame(404, $page->status());
        $this->assertSame(1, count($this->cms->faqs));
        $this->assertSame(1, count($this->cms->banners));
    }

    private function cmsService(): CmsService
    {
        return new CmsService($this->cms, new ImageUploadService($this->uploadRoot, '/uploads/banners', requireHttpUpload: false));
    }
    private function view(): View { return new View(base_path('app/Views')); }
    private function config(): Config { return new Config(['name' => 'OEMS', 'url' => 'http://localhost:8000']); }
    private function routed(string $method, string $uri, array $params): Request { return Request::create($method, $uri)->withRouteParameters($params); }
}
