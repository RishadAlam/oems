<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\VenueRepositoryInterface;
use OEMS\App\Services\EventService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerEventController extends Controller
{
    private const FIELDS = [
        'category_id',
        'venue_id',
        'title',
        'description',
        'map_url',
        'speaker',
        'start_date',
        'end_date',
        'registration_deadline',
        'capacity',
        'ticket_price',
        'tags',
        'location_visibility',
        'arrival_notes',
        'waitlist_enabled',
    ];

    private const STATUSES = [
        'draft',
        'pending',
        'approved',
        'rejected',
        'published',
        'completed',
        'cancelled',
    ];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly EventRepositoryInterface $events,
        private readonly CategoryRepositoryInterface $categories,
        private readonly VenueRepositoryInterface $venues,
        private readonly EventService $eventService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && in_array($requestedStatus, self::STATUSES, true)
            ? $requestedStatus
            : null;

        return $this->render('organizer/events/index', [
            'pageTitle' => 'Events',
            'events' => $this->events->forOrganizerUser($userId, $status),
            'summary' => $this->events->organizerSummary($userId),
            'status' => $status,
            'statuses' => self::STATUSES,
        ], 'dashboard');
    }

    public function create(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        return $this->renderForm(null, $userId);
    }

    public function store(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        $data = $this->safeInput($request);
        $result = $this->eventService->createDraft(
            $userId,
            $data,
            $request->file('banner'),
            $this->galleryUploads($request->file('gallery')),
        );

        if (!$result['success']) {
            return $this->redirectWithErrors('/organizer/events/create', $result['errors'], $data);
        }

        $this->session->flash('success', 'Event draft created.');

        return Response::redirect('/organizer/events/' . (int) $result['event_id']);
    }

    public function show(Request $request): Response
    {
        $event = $this->ownedEvent($request);

        if ($event === null) {
            return $this->notFound();
        }

        $userId = $this->auth->id();

        return $this->render('organizer/events/show', [
            'pageTitle' => (string) $event['title'],
            'event' => $event,
            'gallery' => $userId === null
                ? []
                : $this->events->galleryForOwned($userId, (int) $event['id']),
        ], 'dashboard');
    }

    public function edit(Request $request): Response
    {
        $event = $this->ownedEvent($request);

        if ($event === null) {
            return $this->notFound();
        }

        return $this->renderForm($event, (int) $this->auth->id());
    }

    public function update(Request $request): Response
    {
        $eventId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($eventId === null || $userId === null || $this->events->findOwned($userId, $eventId) === null) {
            return $this->notFound();
        }

        $data = $this->safeInput($request);
        $result = $this->eventService->update(
            $userId,
            $eventId,
            $data,
            $request->file('banner'),
            $this->galleryUploads($request->file('gallery')),
        );

        if (!$result['success']) {
            return $this->redirectWithErrors(
                '/organizer/events/' . $eventId . '/edit',
                $result['errors'],
                $data,
            );
        }

        $this->session->flash('success', 'Event updated successfully.');

        return Response::redirect('/organizer/events/' . $eventId);
    }

    public function submit(Request $request): Response
    {
        return $this->statusAction($request, 'submit');
    }

    public function cancel(Request $request): Response
    {
        return $this->statusAction($request, 'cancel');
    }

    public function publish(Request $request): Response
    {
        return $this->statusAction($request, 'publish', 409);
    }

    public function delete(Request $request): Response
    {
        return $this->statusAction($request, 'delete');
    }

    private function renderForm(?array $event, int $userId): Response
    {
        return $this->render('organizer/events/form', [
            'pageTitle' => $event === null ? 'Create event' : 'Edit event',
            'event' => $event,
            'categories' => $this->categories->active(),
            'venues' => $this->venues->forOrganizerUser($userId),
            'gallery' => $event === null
                ? []
                : $this->events->galleryForOwned($userId, (int) $event['id']),
        ], 'dashboard');
    }

    private function statusAction(Request $request, string $action, ?int $failureStatus = null): Response
    {
        $eventId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($eventId === null || $userId === null || $this->events->findOwned($userId, $eventId) === null) {
            return $this->notFound();
        }

        $result = $this->eventService->{$action}($userId, $eventId);

        if (!$result['success']) {
            if ($failureStatus !== null) {
                return Response::text('Conflict', $failureStatus);
            }

            return $this->redirectWith(
                '/organizer/events/' . $eventId,
                'error',
                $this->firstError($result['errors']),
            );
        }

        $messages = [
            'submit' => 'Event submitted for review.',
            'publish' => 'Event published.',
            'cancel' => 'Event cancelled.',
            'delete' => 'Event deleted.',
        ];
        $this->session->flash('success', $messages[$action]);

        return Response::redirect(
            $action === 'delete' ? '/organizer/events' : '/organizer/events/' . $eventId,
        );
    }

    private function ownedEvent(Request $request): ?array
    {
        $eventId = $this->routeId($request);
        $userId = $this->auth->id();

        return $eventId === null || $userId === null
            ? null
            : $this->events->findOwned($userId, $eventId);
    }

    private function routeId(Request $request): ?int
    {
        $value = $request->route('id');

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function safeInput(Request $request): array
    {
        return array_filter(
            $request->only(self::FIELDS),
            static fn (mixed $value): bool => is_scalar($value),
        );
    }

    private function galleryUploads(?array $files): array
    {
        if ($files === null) {
            return [];
        }

        if (!isset($files['name']) || !is_array($files['name'])) {
            return array_is_list($files) ? array_values(array_filter($files, 'is_array')) : [$files];
        }

        $uploads = [];

        foreach (array_keys($files['name']) as $index) {
            $uploads[] = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $uploads;
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_scalar($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'The event action could not be completed.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
