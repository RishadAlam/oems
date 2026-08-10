<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\CmsRepositoryInterface;
use OEMS\App\Services\ContactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use Throwable;

final class PublicContactController extends Controller
{
    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly CmsRepositoryInterface $cms, private readonly ContactService $contacts, private readonly RateLimiter $limiter)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        try { $page = $this->cms->findPage('contact', true); } catch (Throwable) { $page = null; }
        return $this->render('pages/contact', ['pageTitle' => 'Contact OEMS', 'metaDescription' => 'Contact OEMS support.', 'page' => $page]);
    }

    public function store(Request $request): Response
    {
        $input = array_filter($request->only(['name', 'email', 'subject', 'message', 'website']), 'is_scalar');
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $allowed = $this->limiter->consumeAttempt('contact-ip:' . hash('sha256', $request->ip()));
        if ($email !== '') $allowed = $this->limiter->consumeAttempt('contact-email:' . hash('sha256', $email)) && $allowed;
        if (!$allowed) return Response::html('<h1>Too many messages</h1><p>Please wait before trying again.</p>', 429);
        $result = $this->contacts->submit($input);
        if (!$result['success']) return $this->redirectWithErrors('/contact', $result['errors'], array_intersect_key($input, array_flip(['name', 'email', 'subject', 'message'])));
        $this->session->flash('success', 'Thanks. If your message needs a reply, the OEMS team will contact you by email.');
        return Response::redirect('/contact');
    }
}
