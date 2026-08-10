<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\CmsRepositoryInterface;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use Throwable;

final class PublicContentController extends Controller
{
    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly CmsRepositoryInterface $cms)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function about(Request $request): Response { return $this->page('about'); }
    public function contact(Request $request): Response { return $this->page('contact'); }
    public function privacy(Request $request): Response { return $this->page('privacy'); }
    public function terms(Request $request): Response { return $this->page('terms'); }

    public function faq(Request $request): Response
    {
        try {
            $faqs = $this->cms->activeFaqs();
        } catch (Throwable) {
            return Response::text('Service Unavailable', 503);
        }
        return $this->render('pages/faq', [
            'pageTitle' => 'Frequently asked questions',
            'metaDescription' => 'Answers to common questions about events, registrations, and tickets on OEMS.',
            'faqs' => $faqs,
        ]);
    }

    private function page(string $slug): Response
    {
        try {
            $page = $this->cms->findPage($slug, true);
        } catch (Throwable) {
            return Response::text('Service Unavailable', 503);
        }
        if ($page === null) return Response::text('Not Found', 404);
        return $this->render('pages/show', [
            'pageTitle' => (string) (($page['meta_title'] ?? '') ?: $page['title']),
            'metaDescription' => (string) (($page['meta_description'] ?? '') ?: mb_substr((string) $page['content'], 0, 320)),
            'page' => $page,
        ]);
    }
}
