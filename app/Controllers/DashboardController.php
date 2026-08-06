<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class DashboardController extends Controller
{
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
        return $this->render('dashboard/admin', ['pageTitle' => 'Platform overview'], 'dashboard');
    }
}

