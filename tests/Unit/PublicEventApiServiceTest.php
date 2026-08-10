<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\PublicEventApiService;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\TestCase;

final class PublicEventApiServiceTest extends TestCase
{
    public function testListRejectsUnknownNestedAndOutOfRangeInputs(): void
    {
        $service = $this->service();

        $this->assertFalse($service->index(['unknown' => 'value'])['success']);
        $this->assertFalse($service->index(['search' => ['nested']])['success']);
        $this->assertFalse($service->index(['page' => '0'])['success']);
        $this->assertFalse($service->index(['limit' => '101'])['success']);
        $this->assertFalse($service->index(['date_from' => '2026-02-30'])['success']);
        $this->assertFalse($service->index(['date_from' => '2026-10-01', 'date_to' => '2026-09-01'])['success']);
        $this->assertFalse($service->index(['date_from' => '1900-01-01', 'date_to' => '1900-02-01'])['success']);
    }

    public function testListUsesStableFieldsExactMoneyPaginationAndRestrictedLocationPrivacy(): void
    {
        $result = $this->service()->index([
            'date_from' => '2026-09-01',
            'date_to' => '2026-10-01',
            'page' => '1',
            'limit' => '20',
            'sort' => 'soonest',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['pagination']['total']);
        $this->assertSame('1250.50', $result['events'][0]['price']['amount']);
        $this->assertSame('2026-09-12T18:00:00+06:00', $result['events'][0]['schedule']['starts_at']);
        $this->assertFalse(array_key_exists('id', $result['events'][0]));
        $this->assertSame('registered', $result['events'][1]['location']['visibility']);
        $this->assertSame('Dhaka', $result['events'][1]['location']['city']);
        $this->assertFalse(array_key_exists('venue', $result['events'][1]['location']));
        $this->assertFalse(array_key_exists('latitude', $result['events'][1]['location']));
        $this->assertFalse(array_key_exists('arrival_notes', $result['events'][1]));
    }

    public function testCalendarBuildsAccessibleMonthGridFromTheSamePublicEvents(): void
    {
        $result = $this->service()->calendar('2026-09');

        $this->assertTrue($result['success']);
        $this->assertSame('September 2026', $result['label']);
        $this->assertSame('2026-08', $result['previous_month']);
        $this->assertSame('2026-10', $result['next_month']);
        $this->assertSame(42, count($result['days']));
        $this->assertSame(2, count($result['events']));
        $eventDays = array_values(array_filter($result['days'], static fn (array $day): bool => $day['events'] !== []));
        $this->assertSame(['2026-09-12', '2026-09-13'], array_column($eventDays, 'date'));
    }

    public function testDetailRejectsMalformedOrHiddenSlugsAndScrubsRestrictedLocation(): void
    {
        $service = $this->service();

        $this->assertFalse($service->detail('../private')['success']);
        $this->assertFalse($service->detail('missing-event')['success']);
        $detail = $service->detail('restricted-gathering');
        $this->assertTrue($detail['success']);
        $this->assertSame('Dhaka', $detail['event']['location']['city']);
        $this->assertFalse(array_key_exists('venue', $detail['event']['location']));
    }

    private function service(): PublicEventApiService
    {
        $events = new FakeEventRepository();
        $events->events = [
            1 => $this->event(1, 'public-summit', 'Public summit', 'public', '2026-09-12 18:00:00'),
            2 => $this->event(2, 'restricted-gathering', 'Restricted gathering', 'registered', '2026-09-13 18:00:00'),
        ];

        return new PublicEventApiService(
            $events,
            'Asia/Dhaka',
            'https://events.example.test',
            new DateTimeImmutable('2026-08-10 09:00:00+06:00'),
        );
    }

    private function event(int $id, string $slug, string $title, string $visibility, string $start): array
    {
        return [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'description' => 'A clear public event description.',
            'banner' => '/uploads/events/example.webp',
            'speaker' => 'Ayesha Rahman',
            'start_date' => $start,
            'end_date' => str_replace('18:00:00', '20:00:00', $start),
            'registration_deadline' => str_replace('18:00:00', '12:00:00', $start),
            'capacity' => 50,
            'available_seats' => 5,
            'ticket_price' => '1250.50',
            'currency' => 'BDT',
            'tags' => ['community', 'learning'],
            'status' => 'published',
            'waitlist_enabled' => 1,
            'category_name' => 'Community',
            'category_slug' => 'community',
            'organization_name' => 'OEMS Community',
            'location_visibility' => $visibility,
            'arrival_notes' => 'Use the north entrance.',
            'venue_name' => 'Private Hall',
            'venue_address_line' => '12 Private Road',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
            'venue_postal_code' => '1205',
            'venue_latitude' => '23.8100000',
            'venue_longitude' => '90.4130000',
            'venue_map_url' => 'https://maps.example.test/event',
            'updated_at' => '2026-08-10 08:00:00',
            'deleted_at' => null,
        ];
    }
}
