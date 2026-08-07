<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\Core\Validator;
use Throwable;

final class CategoryService
{
    private const FIELDS = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
    ];

    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    public function create(array $data): array
    {
        [$attributes, $errors] = $this->categoryAttributes($data, null);

        if ($errors !== []) {
            return $this->failure($errors);
        }

        $attributes['is_active'] = 1;

        try {
            $categoryId = $this->categories->create($attributes);
        } catch (Throwable) {
            return $this->failure(['category' => ['The category could not be created.']]);
        }

        return $this->success(['category_id' => $categoryId]);
    }

    public function update(int $categoryId, array $data): array
    {
        $existing = $this->categories->find($categoryId);

        if ($existing === null) {
            return $this->notFound();
        }

        [$attributes, $errors] = $this->categoryAttributes($data, $categoryId);

        if ($errors !== []) {
            return $this->failure($errors);
        }

        if ($this->sameCategory($existing, $attributes)) {
            return $this->success(['category_id' => $categoryId]);
        }

        try {
            $updated = $this->categories->update($categoryId, $attributes);
        } catch (Throwable) {
            $updated = false;
        }

        if (!$updated) {
            return $this->failure(['category' => ['The category could not be updated.']]);
        }

        return $this->success(['category_id' => $categoryId]);
    }

    public function setActive(int $categoryId, mixed $requestedState): array
    {
        if ($this->categories->find($categoryId) === null) {
            return $this->notFound();
        }

        if (!in_array($requestedState, [true, false, 0, 1, '0', '1'], true)) {
            return $this->failure(['is_active' => ['Choose an explicit category status.']]);
        }

        $isActive = in_array($requestedState, [true, 1, '1'], true);

        try {
            $updated = $this->categories->setActive($categoryId, $isActive);
        } catch (Throwable) {
            $updated = false;
        }

        if (!$updated) {
            return $this->failure(['category' => ['The category status could not be changed.']]);
        }

        return $this->success(['category_id' => $categoryId, 'is_active' => $isActive]);
    }

    private function categoryAttributes(array $data, ?int $categoryId): array
    {
        $attributes = [];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : '';
            $attributes[$field] = $value === '' ? null : $value;
        }

        $attributes['name'] = (string) ($attributes['name'] ?? '');
        $attributes['slug'] = $this->slug((string) ($attributes['slug'] ?? $attributes['name']));
        $attributes['sort_order'] ??= '0';
        $errors = Validator::validate($attributes, [
            'parent_id' => 'nullable|integer|min_value:1',
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'required|integer|min_value:0|max_value:1000000',
        ]);

        if ($attributes['slug'] !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $attributes['slug']) !== 1) {
            $errors['slug'][] = 'Slug may contain lowercase letters, numbers, and single hyphens only.';
        }

        if (!isset($errors['slug']) && $this->categories->slugExists($attributes['slug'], $categoryId)) {
            $errors['slug'][] = 'That category slug is already in use.';
        }

        if (!isset($errors['parent_id']) && $attributes['parent_id'] !== null) {
            $parentId = (int) $attributes['parent_id'];

            if ($parentId === $categoryId
                || $this->categories->find($parentId) === null
                || ($categoryId !== null && $this->wouldCreateCycle($categoryId, $parentId))) {
                $errors['parent_id'][] = 'Select another existing parent category.';
            }
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        $attributes['parent_id'] = $attributes['parent_id'] === null ? null : (int) $attributes['parent_id'];
        $attributes['sort_order'] = (int) $attributes['sort_order'];

        return [$attributes, []];
    }

    private function sameCategory(array $existing, array $attributes): bool
    {
        $stored = [
            'parent_id' => ($existing['parent_id'] ?? null) === null ? null : (int) $existing['parent_id'],
            'name' => (string) ($existing['name'] ?? ''),
            'slug' => (string) ($existing['slug'] ?? ''),
            'description' => ($existing['description'] ?? null) === null ? null : (string) $existing['description'],
            'icon' => ($existing['icon'] ?? null) === null ? null : (string) $existing['icon'],
            'sort_order' => (int) ($existing['sort_order'] ?? 0),
        ];

        return $stored === $attributes;
    }

    private function wouldCreateCycle(int $categoryId, int $parentId): bool
    {
        $visited = [];
        $currentId = $parentId;

        while ($currentId > 0) {
            if ($currentId === $categoryId || isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;
            $category = $this->categories->find($currentId);
            $ancestorId = $category['parent_id'] ?? null;

            if (!is_int($ancestorId) && !ctype_digit((string) $ancestorId)) {
                return false;
            }

            $currentId = (int) $ancestorId;
        }

        return false;
    }

    private function slug(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value)) : strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim(is_string($slug) ? $slug : '', '-');
    }

    private function success(array $data = []): array
    {
        return array_merge(['success' => true, 'not_found' => false, 'errors' => []], $data);
    }

    private function failure(array $errors): array
    {
        return ['success' => false, 'not_found' => false, 'errors' => $errors];
    }

    private function notFound(): array
    {
        return ['success' => false, 'not_found' => true, 'errors' => []];
    }
}
