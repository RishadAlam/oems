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

        return $this->renderWorkspace(
            $context[2],
            scanCandidate: $this->pullScanCandidate((int) $context[1]),
        );
    }

    public function verify(Request $request): Response
    {
        $userId = $this->auth->id();
        $submitted = $request->query('token');
        $rawToken = is_scalar($submitted) ? trim((string) $submitted) : '';

        if ($userId === null) {
            $rawToken = null;

            return Response::redirect('/login');
        }

        if ($this->auth->hasRole('participant')) {
            $ticketId = $this->tickets->participantTicketIdByToken($userId, $rawToken);
            $rawToken = null;

            if ($ticketId === null) {
                $this->session->flash('error', 'This QR code is invalid or does not belong to your account.');

                return Response::redirect('/participant/tickets');
            }

            $this->session->flash(
                'info',
                'This is your ticket QR. Event staff must scan it from an organizer account to check you in.',
            );

            return Response::redirect('/participant/tickets/' . $ticketId);
        }

        if ($this->auth->hasRole('super-admin')) {
            $rawToken = null;
            $this->session->flash('info', 'Ticket check-in must be completed by the event organizer.');

            return Response::redirect('/admin/dashboard');
        }

        $candidate = $this->tickets->checkInCandidateByToken($userId, $rawToken);
        $rawToken = null;

        if ($candidate === null) {
            $this->session->flash('error', 'This QR code is invalid, expired, or belongs to another organizer.');

            return Response::redirect('/organizer/events');
        }

        $this->session->flash('check_in_candidate', $candidate);

        return Response::redirect('/organizer/events/' . $candidate['event_id'] . '/check-in');
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

    private function pullScanCandidate(int $eventId): ?array
    {
        $candidate = $this->session->pullFlash('check_in_candidate');

        if (!is_array($candidate)) {
            return null;
        }

        $candidateEventId = (int) ($candidate['event_id'] ?? 0);
        $ticketNumber = strtoupper((string) ($candidate['ticket_number'] ?? ''));

        if (
            $candidateEventId !== $eventId
            || preg_match('/\AOEMS-[A-Z0-9-]{4,35}\z/', $ticketNumber) !== 1
        ) {
            return null;
        }

        return [
            'event_id' => $candidateEventId,
            'ticket_number' => $ticketNumber,
        ];
    }

    private function renderWorkspace(
        array $event,
        ?string $scanError = null,
        int $status = 200,
        ?array $scanCandidate = null,
    ): Response
    {
        $response = $this->render('organizer/check-in/index', [
            'pageTitle' => 'Event check-in',
            'event' => $event,
            'scanError' => $scanError,
            'scanCandidate' => $scanCandidate,
        ], 'dashboard');

        return $status === 200 ? $response : Response::html($response->body(), $status);
    }
}
