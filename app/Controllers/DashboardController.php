<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly DashboardMetricsRepository $dashboardMetrics,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        return match ((string) ($this->auth->user()['role_slug'] ?? '')) {
            'super-admin' => Response::redirect('/admin/dashboard'),
            'organizer' => Response::redirect('/organizer/dashboard'),
            'participant' => Response::redirect('/participant/dashboard'),
            default => Response::redirect('/login'),
        };
    }

    public function participant(Request $request): Response
    {
        return $this->render('dashboard/participant', ['pageTitle' => 'Your dashboard'], 'dashboard');
    }

    public function organizer(Request $request): Response
    {
        return $this->render('dashboard/organizer', ['pageTitle' => 'Organizer workspace'], 'dashboard');
    }

    public function admin(Request $request): Response
    {
        return $this->render('dashboard/admin', [
            'pageTitle' => 'Platform overview',
            'metrics' => $this->dashboardMetrics->totals(),
        ], 'dashboard');
    }
}
