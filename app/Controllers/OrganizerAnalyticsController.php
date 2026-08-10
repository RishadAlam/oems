<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\ReportService;
use OEMS\App\Services\ReportArtifactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerAnalyticsController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly ReportService $reports,
        private readonly ReportArtifactService $artifacts,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::text('Not Found', 404);
        }
        $result = $this->reports->organizerData(
            $userId,
            $request->query('start'),
            $request->query('end'),
            $request->query('event'),
        );
        if (($result['code'] ?? null) === 'not_found') {
            return Response::text('Not Found', 404);
        }
        if (($result['success'] ?? false) !== true) {
            return Response::html($this->analyticsView([
                'range' => $this->reports->dateRange(null, null),
                'event_id' => null,
                'summary' => [],
                'rows' => [],
            ], (string) ($result['error'] ?? 'The analytics filters are invalid.'))->body(), 422);
        }

        return $this->analyticsView($result['data']);
    }

    public function export(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::text('Not Found', 404);
        }
        $start = $request->query('start');
        $end = $request->query('end');
        $event = $request->query('event');
        $preflight = $this->reports->organizerData($userId, $start, $end, $event);
        if (($preflight['code'] ?? null) === 'not_found') {
            return Response::text('Not Found', 404);
        }
        if (($preflight['success'] ?? false) !== true) {
            return Response::text((string) ($preflight['error'] ?? 'Invalid analytics filters.'), 422);
        }

        return Response::stream(function (callable $emit) use ($userId, $start, $end, $event): void {
            $this->reports->streamOrganizerCsv($userId, $start, $end, $event, $emit);
        }, 200, $this->csvHeaders('oems-organizer-analytics.csv'));
    }

    public function pdf(Request $request): Response
    {
        return $this->artifact($request, 'pdf');
    }

    public function spreadsheet(Request $request): Response
    {
        return $this->artifact($request, 'xml');
    }

    private function artifact(Request $request, string $format): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::text('Not Found', 404);
        }
        $result = $this->reports->organizerArtifactData(
            $userId,
            $request->query('start'),
            $request->query('end'),
            $request->query('event'),
        );
        if (($result['code'] ?? null) === 'not_found') {
            return Response::text('Not Found', 404);
        }
        if (($result['success'] ?? false) !== true) {
            return Response::text((string) ($result['error'] ?? 'Invalid analytics filters.'), 422);
        }
        $data = $result['data'];
        $body = $format === 'pdf'
            ? $this->artifacts->pdf('OEMS organizer analytics', $data['columns'], $data['rows'])
            : $this->artifacts->spreadsheetXml('OEMS organizer analytics', $data['columns'], $data['rows']);

        return Response::binary($body, 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="oems-organizer-analytics.' . $format . '"',
            'Content-Length' => (string) strlen($body),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function analyticsView(array $data, ?string $filterError = null): Response
    {
        return $this->render('organizer/analytics/index', [
            'pageTitle' => 'Organizer analytics',
            'range' => $data['range'] ?? $this->reports->dateRange(null, null),
            'eventId' => $data['event_id'] ?? null,
            'summary' => $data['summary'] ?? [],
            'rows' => $data['rows'] ?? [],
            'charts' => $data['charts'] ?? [],
            'analyticsChartsEnabled' => true,
            'filterError' => $filterError,
        ], 'dashboard');
    }

    private function csvHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
