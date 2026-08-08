<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantTicketController;
use OEMS\App\Services\TicketArtifactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeTicketRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ParticipantTicketControllerTest extends TestCase
{
    private mixed $controller = null;

    private FakeTicketRepository $tickets;

    private string $ticketRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Nusrat Participant',
            'email' => 'nusrat@example.test',
            'password' => password_hash('secret-password', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
        $session->put('auth.user_id', 7);
        $auth = new Auth($session, $users);
        $this->ticketRoot = sys_get_temp_dir() . '/oems-ticket-controller-' . bin2hex(random_bytes(6));
        mkdir($this->ticketRoot, 0775, true);
        file_put_contents($this->ticketRoot . '/owned-qr.png', "\x89PNG\r\nowned");
        file_put_contents($this->ticketRoot . '/owned-ticket.pdf', "%PDF-1.4\nowned");
        $this->tickets = new FakeTicketRepository();
        $this->tickets->tickets = [
            41 => $this->ticketFixture(41, 7, 'Owned event'),
            42 => $this->ticketFixture(42, 88, 'Foreign event'),
            43 => array_merge($this->ticketFixture(43, 7, 'Missing artifact'), ['qr_path' => 'uploads/tickets/missing.png']),
            44 => array_merge($this->ticketFixture(44, 7, 'Confined artifact'), ['pdf_path' => '../owned-ticket.pdf']),
        ];

        if (class_exists(ParticipantTicketController::class)) {
            $this->controller = new ParticipantTicketController(
                new View(base_path('app/Views')),
                $session,
                new Security($session),
                $auth,
                new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
                $this->tickets,
                new TicketArtifactService($this->ticketRoot, 'uploads/tickets'),
            );
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->ticketRoot . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->ticketRoot)) {
            rmdir($this->ticketRoot);
        }
        $_SESSION = [];
    }

    public function testTicketHistoryAndDetailAreOwnershipScopedWithoutDirectArtifactPaths(): void
    {
        $index = $this->controller()->index(Request::create('GET', '/participant/tickets'));
        $owned = $this->controller()->show($this->routed(41));
        $foreign = $this->controller()->show($this->routed(42));

        $this->assertTrue(str_contains($index->body(), 'Owned event'));
        $this->assertFalse(str_contains($index->body(), 'Foreign event'));
        $this->assertTrue(str_contains($owned->body(), 'OEMS-ABC-123'));
        $this->assertTrue(str_contains($owned->body(), 'src="/participant/tickets/41/qr"'));
        $this->assertTrue(str_contains($owned->body(), 'href="/participant/tickets/41/pdf"'));
        $this->assertFalse(str_contains($owned->body(), 'uploads/tickets'));
        $this->assertSame(404, $foreign->status());
        $this->assertSame(404, $this->controller()->show($this->routed(0))->status());
    }

    public function testOwnedQrUsesSafeInlineBinaryHeaders(): void
    {
        $response = $this->controller()->qr($this->routed(41, 'qr'));

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame('image/png', $response->header('Content-Type'));
        $this->assertSame('inline; filename="OEMS-ABC-123-qr.png"', $response->header('Content-Disposition'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('private, no-store, max-age=0', $response->header('Cache-Control'));
        $this->assertSame((string) strlen("\x89PNG\r\nowned"), $response->header('Content-Length'));
        ob_start();
        $response->send();
        $this->assertSame("\x89PNG\r\nowned", ob_get_clean());
    }

    public function testOwnedPdfUsesSafeAttachmentHeadersDerivedFromTicketNumber(): void
    {
        $response = $this->controller()->pdf($this->routed(41, 'pdf'));

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame('application/pdf', $response->header('Content-Type'));
        $this->assertSame('attachment; filename="OEMS-ABC-123.pdf"', $response->header('Content-Disposition'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('private, no-store, max-age=0', $response->header('Cache-Control'));
        $this->assertSame((string) strlen("%PDF-1.4\nowned"), $response->header('Content-Length'));
        ob_start();
        $response->send();
        $this->assertSame("%PDF-1.4\nowned", ob_get_clean());
    }

    public function testForeignMissingAndUnconfinedArtifactsReturnTheSame404(): void
    {
        foreach ([[42, 'qr'], [43, 'qr'], [44, 'pdf'], [999, 'pdf']] as [$id, $format]) {
            $response = $format === 'qr'
                ? $this->controller()->qr($this->routed($id, $format))
                : $this->controller()->pdf($this->routed($id, $format));

            $this->assertSame(404, $response->status());
            $this->assertFalse(str_contains($response->body(), $this->ticketRoot));
        }
    }

    private function controller(): ParticipantTicketController
    {
        $this->assertTrue($this->controller instanceof ParticipantTicketController, 'Participant ticket controller is missing.');

        return $this->controller;
    }

    private function routed(int $id, string $suffix = ''): Request
    {
        $uri = '/participant/tickets/' . $id . ($suffix === '' ? '' : '/' . $suffix);

        return Request::create('GET', $uri)->withRouteParameters(['id' => (string) $id]);
    }

    private function ticketFixture(int $id, int $participantId, string $eventTitle): array
    {
        return [
            'id' => $id,
            'registration_id' => 14,
            'participant_id' => $participantId,
            'ticket_number' => 'OEMS-ABC-123',
            'qr_payload_hash' => hash('sha256', 'private-token'),
            'qr_path' => 'uploads/tickets/owned-qr.png',
            'pdf_path' => 'uploads/tickets/owned-ticket.pdf',
            'status' => 'valid',
            'ticket_status' => 'valid',
            'issued_at' => '2026-08-09 10:00:00',
            'registration_number' => 'REG-OWNED-14',
            'registration_status' => 'confirmed',
            'event_id' => 31,
            'event_title' => $eventTitle,
            'event_slug' => 'future-craft',
            'event_status' => 'published',
            'event_start_date' => '2026-08-22 10:00:00',
            'attendance_id' => null,
            'attendance_status' => null,
            'scanned_at' => null,
        ];
    }
}
