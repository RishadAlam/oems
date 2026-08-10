<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\BlogRepositoryInterface;
use PDO;

final class BlogRepository implements BlogRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(int $authorId, array $attributes): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO blog_posts
                (author_id, title, slug, excerpt, body, category, cover_image, status, meta_title, meta_description, published_at)
             VALUES
                (:author_id, :title, :slug, :excerpt, :body, :category, :cover_image, 'draft', :meta_title, :meta_description, NULL)",
        );
        $statement->execute($this->writeParameters($attributes, ['author_id' => $authorId]));

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $postId, string $expectedUpdatedAt, array $attributes): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE blog_posts SET title = :title, slug = :slug, excerpt = :excerpt, body = :body,
                category = :category, cover_image = :cover_image, meta_title = :meta_title,
                meta_description = :meta_description, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND updated_at = :expected_updated_at AND deleted_at IS NULL",
        );
        $statement->execute($this->writeParameters($attributes, [
            'id' => $postId,
            'expected_updated_at' => $expectedUpdatedAt,
        ]));
        if ($statement->rowCount() === 1) {
            return true;
        }
        $current = $this->findAdmin($postId);

        return $current !== null && (string) ($current['updated_at'] ?? '') === $expectedUpdatedAt
            && $this->sameContent($current, $attributes);
    }

    public function transition(int $postId, string $expectedUpdatedAt, string $status, string $changedAt): bool
    {
        $publishedAt = $status === 'published' ? $changedAt : null;
        $statement = $this->connection->prepare(
            'UPDATE blog_posts SET status = :status, published_at = :published_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND updated_at = :expected_updated_at AND deleted_at IS NULL',
        );
        $statement->execute([
            'status' => $status,
            'published_at' => $publishedAt,
            'id' => $postId,
            'expected_updated_at' => $expectedUpdatedAt,
        ]);
        if ($statement->rowCount() === 1) {
            return true;
        }
        $current = $this->findAdmin($postId);

        return $current !== null && (string) ($current['status'] ?? '') === $status;
    }

    public function softDelete(int $postId, string $expectedUpdatedAt, string $deletedAt): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE blog_posts SET deleted_at = :deleted_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND updated_at = :expected_updated_at AND deleted_at IS NULL',
        );
        $statement->execute(['deleted_at' => $deletedAt, 'id' => $postId, 'expected_updated_at' => $expectedUpdatedAt]);

        return $statement->rowCount() === 1;
    }

    public function findAdmin(int $postId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect() . ' WHERE blog_posts.id = :id AND blog_posts.deleted_at IS NULL LIMIT 1',
        );
        $statement->execute(['id' => $postId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function adminList(array $filters, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->adminCriteria($filters);
        $statement = $this->connection->prepare(
            $this->adminSelect() . $where . ' ORDER BY blog_posts.updated_at DESC, blog_posts.id DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countAdmin(array $filters): int
    {
        [$where, $parameters] = $this->adminCriteria($filters);
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM blog_posts' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function publicList(?string $category, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->publicCriteria($category);
        $statement = $this->connection->prepare($this->publicSelect() . $where . ' ORDER BY blog_posts.published_at DESC, blog_posts.id DESC LIMIT :limit OFFSET :offset');
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countPublic(?string $category): int
    {
        [$where, $parameters] = $this->publicCriteria($category);
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM blog_posts' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function publicCategories(): array
    {
        $statement = $this->connection->query(
            "SELECT DISTINCT category FROM blog_posts
             WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP
               AND deleted_at IS NULL AND category IS NOT NULL AND category <> ''
             ORDER BY category ASC",
        );

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $statement = $this->connection->prepare(
            $this->publicSelect()
            . " WHERE blog_posts.slug = :slug AND blog_posts.status = 'published'
                 AND blog_posts.published_at IS NOT NULL AND blog_posts.published_at <= CURRENT_TIMESTAMP
                 AND blog_posts.deleted_at IS NULL LIMIT 1",
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function adminSelect(): string
    {
        return 'SELECT blog_posts.*, users.name AS author_name FROM blog_posts LEFT JOIN users ON users.id = blog_posts.author_id';
    }

    private function publicSelect(): string
    {
        return 'SELECT blog_posts.id, blog_posts.title, blog_posts.slug, blog_posts.excerpt, blog_posts.body,
                       blog_posts.category, blog_posts.cover_image, blog_posts.meta_title, blog_posts.meta_description,
                       blog_posts.published_at, users.name AS author_name
                FROM blog_posts LEFT JOIN users ON users.id = blog_posts.author_id';
    }

    private function adminCriteria(array $filters): array
    {
        $clauses = ['blog_posts.deleted_at IS NULL'];
        $parameters = [];
        if (isset($filters['status'])) {
            $clauses[] = 'blog_posts.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (isset($filters['search'])) {
            $clauses[] = '(blog_posts.title LIKE :search OR blog_posts.slug LIKE :search_slug)';
            $parameters['search'] = '%' . $filters['search'] . '%';
            $parameters['search_slug'] = '%' . $filters['search'] . '%';
        }

        return [' WHERE ' . implode(' AND ', $clauses), $parameters];
    }

    private function publicCriteria(?string $category): array
    {
        $where = " WHERE blog_posts.status = 'published' AND blog_posts.published_at IS NOT NULL
                   AND blog_posts.published_at <= CURRENT_TIMESTAMP AND blog_posts.deleted_at IS NULL";
        $parameters = [];
        if ($category !== null) {
            $where .= ' AND blog_posts.category = :category';
            $parameters['category'] = $category;
        }

        return [$where, $parameters];
    }

    private function writeParameters(array $attributes, array $extra = []): array
    {
        return array_merge([
            'title' => (string) ($attributes['title'] ?? ''),
            'slug' => (string) ($attributes['slug'] ?? ''),
            'excerpt' => (string) ($attributes['excerpt'] ?? ''),
            'body' => (string) ($attributes['body'] ?? ''),
            'category' => $attributes['category'] ?? null,
            'cover_image' => $attributes['cover_image'] ?? null,
            'meta_title' => $attributes['meta_title'] ?? null,
            'meta_description' => $attributes['meta_description'] ?? null,
        ], $extra);
    }

    private function sameContent(array $current, array $attributes): bool
    {
        foreach (['title', 'slug', 'excerpt', 'body', 'category', 'cover_image', 'meta_title', 'meta_description'] as $field) {
            if (($current[$field] ?? null) !== ($attributes[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
