<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\AdminPeopleRepositoryInterface;
use OEMS\App\Services\AdminPeopleService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminOrganizerController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly AdminPeopleRepositoryInterface $people,
        private readonly AdminPeopleService $peopleService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->query('search'),
            'approval_status' => $request->query('approval_status'),
            'page' => $request->query('page'),
            'per_page' => $request->query('per_page'),
        ];

        return $this->render('admin/organizers/index', [
            'pageTitle' => 'Organizers',
            'result' => $this->peopleService->organizers($filters),
            'filters' => $filters,
        ], 'dashboard');
    }

    public function show(Request $request): Response
    {
        $organizer = $this->organizer($request);

        return $organizer === null ? $this->notFound() : $this->render('admin/organizers/show', [
            'pageTitle' => (string) ($organizer['organization_name'] ?? 'Organizer'),
            'organizer' => $organizer,
        ], 'dashboard');
    }

    public function approve(Request $request): Response
    {
        return $this->changeApproval($request, 'approved');
    }

    public function reject(Request $request): Response
    {
        return $this->changeApproval($request, 'rejected');
    }

    private function changeApproval(Request $request, string $status): Response
    {
        $organizerId = $this->routeId($request);
        $actorId = $this->auth->id();
        $organizer = $organizerId === null ? null : $this->people->findOrganizer($organizerId);

        if ($organizerId === null || $actorId === null || $organizer === null) {
            return $this->notFound();
        }

        $expected = $request->input('expected_status');
        $allowedExpected = $status === 'approved'
            ? ['pending', 'rejected', 'approved']
            : ['pending', 'approved', 'rejected'];
        if (!is_string($expected)
            || !in_array($expected, $allowedExpected, true)
            || ($expected !== ($organizer['approval_status'] ?? null)
                && $status !== ($organizer['approval_status'] ?? null))) {
            return Response::text('Conflict', 409);
        }

        $result = $status === 'approved'
            ? $this->peopleService->approveOrganizer($actorId, $organizerId, $this->context($request))
            : $this->peopleService->rejectOrganizer(
                $actorId,
                $organizerId,
                $request->input('reason'),
                $this->context($request),
            );
        $location = '/admin/organizers/' . $organizerId;

        if (!$result['success']) {
            if (($result['code'] ?? null) === 'validation') {
                $reason = $request->input('reason');

                return $this->redirectWithErrors(
                    $location,
                    $result['errors'],
                    ['reason' => is_scalar($reason) ? (string) $reason : ''],
                );
            }

            return match ($result['code'] ?? '') {
                'not_found' => $this->notFound(),
                'forbidden' => Response::text('Forbidden', 403),
                'conflict' => Response::text('Conflict', 409),
                default => $this->redirectWith($location, 'error', $this->firstError($result['errors'] ?? [])),
            };
        }

        $this->session->flash('success', $status === 'approved'
            ? 'Organizer approved.'
            : 'Organizer application rejected with feedback.');

        return Response::redirect($location);
    }

    private function organizer(Request $request): ?array
    {
        $organizerId = $this->routeId($request);

        return $organizerId === null ? null : $this->people->findOrganizer($organizerId);
    }

    private function context(Request $request): array
    {
        $agent = $request->header('User-Agent');

        return [
            'ip_address' => $request->ip(),
            'user_agent' => is_string($agent) ? $agent : null,
        ];
    }

    private function routeId(Request $request): ?int
    {
        $value = $request->route('id');

        return (is_string($value) || is_int($value))
            && ctype_digit((string) $value)
            && (int) $value > 0
                ? (int) $value
                : null;
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && is_scalar($messages[0] ?? null)) {
                return (string) $messages[0];
            }
        }

        return 'The organizer action could not be completed.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
