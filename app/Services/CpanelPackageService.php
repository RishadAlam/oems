<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

final class CpanelPackageService
{
    private const ARCHIVE_ROOT = 'oems';

    /** @var list<string> */
    private const DEPLOYMENT_PATHS = [
        '.htaccess',
        '.env.example',
        'README.md',
        'composer.json',
        'composer.lock',
        'app',
        'Core',
        'bootstrap',
        'config',
        'database',
        'deploy',
        'public',
        'routes',
        'scripts',
        'storage',
        'vendor',
    ];

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function package(string $destination): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to create the cPanel package.');
        }

        $root = realpath($this->projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('The project root could not be found.');
        }

        $destinationDirectory = dirname($destination);
        if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0775, true) && !is_dir($destinationDirectory)) {
            throw new RuntimeException('The package output directory could not be created.');
        }

        $temporary = $destinationDirectory . '/.oems-cpanel-' . bin2hex(random_bytes(8)) . '.tmp.zip';
        $archive = new ZipArchive();
        $opened = $archive->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('The cPanel ZIP archive could not be created.');
        }

        try {
            $archive->addEmptyDir(self::ARCHIVE_ROOT);
            foreach (self::DEPLOYMENT_PATHS as $deploymentPath) {
                $absolute = $root . '/' . $deploymentPath;
                if (!file_exists($absolute) || is_link($absolute)) {
                    continue;
                }

                if (is_file($absolute)) {
                    $this->addFile($archive, $absolute, $deploymentPath);
                    continue;
                }

                $this->addDirectory($archive, $absolute, $deploymentPath);
            }

            if (!$archive->close()) {
                throw new RuntimeException('The cPanel ZIP archive could not be finalized.');
            }

            $this->verifyArchive($temporary);
            if (!rename($temporary, $destination)) {
                throw new RuntimeException('The cPanel ZIP archive could not be moved into place.');
            }

            return $destination;
        } catch (\Throwable $exception) {
            $archive->close();
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw $exception;
        }
    }

    private function addDirectory(ZipArchive $archive, string $absolute, string $relative): void
    {
        $archive->addEmptyDir(self::ARCHIVE_ROOT . '/' . $relative);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }

            $path = $item->getPathname();
            $nested = substr($path, strlen($absolute) + 1);
            $entry = str_replace('\\', '/', $relative . '/' . $nested);

            if ($item->isDir()) {
                $archive->addEmptyDir(self::ARCHIVE_ROOT . '/' . $entry);
                continue;
            }

            if ($item->isFile() && $this->shouldInclude($entry)) {
                $this->addFile($archive, $path, $entry);
            }
        }
    }

    private function addFile(ZipArchive $archive, string $absolute, string $relative): void
    {
        if (!$this->shouldInclude($relative)) {
            return;
        }

        if (!$archive->addFile($absolute, self::ARCHIVE_ROOT . '/' . str_replace('\\', '/', $relative))) {
            throw new RuntimeException('A required deployment file could not be added to the cPanel package.');
        }
    }

    private function shouldInclude(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);
        $basename = basename($relative);

        if ($basename === '.DS_Store') {
            return false;
        }

        if (str_starts_with($relative, 'public/uploads/')) {
            return in_array($basename, ['.gitkeep', '.htaccess'], true);
        }

        if (str_starts_with($relative, 'public/tickets/')) {
            return in_array($basename, ['.gitkeep', '.htaccess'], true);
        }

        foreach (['backups', 'cache', 'certificates', 'logs', 'tickets'] as $runtimeDirectory) {
            if (!str_starts_with($relative, 'storage/' . $runtimeDirectory . '/')) {
                continue;
            }

            $allowed = $runtimeDirectory === 'backups' ? ['.gitignore'] : ['.gitkeep'];
            if ($runtimeDirectory === 'tickets') {
                $allowed[] = '.htaccess';
            }

            return in_array($basename, $allowed, true);
        }

        return true;
    }

    private function verifyArchive(string $path): void
    {
        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The generated cPanel ZIP archive could not be verified.');
        }

        foreach ([self::ARCHIVE_ROOT . '/public/index.php', self::ARCHIVE_ROOT . '/vendor/autoload.php'] as $required) {
            if ($archive->locateName($required) === false) {
                $archive->close();
                throw new RuntimeException('The cPanel ZIP archive is missing a required runtime file.');
            }
        }

        $archive->close();
    }
}
