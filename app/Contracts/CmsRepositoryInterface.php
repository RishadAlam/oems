<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface CmsRepositoryInterface
{
    public function fixedPages(array $slugs): array;
    public function findPage(string $slug, bool $publishedOnly = false): ?array;
    public function updatePage(string $slug, array $attributes, int $userId): bool;
    public function setPagePublished(string $slug, bool $published, int $userId): bool;
    public function allFaqs(): array;
    public function activeFaqs(): array;
    public function findFaq(int $id): ?array;
    public function createFaq(array $attributes): int;
    public function updateFaq(int $id, array $attributes): bool;
    public function setFaqActive(int $id, bool $active): bool;
    public function allBanners(): array;
    public function activeHomeBanners(string $now): array;
    public function findBanner(int $id): ?array;
    public function createBanner(array $attributes): int;
    public function updateBanner(int $id, array $attributes): bool;
    public function setBannerActive(int $id, bool $active): bool;
}
