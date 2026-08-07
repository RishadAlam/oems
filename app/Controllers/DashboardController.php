<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\EventRepositoryInterface;
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
        private readonly EventRepositoryInterface $events,
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
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        return $this->render('dashboard/organizer', [
            'pageTitle' => 'Organizer workspace',
            'summary' => $this->events->organizerSummary($userId),
            'events' => $this->events->recentForOrganizerUser($userId, 5),
        ], 'dashboard');
    }

    public function admin(Request $request): Response
    {
        $metrics = $this->dashboardMetrics->totals();
        $metrics['pending_reviews'] = $this->events->countPendingForAdmin();

        return $this->render('dashboard/admin', [
            'pageTitle' => 'Platform overview',
            'metrics' => $metrics,
        ], 'dashboard');
    }
}
