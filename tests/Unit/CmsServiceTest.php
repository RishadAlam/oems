<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\CmsService;
use OEMS\App\Services\ImageUploadService;
use OEMS\Tests\Support\FakeCmsRepository;
use OEMS\Tests\Support\TestCase;
use OEMS\Tests\Support\TestImage;

final class CmsServiceTest extends TestCase
{
    private string $uploadRoot;
    private FakeCmsRepository $repository;
    private CmsService $service;

    protected function setUp(): void
    {
        $this->uploadRoot = sys_get_temp_dir() . '/oems-cms-' . bin2hex(random_bytes(6));
        mkdir($this->uploadRoot, 0775, true);
        $this->repository = new FakeCmsRepository();
        $this->service = new CmsService(
            $this->repository,
            new ImageUploadService($this->uploadRoot, '/uploads/banners', requireHttpUpload: false),
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadRoot . '/*') ?: [] as $file) {
            if (is_file($file)) unlink($file);
        }
        if (is_dir($this->uploadRoot)) rmdir($this->uploadRoot);
    }

    public function testFixedPagesCannotBeCreatedRenamedOrUpdatedWithHtml(): void
    {
        $unknown = $this->service->updatePage('new-page', ['title' => 'New', 'content' => 'Text'], 7);
        $html = $this->service->updatePage('about', ['title' => 'About', 'content' => '<script>alert(1)</script>'], 7);
        $renamed = $this->service->updatePage('about', ['title' => 'About', 'slug' => 'renamed', 'content' => 'Text'], 7);

        $this->assertTrue($unknown['not_found']);
        $this->assertFalse($html['success']);
        $this->assertArrayHasKey('content', $html['errors']);
        $this->assertTrue($renamed['success']);
        $this->assertSame('about', $this->repository->pages['about']['slug']);
    }

    public function testPageUpdatesNormalizePlainTextMetadataAndPublishingIsExplicit(): void
    {
        $updated = $this->service->updatePage('contact', [
            'title' => '  Contact OEMS ',
            'content' => "  Email our team.\r\n\r\nWe reply within two working days.  ",
            'meta_title' => ' Contact the OEMS team ',
            'meta_description' => ' Public support for the OEMS community. ',
        ], 7);
        $published = $this->service->setPagePublished('contact', '1', 7);

        $this->assertTrue($updated['success']);
        $this->assertSame("Email our team.\n\nWe reply within two working days.", $this->repository->pages['contact']['content']);
        $this->assertTrue($published['success']);
        $this->assertSame('published', $this->repository->pages['contact']['status']);
        $this->assertFalse($this->service->setPagePublished('contact', 'toggle', 7)['success']);
    }

    public function testFaqManagementUsesBoundedPlainTextAndExplicitActivation(): void
    {
        $created = $this->service->createFaq([
            'question' => '  Can I cancel a registration? ',
            'answer' => ' Yes. Open your registration and follow the cancellation guidance. ',
            'category' => ' Registration ',
            'sort_order' => '20',
        ]);
        $id = (int) $created['faq_id'];
        $invalid = $this->service->updateFaq($id, ['question' => ['unsafe'], 'answer' => '<b>Unsafe</b>', 'sort_order' => '-1']);
        $inactive = $this->service->setFaqActive($id, '0');

        $this->assertTrue($created['success']);
        $this->assertSame('Can I cancel a registration?', $this->repository->faqs[$id]['question']);
        $this->assertFalse($invalid['success']);
        $this->assertArrayHasKey('question', $invalid['errors']);
        $this->assertArrayHasKey('answer', $invalid['errors']);
        $this->assertTrue($inactive['success']);
        $this->assertSame(0, $this->repository->faqs[$id]['is_active']);
    }

    public function testBannerRejectsExternalOrProtocolRelativeLinksAndInvalidScheduleBeforeUpload(): void
    {
        $external = $this->service->createBanner([
            'title' => 'Unsafe link', 'link_url' => 'https://example.com', 'sort_order' => '0',
        ], $this->upload('external.jpg'));
        $protocolRelative = $this->service->createBanner([
            'title' => 'Unsafe link', 'link_url' => '//example.com', 'sort_order' => '0',
        ], $this->upload('relative.jpg'));
        $schedule = $this->service->createBanner([
            'title' => 'Invalid schedule', 'starts_at' => '2026-08-12T10:00', 'ends_at' => '2026-08-11T10:00', 'sort_order' => '0',
        ], $this->upload('schedule.jpg'));

        $this->assertArrayHasKey('link_url', $external['errors']);
        $this->assertArrayHasKey('link_url', $protocolRelative['errors']);
        $this->assertArrayHasKey('ends_at', $schedule['errors']);
        $this->assertSame([], glob($this->uploadRoot . '/*') ?: []);
    }

    public function testBannerUploadIsCleanedOnPersistenceFailureAndOldImageOnlyAfterSuccess(): void
    {
        $this->repository->failWrite = true;
        $failed = $this->service->createBanner(['title' => 'Save failure', 'sort_order' => '0'], $this->upload('failed.jpg'));
        $this->assertFalse($failed['success']);
        $this->assertSame([], glob($this->uploadRoot . '/*') ?: []);

        $old = $this->uploadRoot . '/old.jpg';
        TestImage::writeJpeg($old);
        $this->repository->failWrite = false;
        $updated = $this->service->updateBanner(1, [
            'title' => 'Updated banner', 'subtitle' => 'Now open.', 'link_url' => '/events?category=community',
            'starts_at' => '', 'ends_at' => '', 'sort_order' => '3',
        ], $this->upload('new.jpg'));

        $this->assertTrue($updated['success']);
        $this->assertFalse(is_file($old));
        $this->assertTrue(str_starts_with($this->repository->banners[1]['image_path'], '/uploads/banners/'));
        $this->assertSame(1, count(glob($this->uploadRoot . '/*') ?: []));
    }

    public function testRepeatedStatusActionsAreTruthfulWithoutDependingOnChangedRows(): void
    {
        $this->repository->failWrite = true;

        $this->assertTrue($this->service->setPagePublished('about', '1', 7)['success']);
        $this->assertTrue($this->service->setFaqActive(1, '1')['success']);
        $this->assertTrue($this->service->setBannerActive(1, '1')['success']);
    }

    private function upload(string $name): array
    {
        $path = sys_get_temp_dir() . '/oems-upload-' . bin2hex(random_bytes(6)) . '.jpg';
        TestImage::writeJpeg($path);
        return ['name' => $name, 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
    }
}
