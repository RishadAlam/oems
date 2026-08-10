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

final class AdminReportController extends Controller
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
        $result = $this->reportData($request);
        if (($result['success'] ?? false) !== true) {
            return Response::html($this->reportView([
                'type' => 'events',
                'range' => $this->reports->dateRange(null, null),
                'filters' => [],
                'columns' => [],
                'rows' => [],
            ], (string) ($result['error'] ?? 'The report filters are invalid.'))->body(), 422);
        }

        return $this->reportView($result['data']);
    }

    public function export(Request $request): Response
    {
        $preflight = $this->reportData($request);
        if (($preflight['success'] ?? false) !== true) {
            return Response::text((string) ($preflight['error'] ?? 'Invalid report filters.'), 422);
        }
        $type = (string) $preflight['data']['type'];
        $start = $preflight['data']['range']['start'];
        $end = $preflight['data']['range']['end'];
        $filters = $preflight['data']['filters'];

        return Response::stream(function (callable $emit) use ($type, $start, $end, $filters): void {
            $this->reports->streamAdminCsv($type, $start, $end, $filters, $emit);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="oems-' . $type . '-report.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        $result = $this->reports->adminArtifactData(
            $request->query('type'),
            $request->query('start'),
            $request->query('end'),
            $request->query('event_status'),
            $request->query('currency'),
        );
        if (($result['success'] ?? false) !== true) {
            return Response::text((string) ($result['error'] ?? 'Invalid report filters.'), 422);
        }
        $data = $result['data'];
        $type = (string) $data['type'];
        $title = 'OEMS ' . ucfirst($type) . ' report';
        $body = $format === 'pdf'
            ? $this->artifacts->pdf($title, $data['columns'], $data['rows'])
            : $this->artifacts->spreadsheetXml($title, $data['columns'], $data['rows']);

        return Response::binary($body, 200, $this->artifactHeaders(
            $format === 'pdf' ? 'application/pdf' : 'application/vnd.ms-excel; charset=UTF-8',
            'oems-' . $type . '-report.' . $format,
            strlen($body),
        ));
    }

    private function artifactHeaders(string $contentType, string $filename, int $length): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) $length,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function reportData(Request $request): array
    {
        return $this->reports->reportData(
            $request->query('type'),
            $request->query('start'),
            $request->query('end'),
            $request->query('event_status'),
            $request->query('currency'),
        );
    }

    private function reportView(array $data, ?string $filterError = null): Response
    {
        return $this->render('admin/reports/index', [
            'pageTitle' => 'Operational reports',
            'reportType' => $data['type'] ?? 'events',
            'range' => $data['range'] ?? $this->reports->dateRange(null, null),
            'filters' => $data['filters'] ?? [],
            'columns' => $data['columns'] ?? [],
            'rows' => $data['rows'] ?? [],
            'filterError' => $filterError,
        ], 'dashboard');
    }
}
