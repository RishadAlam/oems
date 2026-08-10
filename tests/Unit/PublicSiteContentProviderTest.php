<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\PlatformSettingsService;
use OEMS\App\Services\PublicSiteContentProvider;
use OEMS\Tests\Support\FakeCmsRepository;
use OEMS\Tests\Support\FakePlatformSettingsRepository;
use OEMS\Tests\Support\TestCase;

final class PublicSiteContentProviderTest extends TestCase
{
    public function testProviderSuppliesAllowlistedSettingsAndCurrentHomeBannersToEveryLayout(): void
    {
        $settings = new FakePlatformSettingsRepository(['site_name' => 'Gather Dhaka', 'smtp_password' => 'hidden']);
        $cms = new FakeCmsRepository();
        $provider = new PublicSiteContentProvider(new PlatformSettingsService($settings), $cms, null, static fn (): string => '2026-08-10 12:00:00');

        foreach (['public', 'auth', 'dashboard'] as $layout) {
            $data = $provider->forLayout([], $layout);
            $this->assertSame('Gather Dhaka', $data['siteSettings']['site_name']);
            $this->assertFalse(array_key_exists('smtp_password', $data['siteSettings']));
        }
        $this->assertSame('Dhaka design week', $provider->forLayout([], 'public')['homeBanners'][0]['title']);
        $this->assertSame([], $provider->forLayout([], 'auth')['homeBanners']);
    }

    public function testProviderCatchesDatabaseFailuresAndReturnsSafeDefaults(): void
    {
        $settings = new FakePlatformSettingsRepository();
        $settings->failRead = true;
        $cms = new FakeCmsRepository();
        $cms->failRead = true;
        $provider = new PublicSiteContentProvider(new PlatformSettingsService($settings), $cms);

        $data = $provider->forLayout([], 'public');

        $this->assertSame('OEMS', $data['siteSettings']['site_name']);
        $this->assertSame([], $data['homeBanners']);
    }
}
