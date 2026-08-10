<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\CmsRepositoryInterface;
use PDO;
use Throwable;

final class CmsRepository implements CmsRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function fixedPages(array $slugs): array
    {
        $slugs = array_values(array_unique(array_filter($slugs, 'is_string')));
        if ($slugs === []) return [];
        $statement = $this->connection->prepare(
            'SELECT id, title, slug, content, meta_title, meta_description, status, published_at, created_at, updated_at
             FROM pages WHERE slug IN (' . implode(', ', array_fill(0, count($slugs), '?')) . ')
             ORDER BY CASE slug WHEN \'about\' THEN 1 WHEN \'contact\' THEN 2 WHEN \'privacy\' THEN 3 WHEN \'terms\' THEN 4 ELSE 5 END',
        );
        $statement->execute($slugs);
        return $this->rows($statement);
    }

    public function findPage(string $slug, bool $publishedOnly = false): ?array
    {
        $query = 'SELECT id, title, slug, content, meta_title, meta_description, status, published_at, created_at, updated_at FROM pages WHERE slug = :slug';
        if ($publishedOnly) $query .= " AND status = 'published' AND published_at IS NOT NULL";
        $statement = $this->connection->prepare($query . ' LIMIT 1');
        $statement->execute(['slug' => $slug]);
        return $this->row($statement);
    }

    public function updatePage(string $slug, array $attributes, int $userId): bool
    {
        return $this->transaction(function () use ($slug, $attributes, $userId): bool {
            $statement = $this->connection->prepare(
                'UPDATE pages SET title = :title, content = :content, meta_title = :meta_title,
                 meta_description = :meta_description, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP
                 WHERE slug = :slug',
            );
            $statement->execute([
                'title' => $attributes['title'], 'content' => $attributes['content'],
                'meta_title' => $attributes['meta_title'], 'meta_description' => $attributes['meta_description'],
                'updated_by' => $userId, 'slug' => $slug,
            ]);
            return $statement->rowCount() > 0 || $this->exists('pages', 'slug', $slug);
        });
    }

    public function setPagePublished(string $slug, bool $published, int $userId): bool
    {
        return $this->transaction(function () use ($slug, $published, $userId): bool {
            $statement = $this->connection->prepare(
                "UPDATE pages SET status = :status, published_at = " . ($published ? 'CURRENT_TIMESTAMP' : 'NULL') . ",
                 updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP WHERE slug = :slug",
            );
            $statement->execute(['status' => $published ? 'published' : 'draft', 'updated_by' => $userId, 'slug' => $slug]);
            return $statement->rowCount() > 0 || $this->exists('pages', 'slug', $slug);
        });
    }

    public function allFaqs(): array
    {
        return $this->rows($this->connection->query(
            'SELECT id, question, answer, category, sort_order, is_active, created_at, updated_at FROM faqs ORDER BY sort_order, id',
        ));
    }

    public function activeFaqs(): array
    {
        return $this->rows($this->connection->query(
            'SELECT id, question, answer, category, sort_order FROM faqs WHERE is_active = 1 ORDER BY sort_order, id',
        ));
    }

    public function findFaq(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, question, answer, category, sort_order, is_active, created_at, updated_at FROM faqs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $this->row($statement);
    }

    public function createFaq(array $attributes): int
    {
        return $this->transaction(function () use ($attributes): int {
            $statement = $this->connection->prepare(
                'INSERT INTO faqs (question, answer, category, sort_order, is_active, created_at, updated_at)
                 VALUES (:question, :answer, :category, :sort_order, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );
            $statement->execute($attributes);
            return (int) $this->connection->lastInsertId();
        });
    }

    public function updateFaq(int $id, array $attributes): bool
    {
        return $this->transaction(function () use ($id, $attributes): bool {
            $statement = $this->connection->prepare(
                'UPDATE faqs SET question = :question, answer = :answer, category = :category,
                 sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            );
            $statement->execute(array_merge($attributes, ['id' => $id]));
            return $statement->rowCount() > 0 || $this->exists('faqs', 'id', $id);
        });
    }

    public function setFaqActive(int $id, bool $active): bool
    {
        return $this->setActive('faqs', $id, $active);
    }

    public function allBanners(): array
    {
        return $this->rows($this->connection->query(
            'SELECT id, title, subtitle, image_path, link_url, location, starts_at, ends_at, is_active, sort_order, created_at, updated_at
             FROM banners ORDER BY sort_order, id',
        ));
    }

    public function activeHomeBanners(string $now): array
    {
        $statement = $this->connection->prepare(
            "SELECT id, title, subtitle, image_path, link_url, starts_at, ends_at, sort_order
             FROM banners WHERE location = 'home' AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)
             ORDER BY sort_order, id",
        );
        $statement->execute(['now_start' => $now, 'now_end' => $now]);
        return $this->rows($statement);
    }

    public function findBanner(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, title, subtitle, image_path, link_url, location, starts_at, ends_at, is_active, sort_order, created_at, updated_at FROM banners WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);
        return $this->row($statement);
    }

    public function createBanner(array $attributes): int
    {
        return $this->transaction(function () use ($attributes): int {
            $statement = $this->connection->prepare(
                'INSERT INTO banners (title, subtitle, image_path, link_url, location, starts_at, ends_at, is_active, sort_order, created_at, updated_at)
                 VALUES (:title, :subtitle, :image_path, :link_url, :location, :starts_at, :ends_at, 1, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );
            $statement->execute($attributes);
            return (int) $this->connection->lastInsertId();
        });
    }

    public function updateBanner(int $id, array $attributes): bool
    {
        return $this->transaction(function () use ($id, $attributes): bool {
            $statement = $this->connection->prepare(
                'UPDATE banners SET title = :title, subtitle = :subtitle, image_path = :image_path,
                 link_url = :link_url, location = :location, starts_at = :starts_at, ends_at = :ends_at,
                 sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            );
            $statement->execute(array_merge($attributes, ['id' => $id]));
            return $statement->rowCount() > 0 || $this->exists('banners', 'id', $id);
        });
    }

    public function setBannerActive(int $id, bool $active): bool
    {
        return $this->setActive('banners', $id, $active);
    }

    private function setActive(string $table, int $id, bool $active): bool
    {
        return $this->transaction(function () use ($table, $id, $active): bool {
            $statement = $this->connection->prepare("UPDATE {$table} SET is_active = :active, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $statement->execute(['active' => $active ? 1 : 0, 'id' => $id]);
            return $statement->rowCount() > 0 || $this->exists($table, 'id', $id);
        });
    }

    private function exists(string $table, string $column, int|string $value): bool
    {
        if (!in_array($table, ['pages', 'faqs', 'banners'], true) || !in_array($column, ['id', 'slug'], true)) return false;
        $statement = $this->connection->prepare("SELECT 1 FROM {$table} WHERE {$column} = :value LIMIT 1");
        $statement->execute(['value' => $value]);
        return $statement->fetchColumn() !== false;
    }

    private function transaction(callable $callback): mixed
    {
        $started = !$this->connection->inTransaction();
        if ($started) $this->connection->beginTransaction();
        try {
            $result = $callback();
            if ($started) $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($started && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    private function rows(object $statement): array
    {
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function row(object $statement): ?array
    {
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }
}
