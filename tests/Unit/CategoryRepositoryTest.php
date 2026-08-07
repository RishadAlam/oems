<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\CategoryRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class CategoryRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->exec(
            'CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT NULL,
                icon TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
        $this->connection->exec(
            "INSERT INTO categories
                (id, name, slug, description, icon, is_active, sort_order, created_at, updated_at)
             VALUES
                (1, 'Technology', 'technology', 'Developer events', 'cpu', 1, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (2, 'Community', 'community', 'Neighbourhood events', 'users', 1, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (3, 'Archived', 'archived', 'Disabled events', 'archive', 0, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        );
    }

    public function testActiveCategoriesExcludeDisabledRowsAndFollowSortOrder(): void
    {
        $repository = new CategoryRepository($this->connection);

        $this->assertSame(['technology', 'community'], array_column($repository->active(), 'slug'));
    }

    public function testAllCategoriesIncludeDisabledRowsInSortOrder(): void
    {
        $repository = new CategoryRepository($this->connection);

        $this->assertSame(['archived', 'technology', 'community'], array_column($repository->all(), 'slug'));
    }

    public function testFindReturnsCategoryOrNullWhenItDoesNotExist(): void
    {
        $repository = new CategoryRepository($this->connection);

        $category = $repository->find(1);

        $this->assertNotNull($category);
        $this->assertSame('Technology', $category['name']);
        $this->assertNull($repository->find(99));
    }

    public function testSlugExistsCanExcludeTheCategoryBeingEdited(): void
    {
        $repository = new CategoryRepository($this->connection);

        $this->assertTrue($repository->slugExists('technology', null));
        $this->assertFalse($repository->slugExists('technology', 1));
        $this->assertFalse($repository->slugExists('missing', null));
    }

    public function testCreatePersistsAllCategoryAttributesAndReturnsItsId(): void
    {
        $repository = new CategoryRepository($this->connection);

        $id = $repository->create([
            'parent_id' => 1,
            'name' => 'Workshops',
            'slug' => 'workshops',
            'description' => 'Hands-on learning.',
            'icon' => 'chalkboard',
            'is_active' => false,
            'sort_order' => 25,
        ]);

        $category = $repository->find($id);
        $this->assertTrue($id > 3);
        $this->assertSame(1, (int) $category['parent_id']);
        $this->assertSame('workshops', $category['slug']);
        $this->assertSame(0, (int) $category['is_active']);
        $this->assertSame(25, (int) $category['sort_order']);
    }

    public function testUpdateChangesOnlyTheRequestedCategoryAndReportsMissingRows(): void
    {
        $repository = new CategoryRepository($this->connection);

        $updated = $repository->update(2, [
            'parent_id' => 1,
            'name' => 'Local community',
            'slug' => 'local-community',
            'description' => 'Updated.',
            'icon' => 'people',
            'sort_order' => 12,
        ]);

        $category = $repository->find(2);
        $untouched = $repository->find(1);
        $this->assertTrue($updated);
        $this->assertSame('local-community', $category['slug']);
        $this->assertSame(1, (int) $category['parent_id']);
        $this->assertSame('technology', $untouched['slug']);
        $this->assertFalse($repository->update(99, [
            'parent_id' => null,
            'name' => 'Missing',
            'slug' => 'missing',
            'description' => null,
            'icon' => null,
            'sort_order' => 0,
        ]));
    }

    public function testSetActiveChangesTheRequestedCategoryAndReportsMissingRows(): void
    {
        $repository = new CategoryRepository($this->connection);

        $this->assertTrue($repository->setActive(1, false));
        $this->assertSame(['community'], array_column($repository->active(), 'slug'));
        $this->assertFalse($repository->setActive(99, true));
    }
}
