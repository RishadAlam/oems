<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Support\CmsBannerPresenter;
use OEMS\Tests\Support\TestCase;

final class CmsBannerPresenterTest extends TestCase
{
    private CmsBannerPresenter $presenter;
    private DateTimeImmutable $now;
    private DateTimeZone $timezone;

    protected function setUp(): void
    {
        $this->presenter = new CmsBannerPresenter();
        $this->timezone = new DateTimeZone('Asia/Dhaka');
        $this->now = new DateTimeImmutable('2026-08-15 12:00:00', $this->timezone);
    }

    public function testItDerivesEveryDeliveryStateFromThePublicWindow(): void
    {
        $fixtures = [
            'Live' => [
                'banner' => $this->banner(startsAt: '2026-08-15 11:00:00', endsAt: '2026-08-15 13:00:00'),
                'tone' => 'success',
            ],
            'Scheduled' => [
                'banner' => $this->banner(startsAt: '2026-08-15 13:00:00'),
                'tone' => 'warning',
            ],
            'Ended' => [
                'banner' => $this->banner(endsAt: '2026-08-15 11:59:59'),
                'tone' => 'neutral',
            ],
            'Disabled' => [
                'banner' => $this->banner(active: 0, startsAt: 'not-a-date'),
                'tone' => 'neutral',
            ],
        ];

        foreach ($fixtures as $label => $fixture) {
            $presented = $this->presenter->present($fixture['banner'], $this->now, $this->timezone);

            $this->assertSame($label, $presented['delivery']['label']);
            $this->assertSame($fixture['tone'], $presented['delivery']['tone']);
        }
    }

    public function testExactStartAndEndBoundariesAreLive(): void
    {
        $atStart = $this->presenter->present(
            $this->banner(startsAt: '2026-08-15 12:00:00', endsAt: '2026-08-15 13:00:00'),
            $this->now,
            $this->timezone,
        );
        $atEnd = $this->presenter->present(
            $this->banner(startsAt: '2026-08-15 11:00:00', endsAt: '2026-08-15 12:00:00'),
            $this->now,
            $this->timezone,
        );

        $this->assertSame('Live', $atStart['delivery']['label']);
        $this->assertSame('Live', $atEnd['delivery']['label']);
    }

    public function testFractionalNowUsesThePublicQueriesSecondPrecision(): void
    {
        $fractionalNow = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.u',
            '2026-08-15 12:00:00.500000',
            $this->timezone,
        );
        $this->assertNotSame(false, $fractionalNow);

        $presented = $this->presenter->present(
            $this->banner(startsAt: '2026-08-15 11:00:00', endsAt: '2026-08-15 12:00:00'),
            $fractionalNow,
            $this->timezone,
        );

        $this->assertSame('Live', $presented['delivery']['label']);
    }

    public function testItRequiresExactPublicEligibilityValues(): void
    {
        foreach ([1, '1', true] as $enabled) {
            $banner = $this->banner(active: $enabled);
            $banner['location'] = 'home';
            $this->assertSame('Live', $this->presenter->present($banner, $this->now, $this->timezone)['delivery']['label']);
        }

        foreach ([0, '0', false] as $disabled) {
            $banner = $this->banner(active: $disabled);
            $banner['location'] = 'home';
            $this->assertSame('Disabled', $this->presenter->present($banner, $this->now, $this->timezone)['delivery']['label']);
        }

        foreach ([2, 'active', null, ['1']] as $corrupt) {
            $banner = $this->banner(active: $corrupt);
            $banner['location'] = 'home';
            $presented = $this->presenter->present($banner, $this->now, $this->timezone);
            $this->assertSame('Unknown', $presented['delivery']['label']);
            $this->assertSame('neutral', $presented['delivery']['tone']);
        }

        foreach (['home', 'HOME', 'HOME   '] as $homeLocation) {
            $home = $this->banner();
            $home['location'] = $homeLocation;
            $this->assertSame('Live', $this->presenter->present($home, $this->now, $this->timezone)['delivery']['label']);
        }

        foreach (['footer', ' home', ['home']] as $otherLocation) {
            $nonHome = $this->banner();
            $nonHome['location'] = $otherLocation;
            $this->assertSame('Unknown', $this->presenter->present($nonHome, $this->now, $this->timezone)['delivery']['label']);
        }

        $this->assertSame(
            'Live',
            $this->presenter->present($this->banner(), $this->now, $this->timezone)['delivery']['label'],
            'Location-less hand-written fixtures remain home-banner fixtures for backward compatibility.',
        );
    }

    public function testNullBoundsProduceReadableOpenEndedSchedule(): void
    {
        $presented = $this->presenter->present($this->banner(), $this->now, $this->timezone);

        $this->assertTrue($presented['schedule']['valid']);
        $this->assertSame('Immediately', $presented['schedule']['starts']['display']);
        $this->assertNull($presented['schedule']['starts']['iso']);
        $this->assertSame('No end date', $presented['schedule']['ends']['display']);
        $this->assertNull($presented['schedule']['ends']['iso']);
    }

    public function testMalformedNonScalarAndReversedActiveSchedulesAreUnknown(): void
    {
        $fixtures = [
            $this->banner(startsAt: '2026-02-30 12:00:00'),
            $this->banner(startsAt: ['2026-08-15 12:00:00']),
            $this->banner(startsAt: '2026-08-16 12:00:00', endsAt: '2026-08-16 11:00:00'),
            $this->banner(startsAt: '2026-08-16 12:00:00', endsAt: '2026-08-16 12:00:00'),
        ];

        foreach ($fixtures as $fixture) {
            $presented = $this->presenter->present($fixture, $this->now, $this->timezone);

            $this->assertFalse($presented['schedule']['valid']);
            $this->assertSame('Schedule unavailable', $presented['schedule']['fallback']);
            $this->assertSame('Unknown', $presented['delivery']['label']);
            $this->assertSame('neutral', $presented['delivery']['tone']);
        }
    }

    public function testDisabledMalformedScheduleKeepsDisabledPrecedenceButDoesNotInventDates(): void
    {
        $presented = $this->presenter->present(
            $this->banner(active: '0', startsAt: 'not-a-date'),
            $this->now,
            $this->timezone,
        );

        $this->assertSame('Disabled', $presented['delivery']['label']);
        $this->assertSame('neutral', $presented['delivery']['tone']);
        $this->assertFalse($presented['schedule']['valid']);
        $this->assertSame('Schedule unavailable', $presented['schedule']['fallback']);
        $this->assertNull($presented['schedule']['starts']);
        $this->assertNull($presented['schedule']['ends']);
    }

    public function testValidScheduleIncludesConfiguredOffsetAndReadableDates(): void
    {
        $presented = $this->presenter->present(
            $this->banner(startsAt: '2026-08-16 09:05:00', endsAt: '2026-08-16 17:30:00'),
            $this->now,
            $this->timezone,
        );

        $this->assertSame('2026-08-16T09:05:00+06:00', $presented['schedule']['starts']['iso']);
        $this->assertSame('Aug 16, 2026, 9:05 AM', $presented['schedule']['starts']['display']);
        $this->assertSame('2026-08-16T17:30:00+06:00', $presented['schedule']['ends']['iso']);
        $this->assertSame('Aug 16, 2026, 5:30 PM', $presented['schedule']['ends']['display']);
    }

    private function banner(mixed $active = 1, mixed $startsAt = null, mixed $endsAt = null): array
    {
        return [
            'id' => 7,
            'title' => 'Banner',
            'is_active' => $active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }
}
