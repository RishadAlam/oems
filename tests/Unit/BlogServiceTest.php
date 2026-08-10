<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\BlogRepository;
use OEMS\App\Services\BlogService;
use OEMS\App\Services\ImageUploadService;
use OEMS\Tests\Support\TestCase;
use OEMS\Tests\Support\TestImage;
use PDO;

final class BlogServiceTest extends TestCase
{
    private PDO $connection;

    private string $uploadRoot;

    private BlogService $service;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)');
        $this->connection->exec("CREATE TABLE blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT, author_id INTEGER, title TEXT, slug TEXT UNIQUE,
            excerpt TEXT, body TEXT, category TEXT, cover_image TEXT, status TEXT DEFAULT 'draft',
            meta_title TEXT, meta_description TEXT, published_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT
        )");
        $this->connection->exec("INSERT INTO users VALUES (7, 'Admin Editor', NULL)");
        $this->uploadRoot = sys_get_temp_dir() . '/oems-blog-' . bin2hex(random_bytes(6));
        mkdir($this->uploadRoot, 0775, true);
        $this->service = new BlogService(
            new BlogRepository($this->connection),
            new ImageUploadService($this->uploadRoot, '/uploads/blog', requireHttpUpload: false),
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadRoot . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->uploadRoot);
    }

    public function testValidationRejectsNestedHtmlAndUnboundedPlainTextBeforeUpload(): void
    {
        $result = $this->service->create(7, [
            'title' => ['nested'], 'slug' => 'unsafe slug', 'excerpt' => '<b>HTML</b>',
            'body' => '<script>alert(1)</script>', 'category' => str_repeat('x', 101),
            'meta_title' => '', 'meta_description' => '',
        ], $this->upload('unused.jpg'));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('title', $result['errors']);
        $this->assertArrayHasKey('excerpt', $result['errors']);
        $this->assertArrayHasKey('body', $result['errors']);
        $this->assertArrayHasKey('category', $result['errors']);
        $this->assertSame([], glob($this->uploadRoot . '/*') ?: []);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn());
    }

    public function testDraftCreateNormalizesSlugStoresSafeImageAndStaysPrivateUntilPublished(): void
    {
        $created = $this->service->create(7, $this->validInput(['slug' => '  Build Better Communities  ']), $this->upload('cover.jpg'));

        $this->assertTrue($created['success']);
        $this->assertSame('build-better-communities', $created['post']['slug']);
        $this->assertSame('draft', $created['post']['status']);
        $this->assertTrue(str_starts_with((string) $created['post']['cover_image'], '/uploads/blog/'));
        $this->assertSame(1, count(glob($this->uploadRoot . '/*') ?: []));
        $this->assertNull($this->service->publicDetail('build-better-communities'));

        $published = $this->service->transition((int) $created['post']['id'], 'published', (string) $created['post']['updated_at']);
        $this->assertTrue($published['success']);
        $this->assertSame('published', $published['post']['status']);
        $this->assertNotNull($published['post']['published_at']);
        $this->assertNotNull($this->service->publicDetail('build-better-communities'));

        $repeat = $this->service->transition((int) $created['post']['id'], 'published', (string) $published['post']['updated_at']);
        $this->assertTrue($repeat['success']);
        $this->assertSame($published['post']['published_at'], $repeat['post']['published_at']);
    }

    public function testDuplicateSlugAndStaleUpdateFailClosedWhileSuccessfulReplacementCleansOldImage(): void
    {
        $first = $this->service->create(7, $this->validInput(['slug' => 'community-notes']), $this->upload('first.jpg'));
        $duplicate = $this->service->create(7, $this->validInput(['slug' => 'community-notes']), null);
        $this->assertTrue($first['success']);
        $this->assertFalse($duplicate['success']);
        $this->assertArrayHasKey('slug', $duplicate['errors']);
        $oldPath = $this->uploadRoot . '/' . basename((string) $first['post']['cover_image']);
        $this->assertTrue(is_file($oldPath));

        $stale = $this->service->update((int) $first['post']['id'], $this->validInput([
            'title' => 'Stale update', 'slug' => 'community-notes', 'updated_at' => '2000-01-01 00:00:00',
        ]), $this->upload('stale.jpg'));
        $this->assertFalse($stale['success']);
        $this->assertArrayHasKey('post', $stale['errors']);
        $this->assertTrue(is_file($oldPath));
        $this->assertSame(1, count(glob($this->uploadRoot . '/*') ?: []));

        $updated = $this->service->update((int) $first['post']['id'], $this->validInput([
            'title' => 'Updated community notes', 'slug' => 'community-notes', 'updated_at' => (string) $first['post']['updated_at'],
        ]), $this->upload('replacement.jpg'));
        $this->assertTrue($updated['success']);
        $this->assertFalse(is_file($oldPath));
        $this->assertSame(1, count(glob($this->uploadRoot . '/*') ?: []));
    }

    public function testPublicPaginationReadingTimeUnpublishAndSoftDeleteAreTruthful(): void
    {
        $created = $this->service->create(7, $this->validInput([
            'slug' => 'public-story', 'body' => implode(' ', array_fill(0, 450, 'community')),
        ]), null);
        $published = $this->service->transition((int) $created['post']['id'], 'published', (string) $created['post']['updated_at']);
        $listing = $this->service->publicIndex(['page' => '1', 'category' => 'Community']);

        $this->assertSame(1, $listing['pagination']['total']);
        $this->assertSame(2, $listing['posts'][0]['reading_minutes']);
        $this->assertFalse(array_key_exists('author_id', $listing['posts'][0]));
        $this->assertFalse(array_key_exists('deleted_at', $listing['posts'][0]));

        $unpublished = $this->service->transition((int) $created['post']['id'], 'draft', (string) $published['post']['updated_at']);
        $this->assertTrue($unpublished['success']);
        $this->assertNull($unpublished['post']['published_at']);
        $this->assertSame(0, $this->service->publicIndex([])['pagination']['total']);

        $deleted = $this->service->delete((int) $created['post']['id'], (string) $unpublished['post']['updated_at']);
        $this->assertTrue($deleted['success']);
        $this->assertNull($this->service->adminDetail((int) $created['post']['id']));
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'How to build better event communities',
            'slug' => '',
            'excerpt' => 'Practical notes for turning a single gathering into a thoughtful community rhythm.',
            'body' => "Start with a clear promise to attendees.\n\nFollow up with useful context and another reason to meet.",
            'category' => 'Community',
            'meta_title' => 'Build better event communities',
            'meta_description' => 'Practical notes for thoughtful event communities and useful follow-up.',
            'updated_at' => '',
        ], $overrides);
    }

    private function upload(string $name): array
    {
        $path = sys_get_temp_dir() . '/oems-blog-upload-' . bin2hex(random_bytes(6)) . '.jpg';
        TestImage::writeJpeg($path);

        return ['name' => $name, 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
    }
}
