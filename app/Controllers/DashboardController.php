<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\PaymentRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Contracts\TicketRepositoryInterface;
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
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly PaymentRepositoryInterface $payments,
        private readonly TicketRepositoryInterface $tickets,
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
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        return $this->render('dashboard/participant', [
            'pageTitle' => 'Your dashboard',
            'metrics' => [
                'registration' => $this->registrations->summaryForParticipant($userId),
                'payment' => $this->payments->summaryForParticipant($userId),
                'ticket' => $this->tickets->summaryForParticipant($userId),
                'reviews' => $this->dashboardMetrics->reviewsForParticipant($userId),
            ],
            'workspace' => $this->dashboardMetrics->participantWorkspace($userId),
        ], 'dashboard');
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
            'transactionMetrics' => [
                'registration' => $this->registrations->summaryForOrganizer($userId),
                'payment' => $this->payments->summaryForOrganizer($userId),
                'ticket' => $this->tickets->summaryForOrganizer($userId),
                'reviews' => $this->dashboardMetrics->reviewsForOrganizer($userId),
            ],
        ], 'dashboard');
    }

    public function admin(Request $request): Response
    {
        $metrics = $this->dashboardMetrics->totals();
        $metrics['pending_reviews'] = $this->events->countPendingForAdmin();
        $metrics['registration'] = $this->registrations->summaryForAdmin();
        $metrics['payment'] = $this->payments->summaryForAdmin();
        $metrics['ticket'] = $this->tickets->summaryForAdmin();
        $metrics['reviews'] = $this->dashboardMetrics->reviewsForAdmin();

        return $this->render('dashboard/admin', [
            'pageTitle' => 'Platform overview',
            'metrics' => $metrics,
        ], 'dashboard');
    }
}
