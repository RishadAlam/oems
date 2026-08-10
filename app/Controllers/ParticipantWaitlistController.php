<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Services\WaitlistService;
use OEMS\App\Support\Money;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class ParticipantWaitlistController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly WaitlistService $waitlists,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $participantId = $this->auth->id();
        if ($participantId === null) {
            return Response::redirect('/login');
        }

        return $this->render('participant/waitlist/index', [
            'pageTitle' => 'My waitlist',
            'entries' => array_map($this->present(...), $this->waitlists->forParticipant($participantId)),
        ], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $eventId = $this->positiveId($request->route('id'));
        if ($eventId === null) {
            return $this->notFound();
        }
        $result = $this->waitlists->join((int) ($this->auth->id() ?? 0), $eventId);
        if (!($result['success'] ?? false)) {
            return $this->redirectWithErrors(
                '/participant/waitlist',
                is_array($result['errors'] ?? null) ? $result['errors'] : ['waitlist' => ['The event could not be added to your waitlist.']],
            );
        }

        return $this->redirectWith('/participant/waitlist', 'success', 'You joined the event waitlist.');
    }

    public function destroy(Request $request): Response
    {
        $registrationId = $this->positiveId($request->route('id'));
        if ($registrationId === null) {
            return $this->notFound();
        }
        $reason = is_scalar($request->input('reason')) ? (string) $request->input('reason') : '';
        $result = $this->waitlists->leave((int) ($this->auth->id() ?? 0), $registrationId, $reason);
        if (!($result['success'] ?? false)) {
            return $this->redirectWithErrors(
                '/participant/waitlist',
                is_array($result['errors'] ?? null) ? $result['errors'] : ['waitlist' => ['The waitlist entry could not be updated.']],
                ['reason' => mb_substr(trim($reason), 0, 500), 'registration_id' => $registrationId],
            );
        }

        return $this->redirectWith('/participant/waitlist', 'success', 'You left the event waitlist.');
    }

    private function present(array $entry): array
    {
        $start = trim((string) ($entry['event_start_date'] ?? ''));
        return array_merge($entry, [
            'start_display' => $start === '' ? 'Schedule unavailable' : (new DateTimeImmutable($start, $this->timezone()))->format('M j, Y, g:i A'),
            'amount_display' => Money::format($entry['amount'] ?? null, (string) ($entry['currency'] ?? 'BDT')),
        ]);
    }

    private function positiveId(mixed $value): ?int
    {
        return is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) === 1 ? (int) $value : null;
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', ['pageTitle' => 'Waitlist entry not found'], 'dashboard');
        return Response::html($response->body(), 404);
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'));
    }
}
