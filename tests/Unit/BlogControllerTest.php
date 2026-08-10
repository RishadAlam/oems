<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminBlogController;
use OEMS\App\Controllers\PublicBlogController;
use OEMS\App\Repositories\BlogRepository;
use OEMS\App\Services\BlogService;
use OEMS\App\Services\ImageUploadService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use RuntimeException;

final class BlogControllerTest extends TestCase
{
    private mixed $adminController = null;

    private mixed $publicController = null;

    private BlogService $service;

    private PDO $connection;

    private string $uploadRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)');
        $this->connection->exec("CREATE TABLE blog_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, author_id INTEGER, title TEXT, slug TEXT UNIQUE, excerpt TEXT, body TEXT, category TEXT, cover_image TEXT, status TEXT DEFAULT 'draft', meta_title TEXT, meta_description TEXT, published_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT)");
        $this->connection->exec("INSERT INTO users VALUES (7, 'Admin Editor', NULL)");
        $this->uploadRoot = sys_get_temp_dir() . '/oems-blog-controller-' . bin2hex(random_bytes(6));
        mkdir($this->uploadRoot, 0775, true);
        $this->service = new BlogService(new BlogRepository($this->connection), new ImageUploadService($this->uploadRoot, '/uploads/blog', requireHttpUpload: false));
        $published = $this->service->create(7, $this->post('Published story', 'published-story'), null);
        $this->service->transition((int) $published['post']['id'], 'published', (string) $published['post']['updated_at']);
        $draft = $this->service->create(7, $this->post('Draft story', 'draft-story'), null);
        $this->connection->exec("UPDATE blog_posts SET body = '<script>never render</script> is stored as text.' WHERE slug = 'published-story'");
        $this->connection->exec("UPDATE blog_posts SET meta_title = 'Published story | OEMS' WHERE slug = 'published-story'");
        $this->connection->exec("UPDATE blog_posts SET cover_image = '/assets/images/event-community.webp' WHERE slug = 'published-story'");
        $this->connection->exec("UPDATE blog_posts SET title = 'Draft <Story>' WHERE id = " . (int) $draft['post']['id']);

        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7, 'role_id' => 1, 'role_slug' => 'super-admin', 'role_name' => 'Super Administrator',
            'name' => 'Admin Editor', 'email' => 'admin@example.test',
            'password' => password_hash('secret-password', PASSWORD_DEFAULT), 'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00', 'deleted_at' => null,
        ];
        $this->authenticateSession($session, $users, 7);
        $dependencies = [new View(base_path('app/Views')), $session, new Security($session), new Auth($session, $users), new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka'])];
        if (class_exists(AdminBlogController::class)) {
            $this->adminController = new AdminBlogController(...$dependencies, blog: $this->service);
        }
        if (class_exists(PublicBlogController::class)) {
            $this->publicController = new PublicBlogController(...$dependencies, blog: $this->service);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadRoot . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->uploadRoot);
        $_SESSION = [];
    }

    public function testPublicIndexAndDetailExposeSeoEscapedPlainTextAndNoDrafts(): void
    {
        $index = $this->public()->index(Request::create('GET', '/blog'));
        $detail = $this->public()->show(Request::create('GET', '/blog/published-story')->withRouteParameters(['slug' => 'published-story']));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), 'Published story'));
        $this->assertFalse(str_contains($index->body(), 'Draft &lt;Story&gt;'));
        $this->assertTrue(str_contains($index->body(), '<link rel="canonical" href="https://events.example.test/blog">'));
        $this->assertTrue(str_contains($index->body(), 'og:type'));
        $this->assertTrue(str_contains($index->body(), 'min read'));
        $this->assertTrue(str_contains($index->body(), '<time datetime='));
        $this->assertTrue(str_contains($index->body(), 'width="640" height="360" loading="eager"'));
        $this->assertSame(200, $detail->status());
        $this->assertTrue(str_contains($detail->body(), '<link rel="canonical" href="https://events.example.test/blog/published-story">'));
        $this->assertTrue(str_contains($detail->body(), '<title>Published story | OEMS</title>'));
        $this->assertFalse(str_contains($detail->body(), 'OEMS | OEMS'));
        $this->assertTrue(str_contains($detail->body(), '&lt;script&gt;never render&lt;/script&gt;'));
        $this->assertFalse(str_contains($detail->body(), '<script>never render</script>'));
        $this->assertSame(404, $this->public()->show(Request::create('GET', '/blog/draft-story')->withRouteParameters(['slug' => 'draft-story']))->status());
    }

    public function testAdminListFormsPreviewAndLifecycleActionsAreExplicit(): void
    {
        $index = $this->admin()->index(Request::create('GET', '/admin/blog'));
        $create = $this->admin()->create(Request::create('GET', '/admin/blog/create'));
        $draft = $this->service->adminIndex(['status' => 'draft'])['posts'][0];
        $preview = $this->admin()->preview(Request::create('GET', '/admin/blog/' . $draft['id'] . '/preview')->withRouteParameters(['id' => (string) $draft['id']]));

        $this->assertTrue(str_contains($index->body(), 'Published story'));
        $this->assertTrue(str_contains($index->body(), 'Draft &lt;Story&gt;'));
        $this->assertTrue(str_contains($index->body(), 'Publish'));
        $this->assertTrue(str_contains($index->body(), 'class="operations-table organizer-table"'));
        $this->assertTrue(str_contains($index->body(), '<caption class="sr-only">Managed Blog posts</caption>'));
        $this->assertTrue(str_contains($create->body(), 'enctype="multipart/form-data"'));
        $this->assertTrue(str_contains($create->body(), 'aria-describedby="body-help'));
        $this->assertSame(200, $preview->status());
        $this->assertSame('noindex, nofollow', $preview->header('X-Robots-Tag'));
        $this->assertSame('private, no-store, max-age=0', $preview->header('Cache-Control'));
        $this->assertTrue(str_contains($preview->body(), 'Draft preview'));
        $this->assertFalse(str_contains($preview->body(), '<script>'));
    }

    public function testInvalidAdminFiltersAndIdsFailClosedWithoutPublicWidening(): void
    {
        $invalid = $this->admin()->index(Request::create('GET', '/admin/blog?status=unknown', query: ['status' => 'unknown']));
        $missing = $this->admin()->edit(Request::create('GET', '/admin/blog/999/edit')->withRouteParameters(['id' => '999']));
        $badPublic = $this->public()->index(Request::create('GET', '/blog?unknown=x', query: ['unknown' => 'x']));

        $this->assertSame(422, $invalid->status());
        $this->assertFalse(str_contains($invalid->body(), 'Published story'));
        $this->assertSame(404, $missing->status());
        $this->assertSame(422, $badPublic->status());
        $this->assertFalse(str_contains($badPublic->body(), 'Published story'));
    }

    public function testRoutesAndNavigationProtectAllBlogWrites(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));
        $publicLayout = (string) file_get_contents(base_path('app/Views/layouts/public.php'));
        $dashboardLayout = (string) file_get_contents(base_path('app/Views/layouts/dashboard.php'));
        $styles = (string) file_get_contents(base_path('resources/css/app.css'));

        $this->assertTrue(str_contains($routes, "'/blog'"));
        $this->assertTrue(str_contains($routes, "'/blog/{slug}'"));
        $this->assertTrue(str_contains($routes, "[AdminBlogController::class, 'store'], ['role:super-admin', 'csrf']"));
        $this->assertTrue(str_contains($routes, "[AdminBlogController::class, 'publish'], ['role:super-admin', 'csrf']"));
        $this->assertTrue(str_contains($routes, "[AdminBlogController::class, 'delete'], ['role:super-admin', 'csrf']"));
        $this->assertTrue(str_contains($publicLayout, 'href="/blog"'));
        $this->assertTrue(str_contains($dashboardLayout, 'href="/admin/blog"'));
        $this->assertTrue(str_contains($styles, '.filter-chip'));
        $this->assertTrue(str_contains($styles, '.filter-chip--active'));
    }

    public function testPublicAndAdminPaginationPreserveActiveFilters(): void
    {
        $statement = $this->connection->prepare(
            "INSERT INTO blog_posts (author_id, title, slug, excerpt, body, category, status, published_at) VALUES (7, :title, :slug, 'Useful event guidance for every community.', 'A first paragraph.\n\nA second paragraph.', :category, :status, CURRENT_TIMESTAMP)"
        );
        for ($index = 1; $index <= 42; $index++) {
            $statement->execute([
                'title' => 'Paging story ' . $index,
                'slug' => 'paging-story-' . $index,
                'category' => 'Community',
                'status' => $index <= 21 ? 'published' : 'draft',
            ]);
        }

        $public = $this->public()->index(Request::create('GET', '/blog?category=Community', query: ['category' => 'Community']));
        $admin = $this->admin()->index(Request::create('GET', '/admin/blog?status=draft&search=Paging', query: ['status' => 'draft', 'search' => 'Paging']));

        $this->assertTrue(str_contains($public->body(), 'category=Community&amp;page=2'));
        $this->assertTrue(str_contains($admin->body(), 'status=draft&amp;search=Paging&amp;page=2'));
    }

    private function admin(): AdminBlogController
    {
        if (!$this->adminController instanceof AdminBlogController) {
            throw new RuntimeException('AdminBlogController is not implemented.');
        }

        return $this->adminController;
    }

    private function public(): PublicBlogController
    {
        if (!$this->publicController instanceof PublicBlogController) {
            throw new RuntimeException('PublicBlogController is not implemented.');
        }

        return $this->publicController;
    }

    private function post(string $title, string $slug): array
    {
        return [
            'title' => $title, 'slug' => $slug,
            'excerpt' => 'A practical editorial note for people who care about memorable events.',
            'body' => "First paragraph with useful context.\n\nSecond paragraph with practical next steps.",
            'category' => 'Community', 'meta_title' => $title,
            'meta_description' => 'A practical OEMS editorial note about memorable events.',
        ];
    }
}
