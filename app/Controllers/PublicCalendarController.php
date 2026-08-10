<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\PublicEventApiService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class PublicCalendarController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly PublicEventApiService $events,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $calendar = $this->events->calendar($request->query('month', ''));
        $description = 'Browse public OEMS events by month with a complete chronological list.';
        $response = $this->render('events/calendar', [
            'pageTitle' => ($calendar['success'] ?? false) ? (string) $calendar['label'] . ' event calendar' : 'Event calendar',
            'metaDescription' => $description,
            'canonicalUrl' => rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/') . '/events/calendar',
            'openGraph' => [
                'type' => 'website',
                'title' => 'Event calendar',
                'description' => $description,
                'url' => rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/') . '/events/calendar',
            ],
            'calendar' => $calendar,
        ]);

        return ($calendar['success'] ?? false)
            ? $response
            : Response::html($response->body(), 422, ['Cache-Control' => 'no-store']);
    }
}
