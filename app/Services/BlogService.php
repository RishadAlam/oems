<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\BlogRepositoryInterface;
use OEMS\Core\Logger;
use PDOException;
use Throwable;

final class BlogService
{
    private const PAGE_SIZE = 9;

    public function __construct(
        private readonly BlogRepositoryInterface $posts,
        private readonly ImageUploadService $images,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function create(int $authorId, array $input, ?array $upload): array
    {
        $validated = $this->validate($input);
        if ($authorId <= 0 || $validated['errors'] !== []) {
            return $this->failure($validated['errors'] ?: ['post' => ['The author is invalid.']]);
        }
        $stored = $this->images->store($upload);
        if (!$stored['success']) {
            return $this->failure(['cover_image' => [(string) $stored['error']]]);
        }
        $attributes = array_merge($validated['values'], ['cover_image' => $stored['path']]);
        try {
            $id = $this->posts->create($authorId, $attributes);
            $post = $this->posts->findAdmin($id);
            if ($id <= 0 || $post === null) {
                throw new \RuntimeException('The Blog post could not be read.');
            }

            return ['success' => true, 'post' => $post, 'errors' => []];
        } catch (Throwable $exception) {
            $this->images->delete($stored['path']);
            $this->log('create', null, $exception);

            return $this->failure($this->duplicate($exception) ? ['slug' => ['This slug is already in use.']] : ['post' => ['The Blog post could not be saved.']]);
        }
    }

    public function update(int $postId, array $input, ?array $upload): array
    {
        $current = $postId > 0 ? $this->posts->findAdmin($postId) : null;
        if ($current === null) {
            return $this->failure(['post' => ['The Blog post was not found.']], true);
        }
        $expected = $this->scalar($input['updated_at'] ?? null);
        if ($expected === '' || !hash_equals((string) $current['updated_at'], $expected)) {
            return $this->failure(['post' => ['This post changed in another request. Refresh and try again.']]);
        }
        $validated = $this->validate($input);
        if ($validated['errors'] !== []) {
            return $this->failure($validated['errors']);
        }
        $stored = $this->images->store($upload);
        if (!$stored['success']) {
            return $this->failure(['cover_image' => [(string) $stored['error']]]);
        }
        $newImage = $stored['path'];
        $attributes = array_merge($validated['values'], [
            'cover_image' => $newImage ?? ($current['cover_image'] ?? null),
        ]);
        try {
            if (!$this->posts->update($postId, $expected, $attributes)) {
                $this->images->delete($newImage);

                return $this->failure(['post' => ['This post changed in another request. Refresh and try again.']]);
            }
            if ($newImage !== null && is_string($current['cover_image'] ?? null)) {
                $this->images->delete((string) $current['cover_image']);
            }
            $post = $this->posts->findAdmin($postId);

            return $post === null ? $this->failure(['post' => ['The updated post could not be read.']]) : ['success' => true, 'post' => $post, 'errors' => []];
        } catch (Throwable $exception) {
            $this->images->delete($newImage);
            $this->log('update', $postId, $exception);

            return $this->failure($this->duplicate($exception) ? ['slug' => ['This slug is already in use.']] : ['post' => ['The Blog post could not be saved.']]);
        }
    }

    public function transition(int $postId, string $target, string $expectedUpdatedAt): array
    {
        if (!in_array($target, ['draft', 'published'], true)) {
            return $this->failure(['status' => ['The requested publication state is invalid.']]);
        }
        $current = $postId > 0 ? $this->posts->findAdmin($postId) : null;
        if ($current === null) {
            return $this->failure(['post' => ['The Blog post was not found.']], true);
        }
        if ((string) $current['status'] === $target) {
            return ['success' => true, 'post' => $current, 'errors' => []];
        }
        if ($expectedUpdatedAt === '' || !hash_equals((string) $current['updated_at'], $expectedUpdatedAt)) {
            return $this->failure(['post' => ['This post changed in another request. Refresh and try again.']]);
        }
        try {
            if (!$this->posts->transition($postId, $expectedUpdatedAt, $target, (new DateTimeImmutable())->format('Y-m-d H:i:s'))) {
                return $this->failure(['post' => ['This post changed in another request. Refresh and try again.']]);
            }
            $post = $this->posts->findAdmin($postId);

            return $post === null ? $this->failure(['post' => ['The updated post could not be read.']]) : ['success' => true, 'post' => $post, 'errors' => []];
        } catch (Throwable $exception) {
            $this->log('transition', $postId, $exception);

            return $this->failure(['post' => ['The publication state could not be changed.']]);
        }
    }

    public function delete(int $postId, string $expectedUpdatedAt): array
    {
        $current = $postId > 0 ? $this->posts->findAdmin($postId) : null;
        if ($current === null) {
            return ['success' => true, 'errors' => []];
        }
        if ($expectedUpdatedAt === '' || !hash_equals((string) $current['updated_at'], $expectedUpdatedAt)) {
            return $this->failure(['post' => ['This post changed in another request. Refresh and try again.']]);
        }
        try {
            return $this->posts->softDelete($postId, $expectedUpdatedAt, (new DateTimeImmutable())->format('Y-m-d H:i:s'))
                ? ['success' => true, 'errors' => []]
                : $this->failure(['post' => ['This post changed in another request. Refresh and try again.']]);
        } catch (Throwable $exception) {
            $this->log('delete', $postId, $exception);

            return $this->failure(['post' => ['The Blog post could not be deleted.']]);
        }
    }

    public function adminIndex(array $query): array
    {
        $allowed = ['status', 'search', 'page'];
        if (array_diff(array_keys($query), $allowed) !== []) {
            return ['success' => false, 'errors' => ['query' => ['Unsupported filters were supplied.']]];
        }
        $status = $this->scalar($query['status'] ?? null);
        $search = $this->scalar($query['search'] ?? null);
        $page = $this->page($query['page'] ?? null);
        if (($status !== '' && !in_array($status, ['draft', 'published'], true)) || mb_strlen($search) > 100 || $page === null) {
            return ['success' => false, 'errors' => ['query' => ['The Blog filters are invalid.']]];
        }
        $filters = array_filter(['status' => $status, 'search' => $search], static fn (string $value): bool => $value !== '');
        $total = $this->posts->countAdmin($filters);

        return ['success' => true, 'errors' => [], 'posts' => $this->posts->adminList($filters, 20, ($page - 1) * 20), 'filters' => $filters, 'pagination' => $this->pagination($page, 20, $total)];
    }

    public function adminDetail(int $postId): ?array
    {
        return $postId > 0 ? $this->posts->findAdmin($postId) : null;
    }

    public function publicIndex(array $query): array
    {
        if (array_diff(array_keys($query), ['category', 'page']) !== []) {
            return ['success' => false, 'errors' => ['query' => ['Unsupported filters were supplied.']]];
        }
        $category = $this->scalar($query['category'] ?? null);
        $page = $this->page($query['page'] ?? null);
        if (mb_strlen($category) > 100 || $page === null) {
            return ['success' => false, 'errors' => ['query' => ['The Blog filters are invalid.']]];
        }
        $category = $category === '' ? null : $category;
        $total = $this->posts->countPublic($category);
        $posts = array_map(fn (array $post): array => $this->publicPost($post, false), $this->posts->publicList($category, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE));

        return ['success' => true, 'errors' => [], 'posts' => $posts, 'categories' => $this->posts->publicCategories(), 'category' => $category, 'pagination' => $this->pagination($page, self::PAGE_SIZE, $total)];
    }

    public function publicDetail(string $slug): ?array
    {
        $slug = $this->slug($slug);
        if ($slug === '') {
            return null;
        }
        $post = $this->posts->findPublicBySlug($slug);

        return $post === null ? null : $this->publicPost($post, true);
    }

    private function validate(array $input): array
    {
        $errors = [];
        foreach (['title', 'slug', 'excerpt', 'body', 'category', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $input) && !is_scalar($input[$field]) && $input[$field] !== null) {
                $errors[$field][] = 'This field must be plain text.';
            }
        }
        $title = $this->plain($input['title'] ?? null);
        $slug = $this->slug($this->scalar($input['slug'] ?? null) ?: $title);
        $excerpt = $this->plain($input['excerpt'] ?? null, true);
        $body = $this->plain($input['body'] ?? null, true);
        $category = $this->plain($input['category'] ?? null);
        $metaTitle = $this->plain($input['meta_title'] ?? null);
        $metaDescription = $this->plain($input['meta_description'] ?? null);
        $bounds = [
            'title' => [$title, 3, 180], 'slug' => [$slug, 3, 200], 'excerpt' => [$excerpt, 20, 500],
            'body' => [$body, 40, 50000], 'category' => [$category, 0, 100],
            'meta_title' => [$metaTitle, 0, 190], 'meta_description' => [$metaDescription, 0, 300],
        ];
        foreach ($bounds as $field => [$value, $minimum, $maximum]) {
            $length = mb_strlen($value);
            if ($length < $minimum || $length > $maximum) {
                $errors[$field][] = $minimum > 0 ? "Use between {$minimum} and {$maximum} characters." : "Use no more than {$maximum} characters.";
            }
        }
        foreach (['title', 'excerpt', 'body', 'category', 'meta_title', 'meta_description'] as $field) {
            $raw = $this->scalar($input[$field] ?? null);
            if ($raw !== strip_tags($raw)) {
                $errors[$field][] = 'Use plain text without HTML.';
            }
        }
        if ($slug === '' || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1) {
            $errors['slug'][] = 'Use a URL-safe slug.';
        }

        return ['errors' => $errors, 'values' => [
            'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'body' => $body,
            'category' => $category === '' ? null : $category,
            'meta_title' => $metaTitle === '' ? null : $metaTitle,
            'meta_description' => $metaDescription === '' ? null : $metaDescription,
        ]];
    }

    private function publicPost(array $post, bool $includeBody): array
    {
        $presented = [
            'title' => (string) ($post['title'] ?? ''),
            'slug' => (string) ($post['slug'] ?? ''), 'excerpt' => (string) ($post['excerpt'] ?? ''),
            'category' => is_string($post['category'] ?? null) ? $post['category'] : null,
            'cover_image' => is_string($post['cover_image'] ?? null) ? $post['cover_image'] : null,
            'meta_title' => is_string($post['meta_title'] ?? null) ? $post['meta_title'] : null,
            'meta_description' => is_string($post['meta_description'] ?? null) ? $post['meta_description'] : null,
            'published_at' => (string) ($post['published_at'] ?? ''),
            'author_name' => is_string($post['author_name'] ?? null) && trim($post['author_name']) !== '' ? trim($post['author_name']) : 'OEMS editorial',
            'reading_minutes' => $this->readingMinutes((string) ($post['body'] ?? '')),
        ];
        if ($includeBody) {
            $presented['body'] = (string) ($post['body'] ?? '');
        }

        return $presented;
    }

    private function readingMinutes(string $body): int
    {
        $words = preg_split('/\s+/u', trim($body), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return max(1, (int) ceil(count($words) / 225));
    }

    private function pagination(int $page, int $perPage, int $total): array
    {
        return ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    private function page(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 1;
        }

        return is_scalar($value) && preg_match('/\A[1-9][0-9]{0,3}\z/', (string) $value) === 1 ? (int) $value : null;
    }

    private function plain(mixed $value, bool $newlines = false): string
    {
        $value = $this->scalar($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace($newlines ? '/[^\P{C}\n\t]+/u' : '/[\p{C}]+/u', '', $value) ?? '';
        if ($newlines) {
            $value = preg_replace('/[ \t]+/u', ' ', $value) ?? '';
            $value = preg_replace('/\n{3,}/', "\n\n", $value) ?? '';
        } else {
            $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        }

        return trim($value);
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($slug, '-');
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) || $value === null ? trim((string) $value) : '';
    }

    private function duplicate(Throwable $exception): bool
    {
        return $exception instanceof PDOException && (string) $exception->getCode() === '23000';
    }

    private function failure(array $errors, bool $notFound = false): array
    {
        return ['success' => false, 'post' => null, 'errors' => $errors, 'not_found' => $notFound];
    }

    private function log(string $operation, ?int $postId, Throwable $exception): void
    {
        $this->logger?->error('Blog persistence operation failed.', ['operation' => $operation, 'post_id' => $postId, 'exception' => $exception::class]);
    }
}
