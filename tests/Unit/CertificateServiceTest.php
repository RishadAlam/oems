<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\CertificateRepository;
use OEMS\App\Services\CertificateArtifactService;
use OEMS\App\Services\CertificateService;
use OEMS\Tests\Support\TestCase;
use PDO;

final class CertificateServiceTest extends TestCase
{
    private PDO $connection;

    private string $root;

    private CertificateService $service;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedEligibleRegistration();
        $this->root = sys_get_temp_dir() . '/oems-certificate-' . bin2hex(random_bytes(6));
        $this->service = new CertificateService(
            $this->connection,
            new CertificateRepository($this->connection),
            new CertificateArtifactService($this->root, 'certificates', 'https://events.example.test/certificates/verify'),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testEligibleParticipantReceivesOnePrivateVerifiableCertificate(): void
    {
        $issued = $this->service->issue(1, 10);

        $this->assertTrue($issued['success']);
        $this->assertTrue($issued['created']);
        $this->assertTrue(preg_match('/\A[a-f0-9]{64}\z/', (string) $issued['verification_token']) === 1);
        $this->assertTrue(preg_match('/\AOEMS-CERT-[A-F0-9]{32}\z/', (string) $issued['certificate']['certificate_number']) === 1);
        $this->assertFalse(array_key_exists('verification_token_hash', $issued['certificate']));
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM event_certificates')->fetchColumn());
        $this->assertSame(hash('sha256', $issued['verification_token']), (string) $this->connection->query('SELECT verification_token_hash FROM event_certificates')->fetchColumn());

        $path = $this->service->downloadPath(1, (int) $issued['certificate']['id']);
        $this->assertNotNull($path);
        $this->assertTrue(str_starts_with($path, realpath($this->root) . DIRECTORY_SEPARATOR));
        $this->assertSame('%PDF-', file_get_contents($path, false, null, 0, 5));
        $pdf = (string) file_get_contents($path);
        $this->assertTrue(str_contains($pdf, 'OEMS Attendance Certificate'));
        $this->assertFalse(str_contains($pdf, 'participant@example.test'));
        $this->assertFalse(str_contains($pdf, 'QR-CHECK-IN-SECRET'));

        $verified = $this->service->verify((string) $issued['verification_token']);
        $this->assertSame(
            ['valid', 'participant_name', 'event_title', 'completion_date', 'issued_at'],
            array_keys($verified ?? []),
        );
        $this->assertSame(true, $verified['valid'] ?? null);
        $this->assertSame('Ada Participant', $verified['participant_name'] ?? null);
        $this->assertSame('Completed Event', $verified['event_title'] ?? null);

        $repeat = $this->service->issue(1, 10);
        $this->assertTrue($repeat['success']);
        $this->assertFalse($repeat['created']);
        $this->assertSame($issued['certificate']['id'], $repeat['certificate']['id']);
        $this->assertNull($repeat['verification_token']);
        $this->assertSame(1, count(glob($this->root . '/*.pdf') ?: []));
    }

    public function testEligibilityAndOwnershipFailClosedWithoutCreatingArtifacts(): void
    {
        $this->connection->exec("UPDATE events SET status = 'published'");
        $notCompleted = $this->service->issue(1, 10);
        $this->assertFalse($notCompleted['success']);
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM event_certificates')->fetchColumn());
        $this->assertSame([], glob($this->root . '/*.pdf') ?: []);

        $this->connection->exec("UPDATE events SET status = 'completed'");
        $this->connection->exec("UPDATE attendance SET status = 'absent'");
        $absent = $this->service->issue(1, 10);
        $this->assertFalse($absent['success']);
        $this->assertFalse($this->service->issue(2, 10)['success']);
        $this->assertNull($this->service->downloadPath(2, 1));
        $this->assertNull($this->service->verify('not-a-token'));
    }

    public function testRevokedUnknownAndDeletedUserCertificatesAreUnavailablePublicly(): void
    {
        $issued = $this->service->issue(1, 10);
        $token = (string) $issued['verification_token'];
        $this->assertNotNull($this->service->verify($token));

        $this->connection->exec("UPDATE event_certificates SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP, revocation_reason = 'Invalid attendance'");
        $this->assertNull($this->service->verify($token));

        $this->connection->exec("UPDATE event_certificates SET status = 'valid', revoked_at = NULL, revocation_reason = NULL");
        $this->connection->exec("UPDATE users SET deleted_at = CURRENT_TIMESTAMP");
        $this->assertNull($this->service->verify($token));
        $this->assertNull($this->service->verify(str_repeat('a', 64)));
    }

    public function testArtifactPathsRejectTraversalAndSymlinkEscape(): void
    {
        $service = new CertificateArtifactService($this->root, 'certificates', 'https://events.example.test/certificates/verify');
        $artifact = $service->generate([
            'participant_name' => 'Ada Participant',
            'event_title' => 'Completed Event',
            'completion_date' => 'August 9, 2026',
            'issued_at' => 'August 10, 2026',
        ]);
        $outside = dirname($this->root) . '/outside-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($outside, '%PDF-outside');
        symlink($outside, $this->root . '/certificate-escape.pdf');

        $this->assertNotNull($service->resolvePath($artifact['pdf_path']));
        $this->assertNull($service->resolvePath('certificates/../outside.pdf'));
        $this->assertNull($service->resolvePath('certificates/certificate-escape.pdf'));
        $this->assertFalse($service->delete('certificates/certificate-escape.pdf'));
        $this->assertTrue(is_file($outside));
        unlink($outside);
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, status TEXT, email_verified_at TEXT, deleted_at TEXT)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, title TEXT, status TEXT, end_date TEXT, deleted_at TEXT)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER, user_id INTEGER, status TEXT)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, registration_id INTEGER, ticket_number TEXT, status TEXT)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY, registration_id INTEGER, ticket_id INTEGER, status TEXT, scanned_at TEXT)');
        $this->connection->exec("CREATE TABLE event_certificates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            registration_id INTEGER NOT NULL UNIQUE,
            participant_id INTEGER NOT NULL,
            certificate_number TEXT NOT NULL UNIQUE,
            verification_token_hash TEXT NOT NULL UNIQUE,
            pdf_path TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'valid',
            issued_at TEXT NOT NULL,
            revoked_at TEXT,
            revoked_by INTEGER,
            revocation_reason TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private function seedEligibleRegistration(): void
    {
        $this->connection->exec("INSERT INTO users VALUES (1, 'Ada Participant', 'participant@example.test', 'active', '2026-08-01 10:00:00', NULL)");
        $this->connection->exec("INSERT INTO users VALUES (2, 'Other Participant', 'other@example.test', 'active', '2026-08-01 10:00:00', NULL)");
        $this->connection->exec("INSERT INTO events VALUES (5, 'Completed Event', 'completed', '2026-08-09 18:00:00', NULL)");
        $this->connection->exec("INSERT INTO registrations VALUES (10, 5, 1, 'confirmed')");
        $this->connection->exec("INSERT INTO tickets VALUES (20, 10, 'QR-CHECK-IN-SECRET', 'used')");
        $this->connection->exec("INSERT INTO attendance VALUES (30, 10, 20, 'present', '2026-08-09 12:00:00')");
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($target) && !is_link($target)) {
                $this->removeDirectory($target);
            } else {
                @unlink($target);
            }
        }
        @rmdir($path);
    }
}
