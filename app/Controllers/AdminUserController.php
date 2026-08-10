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

final class AdminUserController extends Controller
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
        $filters = $this->listInput($request);

        return $this->render('admin/users/index', [
            'pageTitle' => 'Users',
            'result' => $this->peopleService->users($filters),
            'filters' => $filters,
        ], 'dashboard');
    }

    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return $user === null ? $this->notFound() : $this->render('admin/users/show', [
            'pageTitle' => (string) ($user['name'] ?? 'User'),
            'managedUser' => $user,
        ], 'dashboard');
    }

    public function suspend(Request $request): Response
    {
        return $this->changeStatus($request, ['active'], 'suspended');
    }

    public function deactivate(Request $request): Response
    {
        return $this->changeStatus($request, ['active'], 'inactive');
    }

    public function reactivate(Request $request): Response
    {
        return $this->changeStatus($request, ['suspended', 'inactive'], 'active');
    }

    private function changeStatus(Request $request, array $expectedStatuses, string $status): Response
    {
        $userId = $this->routeId($request);
        $actorId = $this->auth->id();
        $user = $userId === null ? null : $this->people->findUser($userId);

        if ($userId === null || $actorId === null || $user === null) {
            return $this->notFound();
        }

        if (!is_string($request->input('expected_status'))
            || !in_array($request->input('expected_status'), $expectedStatuses, true)
            || $request->input('expected_status') !== ($user['status'] ?? null)) {
            return Response::text('Conflict', 409);
        }

        $result = match ($status) {
            'suspended' => $this->peopleService->suspend($actorId, $userId, $this->context($request)),
            'inactive' => $this->peopleService->deactivate($actorId, $userId, $this->context($request)),
            default => $this->peopleService->reactivate($actorId, $userId, $this->context($request)),
        };

        if (!$result['success']) {
            return $this->actionFailure($result, '/admin/users/' . $userId);
        }

        $this->session->flash('success', match ($status) {
            'suspended' => 'User suspended and active sign-in sessions revoked.',
            'inactive' => 'User deactivated and active sign-in sessions revoked.',
            default => 'User account reactivated.',
        });

        return Response::redirect('/admin/users/' . $userId);
    }

    private function listInput(Request $request): array
    {
        return [
            'search' => $request->query('search'),
            'role' => $request->query('role'),
            'status' => $request->query('status'),
            'page' => $request->query('page'),
            'per_page' => $request->query('per_page'),
        ];
    }

    private function user(Request $request): ?array
    {
        $userId = $this->routeId($request);

        return $userId === null ? null : $this->people->findUser($userId);
    }

    private function actionFailure(array $result, string $location): Response
    {
        return match ($result['code'] ?? '') {
            'not_found' => $this->notFound(),
            'forbidden' => Response::text('Forbidden', 403),
            'conflict' => Response::text('Conflict', 409),
            default => $this->redirectWith($location, 'error', $this->firstError($result['errors'] ?? [])),
        };
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

        return 'The user action could not be completed.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
