<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\NewsletterService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminNewsletterController extends Controller
{
    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly NewsletterService $newsletter)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response { return $this->render('admin/newsletter/index', ['pageTitle' => 'Newsletter campaigns', 'campaigns' => $this->newsletter->campaigns()], 'dashboard'); }
    public function create(Request $request): Response { return $this->render('admin/newsletter/campaign-form', ['pageTitle' => 'Create newsletter campaign'], 'dashboard'); }

    public function store(Request $request): Response
    {
        $input = array_filter($request->only(['subject', 'message']), 'is_scalar'); $result = $this->newsletter->createCampaign((int) ($this->auth->id() ?? 0), $input);
        if (!$result['success']) return $this->redirectWithErrors('/admin/newsletter/create', $result['errors'], $input);
        $this->session->flash('success', 'Campaign draft created. Review it before queueing.'); return Response::redirect('/admin/newsletter');
    }

    public function queue(Request $request): Response
    {
        $id = $this->id($request->route('id')); if ($id === null) return Response::text('Not Found', 404);
        $result = $this->newsletter->queueCampaign((int) ($this->auth->id() ?? 0), $id);
        if (!$result['success']) return Response::html(($result['code'] ?? null) === 'empty' ? 'No confirmed subscribers are available.' : 'Campaign could not be queued.', ($result['code'] ?? null) === 'not_found' ? 404 : 409);
        $this->session->flash('success', !empty($result['idempotent']) ? 'Campaign was already queued.' : 'Campaign queued for ' . (int) $result['queued_count'] . ' confirmed subscribers.'); return Response::redirect('/admin/newsletter');
    }

    private function id(mixed $value): ?int { return is_scalar($value) && preg_match('/\A[1-9][0-9]*\z/D', (string) $value) === 1 ? (int) $value : null; }
}
