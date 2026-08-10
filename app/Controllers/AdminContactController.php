<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\ContactRepositoryInterface;
use OEMS\App\Services\ContactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminContactController extends Controller
{
    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly ContactRepositoryInterface $contacts, private readonly ContactService $service)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $workspace = $this->service->index(['status' => $request->query('status'), 'search' => $request->query('search'), 'page' => $request->query('page')]);
        $response = $this->render('admin/contact/index', ['pageTitle' => 'Contact inbox', ...$workspace], 'dashboard');
        return ($workspace['valid'] ?? false) ? $response : Response::html($response->body(), 422);
    }

    public function show(Request $request): Response
    {
        $id = $this->id($request->route('id')); $message = $id === null ? null : $this->contacts->findForAdmin($id);
        return $message === null ? $this->notFound() : $this->render('admin/contact/show', ['pageTitle' => 'Contact message', 'message' => $message], 'dashboard');
    }

    public function status(Request $request): Response
    {
        $id = $this->id($request->route('id')); if ($id === null || $this->contacts->findForAdmin($id) === null) return $this->notFound();
        $from = is_scalar($request->input('from')) ? (string) $request->input('from') : ''; $to = is_scalar($request->input('status')) ? (string) $request->input('status') : '';
        $result = $this->service->setStatus((int) ($this->auth->id() ?? 0), $id, $from, $to);
        if (!$result['success']) return Response::html('Contact state changed. Reload and try again.', ($result['code'] ?? '') === 'conflict' ? 409 : 422);
        $this->session->flash('success', 'Contact status updated.'); return Response::redirect('/admin/contact/' . $id);
    }

    public function reply(Request $request): Response
    {
        $id = $this->id($request->route('id')); if ($id === null || $this->contacts->findForAdmin($id) === null) return $this->notFound();
        $result = $this->service->reply((int) ($this->auth->id() ?? 0), $id, $request->input('reply'));
        if (!$result['success']) {
            if (($result['code'] ?? null) === 'conflict') return Response::html('This message cannot be replied to in its current state.', 409);
            return $this->redirectWithErrors('/admin/contact/' . $id, $result['errors'] ?? [], []);
        }
        $this->session->flash('success', !empty($result['idempotent']) ? 'This reply was already queued.' : 'Reply queued for email delivery.'); return Response::redirect('/admin/contact/' . $id);
    }

    private function id(mixed $value): ?int { return is_scalar($value) && preg_match('/\A[1-9][0-9]*\z/D', (string) $value) === 1 ? (int) $value : null; }
    private function notFound(): Response { return Response::text('Not Found', 404); }
}
