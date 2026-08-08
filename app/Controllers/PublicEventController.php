<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class PublicEventController extends Controller
{
    private const DATE_FILTERS = ['upcoming', 'today', 'this_week', 'this_month'];

    private const PRICE_FILTERS = ['', 'free', 'paid'];

    private const SORT_FILTERS = ['soonest', 'latest', 'price_low', 'price_high'];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly EventRepositoryInterface $events,
        private readonly CategoryRepositoryInterface $categories,
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly ?FavoriteRepositoryInterface $favorites = null,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $categories = $this->categories->active();
        $cities = $this->events->publicCities();
        $filters = $this->filters($request, $categories, $cities);
        $matches = $this->events->publicSearch($filters);
        $favoriteStates = $this->favoriteStates($matches);
        $events = array_map(
            fn (array $event): array => $this->presentEvent($event, isset($favoriteStates[(int) ($event['id'] ?? 0)])),
            $matches,
        );
        $description = 'Explore published workshops, talks, and gatherings by date, place, category, and price.';
        $canonicalUrl = $this->absoluteUrl('/events');

        return $this->render('events/index', [
            'pageTitle' => 'Explore events',
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'openGraph' => [
                'type' => 'website',
                'title' => 'Explore events',
                'description' => $description,
                'url' => $canonicalUrl,
            ],
            'events' => $events,
            'categories' => $categories,
            'cities' => $cities,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = mb_strtolower(trim($this->stringValue($request->route('slug'))));

        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return $this->notFound();
        }

        $event = $this->events->findPublishedBySlug($slug);

        if ($event === null
            || ($event['status'] ?? null) !== 'published'
            || !empty($event['deleted_at'])) {
            return $this->notFound();
        }

        $favoriteStates = $this->favoriteStates([$event]);
        $event = $this->presentEvent($event, isset($favoriteStates[(int) ($event['id'] ?? 0)]));
        $gallery = array_map(
            fn (array $image): array => [
                'path' => (string) ($image['image_path'] ?? ''),
                'alt' => trim((string) ($image['alt_text'] ?? '')) ?: 'View of ' . (string) $event['title'],
            ],
            $this->events->gallery((int) $event['id']),
        );
        $canonicalUrl = $this->absoluteUrl('/events/' . rawurlencode($slug));
        $description = $this->description((string) ($event['description'] ?? ''));
        $images = array_values(array_filter([
            $this->absoluteUrl((string) $event['banner_display']),
            ...array_map(fn (array $image): string => $this->absoluteUrl($image['path']), $gallery),
        ], static fn (?string $value): bool => is_string($value) && $value !== ''));
        $openGraph = [
            'type' => 'event',
            'title' => (string) $event['title'],
            'description' => $description,
            'url' => $canonicalUrl,
        ];

        if ($images !== []) {
            $openGraph['image'] = $images[0];
        }

        return $this->render('events/show', [
            'pageTitle' => (string) $event['title'],
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'openGraph' => $openGraph,
            'jsonLd' => $this->jsonLd($event, $canonicalUrl, $images),
            'event' => $event,
            'gallery' => $gallery,
            'registrationAction' => $this->registrationAction($event),
        ]);
    }

    private function registrationAction(array $event): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka')));
        $userId = $this->auth->id();

        if ($userId !== null && $this->auth->hasRole('participant')) {
            $registration = $this->registrations->findForParticipantEvent($userId, (int) $event['id']);
            $status = (string) ($registration['registration_status'] ?? $registration['status'] ?? '');

            if ($registration !== null && in_array($status, ['pending', 'confirmed'], true)) {
                return [
                    'label' => 'View registration',
                    'description' => $status === 'confirmed'
                        ? 'Your place is confirmed.'
                        : 'Your registration is awaiting payment review.',
                    'href' => '/participant/registrations/' . (int) $registration['id'],
                ];
            }
        }

        if ($this->date((string) $event['end_date']) <= $now) {
            return ['label' => 'Event ended', 'description' => 'This event has already ended.', 'href' => null];
        }

        if ($this->date((string) $event['start_date']) <= $now) {
            return ['label' => 'Registration closed', 'description' => 'This event has already started.', 'href' => null];
        }

        if ($this->date((string) $event['registration_deadline']) <= $now) {
            return ['label' => 'Registration closed', 'description' => 'The registration deadline has passed.', 'href' => null];
        }

        if ((int) ($event['available_seats'] ?? 0) <= 0) {
            return ['label' => 'Sold out', 'description' => 'No places are currently available.', 'href' => null];
        }

        $label = (float) ($event['ticket_price'] ?? 0) <= 0 ? 'Register free' : 'Register and pay';

        if ($userId !== null && !$this->auth->hasRole('participant')) {
            return [
                'label' => 'Participant account required',
                'description' => 'Registration is available to participant accounts.',
                'href' => null,
            ];
        }

        $description = match (true) {
            $this->auth->guest() => 'Sign in with a participant account to reserve one place.',
            (float) ($event['ticket_price'] ?? 0) <= 0 => 'Confirm one free place for this event.',
            default => 'Review the total and submit your payment reference.',
        };

        return [
            'label' => $label,
            'description' => $description,
            'href' => $this->auth->guest() ? '/login' : '/participant/events/' . rawurlencode((string) $event['slug']) . '/register',
        ];
    }

    private function filters(Request $request, array $categories, array $cities): array
    {
        $categoryOptions = [];
        foreach ($categories as $category) {
            $slug = mb_strtolower(trim((string) ($category['slug'] ?? '')));
            if ($slug !== '') {
                $categoryOptions[$slug] = $slug;
            }
        }

        $cityOptions = [];
        foreach ($cities as $city) {
            $city = trim((string) $city);
            if ($city !== '') {
                $cityOptions[mb_strtolower($city)] = $city;
            }
        }

        $category = mb_strtolower(trim($this->stringValue($request->query('category'))));
        $city = trim($this->stringValue($request->query('city')));
        $date = mb_strtolower(trim($this->stringValue($request->query('date', 'upcoming'))));
        $price = mb_strtolower(trim($this->stringValue($request->query('price'))));
        $sort = mb_strtolower(trim($this->stringValue($request->query('sort', 'soonest'))));

        return [
            'search' => trim($this->stringValue($request->query('search'))),
            'category' => $categoryOptions[$category] ?? '',
            'city' => $cityOptions[mb_strtolower($city)] ?? '',
            'date' => in_array($date, self::DATE_FILTERS, true) ? $date : 'upcoming',
            'price' => in_array($price, self::PRICE_FILTERS, true) ? $price : '',
            'sort' => in_array($sort, self::SORT_FILTERS, true) ? $sort : 'soonest',
        ];
    }

    private function presentEvent(array $event, bool $isFavorited = false): array
    {
        $start = $this->date((string) $event['start_date']);
        $end = $this->date((string) $event['end_date']);
        $deadline = $this->date((string) $event['registration_deadline']);
        $address = array_values(array_filter([
            trim((string) ($event['venue_name'] ?? '')),
            trim((string) ($event['venue_city'] ?? '')),
            trim((string) ($event['venue_country'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));
        $price = (float) ($event['ticket_price'] ?? 0);

        return array_merge($event, [
            'start_iso' => $start->format(DATE_ATOM),
            'start_date_display' => $start->format('M j, Y'),
            'start_time_display' => $start->format('g:i A'),
            'end_iso' => $end->format(DATE_ATOM),
            'end_display' => $end->format('M j, Y, g:i A'),
            'deadline_iso' => $deadline->format(DATE_ATOM),
            'deadline_display' => $deadline->format('M j, Y, g:i A'),
            'address' => $address === [] ? 'Venue to be announced' : implode(', ', $address),
            'price_display' => $price <= 0
                ? 'Free'
                : $this->currency($price, (string) ($event['currency'] ?? 'BDT')),
            'banner_display' => (string) (($event['banner'] ?? '') ?: '/assets/images/event-creative.webp'),
            'banner_alt' => 'Banner for ' . (string) $event['title'],
            'favorite' => [
                'is_participant' => $this->auth->hasRole('participant'),
                'is_guest' => $this->auth->guest(),
                'is_saved' => $isFavorited,
            ],
        ]);
    }

    /** @return array<int, bool> */
    private function favoriteStates(array $events): array
    {
        if ($this->favorites === null || !$this->auth->hasRole('participant')) {
            return [];
        }

        return $this->favorites->statesForParticipant(
            (int) $this->auth->id(),
            array_map(static fn (array $event): int => (int) ($event['id'] ?? 0), $events),
        );
    }

    private function jsonLd(array $event, string $url, array $images): array
    {
        $json = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => (string) $event['title'],
            'description' => $this->description((string) ($event['description'] ?? '')),
            'startDate' => (string) $event['start_iso'],
            'endDate' => (string) $event['end_iso'],
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url' => $url,
            'location' => [
                '@type' => 'Place',
                'name' => (string) (($event['venue_name'] ?? '') ?: 'Venue to be announced'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => (string) ($event['venue_city'] ?? ''),
                    'addressCountry' => (string) ($event['venue_country'] ?? ''),
                ],
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => (string) ($event['organization_name'] ?? 'OEMS organizer'),
            ],
        ];

        if ($images !== []) {
            $json['image'] = $images;
        }

        if (trim((string) ($event['speaker'] ?? '')) !== '') {
            $json['performer'] = [
                '@type' => 'Person',
                'name' => trim((string) $event['speaker']),
            ];
        }

        return $json;
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', [
            'pageTitle' => 'Event not found',
            'metaDescription' => 'This event is not available on OEMS.',
        ]);

        return Response::html($response->body(), 404);
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $value,
            new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka')),
        );
    }

    private function currency(float $amount, string $currency): string
    {
        $formatted = number_format($amount, floor($amount) === $amount ? 0 : 2);

        return match (strtoupper($currency)) {
            'BDT' => '৳' . $formatted,
            'USD' => '$' . $formatted,
            default => $formatted . ' ' . strtoupper($currency),
        };
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/')
            . '/' . ltrim($path, '/');
    }

    private function description(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        return mb_strlen($value) > 155 ? mb_substr($value, 0, 152) . '…' : $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
