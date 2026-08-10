<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\BlogService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminBlogController extends Controller
{
    private const FIELDS = ['title', 'slug', 'excerpt', 'body', 'category', 'meta_title', 'meta_description', 'updated_at'];

    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly BlogService $blog)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $result = $this->blog->adminIndex($request->all());
        if (!($result['success'] ?? false)) {
            return Response::html('<h1>Invalid Blog filters</h1><p>Use only the documented status, search, and page filters.</p>', 422);
        }

        return $this->render('admin/blog/index', ['pageTitle' => 'Blog publishing'] + $result, 'dashboard');
    }

    public function create(Request $request): Response
    {
        return $this->render('admin/blog/form', ['pageTitle' => 'Create Blog post', 'post' => null], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $input = $request->only(self::FIELDS);
        $result = $this->blog->create((int) $this->auth->id(), $input, $request->file('cover_image'));
        if (!($result['success'] ?? false)) {
            return $this->redirectWithErrors('/admin/blog/create', $result['errors'] ?? [], $this->scalarOld($input));
        }
        $this->session->flash('success', 'Blog draft created.');

        return Response::redirect('/admin/blog/' . (int) $result['post']['id'] . '/edit');
    }

    public function edit(Request $request): Response
    {
        $post = $this->post($request);

        return $post === null ? $this->notFound() : $this->render('admin/blog/form', ['pageTitle' => 'Edit Blog post', 'post' => $post], 'dashboard');
    }

    public function update(Request $request): Response
    {
        $id = $this->id($request);
        if ($id === null || $this->blog->adminDetail($id) === null) {
            return $this->notFound();
        }
        $input = $request->only(self::FIELDS);
        $result = $this->blog->update($id, $input, $request->file('cover_image'));
        if (!($result['success'] ?? false)) {
            return $this->redirectWithErrors('/admin/blog/' . $id . '/edit', $result['errors'] ?? [], $this->scalarOld($input));
        }
        $this->session->flash('success', 'Blog post saved.');

        return Response::redirect('/admin/blog/' . $id . '/edit');
    }

    public function preview(Request $request): Response
    {
        $post = $this->post($request);
        if ($post === null) {
            return $this->notFound();
        }
        $response = $this->render('admin/blog/preview', [
            'pageTitle' => 'Draft preview', 'post' => $this->present($post), 'robots' => 'noindex, nofollow',
        ], 'dashboard');

        return $response->withHeader('X-Robots-Tag', 'noindex, nofollow')->withHeader('Cache-Control', 'private, no-store, max-age=0');
    }

    public function publish(Request $request): Response
    {
        return $this->status($request, 'published');
    }

    public function unpublish(Request $request): Response
    {
        return $this->status($request, 'draft');
    }

    public function delete(Request $request): Response
    {
        $id = $this->id($request);
        if ($id === null) {
            return $this->notFound();
        }
        $result = $this->blog->delete($id, $this->scalar($request->input('updated_at')));
        if (!($result['success'] ?? false)) {
            return $this->redirectWith('/admin/blog', 'error', $this->firstError($result));
        }
        $this->session->flash('success', 'Blog post moved out of publication.');

        return Response::redirect('/admin/blog');
    }

    private function status(Request $request, string $target): Response
    {
        $id = $this->id($request);
        if ($id === null || $this->blog->adminDetail($id) === null) {
            return $this->notFound();
        }
        $result = $this->blog->transition($id, $target, $this->scalar($request->input('updated_at')));
        if (!($result['success'] ?? false)) {
            return $this->redirectWith('/admin/blog', 'error', $this->firstError($result));
        }
        $this->session->flash('success', $target === 'published' ? 'Blog post published.' : 'Blog post returned to draft.');

        return Response::redirect('/admin/blog');
    }

    private function post(Request $request): ?array
    {
        $id = $this->id($request);

        return $id === null ? null : $this->blog->adminDetail($id);
    }

    private function present(array $post): array
    {
        $body = (string) ($post['body'] ?? '');
        $post['paragraphs'] = array_values(array_filter(preg_split('/\n{2,}/', $body) ?: [], static fn (string $paragraph): bool => trim($paragraph) !== ''));
        $post['reading_minutes'] = max(1, (int) ceil(count(preg_split('/\s+/u', trim($body), -1, PREG_SPLIT_NO_EMPTY) ?: []) / 225));

        return $post;
    }

    private function id(Request $request): ?int
    {
        $id = $request->route('id');

        return is_scalar($id) && preg_match('/\A[1-9][0-9]*\z/', (string) $id) === 1 ? (int) $id : null;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function scalarOld(array $input): array
    {
        return array_filter($input, 'is_scalar');
    }

    private function firstError(array $result): string
    {
        foreach (($result['errors'] ?? []) as $messages) {
            if (is_array($messages) && is_scalar($messages[0] ?? null)) {
                return (string) $messages[0];
            }
        }

        return 'The Blog action could not be completed.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
