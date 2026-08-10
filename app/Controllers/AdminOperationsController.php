<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\HealthCheckService;
use OEMS\App\Services\MaintenanceService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use Throwable;

final class AdminOperationsController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly MaintenanceService $maintenance,
        private readonly HealthCheckService $health,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        return $this->page();
    }

    public function updateMaintenance(Request $request): Response
    {
        $rawEnabled = $request->input('enabled');
        $enabled = $rawEnabled === '1' ? true : ($rawEnabled === '0' ? false : null);
        $confirmation = is_scalar($request->input('confirmation')) ? trim((string) $request->input('confirmation')) : '';
        $expected = $enabled === true ? 'ENABLE MAINTENANCE' : 'DISABLE MAINTENANCE';

        if ($enabled === null || !hash_equals($expected, $confirmation)) {
            return $this->page(['confirmation' => ['Type the exact confirmation phrase shown below.']], 422);
        }

        try {
            $this->maintenance->setEnabled($enabled, (int) $this->auth->id());
        } catch (Throwable) {
            return $this->page(['operations' => ['Maintenance state could not be changed.']], 503);
        }

        $this->session->flash('success', $enabled ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.');

        return Response::redirect('/admin/operations');
    }

    private function page(array $errors = [], int $status = 200): Response
    {
        $enabled = $this->maintenance->isEnabled();
        $response = $this->render('admin/operations/index', [
            'pageTitle' => 'Operations',
            'maintenanceEnabled' => $enabled,
            'readiness' => $this->health->ready(),
            'errors' => $errors,
        ], 'dashboard');

        return $status === 200 ? $response : Response::html($response->body(), $status, $response->headers());
    }
}
