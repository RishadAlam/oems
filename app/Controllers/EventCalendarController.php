<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use InvalidArgumentException;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Services\CalendarService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class EventCalendarController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly EventRepositoryInterface $events,
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly CalendarService $calendar,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function publicIcs(Request $request): Response
    {
        $event = $this->publicEvent($request);
        if ($event === null) {
            return $this->notFound();
        }

        try {
            return $this->ics(
                $this->calendar->forPublicEvent($event),
                $this->filename((string) $event['slug']),
                'public, max-age=300',
            );
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }
    }

    public function publicGoogle(Request $request): Response
    {
        $event = $this->publicEvent($request);
        if ($event === null) {
            return $this->notFound();
        }

        try {
            return Response::redirect($this->calendar->googleUrl($event, false), 302, [
                'Cache-Control' => 'no-store',
            ]);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }
    }

    public function registrationIcs(Request $request): Response
    {
        $registration = $this->ownedRegistration($request);
        if ($registration === null) {
            return $this->notFound();
        }

        try {
            return $this->ics(
                $this->calendar->forOwnedRegistration($registration),
                $this->filename((string) ($registration['event_slug'] ?? 'event')),
                'private, no-store, max-age=0',
            );
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }
    }

    public function registrationGoogle(Request $request): Response
    {
        $registration = $this->ownedRegistration($request);
        if ($registration === null) {
            return $this->notFound();
        }

        try {
            return Response::redirect($this->calendar->googleUrl($registration, true), 302, [
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }
    }

    private function publicEvent(Request $request): ?array
    {
        $slug = $request->route('slug');
        if (!is_string($slug) || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) !== 1) {
            return null;
        }
        $event = $this->events->findPublishedBySlug($slug);

        return is_array($event)
            && in_array(($event['status'] ?? null), ['published', 'completed'], true)
            && empty($event['deleted_at'])
                ? $event
                : null;
    }

    private function ownedRegistration(Request $request): ?array
    {
        $id = filter_var($request->route('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $userId = $this->auth->id();

        return $id === false || $userId === null
            ? null
            : $this->registrations->findCalendarForParticipant($userId, (int) $id);
    }

    private function ics(string $body, string $filename, string $cacheControl): Response
    {
        return Response::text($body, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filename(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9-]+/', '-', mb_strtolower($slug)) ?? 'event';
        $slug = trim($slug, '-');

        return ($slug !== '' ? mb_substr($slug, 0, 120) : 'event') . '.ics';
    }

    private function notFound(): Response
    {
        return Response::html('<h1>404</h1><p>The requested calendar is unavailable.</p>', 404);
    }
}
