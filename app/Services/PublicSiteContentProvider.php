<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Closure;
use DateTimeImmutable;
use OEMS\App\Contracts\CmsRepositoryInterface;
use Throwable;

final class PublicSiteContentProvider
{
    private readonly Closure $clock;

    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly CmsRepositoryInterface $cms,
        private readonly ?DashboardLayoutDataProvider $dashboard = null,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): string => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    public function forLayout(array $data, string $layout): array
    {
        $provided = $this->dashboard?->forLayout($data, $layout) ?? [];
        $provided['siteSettings'] = $this->settings->publicValues();
        $provided['homeBanners'] = [];

        if ($layout === 'public') {
            try {
                $provided['homeBanners'] = $this->cms->activeHomeBanners(($this->clock)());
            } catch (Throwable) {
                $provided['homeBanners'] = [];
            }
        }

        return $provided;
    }
}
