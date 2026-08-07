<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\CategoryService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\TestCase;

final class CategoryServiceTest extends TestCase
{
    private FakeCategoryRepository $categories;

    private CategoryService $service;

    protected function setUp(): void
    {
        $this->categories = new FakeCategoryRepository();
        $this->service = new CategoryService($this->categories);
    }

    public function testCreateNormalizesCategoryFieldsAndRejectsDuplicateSlug(): void
    {
        $duplicate = $this->service->create([
            'name' => 'Technology Events',
            'slug' => '  TECHNOLOGY  ',
        ]);
        $created = $this->service->create([
            'name' => '  Community Learning  ',
            'slug' => ' Community Learning ',
            'description' => '  Practical sessions for local groups.  ',
            'icon' => '  users-three  ',
            'sort_order' => '12',
        ]);

        $this->assertFalse($duplicate['success']);
        $this->assertArrayHasKey('slug', $duplicate['errors']);
        $this->assertTrue($created['success']);
        $category = $this->categories->find((int) $created['category_id']);
        $this->assertNotNull($category);
        $this->assertSame('Community Learning', $category['name']);
        $this->assertSame('community-learning', $category['slug']);
        $this->assertSame('Practical sessions for local groups.', $category['description']);
        $this->assertSame('users-three', $category['icon']);
        $this->assertSame(12, $category['sort_order']);
        $this->assertSame(1, $category['is_active']);
    }

    public function testUpdateExcludesCurrentCategoryFromDuplicateCheckButRejectsAnotherSlug(): void
    {
        $sameSlug = $this->service->update(1, [
            'name' => 'Technology and Innovation',
            'slug' => 'technology',
            'sort_order' => '3',
        ]);
        $duplicate = $this->service->update(1, [
            'name' => 'Technology and Innovation',
            'slug' => 'archived',
            'sort_order' => '3',
        ]);

        $this->assertTrue($sameSlug['success']);
        $this->assertSame('Technology and Innovation', $this->categories->categories[1]['name']);
        $this->assertFalse($duplicate['success']);
        $this->assertArrayHasKey('slug', $duplicate['errors']);
        $this->assertSame('technology', $this->categories->categories[1]['slug']);
    }

    public function testSetActiveRequiresAnExistingCategoryAndExplicitBooleanState(): void
    {
        $invalid = $this->service->setActive(1, 'toggle');
        $missing = $this->service->setActive(999, '1');
        $deactivated = $this->service->setActive(1, '0');

        $this->assertFalse($invalid['success']);
        $this->assertArrayHasKey('is_active', $invalid['errors']);
        $this->assertFalse($missing['success']);
        $this->assertTrue($missing['not_found']);
        $this->assertTrue($deactivated['success']);
        $this->assertSame(0, $this->categories->categories[1]['is_active']);
    }

    public function testSetActiveSucceedsWithoutWritingWhenCategoryAlreadyHasRequestedState(): void
    {
        $result = $this->service->setActive(2, '0');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_active']);
        $this->assertSame(0, $this->categories->setActiveCalls);
        $this->assertSame(0, $this->categories->categories[2]['is_active']);
    }

    public function testUnchangedUpdateSucceedsWithoutDependingOnDatabaseChangedRowCount(): void
    {
        $this->categories->categories[1] = array_merge($this->categories->categories[1], [
            'parent_id' => null,
            'description' => null,
            'icon' => null,
            'sort_order' => 0,
        ]);
        $this->categories->failUpdate = true;

        $result = $this->service->update(1, [
            'name' => 'Technology',
            'slug' => 'technology',
            'sort_order' => '0',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->categories->updateCalls);
    }

    public function testUpdateRejectsDirectAndDeeperCategoryHierarchyCycles(): void
    {
        $this->categories->categories[1]['parent_id'] = null;
        $this->categories->categories[2]['parent_id'] = 1;
        $this->categories->categories[3] = [
            'id' => 3,
            'parent_id' => 2,
            'name' => 'Deep child',
            'slug' => 'deep-child',
            'is_active' => 1,
        ];

        $direct = $this->service->update(1, [
            'name' => 'Technology',
            'slug' => 'technology',
            'parent_id' => '2',
            'sort_order' => '0',
        ]);
        $deep = $this->service->update(1, [
            'name' => 'Technology',
            'slug' => 'technology',
            'parent_id' => '3',
            'sort_order' => '0',
        ]);

        $this->assertFalse($direct['success']);
        $this->assertArrayHasKey('parent_id', $direct['errors']);
        $this->assertFalse($deep['success']);
        $this->assertArrayHasKey('parent_id', $deep['errors']);
        $this->assertNull($this->categories->categories[1]['parent_id']);
    }

    public function testCaughtCategoryPersistenceExceptionsLogOnlySanitizedIdentifiers(): void
    {
        $logPath = sys_get_temp_dir() . '/oems-category-log-' . bin2hex(random_bytes(6)) . '.log';
        file_put_contents($logPath, '');
        $service = new CategoryService($this->categories, new Logger($logPath));
        $this->categories->failCreate = true;
        $created = $service->create(['name' => 'Logged category', 'sort_order' => '0']);
        $this->categories->failCreate = false;
        $this->categories->throwUpdate = true;
        $updated = $service->update(1, [
            'name' => 'Changed technology',
            'slug' => 'technology',
            'sort_order' => '0',
        ]);
        $this->categories->throwSetActive = true;
        $activated = $service->setActive(1, '0');
        $log = file_get_contents($logPath);

        $this->assertFalse($created['success']);
        $this->assertFalse($updated['success']);
        $this->assertFalse($activated['success']);
        $this->assertTrue(is_string($log));
        $this->assertTrue(str_contains($log, 'Category persistence operation failed.'));
        $this->assertTrue(str_contains($log, '"operation":"create"'));
        $this->assertTrue(str_contains($log, '"operation":"update"'));
        $this->assertTrue(str_contains($log, '"operation":"set_active"'));
        $this->assertTrue(str_contains($log, '"category_id":1'));
        $this->assertFalse(str_contains($log, 'SQL secret'));
        $this->assertFalse(str_contains($log, 'Changed technology'));

        unlink($logPath);
    }
}
