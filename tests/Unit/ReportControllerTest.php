<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Controllers\AdminReportController;
use OEMS\App\Services\ReportArtifactService;
use OEMS\App\Services\ReportService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeAnalyticsRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ReportControllerTest extends TestCase
{
    public function testReportWorkspaceUsesAllowlistedTypeAndSafeAggregatePreview(): void
    {
        $controller = $this->controller();
        $response = $controller->index(Request::create('GET', '/admin/reports', query: [
            'type' => 'events', 'start' => '2026-08-01', 'end' => '2026-08-10',
        ]));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Operational reports'));
        $this->assertTrue(str_contains($response->body(), 'value="events" selected'));
        $this->assertTrue(str_contains($response->body(), 'data-label="Verified payments"'));
        $this->assertTrue(str_contains($response->body(), '=2+2'));
        $this->assertFalse(str_contains(strtolower($response->body()), 'gateway_response'));
        $this->assertFalse(str_contains(strtolower($response->body()), 'participant email'));

        foreach (['users', ['events'], '<script>'] as $invalidType) {
            $invalid = $controller->index(Request::create('GET', '/admin/reports', query: ['type' => $invalidType]));
            $this->assertSame(422, $invalid->status());
            $this->assertTrue(str_contains($invalid->body(), 'role="alert"'));
            $this->assertFalse(str_contains($invalid->body(), '<script>'));
        }
    }

    public function testReportCsvIsPrivateChunkedAndUsesSafeDisposition(): void
    {
        $controller = $this->controller();
        $response = $controller->export(Request::create('GET', '/admin/reports.csv', query: [
            'type' => 'events', 'start' => '2026-08-01', 'end' => '2026-08-10',
        ]));

        $this->assertSame(200, $response->status());
        $this->assertSame('text/csv; charset=UTF-8', $response->header('Content-Type'));
        $this->assertSame('attachment; filename="oems-events-report.csv"', $response->header('Content-Disposition'));
        $this->assertSame('private, no-store', $response->header('Cache-Control'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        ob_start();
        $response->send();
        $csv = ob_get_clean();
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertTrue(str_contains($csv, "'=2+2"));
        $this->assertFalse(str_contains($csv, "\r"));
        foreach (['gateway_response', 'transaction_reference', 'email', 'token', 'qr', 'location'] as $forbidden) {
            $this->assertFalse(str_contains(strtolower($csv), $forbidden));
        }

        $invalid = $controller->export(Request::create('GET', '/admin/reports.csv', query: ['type' => "events\r\nX-Bad: yes"]));
        $this->assertSame(422, $invalid->status());
        $this->assertNull($invalid->header('Content-Disposition'));
    }

    public function testReportPdfAndSpreadsheetArePrivateBoundedArtifacts(): void
    {
        $controller = $this->controller();
        $request = Request::create('GET', '/admin/reports.pdf', query: [
            'type' => 'events', 'start' => '2026-08-01', 'end' => '2026-08-10',
        ]);
        $pdf = $controller->pdf($request);

        $this->assertSame(200, $pdf->status());
        $this->assertSame('application/pdf', $pdf->header('Content-Type'));
        $this->assertSame('attachment; filename="oems-events-report.pdf"', $pdf->header('Content-Disposition'));
        $this->assertSame('private, no-store', $pdf->header('Cache-Control'));
        $this->assertTrue(str_starts_with($pdf->body(), '%PDF-'));
        $this->assertFalse(str_contains(strtolower($pdf->body()), 'gateway_response'));

        $xml = $controller->spreadsheet(Request::create('GET', '/admin/reports.xml', query: [
            'type' => 'events', 'start' => '2026-08-01', 'end' => '2026-08-10',
        ]));
        $this->assertSame(200, $xml->status());
        $this->assertSame('application/vnd.ms-excel; charset=UTF-8', $xml->header('Content-Type'));
        $this->assertSame('attachment; filename="oems-events-report.xml"', $xml->header('Content-Disposition'));
        $this->assertTrue(str_contains($xml->body(), '<Workbook'));
        $this->assertFalse(str_contains($xml->body(), 'ss:Formula'));

        $invalid = $controller->pdf(Request::create('GET', '/admin/reports.pdf', query: ['type' => "events\r\nBad"]));
        $this->assertSame(422, $invalid->status());
        $this->assertNull($invalid->header('Content-Disposition'));
    }

    private function controller(): AdminReportController
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = new FakeUserRepository();
        $users->users[10] = [
            'id' => 10, 'role_id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => password_hash('TestPass!2026', PASSWORD_DEFAULT), 'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
        $this->authenticateSession($session, $users, 10);
        $repository = new FakeAnalyticsRepository();
        $repository->reportRows['events'] = [[
            'event_id' => 7, 'event_status' => 'published', 'start_date' => '2026-08-05 10:00:00',
            'capacity' => 10, 'confirmed_registrations' => 4, 'attendance_count' => 3,
            'favorites_count' => 5, 'published_review_count' => 2,
            'published_review_average' => '4.50', 'verified_payments' => '=2+2',
            'refund_attention_count' => 1, 'archived' => 0,
        ]];

        return new AdminReportController(
            new View(base_path('app/Views')), $session, $security, new Auth($session, $users),
            new Config(['name' => 'OEMS']),
            new ReportService($repository, new DateTimeImmutable('2026-08-10 10:00:00')),
            new ReportArtifactService(),
        );
    }
}
