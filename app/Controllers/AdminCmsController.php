<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\CmsRepositoryInterface;
use OEMS\App\Services\CmsService;
use OEMS\App\Support\CmsBannerPresenter;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminCmsController extends Controller
{
    private const PAGE_FIELDS = ['title', 'content', 'meta_title', 'meta_description'];
    private const FAQ_FIELDS = ['question', 'answer', 'category', 'sort_order'];
    private const BANNER_FIELDS = ['title', 'subtitle', 'link_url', 'starts_at', 'ends_at', 'sort_order'];

    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly CmsRepositoryInterface $cms, private readonly CmsService $service, private readonly CmsBannerPresenter $bannerPresenter)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $timezone = new DateTimeZone((string) $this->config->get('timezone', 'UTC'));
        $now = new DateTimeImmutable('now', $timezone);
        $now = $now->setTime(
            (int) $now->format('H'),
            (int) $now->format('i'),
            (int) $now->format('s'),
            0,
        );
        $banners = array_map(
            fn (array $banner): array => $this->bannerPresenter->present($banner, $now, $timezone),
            $this->cms->allBanners(),
        );

        return $this->render('admin/cms/index', [
            'pageTitle' => 'Content management',
            'pages' => $this->cms->fixedPages(CmsService::PAGE_SLUGS),
            'faqs' => $this->cms->allFaqs(),
            'banners' => $banners,
        ], 'dashboard');
    }

    public function editPage(Request $request): Response
    {
        $page = $this->page($request);
        return $page === null ? $this->notFound() : $this->render('admin/cms/page-form', ['pageTitle' => 'Edit ' . $page['title'], 'page' => $page], 'dashboard');
    }

    public function updatePage(Request $request): Response
    {
        $slug = $this->slug($request);
        if ($slug === null || $this->cms->findPage($slug) === null) return $this->notFound();
        $input = $this->safeInput($request, self::PAGE_FIELDS);
        $result = $this->service->updatePage($slug, $input, (int) $this->auth->id());
        if (!$result['success']) return $this->redirectWithErrors('/admin/cms/pages/' . $slug, $result['errors'], $input);
        $this->session->flash('success', 'Page content saved.');
        return Response::redirect('/admin/cms');
    }

    public function publishPage(Request $request): Response { return $this->pageStatus($request, true); }
    public function unpublishPage(Request $request): Response { return $this->pageStatus($request, false); }

    public function createFaq(Request $request): Response { return $this->render('admin/cms/faq-form', ['pageTitle' => 'Create FAQ', 'faq' => null], 'dashboard'); }
    public function editFaq(Request $request): Response
    {
        $id = $this->id($request); $faq = $id === null ? null : $this->cms->findFaq($id);
        return $faq === null ? $this->notFound() : $this->render('admin/cms/faq-form', ['pageTitle' => 'Edit FAQ', 'faq' => $faq], 'dashboard');
    }
    public function storeFaq(Request $request): Response
    {
        $input = $this->safeInput($request, self::FAQ_FIELDS); $result = $this->service->createFaq($input);
        if (!$result['success']) return $this->redirectWithErrors('/admin/cms/faqs/create', $result['errors'], $input);
        $this->session->flash('success', 'FAQ created.'); return Response::redirect('/admin/cms');
    }
    public function updateFaq(Request $request): Response
    {
        $id = $this->id($request); if ($id === null || $this->cms->findFaq($id) === null) return $this->notFound();
        $input = $this->safeInput($request, self::FAQ_FIELDS); $result = $this->service->updateFaq($id, $input);
        if (!$result['success']) return $this->redirectWithErrors('/admin/cms/faqs/' . $id . '/edit', $result['errors'], $input);
        $this->session->flash('success', 'FAQ updated.'); return Response::redirect('/admin/cms');
    }
    public function setFaqActive(Request $request): Response
    {
        $id = $this->id($request); if ($id === null || $this->cms->findFaq($id) === null) return $this->notFound();
        $result = $this->service->setFaqActive($id, $request->input('is_active'));
        if (!$result['success']) return $this->redirectWith('/admin/cms', 'error', $this->firstError($result));
        $this->session->flash('success', $result['active'] ? 'FAQ activated.' : 'FAQ deactivated.'); return Response::redirect('/admin/cms');
    }

    public function createBanner(Request $request): Response { return $this->render('admin/cms/banner-form', ['pageTitle' => 'Create banner', 'banner' => null], 'dashboard'); }
    public function editBanner(Request $request): Response
    {
        $id = $this->id($request); $banner = $id === null ? null : $this->cms->findBanner($id);
        return $banner === null ? $this->notFound() : $this->render('admin/cms/banner-form', ['pageTitle' => 'Edit banner', 'banner' => $banner], 'dashboard');
    }
    public function storeBanner(Request $request): Response
    {
        $input = $this->safeInput($request, self::BANNER_FIELDS); $result = $this->service->createBanner($input, $request->file('image'));
        if (!$result['success']) return $this->redirectWithErrors('/admin/cms/banners/create', $result['errors'], $input);
        $this->session->flash('success', 'Banner created.'); return Response::redirect('/admin/cms');
    }
    public function updateBanner(Request $request): Response
    {
        $id = $this->id($request); if ($id === null || $this->cms->findBanner($id) === null) return $this->notFound();
        $input = $this->safeInput($request, self::BANNER_FIELDS); $result = $this->service->updateBanner($id, $input, $request->file('image'));
        if (!$result['success']) return $this->redirectWithErrors('/admin/cms/banners/' . $id . '/edit', $result['errors'], $input);
        $this->session->flash('success', 'Banner updated.'); return Response::redirect('/admin/cms');
    }
    public function setBannerActive(Request $request): Response
    {
        $id = $this->id($request); if ($id === null || $this->cms->findBanner($id) === null) return $this->notFound();
        $result = $this->service->setBannerActive($id, $request->input('is_active'));
        if (!$result['success']) return $this->redirectWith('/admin/cms', 'error', $this->firstError($result));
        $this->session->flash('success', $result['active'] ? 'Banner activated.' : 'Banner deactivated.'); return Response::redirect('/admin/cms');
    }

    private function pageStatus(Request $request, bool $published): Response
    {
        $slug = $this->slug($request); if ($slug === null || $this->cms->findPage($slug) === null) return $this->notFound();
        $result = $this->service->setPagePublished($slug, $published ? '1' : '0', (int) $this->auth->id());
        if (!$result['success']) return $this->redirectWith('/admin/cms', 'error', $this->firstError($result));
        $this->session->flash('success', $published ? 'Page published.' : 'Page unpublished.'); return Response::redirect('/admin/cms');
    }
    private function page(Request $request): ?array { $slug = $this->slug($request); return $slug === null ? null : $this->cms->findPage($slug); }
    private function slug(Request $request): ?string { $slug = $request->route('slug'); return is_string($slug) && in_array($slug, CmsService::PAGE_SLUGS, true) ? $slug : null; }
    private function id(Request $request): ?int { $id = $request->route('id'); return (is_string($id) || is_int($id)) && ctype_digit((string) $id) && (int) $id > 0 ? (int) $id : null; }
    private function safeInput(Request $request, array $fields): array { return array_filter($request->only($fields), 'is_scalar'); }
    private function notFound(): Response { return Response::text('Not Found', 404); }
    private function firstError(array $result): string { foreach (($result['errors'] ?? []) as $messages) if (is_array($messages) && is_scalar($messages[0] ?? null)) return (string) $messages[0]; return 'The content action could not be completed.'; }
}
