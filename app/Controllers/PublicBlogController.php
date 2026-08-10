<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Services\BlogService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use Throwable;

final class PublicBlogController extends Controller
{
    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly BlogService $blog)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $result = $this->blog->publicIndex($request->all());
        $canonical = $this->absolute('/blog');
        if (!($result['success'] ?? false)) {
            $response = $this->render('blog/index', [
                'pageTitle' => 'Blog unavailable', 'metaDescription' => 'Browse OEMS event guides and community stories.',
                'canonicalUrl' => $canonical, 'openGraph' => ['type' => 'website', 'title' => 'OEMS Blog', 'url' => $canonical],
                'posts' => [], 'categories' => [], 'category' => null, 'pagination' => ['page' => 1, 'last_page' => 1, 'total' => 0],
                'filterError' => true,
            ]);

            return Response::html($response->body(), 422);
        }

        $result['posts'] = array_map(function (array $post): array {
            $post['published_display'] = $this->date($post['published_at'] ?? null);

            return $post;
        }, $result['posts']);
        $canonicalQuery = array_filter([
            'category' => $result['category'] ?? null,
            'page' => (($result['pagination']['page'] ?? 1) > 1) ? $result['pagination']['page'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $canonical = $this->absolute('/blog' . ($canonicalQuery === [] ? '' : '?' . http_build_query($canonicalQuery)));

        return $this->render('blog/index', [
            'pageTitle' => 'Event guides and community stories',
            'metaDescription' => 'Read practical event guides, organizer notes, and community stories from OEMS.',
            'canonicalUrl' => $canonical,
            'openGraph' => ['type' => 'website', 'title' => 'OEMS Blog', 'description' => 'Practical event guides and community stories.', 'url' => $canonical],
        ] + $result);
    }

    public function show(Request $request): Response
    {
        $slug = $request->route('slug');
        $post = is_scalar($slug) ? $this->blog->publicDetail((string) $slug) : null;
        if ($post === null) {
            return $this->notFound();
        }
        $post = $this->present($post);
        $canonical = $this->absolute('/blog/' . rawurlencode((string) $post['slug']));
        $description = (string) ($post['meta_description'] ?? $post['excerpt']);
        $openGraph = ['type' => 'article', 'title' => (string) ($post['meta_title'] ?? $post['title']), 'description' => $description, 'url' => $canonical];
        if (is_string($post['cover_image'] ?? null)) {
            $openGraph['image'] = $this->absolute((string) $post['cover_image']);
        }

        return $this->render('blog/show', [
            'pageTitle' => (string) ($post['meta_title'] ?? $post['title']),
            'metaDescription' => $description,
            'canonicalUrl' => $canonical,
            'openGraph' => $openGraph,
            'post' => $post,
        ]);
    }

    private function present(array $post): array
    {
        $post['published_display'] = $this->date($post['published_at'] ?? null);
        $post['paragraphs'] = array_values(array_filter(preg_split('/\n{2,}/', (string) ($post['body'] ?? '')) ?: [], static fn (string $paragraph): bool => trim($paragraph) !== ''));

        return $post;
    }

    private function date(mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return 'Publication date unavailable';
        }
        try {
            return (new DateTimeImmutable((string) $value, new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'))))->format('F j, Y');
        } catch (Throwable) {
            return 'Publication date unavailable';
        }
    }

    private function absolute(string $path): string
    {
        return rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/') . '/' . ltrim($path, '/');
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', ['pageTitle' => 'Blog post not found'], 'public');

        return Response::html($response->body(), 404);
    }
}
