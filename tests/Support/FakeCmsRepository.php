<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\CmsRepositoryInterface;
use RuntimeException;

final class FakeCmsRepository implements CmsRepositoryInterface
{
    public array $pages = [];
    public array $faqs = [];
    public array $banners = [];
    public bool $failWrite = false;
    public bool $failRead = false;

    public function __construct()
    {
        foreach (['about', 'contact', 'privacy', 'terms'] as $index => $slug) {
            $this->pages[$slug] = [
                'id' => $index + 1,
                'title' => ucfirst($slug),
                'slug' => $slug,
                'content' => ucfirst($slug) . ' content.',
                'meta_title' => null,
                'meta_description' => null,
                'status' => $slug === 'contact' ? 'draft' : 'published',
                'published_at' => $slug === 'contact' ? null : '2026-08-10 10:00:00',
            ];
        }
        $this->faqs[1] = ['id' => 1, 'question' => 'How do tickets work?', 'answer' => 'Tickets appear after confirmation.', 'category' => 'Tickets', 'sort_order' => 10, 'is_active' => 1];
        $this->banners[1] = ['id' => 1, 'title' => 'Dhaka design week', 'subtitle' => 'Book a place.', 'image_path' => '/uploads/banners/old.jpg', 'link_url' => '/events', 'location' => 'home', 'starts_at' => null, 'ends_at' => null, 'sort_order' => 10, 'is_active' => 1];
    }

    public function fixedPages(array $slugs): array { return array_values(array_intersect_key($this->pages, array_flip($slugs))); }
    public function findPage(string $slug, bool $publishedOnly = false): ?array
    {
        if ($this->failRead) throw new RuntimeException('SQL secret');
        $page = $this->pages[$slug] ?? null;
        return $publishedOnly && ($page['status'] ?? '') !== 'published' ? null : $page;
    }
    public function updatePage(string $slug, array $attributes, int $userId): bool
    {
        $this->guard();
        if (!isset($this->pages[$slug])) return false;
        $this->pages[$slug] = array_merge($this->pages[$slug], $attributes, ['updated_by' => $userId]);
        return true;
    }
    public function setPagePublished(string $slug, bool $published, int $userId): bool
    {
        $this->guard();
        if (!isset($this->pages[$slug])) return false;
        $this->pages[$slug]['status'] = $published ? 'published' : 'draft';
        $this->pages[$slug]['published_at'] = $published ? '2026-08-10 12:00:00' : null;
        $this->pages[$slug]['updated_by'] = $userId;
        return true;
    }
    public function allFaqs(): array { return array_values($this->faqs); }
    public function activeFaqs(): array { return array_values(array_filter($this->faqs, static fn(array $faq): bool => !empty($faq['is_active']))); }
    public function findFaq(int $id): ?array { return $this->faqs[$id] ?? null; }
    public function createFaq(array $attributes): int { $this->guard(); $id = $this->faqs === [] ? 1 : max(array_keys($this->faqs)) + 1; $this->faqs[$id] = array_merge(['id' => $id, 'is_active' => 1], $attributes); return $id; }
    public function updateFaq(int $id, array $attributes): bool { $this->guard(); if (!isset($this->faqs[$id])) return false; $this->faqs[$id] = array_merge($this->faqs[$id], $attributes); return true; }
    public function setFaqActive(int $id, bool $active): bool { $this->guard(); if (!isset($this->faqs[$id])) return false; $this->faqs[$id]['is_active'] = $active ? 1 : 0; return true; }
    public function allBanners(): array { return array_values($this->banners); }
    public function activeHomeBanners(string $now): array { if ($this->failRead) throw new RuntimeException('SQL secret'); return array_values(array_filter($this->banners, static fn(array $banner): bool => !empty($banner['is_active']) && ($banner['location'] ?? '') === 'home')); }
    public function findBanner(int $id): ?array { return $this->banners[$id] ?? null; }
    public function createBanner(array $attributes): int { $this->guard(); $id = $this->banners === [] ? 1 : max(array_keys($this->banners)) + 1; $this->banners[$id] = array_merge(['id' => $id], $attributes); return $id; }
    public function updateBanner(int $id, array $attributes): bool { $this->guard(); if (!isset($this->banners[$id])) return false; $this->banners[$id] = array_merge($this->banners[$id], $attributes); return true; }
    public function setBannerActive(int $id, bool $active): bool { $this->guard(); if (!isset($this->banners[$id])) return false; $this->banners[$id]['is_active'] = $active ? 1 : 0; return true; }

    private function guard(): void { if ($this->failWrite) throw new RuntimeException('SQL secret'); }
}
