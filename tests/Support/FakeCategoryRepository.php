<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\CategoryRepositoryInterface;

final class FakeCategoryRepository implements CategoryRepositoryInterface
{
    public bool $failCreate = false;

    public bool $failUpdate = false;

    public int $updateCalls = 0;

    public array $categories = [
        1 => ['id' => 1, 'name' => 'Technology', 'slug' => 'technology', 'is_active' => 1],
        2 => ['id' => 2, 'name' => 'Archived', 'slug' => 'archived', 'is_active' => 0],
    ];

    public function active(): array
    {
        return array_values(array_filter(
            $this->categories,
            static fn (array $category): bool => (bool) ($category['is_active'] ?? false),
        ));
    }

    public function all(): array
    {
        return array_values($this->categories);
    }

    public function find(int $id): ?array
    {
        return $this->categories[$id] ?? null;
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        foreach ($this->categories as $id => $category) {
            if ($id !== $exceptId && $category['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    public function create(array $attributes): int
    {
        if ($this->failCreate) {
            throw new \RuntimeException('Category create failed.');
        }

        $id = $this->categories === [] ? 1 : max(array_keys($this->categories)) + 1;
        $this->categories[$id] = array_merge($attributes, ['id' => $id]);

        return $id;
    }

    public function update(int $id, array $attributes): bool
    {
        $this->updateCalls++;

        if ($this->failUpdate) {
            return false;
        }

        if (!isset($this->categories[$id])) {
            return false;
        }

        $this->categories[$id] = array_merge($this->categories[$id], $attributes);

        return true;
    }

    public function setActive(int $id, bool $isActive): bool
    {
        if (!isset($this->categories[$id])) {
            return false;
        }

        $this->categories[$id]['is_active'] = $isActive ? 1 : 0;

        return true;
    }
}
