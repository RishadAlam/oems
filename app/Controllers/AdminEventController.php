<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Services\EventService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminEventController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'published', 'rejected', 'completed', 'cancelled', 'draft'];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly EventRepositoryInterface $events,
        private readonly EventService $eventService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $requestedStatus = $request->query('status');
        $status = is_string($requestedStatus) && in_array($requestedStatus, self::STATUSES, true)
            ? $requestedStatus
            : 'pending';

        if ($requestedStatus === 'all') {
            $status = null;
        }

        return $this->render('admin/events/index', [
            'pageTitle' => 'Event moderation',
            'events' => $this->events->forAdmin($status),
            'status' => $status,
            'statuses' => self::STATUSES,
        ], 'dashboard');
    }

    public function trash(Request $request): Response
    {
        return $this->render('admin/events/trash', [
            'pageTitle' => 'Deleted events',
            'events' => $this->events->trashAdmin(100, 0),
        ], 'dashboard');
    }

    public function restore(Request $request): Response
    {
        $eventId = $this->routeId($request);
        $userId = $this->auth->id();
        if ($eventId === null || $userId === null || $this->events->findDeletedAdmin($eventId) === null) {
            return $this->notFound();
        }
        $expected = $request->input('deleted_at');
        $agent = $request->header('User-Agent');
        $result = $this->eventService->restoreAsAdmin(
            $userId,
            $eventId,
            is_scalar($expected) ? (string) $expected : '',
            ['ip_address' => $request->ip(), 'user_agent' => is_string($agent) ? mb_substr($agent, 0, 500) : null],
        );
        if (!($result['success'] ?? false)) {
            if (($result['code'] ?? null) === 'not_found') {
                return $this->notFound();
            }
            if (($result['code'] ?? null) === 'conflict') {
                return Response::text('Conflict', 409);
            }

            return $this->redirectWith('/admin/events/trash', 'error', $this->firstError($result['errors'] ?? []));
        }
        $this->session->flash('success', 'Event restored with its retained ' . (string) ($result['status'] ?? 'draft') . ' lifecycle.');

        return Response::redirect('/admin/events/' . $eventId);
    }

    public function show(Request $request): Response
    {
        $event = $this->event($request);

        if ($event === null) {
            return $this->notFound();
        }

        return $this->render('admin/events/show', [
            'pageTitle' => (string) $event['title'],
            'event' => $event,
            'gallery' => $this->events->galleryForAdmin((int) $event['id']),
        ], 'dashboard');
    }

    public function approve(Request $request): Response
    {
        return $this->moderate($request, 'approved');
    }

    public function reject(Request $request): Response
    {
        return $this->moderate($request, 'rejected');
    }

    public function publish(Request $request): Response
    {
        return $this->moderate($request, 'published');
    }

    public function complete(Request $request): Response
    {
        return $this->moderate($request, 'completed');
    }

    public function cancel(Request $request): Response
    {
        return $this->moderate($request, 'cancelled');
    }

    public function delete(Request $request): Response
    {
        $eventId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($eventId === null || $userId === null || $this->events->findForAdmin($eventId) === null) {
            return $this->notFound();
        }

        $agent = $request->header('User-Agent');
        $result = $this->eventService->deleteAsAdmin($userId, $eventId, [
            'ip_address' => $request->ip(),
            'user_agent' => is_string($agent) ? mb_substr($agent, 0, 500) : null,
        ]);

        if (!$result['success']) {
            return $this->redirectWith(
                '/admin/events/' . $eventId,
                'error',
                $this->firstError($result['errors']),
            );
        }

        $this->session->flash('success', 'Event deleted. Its audit history remains available.');

        return Response::redirect('/admin/events');
    }

    private function moderate(Request $request, string $status): Response
    {
        $eventId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($eventId === null || $userId === null || $this->events->findForAdmin($eventId) === null) {
            return $this->notFound();
        }

        $reason = $status === 'rejected' && is_scalar($request->input('reason'))
            ? (string) $request->input('reason')
            : null;
        $result = $this->eventService->moderate($userId, $eventId, $status, $reason);
        $location = '/admin/events/' . $eventId;

        if (!$result['success']) {
            if ($status === 'rejected' && isset($result['errors']['reason'])) {
                return $this->redirectWithErrors($location, $result['errors'], ['reason' => $reason ?? '']);
            }

            return $this->redirectWith($location, 'error', $this->firstError($result['errors']));
        }

        $messages = [
            'approved' => 'Event approved.',
            'rejected' => 'Event rejected with a review reason.',
            'published' => 'Event published.',
            'completed' => 'Event marked complete.',
            'cancelled' => 'Event cancelled.',
        ];
        $this->session->flash('success', $messages[$status]);

        return Response::redirect($location);
    }

    private function event(Request $request): ?array
    {
        $eventId = $this->routeId($request);

        return $eventId === null ? null : $this->events->findForAdmin($eventId);
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

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_scalar($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'The moderation action could not be completed.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
