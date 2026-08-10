<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Controllers\AdminAnalyticsController;
use OEMS\App\Controllers\AdminReportController;
use OEMS\App\Controllers\OrganizerAnalyticsController;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\ReportService;
use OEMS\App\Services\ReportArtifactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeAnalyticsRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class AnalyticsControllerTest extends TestCase
{
    public function testOrganizerAnalyticsRendersEscapedSemanticResponsiveSummary(): void
    {
        [$controller, $repository] = $this->organizerController();
        $response = $controller->index(Request::create('GET', '/organizer/analytics', query: [
            'start' => '2026-08-01',
            'end' => '2026-08-10',
        ]));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Organizer analytics'));
        $this->assertTrue(str_contains($response->body(), 'name="start" type="date" value="2026-08-01"'));
        $this->assertTrue(str_contains($response->body(), 'href="/organizer/analytics.csv?'));
        $this->assertTrue(str_contains($response->body(), '&lt;script&gt;event&lt;/script&gt;'));
        $this->assertFalse(str_contains($response->body(), '<script>event</script>'));
        foreach (['Event', 'Lifecycle', 'Registrations', 'Attendance', 'Payments', 'Engagement'] as $label) {
            $this->assertTrue(str_contains($response->body(), 'data-label="' . $label . '"'));
        }
        $this->assertTrue(str_contains($response->body(), 'aria-label="Capacity utilization: 40.0 percent"'));
        $this->assertTrue(str_contains($response->body(), 'BDT 42.30'));
        $this->assertTrue(str_contains($response->body(), '/assets/vendor/chartjs/chart.umd.min.js'));
        $this->assertTrue(str_contains($response->body(), 'id="analytics-chart-data"'));
        foreach (['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'] as $status) {
            $this->assertTrue(str_contains($response->body(), 'data-lifecycle-status="' . $status . '"'));
        }
        foreach (['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'] as $status) {
            $this->assertTrue(str_contains($response->body(), 'data-registration-status="' . $status . '"'));
        }
        $this->assertSame('organizerSummary', $repository->calls[0][0]);
    }

    public function testOrganizerAnalyticsRejectsInvalidDatesAndForeignEventWithoutEchoingInput(): void
    {
        [$controller, $repository] = $this->organizerController();
        $invalid = $controller->index(Request::create('GET', '/organizer/analytics', query: [
            'start' => ['nested'],
            'end' => '2026-08-10',
        ]));
        $repository->foreignEventIds = [99];
        $foreign = $controller->index(Request::create('GET', '/organizer/analytics', query: [
            'event' => '99',
        ]));

        $this->assertSame(422, $invalid->status());
        $this->assertTrue(str_contains($invalid->body(), 'role="alert"'));
        $this->assertFalse(str_contains($invalid->body(), 'nested'));
        $this->assertSame(404, $foreign->status());
        $this->assertSame('Not Found', $foreign->body());
        $this->assertSame(404, $controller->export(Request::create('GET', '/organizer/analytics.csv', query: ['event' => '99']))->status());
    }

    public function testOrganizerCsvIsPrivateStreamedAndFormulaSafe(): void
    {
        [$controller] = $this->organizerController();
        $response = $controller->export(Request::create('GET', '/organizer/analytics.csv', query: [
            'start' => '2026-08-01', 'end' => '2026-08-10',
        ]));

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame('text/csv; charset=UTF-8', $response->header('Content-Type'));
        $this->assertSame('private, no-store', $response->header('Cache-Control'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('attachment; filename="oems-organizer-analytics.csv"', $response->header('Content-Disposition'));
        ob_start();
        $response->send();
        $csv = ob_get_clean();
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertTrue(str_contains($csv, "'=<script>event</script>"));
        foreach (['email', 'gateway_response', 'token', 'address', 'latitude', 'longitude'] as $forbidden) {
            $this->assertFalse(str_contains(strtolower($csv), $forbidden));
        }
    }

    public function testOrganizerPdfAndSpreadsheetArePrivateAndOwnerScoped(): void
    {
        [$controller, $repository] = $this->organizerController();
        $pdf = $controller->pdf(Request::create('GET', '/organizer/analytics.pdf', query: [
            'start' => '2026-08-01', 'end' => '2026-08-10',
        ]));
        $this->assertSame(200, $pdf->status());
        $this->assertSame('application/pdf', $pdf->header('Content-Type'));
        $this->assertSame('private, no-store', $pdf->header('Cache-Control'));
        $this->assertTrue(str_starts_with($pdf->body(), '%PDF-'));

        $xml = $controller->spreadsheet(Request::create('GET', '/organizer/analytics.xml'));
        $this->assertSame(200, $xml->status());
        $this->assertSame('application/vnd.ms-excel; charset=UTF-8', $xml->header('Content-Type'));
        $this->assertFalse(str_contains($xml->body(), 'ss:Formula'));
        $this->assertFalse(str_contains(strtolower($xml->body()), 'participant email'));

        $repository->foreignEventIds = [99];
        $this->assertSame(404, $controller->pdf(Request::create('GET', '/organizer/analytics.pdf', query: ['event' => '99']))->status());
        $this->assertSame(404, $controller->spreadsheet(Request::create('GET', '/organizer/analytics.xml', query: ['event' => '99']))->status());
    }

    public function testAdminAnalyticsAppliesAllowlistedFiltersAndEscapesBreakdowns(): void
    {
        [$controller, $repository] = $this->adminController();
        $response = $controller->index(Request::create('GET', '/admin/analytics', query: [
            'start' => '2026-08-01', 'end' => '2026-08-10',
            'event_status' => 'published', 'currency' => 'usd',
        ]));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Platform analytics'));
        $this->assertTrue(str_contains($response->body(), 'value="published" selected'));
        $this->assertTrue(str_contains($response->body(), 'name="currency" maxlength="3" value="USD"'));
        $this->assertTrue(str_contains($response->body(), '&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertFalse(str_contains($response->body(), '<img src=x onerror=alert(1)>'));
        $this->assertTrue(str_contains($response->body(), '/assets/js/analytics-charts.js'));
        $this->assertFalse(str_contains($response->body(), '<script>Chart category</script>'));
        $this->assertSame(['event_status' => 'published', 'currency' => 'USD'], $repository->calls[0][3]);
        foreach (['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'] as $status) {
            $this->assertTrue(str_contains($response->body(), 'data-lifecycle-status="' . $status . '"'));
        }
        foreach (['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'] as $status) {
            $this->assertTrue(str_contains($response->body(), 'data-registration-status="' . $status . '"'));
        }

        $invalid = $controller->index(Request::create('GET', '/admin/analytics', query: ['event_status' => 'private']));
        $this->assertSame(422, $invalid->status());
        $this->assertTrue(str_contains($invalid->body(), 'role="alert"'));
    }

    public function testAnalyticsRoutesRequireTheirRoleAndRejectWrongMethods(): void
    {
        $this->assertSame(200, $this->router('organizer')->dispatch(Request::create('GET', '/organizer/analytics'))->status());
        $this->assertSame(403, $this->router('organizer')->dispatch(Request::create('GET', '/admin/analytics'))->status());
        $this->assertSame(200, $this->router('super-admin')->dispatch(Request::create('GET', '/admin/analytics'))->status());
        $this->assertSame(403, $this->router('super-admin')->dispatch(Request::create('GET', '/organizer/analytics'))->status());
        $this->assertSame(403, $this->router('participant')->dispatch(Request::create('GET', '/organizer/analytics'))->status());
        $this->assertSame('/login', $this->router(null)->dispatch(Request::create('GET', '/admin/analytics'))->header('Location'));
        $this->assertSame(405, $this->router('organizer')->dispatch(Request::create('POST', '/organizer/analytics'))->status());
        $this->assertSame(405, $this->router('super-admin')->dispatch(Request::create('POST', '/admin/analytics'))->status());
        $this->assertSame(200, $this->router('organizer')->dispatch(Request::create('GET', '/organizer/analytics.csv'))->status());
        $this->assertSame(403, $this->router('participant')->dispatch(Request::create('GET', '/organizer/analytics.csv'))->status());
        $this->assertSame(405, $this->router('organizer')->dispatch(Request::create('POST', '/organizer/analytics.csv'))->status());
        $this->assertSame(200, $this->router('organizer')->dispatch(Request::create('GET', '/organizer/analytics.pdf'))->status());
        $this->assertSame(200, $this->router('organizer')->dispatch(Request::create('GET', '/organizer/analytics.xml'))->status());
        $this->assertSame(403, $this->router('participant')->dispatch(Request::create('GET', '/organizer/analytics.pdf'))->status());
        $this->assertSame(405, $this->router('organizer')->dispatch(Request::create('POST', '/organizer/analytics.xml'))->status());
        $this->assertSame(200, $this->router('super-admin')->dispatch(Request::create('GET', '/admin/reports'))->status());
        $this->assertSame(200, $this->router('super-admin')->dispatch(Request::create('GET', '/admin/reports.csv'))->status());
        $this->assertSame(403, $this->router('organizer')->dispatch(Request::create('GET', '/admin/reports'))->status());
        $this->assertSame(403, $this->router('participant')->dispatch(Request::create('GET', '/admin/reports.csv'))->status());
        $this->assertSame('/login', $this->router(null)->dispatch(Request::create('GET', '/admin/reports'))->header('Location'));
        $this->assertSame(405, $this->router('super-admin')->dispatch(Request::create('POST', '/admin/reports.csv'))->status());
        $this->assertSame(200, $this->router('super-admin')->dispatch(Request::create('GET', '/admin/reports.pdf'))->status());
        $this->assertSame(200, $this->router('super-admin')->dispatch(Request::create('GET', '/admin/reports.xml'))->status());
        $this->assertSame(403, $this->router('participant')->dispatch(Request::create('GET', '/admin/reports.pdf'))->status());
        $this->assertSame(405, $this->router('super-admin')->dispatch(Request::create('POST', '/admin/reports.xml'))->status());
    }

    private function organizerController(): array
    {
        [$view, $session, $security, $auth, $config] = $this->dependencies('organizer');
        $repository = $this->repository();

        return [new OrganizerAnalyticsController(
            $view, $session, $security, $auth, $config,
            new ReportService($repository, new DateTimeImmutable('2026-08-10 10:00:00')),
            new ReportArtifactService(),
        ), $repository];
    }

    private function adminController(): array
    {
        [$view, $session, $security, $auth, $config] = $this->dependencies('super-admin');
        $repository = $this->repository();

        return [new AdminAnalyticsController(
            $view, $session, $security, $auth, $config,
            new ReportService($repository, new DateTimeImmutable('2026-08-10 10:00:00')),
        ), $repository];
    }

    private function repository(): FakeAnalyticsRepository
    {
        $repository = new FakeAnalyticsRepository();
        $repository->organizerSummary = $this->summary();
        $repository->adminSummary = array_merge($this->summary(), [
            'active_users' => 15,
            'approved_organizers' => 4,
            'pending_event_queue' => 2,
            'pending_payment_queue' => 3,
            'top_events' => [['event_id' => 7, 'event_status' => '<img src=x onerror=alert(1)>', 'registration_count' => 8]],
            'top_categories' => [['category_id' => 3, 'category_name' => '<script>category</script>', 'registration_count' => 9]],
        ]);
        $repository->organizerRows = [[
            'event_id' => 7,
            'event_title' => '=<script>event</script>',
            'event_status' => 'published',
            'start_date' => '2026-08-05 10:00:00',
            'capacity' => 10,
            'archived' => 0,
            'registration_counts' => ['confirmed' => 4, 'pending' => 1, 'cancelled' => 1],
            'attendance_count' => 3,
            'favorites_count' => 5,
            'review_count' => 2,
            'review_average' => '4.50',
            'verified_payments' => ['BDT' => '42.30'],
            'refund_attention_count' => 1,
        ]];
        $series = [
            'granularity' => 'day',
            'periods' => ['2026-08-05'],
            'events' => ['2026-08-05' => 1],
            'registrations' => ['2026-08-05' => 4],
            'attendance' => ['2026-08-05' => 3],
            'payments' => ['BDT' => ['2026-08-05' => '42.30']],
            'categories' => [['label' => '<script>Chart category</script>', 'count' => 4]],
        ];
        $repository->organizerSeries = $series;
        $repository->adminSeries = $series;

        return $repository;
    }

    private function summary(): array
    {
        return [
            'lifecycle' => ['total' => 2, 'draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'published' => 1, 'completed' => 1, 'cancelled' => 0],
            'registrations' => ['total' => 6, 'pending' => 1, 'confirmed' => 4, 'cancelled' => 1, 'waitlisted' => 0, 'refunded' => 0],
            'attendance_count' => 3,
            'favorites_count' => 5,
            'reviews' => ['published' => 2, 'average' => '4.50'],
            'verified_payments' => ['BDT' => '42.30'],
            'refund_attention_count' => 1,
            'capacity_total' => 10,
            'capacity_utilization_rate' => '40.0',
            'attendance_rate' => '75.0',
        ];
    }

    private function router(?string $role): Router
    {
        [$view, $session, $security, $auth, $config] = $this->dependencies($role);
        $service = new ReportService($this->repository(), new DateTimeImmutable('2026-08-10 10:00:00'));
        $container = new Container();
        $artifacts = new ReportArtifactService();
        $container->instance(OrganizerAnalyticsController::class, new OrganizerAnalyticsController($view, $session, $security, $auth, $config, $service, $artifacts));
        $container->instance(AdminAnalyticsController::class, new AdminAnalyticsController($view, $session, $security, $auth, $config, $service));
        $container->instance(AdminReportController::class, new AdminReportController($view, $session, $security, $auth, $config, $service, $artifacts));
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $routes = require base_path('routes/web.php');
        $routes($router);

        return $router;
    }

    private function dependencies(?string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = new FakeUserRepository();
        if ($role !== null) {
            $users->users[10] = [
                'id' => 10, 'role_id' => $role === 'organizer' ? 2 : ($role === 'super-admin' ? 1 : 3),
                'name' => 'Analytics User', 'email' => 'analytics@example.test',
                'password' => password_hash('TestPass!2026', PASSWORD_DEFAULT),
                'status' => 'active', 'email_verified_at' => '2026-08-01 09:00:00',
            ];
            $this->authenticateSession($session, $users, 10);
        }
        $auth = new Auth($session, $users);

        return [new View(base_path('app/Views')), $session, $security, $auth, new Config(['name' => 'OEMS'])];
    }
}
