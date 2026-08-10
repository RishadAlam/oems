<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\CmsRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class CmsService
{
    public const PAGE_SLUGS = ['about', 'contact', 'privacy', 'terms'];

    public function __construct(
        private readonly CmsRepositoryInterface $cms,
        private readonly ImageUploadService $bannerImages,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function updatePage(string $slug, array $data, int $userId): array
    {
        if (!in_array($slug, self::PAGE_SLUGS, true) || $this->cms->findPage($slug) === null) return $this->notFound();
        [$attributes, $errors] = $this->pageAttributes($data);
        if ($errors !== []) return $this->failure($errors);
        try {
            return $this->cms->updatePage($slug, $attributes, $userId)
                ? $this->success() : $this->failure(['page' => ['The page could not be updated.']]);
        } catch (Throwable) {
            $this->log('page_update', ['slug' => $slug]);
            return $this->failure(['page' => ['The page could not be updated.']]);
        }
    }

    public function setPagePublished(string $slug, mixed $state, int $userId): array
    {
        $page = in_array($slug, self::PAGE_SLUGS, true) ? $this->cms->findPage($slug) : null;
        if ($page === null) return $this->notFound();
        $active = $this->explicitBoolean($state);
        if ($active === null) return $this->failure(['status' => ['Choose publish or unpublish explicitly.']]);
        if ((($page['status'] ?? '') === 'published') === $active) return $this->success(['published' => $active]);
        try {
            return $this->cms->setPagePublished($slug, $active, $userId)
                ? $this->success(['published' => $active]) : $this->failure(['page' => ['The page status could not be changed.']]);
        } catch (Throwable) {
            $this->log('page_status', ['slug' => $slug]);
            return $this->failure(['page' => ['The page status could not be changed.']]);
        }
    }

    public function createFaq(array $data): array
    {
        [$attributes, $errors] = $this->faqAttributes($data);
        if ($errors !== []) return $this->failure($errors);
        try {
            return $this->success(['faq_id' => $this->cms->createFaq($attributes)]);
        } catch (Throwable) {
            $this->log('faq_create');
            return $this->failure(['faq' => ['The FAQ could not be created.']]);
        }
    }

    public function updateFaq(int $id, array $data): array
    {
        if ($id <= 0 || $this->cms->findFaq($id) === null) return $this->notFound();
        [$attributes, $errors] = $this->faqAttributes($data);
        if ($errors !== []) return $this->failure($errors);
        try {
            return $this->cms->updateFaq($id, $attributes) ? $this->success() : $this->failure(['faq' => ['The FAQ could not be updated.']]);
        } catch (Throwable) {
            $this->log('faq_update', ['faq_id' => $id]);
            return $this->failure(['faq' => ['The FAQ could not be updated.']]);
        }
    }

    public function setFaqActive(int $id, mixed $state): array
    {
        $faq = $id > 0 ? $this->cms->findFaq($id) : null;
        if ($faq === null) return $this->notFound();
        $active = $this->explicitBoolean($state);
        if ($active === null) return $this->failure(['is_active' => ['Choose an explicit FAQ status.']]);
        if (!empty($faq['is_active']) === $active) return $this->success(['active' => $active]);
        try {
            return $this->cms->setFaqActive($id, $active) ? $this->success(['active' => $active]) : $this->failure(['faq' => ['The FAQ status could not be changed.']]);
        } catch (Throwable) {
            $this->log('faq_status', ['faq_id' => $id]);
            return $this->failure(['faq' => ['The FAQ status could not be changed.']]);
        }
    }

    public function createBanner(array $data, ?array $upload): array
    {
        [$attributes, $errors] = $this->bannerAttributes($data);
        if ($errors !== []) return $this->failure($errors);
        $stored = $this->bannerImages->store($upload);
        if (!$stored['success']) return $this->failure(['image' => [$stored['error'] ?? 'Choose a valid banner image.']]);
        if ($stored['path'] === null) return $this->failure(['image' => ['Choose a banner image.']]);
        $attributes['image_path'] = $stored['path'];
        try {
            return $this->success(['banner_id' => $this->cms->createBanner($attributes)]);
        } catch (Throwable) {
            $this->bannerImages->delete($stored['path']);
            $this->log('banner_create');
            return $this->failure(['banner' => ['The banner could not be created.']]);
        }
    }

    public function updateBanner(int $id, array $data, ?array $upload): array
    {
        $existing = $id > 0 ? $this->cms->findBanner($id) : null;
        if ($existing === null) return $this->notFound();
        [$attributes, $errors] = $this->bannerAttributes($data);
        if ($errors !== []) return $this->failure($errors);
        $stored = $this->bannerImages->store($upload);
        if (!$stored['success']) return $this->failure(['image' => [$stored['error'] ?? 'Choose a valid banner image.']]);
        $attributes['image_path'] = $stored['path'] ?? (string) $existing['image_path'];
        try {
            $updated = $this->cms->updateBanner($id, $attributes);
        } catch (Throwable) {
            if ($stored['path'] !== null) $this->bannerImages->delete($stored['path']);
            $this->log('banner_update', ['banner_id' => $id]);
            return $this->failure(['banner' => ['The banner could not be updated.']]);
        }
        if (!$updated) {
            if ($stored['path'] !== null) $this->bannerImages->delete($stored['path']);
            return $this->failure(['banner' => ['The banner could not be updated.']]);
        }
        if ($stored['path'] !== null) $this->bannerImages->delete((string) ($existing['image_path'] ?? ''));
        return $this->success();
    }

    public function setBannerActive(int $id, mixed $state): array
    {
        $banner = $id > 0 ? $this->cms->findBanner($id) : null;
        if ($banner === null) return $this->notFound();
        $active = $this->explicitBoolean($state);
        if ($active === null) return $this->failure(['is_active' => ['Choose an explicit banner status.']]);
        if (!empty($banner['is_active']) === $active) return $this->success(['active' => $active]);
        try {
            return $this->cms->setBannerActive($id, $active) ? $this->success(['active' => $active]) : $this->failure(['banner' => ['The banner status could not be changed.']]);
        } catch (Throwable) {
            $this->log('banner_status', ['banner_id' => $id]);
            return $this->failure(['banner' => ['The banner status could not be changed.']]);
        }
    }

    private function pageAttributes(array $data): array
    {
        $attributes = [];
        $errors = [];
        foreach (['title' => [180, true], 'content' => [20000, true], 'meta_title' => [190, false], 'meta_description' => [320, false]] as $field => [$max, $required]) {
            $value = $this->plainText($data[$field] ?? '', $max, $required);
            if ($value === false) $errors[$field][] = 'Enter plain text within the allowed length.';
            else $attributes[$field] = $value === '' ? null : $value;
        }
        if (isset($attributes['title'])) $attributes['title'] = (string) $attributes['title'];
        if (isset($attributes['content'])) $attributes['content'] = (string) $attributes['content'];
        return [$attributes, $errors];
    }

    private function faqAttributes(array $data): array
    {
        $attributes = [];
        $errors = [];
        foreach (['question' => [255, true], 'answer' => [5000, true], 'category' => [100, false]] as $field => [$max, $required]) {
            $value = $this->plainText($data[$field] ?? '', $max, $required);
            if ($value === false) $errors[$field][] = 'Enter plain text within the allowed length.';
            else $attributes[$field] = $value === '' ? null : $value;
        }
        $order = $data['sort_order'] ?? '0';
        if (!is_scalar($order) || filter_var((string) $order, FILTER_VALIDATE_INT) === false || (int) $order < 0 || (int) $order > 1000000) $errors['sort_order'][] = 'Enter a sort order from 0 to 1000000.';
        else $attributes['sort_order'] = (int) $order;
        if (isset($attributes['question'])) $attributes['question'] = (string) $attributes['question'];
        if (isset($attributes['answer'])) $attributes['answer'] = (string) $attributes['answer'];
        return [$attributes, $errors];
    }

    private function bannerAttributes(array $data): array
    {
        $attributes = ['location' => 'home'];
        $errors = [];
        foreach (['title' => [180, true], 'subtitle' => [255, false]] as $field => [$max, $required]) {
            $value = $this->plainText($data[$field] ?? '', $max, $required);
            if ($value === false) $errors[$field][] = 'Enter plain text within the allowed length.';
            else $attributes[$field] = $value === '' ? null : $value;
        }
        $attributes['title'] = (string) ($attributes['title'] ?? '');
        $link = $this->plainText($data['link_url'] ?? '', 500, false);
        if ($link === false || ($link !== '' && (!$this->relativeLink($link)))) $errors['link_url'][] = 'Use a same-origin relative path beginning with one slash.';
        else $attributes['link_url'] = $link === '' ? null : $link;
        foreach (['starts_at', 'ends_at'] as $field) {
            $date = $this->date($data[$field] ?? '');
            if ($date === false) $errors[$field][] = 'Enter a valid date and time.';
            else $attributes[$field] = $date;
        }
        if (($attributes['starts_at'] ?? null) !== null && ($attributes['ends_at'] ?? null) !== null && $attributes['ends_at'] <= $attributes['starts_at']) $errors['ends_at'][] = 'The end must be after the start.';
        $order = $data['sort_order'] ?? '0';
        if (!is_scalar($order) || filter_var((string) $order, FILTER_VALIDATE_INT) === false || (int) $order < 0 || (int) $order > 1000000) $errors['sort_order'][] = 'Enter a sort order from 0 to 1000000.';
        else $attributes['sort_order'] = (int) $order;
        return [$attributes, $errors];
    }

    private function plainText(mixed $value, int $max, bool $required): string|false
    {
        if (!is_scalar($value)) return false;
        $value = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
        if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/<\/?[A-Za-z][^>]*>/', $value) === 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) return false;
        return $value;
    }

    private function date(mixed $value): string|null|false
    {
        if (!is_scalar($value)) return false;
        $value = trim((string) $value);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d\TH:i') !== $value) return false;
        return $date->format('Y-m-d H:i:s');
    }

    private function relativeLink(string $link): bool
    {
        return str_starts_with($link, '/') && !str_starts_with($link, '//') && !str_contains($link, '\\')
            && preg_match('/[\x00-\x20\x7F]/', $link) !== 1 && parse_url($link, PHP_URL_SCHEME) === null && parse_url($link, PHP_URL_HOST) === null;
    }

    private function explicitBoolean(mixed $value): ?bool
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) return null;
        return in_array($value, [true, 1, '1'], true);
    }

    private function success(array $data = []): array { return array_merge(['success' => true, 'not_found' => false, 'errors' => []], $data); }
    private function failure(array $errors): array { return ['success' => false, 'not_found' => false, 'errors' => $errors]; }
    private function notFound(): array { return ['success' => false, 'not_found' => true, 'errors' => []]; }
    private function log(string $operation, array $context = []): void
    {
        if ($this->logger === null) return;
        try { $this->logger->error('CMS persistence failed.', array_merge(['operation' => $operation], $context)); } catch (Throwable) {}
    }
}
