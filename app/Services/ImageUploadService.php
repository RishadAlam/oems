<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use finfo;

final class ImageUploadService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $uploadRoot,
        private readonly string $publicPath = '/uploads/events',
        private readonly int $maxBytes = 5 * 1024 * 1024,
        private readonly bool $requireHttpUpload = true,
        private readonly int $maxPixels = 16_000_000,
    ) {
    }

    /**
     * @return array{success: bool, path: ?string, error: ?string}
     */
    public function store(?array $upload): array
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->result(true, null, null);
        }

        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->result(false, null, 'The image upload failed.');
        }

        $declaredSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);

        if ($declaredSize === false || $declaredSize < 0) {
            return $this->result(false, null, 'The image upload is invalid.');
        }

        if ($declaredSize > $this->maxBytes) {
            return $this->result(false, null, 'The image may not be larger than 5 MB.');
        }

        $temporaryPath = $upload['tmp_name'] ?? null;

        if (!is_string($temporaryPath) || $temporaryPath === '' || !is_file($temporaryPath)) {
            return $this->result(false, null, 'The image upload is invalid.');
        }

        if ($this->requireHttpUpload && !is_uploaded_file($temporaryPath)) {
            return $this->result(false, null, 'The image upload is invalid.');
        }

        $actualSize = @filesize($temporaryPath);

        if ($actualSize === false || $actualSize > $this->maxBytes) {
            return $this->result(false, null, 'The image may not be larger than 5 MB.');
        }

        $mime = @(new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extension = is_string($mime) ? (self::MIME_EXTENSIONS[$mime] ?? null) : null;

        if ($extension === null) {
            return $this->result(false, null, 'The image must be a JPEG, PNG, or WebP file.');
        }

        $dimensions = @getimagesize($temporaryPath);

        if (!is_array($dimensions)
            || ($dimensions[0] ?? 0) < 1
            || ($dimensions[1] ?? 0) < 1
            || ($dimensions['mime'] ?? null) !== $mime) {
            return $this->result(false, null, 'The uploaded file is not a valid image.');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];

        if ($height > 0 && $width > intdiv($this->maxPixels, $height)) {
            return $this->result(false, null, 'The image dimensions are too large.');
        }

        if (!$this->ensureUploadRoot()) {
            return $this->result(false, null, 'The image could not be stored.');
        }

        do {
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = rtrim($this->uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        } while (file_exists($destination));

        $moved = $this->requireHttpUpload
            ? @move_uploaded_file($temporaryPath, $destination)
            : @rename($temporaryPath, $destination);

        if (!$moved) {
            return $this->result(false, null, 'The image could not be stored.');
        }

        return $this->result(true, rtrim($this->publicPath, '/') . '/' . $filename, null);
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $prefix = rtrim($this->publicPath, '/') . '/';

        if (!str_starts_with($path, $prefix)) {
            return;
        }

        $relative = substr($path, strlen($prefix));

        if ($relative === '' || basename($relative) !== $relative || str_contains($relative, "\0")) {
            return;
        }

        $root = realpath($this->uploadRoot);
        $target = realpath(rtrim($this->uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative);

        if ($root === false
            || $target === false
            || !str_starts_with($target, $root . DIRECTORY_SEPARATOR)
            || !is_file($target)) {
            return;
        }

        @unlink($target);
    }

    private function ensureUploadRoot(): bool
    {
        return is_dir($this->uploadRoot)
            ? is_writable($this->uploadRoot)
            : @mkdir($this->uploadRoot, 0775, true);
    }

    /**
     * @return array{success: bool, path: ?string, error: ?string}
     */
    private function result(bool $success, ?string $path, ?string $error): array
    {
        return ['success' => $success, 'path' => $path, 'error' => $error];
    }
}
