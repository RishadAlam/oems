<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\NewsletterService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class PublicNewsletterController extends Controller
{
    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly NewsletterService $newsletter, private readonly RateLimiter $limiter)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function subscribe(Request $request): Response
    {
        if (is_scalar($request->input('website')) && trim((string) $request->input('website')) !== '') return $this->generic();
        $email = is_scalar($request->input('email')) ? mb_strtolower(trim((string) $request->input('email'))) : '';
        if (!$this->limiter->consumeAttempt('newsletter-ip:' . hash('sha256', $request->ip())) || ($email !== '' && !$this->limiter->consumeAttempt('newsletter-email:' . hash('sha256', $email)))) return Response::html('<h1>Too many requests</h1><p>Please wait before trying again.</p>', 429);
        $result = $this->newsletter->subscribe($email);
        if (!$result['success'] && isset($result['errors']['email'])) return $this->redirectWithErrors('/#newsletter', $result['errors'], ['newsletter_email' => $email]);
        return $this->generic();
    }

    public function confirm(Request $request): Response
    {
        $result = $this->newsletter->confirm($request->route('token'));
        return $this->render('pages/newsletter-result', [
            'pageTitle' => 'Newsletter confirmation',
            'success' => $result['success'],
            'heading' => $result['success'] ? 'Subscription confirmed' : 'Confirmation link unavailable',
            'copy' => $result['success']
                ? 'You will now receive selected OEMS updates.'
                : 'This link is invalid, expired, or already used.',
            'actionUrl' => $result['success'] ? '/events' : '/#newsletter',
            'actionLabel' => $result['success'] ? 'Explore events' : 'Request another confirmation email',
        ]);
    }

    public function unsubscribe(Request $request): Response
    {
        $result = $this->newsletter->unsubscribe($request->route('token'));
        return $this->render('pages/newsletter-result', [
            'pageTitle' => 'Newsletter preferences',
            'success' => $result['success'],
            'heading' => $result['success'] ? 'You are unsubscribed' : 'Unsubscribe link unavailable',
            'copy' => $result['success'] ? 'OEMS newsletter delivery has stopped.' : 'This link is invalid or already used.',
            'actionUrl' => '/events',
            'actionLabel' => 'Explore events',
        ]);
    }

    private function generic(): Response
    {
        $this->session->flash('success', 'If this address can be subscribed, a confirmation email is on its way.'); return Response::redirect('/#newsletter');
    }
}
