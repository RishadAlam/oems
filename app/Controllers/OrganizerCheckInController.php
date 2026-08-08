<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Services\TicketService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerCheckInController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly TicketService $tickets,
        private readonly RateLimiter $limiter,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $context = $this->ownedContext($request);

        if ($context === null) {
            return Response::text('Not Found', 404);
        }

        return $this->renderWorkspace($context[2]);
    }

    public function store(Request $request): Response
    {
        $context = $this->ownedContext($request);

        if ($context === null) {
            return Response::text('Not Found', 404);
        }

        [$userId, $eventId, $event] = $context;
        $limitKey = 'organizer-check-in:' . $userId . ':' . hash('sha256', $request->ip());

        if (!$this->limiter->consumeAttempt($limitKey)) {
            return $this->renderWorkspace(
                $event,
                'Too many unsuccessful attempts. Wait before trying again.',
                429,
            );
        }

        $submitted = $request->input('code');
        $result = $this->tickets->checkIn(
            $userId,
            $eventId,
            is_scalar($submitted) ? (string) $submitted : '',
            $userId,
            $request->ip(),
        );
        $submitted = null;

        if ($result === null) {
            $this->session->flash('error', 'Ticket not found or not eligible for this event.');

            return Response::redirect('/organizer/events/' . $eventId . '/check-in');
        }

        $this->limiter->clear($limitKey);

        if (!empty($result['duplicate'])) {
            $time = is_scalar($result['scanned_at'] ?? null) ? (string) $result['scanned_at'] : '';
            $this->session->flash('info', 'This ticket was already checked in' . ($time === '' ? '.' : ' at ' . $time . '.'));
        } else {
            $this->session->flash('success', 'Ticket checked in.');
        }

        return Response::redirect('/organizer/events/' . $eventId . '/check-in');
    }

    private function ownedContext(Request $request): ?array
    {
        $userId = $this->auth->id();
        $value = $request->route('id');
        $eventId = (is_int($value) || is_string($value))
            && ctype_digit((string) $value)
            && (int) $value > 0
                ? (int) $value
                : null;

        if ($userId === null || $eventId === null) {
            return null;
        }

        $event = $this->registrations->findOrganizerEvent($userId, $eventId);

        return $event === null ? null : [$userId, $eventId, $event];
    }

    private function renderWorkspace(array $event, ?string $scanError = null, int $status = 200): Response
    {
        $response = $this->render('organizer/check-in/index', [
            'pageTitle' => 'Event check-in',
            'event' => $event,
            'scanError' => $scanError,
        ], 'dashboard');

        return $status === 200 ? $response : Response::html($response->body(), $status);
    }
}
