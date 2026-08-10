<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\PlatformSettingsService;
use OEMS\Tests\Support\FakePlatformSettingsRepository;
use OEMS\Tests\Support\TestCase;

final class PlatformSettingsServiceTest extends TestCase
{
    public function testPublicValuesExposeOnlyTheCatalogAndFallBackFromInvalidStoredValues(): void
    {
        $repository = new FakePlatformSettingsRepository([
            'site_name' => '  Gather Dhaka  ',
            'contact_email' => 'not-an-email',
            'footer_blurb' => ['unsafe'],
            'smtp_password' => 'must-not-leak',
        ]);
        $service = new PlatformSettingsService($repository);

        $values = $service->publicValues();

        $this->assertSame('Gather Dhaka', $values['site_name']);
        $this->assertSame('hello@oems.local', $values['contact_email']);
        $this->assertSame('+880 2 0000 0000', $values['support_phone']);
        $this->assertSame('Better tools for finding a crowd, filling a room, and running an event people remember.', $values['footer_blurb']);
        $this->assertFalse(array_key_exists('smtp_password', $values));
        $this->assertSame(10, count($values));
    }

    public function testUpdateRejectsUnknownKeysArraysAndInvalidBoundedValuesWithoutWriting(): void
    {
        $repository = new FakePlatformSettingsRepository();
        $service = new PlatformSettingsService($repository);

        $result = $service->update([
            'site_name' => ['unsafe'],
            'contact_email' => 'invalid',
            'smtp_password' => 'secret',
            'home_hero_title' => str_repeat('x', 101),
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('site_name', $result['errors']);
        $this->assertArrayHasKey('contact_email', $result['errors']);
        $this->assertArrayHasKey('home_hero_title', $result['errors']);
        $this->assertFalse(array_key_exists('smtp_password', $repository->values));
        $this->assertSame(0, $repository->updateCalls);
    }

    public function testUpdateNormalizesEveryAllowlistedValueAndWritesTransactionally(): void
    {
        $repository = new FakePlatformSettingsRepository();
        $service = new PlatformSettingsService($repository);
        $input = [];
        foreach (PlatformSettingsService::defaults() as $key => $value) {
            $input[$key] = "  {$value}  ";
        }

        $result = $service->update($input);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $repository->updateCalls);
        $this->assertSame(PlatformSettingsService::defaults(), $repository->values);
    }

    public function testPersistenceFailureReturnsStableErrorWithoutPartialMutation(): void
    {
        $repository = new FakePlatformSettingsRepository(['site_name' => 'Before']);
        $repository->failUpdate = true;
        $service = new PlatformSettingsService($repository);

        $result = $service->update(array_merge(PlatformSettingsService::defaults(), ['site_name' => 'After']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('settings', $result['errors']);
        $this->assertSame('Before', $repository->values['site_name']);
    }
}
