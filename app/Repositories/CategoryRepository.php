<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\CategoryRepositoryInterface;
use PDO;

final class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function active(): array
    {
        return $this->fetchCategories('WHERE is_active = 1');
    }

    public function all(): array
    {
        return $this->fetchCategories();
    }

    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, parent_id, name, slug, description, icon, is_active, sort_order, created_at, updated_at
             FROM categories
             WHERE id = :id
             LIMIT 1',
        );
        $statement->execute(['id' => $id]);
        $category = $statement->fetch();

        return is_array($category) ? $category : null;
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        $query = 'SELECT id FROM categories WHERE slug = :slug';
        $parameters = ['slug' => $slug];

        if ($exceptId !== null) {
            $query .= ' AND id != :except_id';
            $parameters['except_id'] = $exceptId;
        }

        $query .= ' LIMIT 1';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function create(array $attributes): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO categories
                (parent_id, name, slug, description, icon, is_active, sort_order, created_at, updated_at)
             VALUES
                (:parent_id, :name, :slug, :description, :icon, :is_active, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'parent_id' => $attributes['parent_id'] ?? null,
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'description' => $attributes['description'] ?? null,
            'icon' => $attributes['icon'] ?? null,
            'is_active' => array_key_exists('is_active', $attributes) && !$attributes['is_active'] ? 0 : 1,
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $attributes): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE categories
             SET parent_id = :parent_id,
                 name = :name,
                 slug = :slug,
                 description = :description,
                 icon = :icon,
                 sort_order = :sort_order,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute([
            'parent_id' => $attributes['parent_id'] ?? null,
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'description' => $attributes['description'] ?? null,
            'icon' => $attributes['icon'] ?? null,
            'sort_order' => $attributes['sort_order'] ?? 0,
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }

    public function setActive(int $id, bool $isActive): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE categories
             SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute([
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }

    private function fetchCategories(string $where = ''): array
    {
        $statement = $this->connection->query(
            'SELECT id, parent_id, name, slug, description, icon, is_active, sort_order, created_at, updated_at
             FROM categories
             ' . $where . '
             ORDER BY sort_order ASC, name ASC, id ASC',
        );
        $categories = $statement->fetchAll();

        return is_array($categories) ? $categories : [];
    }
}
