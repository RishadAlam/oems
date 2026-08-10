<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantCertificateController;
use OEMS\App\Controllers\PublicCertificateController;
use OEMS\App\Repositories\CertificateRepository;
use OEMS\App\Services\CertificateArtifactService;
use OEMS\App\Services\CertificateService;
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

final class CertificateControllerTest extends TestCase
{
    private mixed $participantController = null;

    private mixed $publicController = null;

    private PDO $connection;

    private string $root;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seed();
        $this->root = sys_get_temp_dir() . '/oems-certificate-controller-' . bin2hex(random_bytes(6));
        $service = new CertificateService(
            $this->connection,
            new CertificateRepository($this->connection),
            new CertificateArtifactService($this->root, 'certificates', 'https://events.example.test/certificates/verify'),
        );
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[1] = [
            'id' => 1, 'role_id' => 3, 'role_slug' => 'participant', 'role_name' => 'Participant',
            'name' => 'Ada Participant', 'email' => 'participant@example.test',
            'password' => password_hash('secret-password', PASSWORD_DEFAULT), 'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00', 'deleted_at' => null,
        ];
        $this->authenticateSession($session, $users, 1);
        $dependencies = [
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
        ];

        if (class_exists(ParticipantCertificateController::class)) {
            $this->participantController = new ParticipantCertificateController(...$dependencies, certificates: $service);
        }
        if (class_exists(PublicCertificateController::class)) {
            $this->publicController = new PublicCertificateController(...$dependencies, certificates: $service);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        $_SESSION = [];
    }

    public function testParticipantCanIssueListAndDownloadOwnedCertificate(): void
    {
        $empty = $this->participant()->index(Request::create('GET', '/participant/certificates'));
        $this->assertTrue(str_contains($empty->body(), 'No certificates yet'));

        $issued = $this->participant()->issue($this->routed(10));
        $this->assertSame(302, $issued->status());
        $this->assertSame('/participant/certificates', $issued->header('Location'));

        $index = $this->participant()->index(Request::create('GET', '/participant/certificates'));
        $this->assertTrue(str_contains($index->body(), 'Completed Event'));
        $this->assertTrue(str_contains($index->body(), 'OEMS-CERT-'));
        $this->assertFalse(str_contains($index->body(), 'certificates/certificate-'));
        $id = (int) $this->connection->query('SELECT id FROM event_certificates')->fetchColumn();
        $download = $this->participant()->pdf($this->routed($id));
        $this->assertSame(200, $download->status());
        $this->assertSame('application/pdf', $download->header('Content-Type'));
        $this->assertSame('private, no-store, max-age=0', $download->header('Cache-Control'));
        $this->assertTrue(str_starts_with((string) $download->header('Content-Disposition'), 'attachment; filename="OEMS-CERT-'));
        $this->assertSame('', $download->body());
    }

    public function testParticipantIssuanceIsOwnedAndPublicVerificationIsBounded(): void
    {
        $foreign = $this->participant()->issue($this->routed(99));
        $this->assertSame(302, $foreign->status());
        $this->assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM event_certificates')->fetchColumn());

        $issued = $this->participant()->issue($this->routed(10));
        $this->assertSame(302, $issued->status());
        $digest = (string) $this->connection->query('SELECT verification_token_hash FROM event_certificates')->fetchColumn();
        $this->assertSame(64, strlen($digest));
        $pdf = glob($this->root . '/*.pdf')[0] ?? null;
        $this->assertNotNull($pdf);
        preg_match('/certificates\/verify\/([a-f0-9]{64})/', (string) file_get_contents((string) $pdf), $match);
        $token = $match[1] ?? '';
        $verified = $this->public()->show($this->tokenRequest($token));
        $this->assertSame(200, $verified->status());
        $this->assertTrue(str_contains($verified->body(), 'Certificate verified'));
        $this->assertTrue(str_contains($verified->body(), 'Ada Participant'));
        $this->assertTrue(str_contains($verified->body(), 'Completed Event'));
        $this->assertFalse(str_contains($verified->body(), 'participant@example.test'));
        $this->assertFalse(str_contains($verified->body(), $token));

        $unknown = $this->public()->show($this->tokenRequest(str_repeat('f', 64)));
        $malformed = $this->public()->show($this->tokenRequest('../secret'));
        $this->assertSame(404, $unknown->status());
        $this->assertSame(404, $malformed->status());
        $this->assertTrue(str_contains($unknown->body(), 'Certificate unavailable'));
        $this->assertSame($unknown->body(), $malformed->body());
    }

    public function testRoutesNavigationAndRegistrationSurfaceAreRoleAndCsrfExplicit(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));
        $layout = (string) file_get_contents(base_path('app/Views/layouts/dashboard.php'));
        $registration = (string) file_get_contents(base_path('app/Views/participant/registrations/show.php'));

        $this->assertTrue(str_contains($routes, "'/participant/certificates'"));
        $this->assertTrue(str_contains($routes, "'/participant/registrations/{id}/certificate'"));
        $this->assertTrue(str_contains($routes, "[ParticipantCertificateController::class, 'issue'], ['role:participant', 'csrf']"));
        $this->assertTrue(str_contains($routes, "'/certificates/verify/{token}'"));
        $this->assertTrue(str_contains($layout, 'href="/participant/certificates"'));
        $this->assertTrue(str_contains($registration, '/certificate'));
    }

    private function participant(): ParticipantCertificateController
    {
        if (!$this->participantController instanceof ParticipantCertificateController) {
            throw new RuntimeException('ParticipantCertificateController is not implemented.');
        }

        return $this->participantController;
    }

    private function public(): PublicCertificateController
    {
        if (!$this->publicController instanceof PublicCertificateController) {
            throw new RuntimeException('PublicCertificateController is not implemented.');
        }

        return $this->publicController;
    }

    private function routed(int $id): Request
    {
        return Request::create('POST', '/participant/registrations/' . $id . '/certificate')->withRouteParameters(['id' => (string) $id]);
    }

    private function tokenRequest(string $token): Request
    {
        return Request::create('GET', '/certificates/verify/' . rawurlencode($token))->withRouteParameters(['token' => $token]);
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, status TEXT, email_verified_at TEXT, deleted_at TEXT)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, title TEXT, status TEXT, end_date TEXT, deleted_at TEXT)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER, user_id INTEGER, status TEXT)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, registration_id INTEGER, ticket_number TEXT, status TEXT)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY, registration_id INTEGER, ticket_id INTEGER, status TEXT, scanned_at TEXT)');
        $this->connection->exec("CREATE TABLE event_certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER UNIQUE, participant_id INTEGER, certificate_number TEXT UNIQUE, verification_token_hash TEXT UNIQUE, pdf_path TEXT, status TEXT, issued_at TEXT, revoked_at TEXT, revoked_by INTEGER, revocation_reason TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    }

    private function seed(): void
    {
        $this->connection->exec("INSERT INTO users VALUES (1, 'Ada Participant', 'participant@example.test', 'active', '2026-08-01 10:00:00', NULL)");
        $this->connection->exec("INSERT INTO events VALUES (5, 'Completed Event', 'completed', '2026-08-09 18:00:00', NULL)");
        $this->connection->exec("INSERT INTO registrations VALUES (10, 5, 1, 'confirmed')");
        $this->connection->exec("INSERT INTO tickets VALUES (20, 10, 'OEMS-TICKET', 'used')");
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
            is_dir($target) && !is_link($target) ? $this->removeDirectory($target) : @unlink($target);
        }
        @rmdir($path);
    }
}
