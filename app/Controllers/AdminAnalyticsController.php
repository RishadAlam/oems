<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\ReportService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminAnalyticsController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly ReportService $reports,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $result = $this->reports->adminData(
            $request->query('start'),
            $request->query('end'),
            $request->query('event_status'),
            $request->query('currency'),
        );
        if (($result['success'] ?? false) !== true) {
            return Response::html($this->analyticsView([
                'range' => $this->reports->dateRange(null, null),
                'filters' => [],
                'summary' => [],
            ], (string) ($result['error'] ?? 'The analytics filters are invalid.'))->body(), 422);
        }

        return $this->analyticsView($result['data']);
    }

    private function analyticsView(array $data, ?string $filterError = null): Response
    {
        return $this->render('admin/analytics/index', [
            'pageTitle' => 'Platform analytics',
            'range' => $data['range'] ?? $this->reports->dateRange(null, null),
            'filters' => $data['filters'] ?? [],
            'summary' => $data['summary'] ?? [],
            'charts' => $data['charts'] ?? [],
            'analyticsChartsEnabled' => true,
            'filterError' => $filterError,
        ], 'dashboard');
    }
}
