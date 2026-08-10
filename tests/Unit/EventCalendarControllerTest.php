<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Controllers\EventCalendarController;
use OEMS\App\Services\CalendarService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class EventCalendarControllerTest extends TestCase
{
    public function testPublicCalendarIsSafeAndOwnedCalendarIsPrivateAndExact(): void
    {
        [$controller, $events, $registrations] = $this->controller(true);
        $events->events[41] = $this->event();
        $registrations->registrations[72] = array_merge($this->event(), [
            'id' => 72,
            'registration_id' => 72,
            'event_id' => 41,
            'user_id' => 7,
            'status' => 'confirmed',
            'registration_status' => 'confirmed',
            'event_status' => 'published',
        ]);

        $public = $controller->publicIcs($this->request('/events/restricted-event/calendar.ics', ['slug' => 'restricted-event']));
        $owned = $controller->registrationIcs($this->request('/participant/registrations/72/calendar.ics', ['id' => '72']));

        $this->assertSame(200, $public->status());
        $this->assertSame('text/calendar; charset=UTF-8', $public->header('Content-Type'));
        $this->assertSame('public, max-age=300', $public->header('Cache-Control'));
        $this->assertFalse(str_contains($public->body(), 'Secret Hall'));
        $this->assertSame('private, no-store, max-age=0', $owned->header('Cache-Control'));
        $this->assertTrue(str_contains($owned->body(), 'Secret Hall'));
        $this->assertSame('attachment; filename="restricted-event.ics"', $owned->header('Content-Disposition'));
    }

    public function testMissingForeignMalformedAndUnconfirmedRegistrationsAreNotFound(): void
    {
        [$controller, $events, $registrations] = $this->controller(true);
        $events->events[41] = $this->event();
        $registrations->registrations[72] = array_merge($this->event(), [
            'id' => 72, 'event_id' => 41, 'user_id' => 99, 'status' => 'confirmed', 'event_status' => 'published',
        ]);
        $registrations->registrations[73] = array_merge($this->event(), [
            'id' => 73, 'event_id' => 41, 'user_id' => 7, 'status' => 'pending', 'event_status' => 'published',
        ]);

        $this->assertSame(404, $controller->registrationIcs($this->request('/participant/registrations/no/calendar.ics', ['id' => 'no']))->status());
        $this->assertSame(404, $controller->registrationIcs($this->request('/participant/registrations/72/calendar.ics', ['id' => '72']))->status());
        $this->assertSame(404, $controller->registrationIcs($this->request('/participant/registrations/73/calendar.ics', ['id' => '73']))->status());
    }

    public function testCalendarRoutesAreGetOnlyAndParticipantOwnedRoutesRequireRole(): void
    {
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';
        $eventView = file_get_contents(base_path('app/Views/events/show.php')) ?: '';
        $registrationView = file_get_contents(base_path('app/Views/participant/registrations/show.php')) ?: '';
        $ticketView = file_get_contents(base_path('app/Views/participant/tickets/show.php')) ?: '';

        $this->assertTrue(str_contains($routes, "'/events/{slug}/calendar.ics'"));
        $this->assertTrue(str_contains($routes, "'/participant/registrations/{id}/calendar.ics'"));
        $this->assertTrue(str_contains($routes, "['role:participant']"));
        $this->assertTrue(str_contains($eventView, 'calendar.ics'));
        $this->assertTrue(str_contains($registrationView, 'calendar.ics'));
        $this->assertTrue(str_contains($ticketView, 'calendar.ics'));
    }

    private function controller(bool $authenticated): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7, 'name' => 'Participant', 'email' => 'participant@example.test',
            'password' => 'hash', 'role_id' => 3, 'role_slug' => 'participant', 'status' => 'active',
            'email_verified_at' => '2026-01-01 00:00:00',
        ];
        if ($authenticated) {
            $this->authenticateSession($session, $users, 7);
        }
        $events = new FakeEventRepository();
        $registrations = new FakeRegistrationRepository();
        $controller = new EventCalendarController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
            $events,
            $registrations,
            new CalendarService('Asia/Dhaka', 'https://events.example.test', new DateTimeImmutable('2026-08-10 10:00:00+06:00')),
        );

        return [$controller, $events, $registrations];
    }

    private function request(string $uri, array $parameters): Request
    {
        return Request::create('GET', $uri)->withRouteParameters($parameters);
    }

    private function event(): array
    {
        return [
            'id' => 41, 'slug' => 'restricted-event', 'event_slug' => 'restricted-event', 'title' => 'Restricted event',
            'description' => 'Participant-only venue details.', 'status' => 'published',
            'start_date' => '2026-08-11 18:00:00', 'end_date' => '2026-08-11 20:00:00',
            'updated_at' => '2026-08-10 08:00:00', 'location_visibility' => 'registered',
            'venue_name' => 'Secret Hall', 'venue_address_line' => 'Road 42',
            'venue_city' => 'Dhaka', 'venue_country' => 'Bangladesh', 'deleted_at' => null,
        ];
    }
}
