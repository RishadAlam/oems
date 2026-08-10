<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\TicketArtifactService;
use OEMS\Tests\Support\TestCase;
use finfo;

final class TicketArtifactServiceTest extends TestCase
{
    private string $temporaryDirectory;

    private string $uploadRoot;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/oems-ticket-artifacts-' . bin2hex(random_bytes(6));
        $this->uploadRoot = $this->temporaryDirectory . '/storage/tickets';
        mkdir($this->uploadRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testGeneratesOpaqueTokenDigestAndUnpredictableTicketNumber(): void
    {
        $artifact = $this->service()->generate($this->displayData());

        $this->assertSame(
            ['ticket_number', 'raw_token', 'qr_payload_hash', 'qr_path', 'pdf_path'],
            array_keys($artifact),
        );
        $this->assertTrue(preg_match('/\A[a-f0-9]{64}\z/', $artifact['raw_token']) === 1);
        $this->assertSame(hash('sha256', $artifact['raw_token']), $artifact['qr_payload_hash']);
        $this->assertTrue(preg_match('/\AOEMS-[A-F0-9]{32}\z/', $artifact['ticket_number']) === 1);
        $this->assertFalse(str_contains($artifact['raw_token'], 'participant@example.test'));
    }

    public function testGeneratesRandomizedPngQrAndPdfTicketArtifacts(): void
    {
        $first = $this->service()->generate($this->displayData());
        $second = $this->service()->generate($this->displayData());

        $this->assertNotSame($first['ticket_number'], $second['ticket_number']);
        $this->assertNotSame($first['raw_token'], $second['raw_token']);
        $this->assertNotSame($first['qr_path'], $second['qr_path']);
        $this->assertNotSame($first['pdf_path'], $second['pdf_path']);
        $this->assertTrue(preg_match('/\Auploads\/tickets\/qr-[a-f0-9]{32}\.png\z/', $first['qr_path']) === 1);
        $this->assertTrue(preg_match('/\Auploads\/tickets\/ticket-[a-f0-9]{32}\.pdf\z/', $first['pdf_path']) === 1);

        $qrPath = $this->service()->resolvePath($first['qr_path']);
        $pdfPath = $this->service()->resolvePath($first['pdf_path']);

        $this->assertNotNull($qrPath);
        $this->assertNotNull($pdfPath);
        $this->assertSame("\x89PNG\r\n\x1A\n", file_get_contents($qrPath, false, null, 0, 8));
        $this->assertSame('%PDF-', file_get_contents($pdfPath, false, null, 0, 5));
        $this->assertSame('image/png', (new finfo(FILEINFO_MIME_TYPE))->file($qrPath));
        $this->assertSame('application/pdf', (new finfo(FILEINFO_MIME_TYPE))->file($pdfPath));
    }

    public function testNormalizesBoundedPlainTextForThePdfWithoutEmbeddingHtmlOrParticipantEmail(): void
    {
        $artifact = $this->service()->generate([
            'event_title' => '&lt;script&gt;alert(1)&lt;/script&gt;' . str_repeat('A', 2000),
            'event_starts_at' => '<b>2026-09-01 09:00</b>',
            'venue_name' => '<em>Grand Hall</em>',
            'participant_name' => '<img src=x onerror=alert(1)>Ada',
            'participant_email' => 'participant@example.test',
        ]);

        $pdfPath = $this->service()->resolvePath($artifact['pdf_path']);
        $this->assertNotNull($pdfPath);
        $pdf = (string) file_get_contents($pdfPath);

        $this->assertTrue(str_contains($pdf, 'OEMS Ticket'));
        $this->assertFalse(str_contains($pdf, '<script>'));
        $this->assertFalse(str_contains($pdf, '<img'));
        $this->assertFalse(str_contains($pdf, 'participant@example.test'));
        $this->assertFalse(str_contains($pdf, str_repeat('A', 1000)));
    }

    public function testResolvesAndCleansUpOnlyFilesConfinedToTheTicketDirectory(): void
    {
        $service = $this->service();
        $artifact = $service->generate($this->displayData());
        $outside = $this->temporaryDirectory . '/public/uploads/outside.txt';
        mkdir(dirname($outside), 0775, true);
        file_put_contents($outside, 'keep');
        $escapedSymlink = $this->uploadRoot . '/qr-escape.png';
        symlink($outside, $escapedSymlink);

        $qrPath = $service->resolvePath($artifact['qr_path']);

        $this->assertNotNull($qrPath);
        $this->assertTrue(str_starts_with($qrPath, realpath($this->uploadRoot) . DIRECTORY_SEPARATOR));
        $this->assertNull($service->resolvePath('uploads/tickets/../outside.txt'));
        $this->assertNull($service->resolvePath('uploads/outside.txt'));
        $this->assertNull($service->resolvePath('uploads/tickets/qr-escape.png'));

        $service->delete($artifact['qr_path']);
        $service->delete($artifact['pdf_path']);
        $service->delete('uploads/tickets/qr-escape.png');
        $service->delete('uploads/tickets/../outside.txt');

        $this->assertNull($service->resolvePath($artifact['qr_path']));
        $this->assertNull($service->resolvePath($artifact['pdf_path']));
        $this->assertTrue(is_file($outside));
        $this->assertSame('keep', file_get_contents($outside));
    }

    public function testMigratesLegacyPublicArtifactsWithoutChangingStoredDatabasePaths(): void
    {
        $legacyRoot = $this->temporaryDirectory . '/public/uploads/tickets';
        $legacyService = new TicketArtifactService(
            $legacyRoot,
            'uploads/tickets',
            'https://oems.test/organizer/check-in',
        );
        $generated = $legacyService->generate($this->displayData());

        $service = new TicketArtifactService(
            $this->uploadRoot,
            'uploads/tickets',
            'https://oems.test/organizer/check-in',
            $legacyRoot,
        );

        $this->assertNull($legacyService->resolvePath($generated['pdf_path']));
        $this->assertNull($legacyService->resolvePath($generated['qr_path']));
        $this->assertNotNull($service->resolvePath($generated['pdf_path']));
        $this->assertNotNull($service->resolvePath($generated['qr_path']));
        $this->assertSame('%PDF-', file_get_contents((string) $service->resolvePath($generated['pdf_path']), false, null, 0, 5));
        $this->assertSame("\x89PNG\r\n\x1A\n", file_get_contents((string) $service->resolvePath($generated['qr_path']), false, null, 0, 8));
    }

    public function testLegacyMigrationPreservesPublicAccessControlFiles(): void
    {
        $legacyRoot = $this->temporaryDirectory . '/public/uploads/tickets';
        mkdir($legacyRoot, 0775, true);
        file_put_contents($legacyRoot . '/.htaccess', "Options -Indexes\nRequire all denied\n");
        file_put_contents($legacyRoot . '/.gitkeep', '');
        $legacyService = new TicketArtifactService(
            $legacyRoot,
            'uploads/tickets',
            'https://oems.test/organizer/check-in',
        );
        $legacyService->generate($this->displayData());

        $service = new TicketArtifactService(
            $this->uploadRoot,
            'uploads/tickets',
            'https://oems.test/organizer/check-in',
        );

        $this->assertSame(2, $service->migrateLegacyArtifacts($legacyRoot));
        $this->assertTrue(is_file($legacyRoot . '/.htaccess'));
        $this->assertTrue(is_file($legacyRoot . '/.gitkeep'));
        $this->assertSame("Options -Indexes\nRequire all denied\n", file_get_contents($legacyRoot . '/.htaccess'));
        $this->assertSame(0, $service->migrateLegacyArtifacts($legacyRoot));
    }

    public function testLegacyMigrationRejectsUnexpectedOrdinaryFiles(): void
    {
        $legacyRoot = $this->temporaryDirectory . '/public/uploads/tickets';
        mkdir($legacyRoot, 0775, true);
        file_put_contents($legacyRoot . '/notes.txt', 'must not be moved into private ticket storage');

        $thrown = false;

        try {
            $this->service()->migrateLegacyArtifacts($legacyRoot);
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Unexpected legacy files must fail closed.');
        $this->assertTrue(is_file($legacyRoot . '/notes.txt'));
    }

    public function testLegacyMigrationRejectsSymlinkedAccessControlFiles(): void
    {
        $legacyRoot = $this->temporaryDirectory . '/public/uploads/tickets';
        mkdir($legacyRoot, 0775, true);
        $outside = $this->temporaryDirectory . '/outside-access-control';
        file_put_contents($outside, 'outside');
        symlink($outside, $legacyRoot . '/.htaccess');

        $thrown = false;

        try {
            $this->service()->migrateLegacyArtifacts($legacyRoot);
        } catch (\RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Symlinked access-control files must fail closed.');
        $this->assertTrue(is_link($legacyRoot . '/.htaccess'));
        $this->assertSame('outside', file_get_contents($outside));
    }

    public function testLegacyMigrationRejectsMisnamedAndMalformedTicketFiles(): void
    {
        $legacyRoot = $this->temporaryDirectory . '/public/uploads/tickets';
        mkdir($legacyRoot, 0775, true);
        file_put_contents($legacyRoot . '/legacy.pdf', '%PDF-1.4');

        $service = $this->service();
        $misnamedThrown = false;

        try {
            $service->migrateLegacyArtifacts($legacyRoot);
        } catch (\RuntimeException) {
            $misnamedThrown = true;
        }

        $this->assertTrue($misnamedThrown, 'Only generated ticket filenames may be migrated.');
        $this->assertTrue(is_file($legacyRoot . '/legacy.pdf'));
        unlink($legacyRoot . '/legacy.pdf');
        file_put_contents($legacyRoot . '/qr-' . str_repeat('a', 32) . '.png', 'not a PNG');

        $malformedThrown = false;

        try {
            $service->migrateLegacyArtifacts($legacyRoot);
        } catch (\RuntimeException) {
            $malformedThrown = true;
        }

        $this->assertTrue($malformedThrown, 'Generated-looking ticket files must have valid artifact bytes.');
        $this->assertTrue(is_file($legacyRoot . '/qr-' . str_repeat('a', 32) . '.png'));
    }

    private function service(): TicketArtifactService
    {
        return new TicketArtifactService(
            $this->uploadRoot,
            'uploads/tickets',
            'https://oems.test/organizer/check-in',
        );
    }

    /**
     * @return array{event_title: string, event_starts_at: string, venue_name: string, participant_name: string, participant_email: string}
     */
    private function displayData(): array
    {
        return [
            'event_title' => 'OEMS Security Summit',
            'event_starts_at' => '2026-09-01 09:00',
            'venue_name' => 'Grand Hall',
            'participant_name' => 'Ada Participant',
            'participant_email' => 'participant@example.test',
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
