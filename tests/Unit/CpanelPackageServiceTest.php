<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\CpanelPackageService;
use OEMS\Tests\Support\TestCase;
use ZipArchive;

final class CpanelPackageServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/oems-cpanel-package-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);

        foreach ([
            '.htaccess' => 'secure root',
            '.env.example' => 'APP_ENV=production',
            'README.md' => 'deployment instructions',
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'app/Runtime.php' => '<?php',
            'Core/Router.php' => '<?php',
            'bootstrap/app.php' => '<?php',
            'config/app.php' => '<?php return [];',
            'database/schema.sql' => 'CREATE TABLE demo (id INT);',
            'database/migrations/manifest.php' => '<?php return [];',
            'deploy/nginx/oems.conf' => 'server {}',
            'public/.htaccess' => 'route requests',
            'public/index.php' => '<?php',
            'public/assets/css/app.css' => 'body{}',
            'public/uploads/.gitkeep' => '',
            'public/uploads/events/.gitkeep' => '',
            'public/uploads/tickets/.htaccess' => 'deny',
            'public/uploads/tickets/.gitkeep' => '',
            'public/tickets/.gitkeep' => '',
            'routes/web.php' => '<?php',
            'scripts/database.php' => '<?php',
            'storage/backups/.gitignore' => "*\n!.gitignore",
            'storage/cache/.gitkeep' => '',
            'storage/certificates/.gitkeep' => '',
            'storage/logs/.gitkeep' => '',
            'storage/tickets/.htaccess' => 'deny',
            'storage/tickets/.gitkeep' => '',
            'vendor/autoload.php' => '<?php',
            '.env' => 'DB_PASSWORD=secret',
            'tests/Unit/SecretTest.php' => 'secret test',
            'docs/private.md' => 'private docs',
            'node_modules/package/index.js' => 'node dependency',
            'resources/css/app.css' => 'source css',
            'dist/old.zip' => 'old archive',
            'storage/backups/database.sql.gz' => 'private backup',
            'storage/cache/session.json' => 'private cache',
            'storage/certificates/private.pdf' => 'private certificate',
            'storage/logs/oems.log' => 'private log',
            'storage/tickets/private.png' => 'private ticket',
            'public/uploads/events/customer.jpg' => 'customer image',
            'public/uploads/blog/customer.jpg' => 'customer blog image',
            'public/uploads/tickets/private.pdf' => 'legacy private ticket',
        ] as $path => $contents) {
            $this->write($path, $contents);
        }

        symlink($this->root . '/.env', $this->root . '/app/environment-link');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testPackageContainsOnlyDeployableRuntimeFilesUnderOneRoot(): void
    {
        $destination = $this->root . '/dist/oems-cpanel.zip';
        $path = (new CpanelPackageService($this->root))->package($destination);

        $this->assertSame($destination, $path);
        $this->assertTrue(is_file($path) && filesize($path) > 0);

        $archive = new ZipArchive();
        $this->assertSame(true, $archive->open($path));
        $entries = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entries[] = (string) $archive->getNameIndex($index);
        }
        $archive->close();

        foreach ($entries as $entry) {
            $this->assertTrue(str_starts_with($entry, 'oems/'), $entry . ' must stay below the archive root.');
        }
        foreach ([
            'oems/.htaccess',
            'oems/.env.example',
            'oems/public/.htaccess',
            'oems/public/index.php',
            'oems/public/assets/css/app.css',
            'oems/public/uploads/.gitkeep',
            'oems/public/uploads/events/.gitkeep',
            'oems/public/uploads/tickets/.htaccess',
            'oems/storage/backups/.gitignore',
            'oems/storage/cache/.gitkeep',
            'oems/storage/tickets/.htaccess',
            'oems/vendor/autoload.php',
            'oems/database/migrations/manifest.php',
            'oems/scripts/database.php',
        ] as $required) {
            $this->assertTrue(in_array($required, $entries, true), $required . ' must be packaged.');
        }
        foreach ([
            'oems/.env',
            'oems/app/environment-link',
            'oems/tests/Unit/SecretTest.php',
            'oems/docs/private.md',
            'oems/node_modules/package/index.js',
            'oems/resources/css/app.css',
            'oems/dist/old.zip',
            'oems/storage/backups/database.sql.gz',
            'oems/storage/cache/session.json',
            'oems/storage/certificates/private.pdf',
            'oems/storage/logs/oems.log',
            'oems/storage/tickets/private.png',
            'oems/public/uploads/events/customer.jpg',
            'oems/public/uploads/blog/customer.jpg',
            'oems/public/uploads/tickets/private.pdf',
        ] as $forbidden) {
            $this->assertFalse(in_array($forbidden, $entries, true), $forbidden . ' must not be packaged.');
        }
    }

    private function write(string $path, string $contents): void
    {
        $absolute = $this->root . '/' . $path;
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        file_put_contents($absolute, $contents);
    }
}
