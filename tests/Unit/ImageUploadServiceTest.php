<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\ImageUploadService;
use OEMS\Tests\Support\TestCase;

final class ImageUploadServiceTest extends TestCase
{
    private string $temporaryDirectory;

    private string $uploadRoot;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/oems-image-upload-' . bin2hex(random_bytes(6));
        $this->uploadRoot = $this->temporaryDirectory . '/public/uploads/events';
        mkdir($this->uploadRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testStoresAGeneratedJpegUnderTheConfiguredPublicPath(): void
    {
        $source = $this->generatedJpeg('valid.jpg');
        $service = $this->serviceForLocalFiles();

        $result = $service->store($this->upload($source, 'conference-banner.jpg', filesize($source)));

        $this->assertTrue($result['success']);
        $this->assertTrue(is_string($result['path']));
        $this->assertTrue(str_starts_with((string) $result['path'], '/uploads/events/'));
        $this->assertTrue(str_ends_with((string) $result['path'], '.jpg'));
        $this->assertTrue(is_file($this->uploadRoot . '/' . basename((string) $result['path'])));
        $this->assertFalse(is_file($source));

        $service->delete($result['path']);

        $this->assertFalse(is_file($this->uploadRoot . '/' . basename((string) $result['path'])));
    }

    public function testDefaultModeRejectsFilesThatDidNotComeFromAnHttpUpload(): void
    {
        $source = $this->generatedJpeg('local.jpg');
        $service = new ImageUploadService($this->uploadRoot);

        $result = $service->store($this->upload($source, 'local.jpg', filesize($source)));

        $this->assertFalse($result['success']);
        $this->assertTrue(is_file($source));
    }

    public function testRejectsTextDisguisedAsAJpegWithoutMovingIt(): void
    {
        $source = $this->temporaryDirectory . '/disguised.jpg';
        file_put_contents($source, 'This is not an image.');
        $service = $this->serviceForLocalFiles();

        $result = $service->store($this->upload($source, 'disguised.jpg', filesize($source)));

        $this->assertFalse($result['success']);
        $this->assertTrue(is_file($source));
        $this->assertSame([], array_values(array_filter(
            scandir($this->uploadRoot) ?: [],
            static fn (string $entry): bool => !in_array($entry, ['.', '..'], true),
        )));
    }

    public function testRejectsAJpegMimeSignatureWithoutValidImageDimensions(): void
    {
        $source = $this->temporaryDirectory . '/broken.jpg';
        file_put_contents($source, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00" . str_repeat('x', 64));
        $service = $this->serviceForLocalFiles();

        $result = $service->store($this->upload($source, 'broken.jpg', filesize($source)));

        $this->assertFalse($result['success']);
        $this->assertSame('The uploaded file is not a valid image.', $result['error']);
        $this->assertTrue(is_file($source));
    }

    public function testRejectsOversizedInputBeforeTryingToMoveIt(): void
    {
        $source = $this->generatedJpeg('oversized.jpg');
        $service = $this->serviceForLocalFiles();

        $result = $service->store($this->upload($source, 'oversized.jpg', 5 * 1024 * 1024 + 1));

        $this->assertFalse($result['success']);
        $this->assertSame('The image may not be larger than 5 MB.', $result['error']);
        $this->assertTrue(is_file($source));
    }

    public function testDeleteCannotEscapeTheConfiguredUploadRoot(): void
    {
        $outside = $this->temporaryDirectory . '/public/uploads/outside.jpg';
        file_put_contents($outside, 'keep');
        $service = $this->serviceForLocalFiles();

        $service->delete('/uploads/events/../outside.jpg');
        $service->delete('/uploads/events/not-present.jpg');

        $this->assertTrue(is_file($outside));
        $this->assertSame('keep', file_get_contents($outside));
    }

    private function serviceForLocalFiles(): ImageUploadService
    {
        return new ImageUploadService($this->uploadRoot, '/uploads/events', 5 * 1024 * 1024, false);
    }

    private function generatedJpeg(string $filename): string
    {
        $path = $this->temporaryDirectory . '/' . $filename;
        $image = imagecreatetruecolor(12, 8);

        if ($image === false) {
            throw new \RuntimeException('Unable to create a test image.');
        }

        $color = imagecolorallocate($image, 42, 91, 210);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    private function upload(string $path, string $name, int|false $size): array
    {
        return [
            'name' => $name,
            'full_path' => $name,
            'type' => 'image/jpeg',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => $size === false ? 0 : $size,
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
