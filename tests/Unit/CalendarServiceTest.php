<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\CalendarService;
use OEMS\Tests\Support\TestCase;

final class CalendarServiceTest extends TestCase
{
    public function testPublicRestrictedCalendarUsesCoarseLocationAndValidUtcIcs(): void
    {
        $service = $this->service();
        $calendar = $service->forPublicEvent($this->event('registered'));

        $this->assertTrue(str_contains($calendar, "BEGIN:VCALENDAR\r\n"));
        $this->assertTrue(str_contains($calendar, "DTSTART:20260811T120000Z\r\n"));
        $this->assertTrue(str_contains($calendar, "DTEND:20260811T140000Z\r\n"));
        $this->assertTrue(str_contains($calendar, "LOCATION:Dhaka\\, Bangladesh\r\n"));
        $this->assertFalse(str_contains($calendar, 'Secret Hall'));
        $this->assertFalse(str_contains($calendar, 'Road 42'));
        $this->assertTrue(str_contains($calendar, 'SUMMARY:Build\\, Ship\\; Learn'));
        $this->assertFalse(str_contains(str_replace("\r\n", '', $calendar), "\n"));
    }

    public function testOwnedRegistrationCalendarIncludesExactLocationAndFoldsLongLines(): void
    {
        $service = $this->service();
        $event = array_merge($this->event('registered'), [
            'registration_id' => 72,
            'event_id' => 41,
            'registration_status' => 'confirmed',
            'description' => str_repeat('Accessible product operations ', 8),
        ]);
        $calendar = $service->forOwnedRegistration($event);

        $this->assertTrue(str_contains($calendar, 'UID:registration-72-event-41@events.example.test'));
        $this->assertTrue(str_contains($calendar, 'LOCATION:Secret Hall\\, Road 42\\, Dhaka\\, Bangladesh'));
        $this->assertTrue(str_contains($calendar, "\r\n "));
        foreach (explode("\r\n", trim($calendar)) as $line) {
            $this->assertTrue(strlen($line) <= 75, 'ICS content lines must be folded to 75 octets.');
        }
    }

    public function testGoogleUrlUsesTheSamePrivacyNormalizedPayload(): void
    {
        $url = $this->service()->googleUrl($this->event('registered'), false);

        $this->assertTrue(str_starts_with($url, 'https://calendar.google.com/calendar/render?'));
        $this->assertTrue(str_contains($url, 'location=Dhaka%2C%20Bangladesh'));
        $this->assertFalse(str_contains($url, 'Secret'));
        $this->assertFalse(str_contains($url, '%0D%0A'));
    }

    private function service(): CalendarService
    {
        return new CalendarService(
            'Asia/Dhaka',
            'https://events.example.test',
            new DateTimeImmutable('2026-08-10 10:00:00+06:00'),
        );
    }

    private function event(string $visibility): array
    {
        return [
            'id' => 41,
            'slug' => 'build-ship-learn',
            'title' => 'Build, Ship; Learn',
            'description' => "Practical delivery\nwithout noise",
            'start_date' => '2026-08-11 18:00:00',
            'end_date' => '2026-08-11 20:00:00',
            'updated_at' => '2026-08-10 08:00:00',
            'location_visibility' => $visibility,
            'venue_name' => 'Secret Hall',
            'venue_address_line' => 'Road 42',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
        ];
    }
}
