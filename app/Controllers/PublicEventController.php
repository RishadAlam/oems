<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Contracts\ReviewRepositoryInterface;
use OEMS\App\Services\LocationService;
use OEMS\App\Support\Money;
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

    private const RADII = [5, 10, 25, 50, 100];

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
        private readonly ?ReviewRepositoryInterface $reviews = null,
        private readonly ?LocationService $locations = null,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $categories = $this->categories->active();
        $cities = $this->events->publicCities();
        $location = $this->locations?->fromSession($this->session->get('event_location'));

        if ($this->locations !== null && $location === null && $this->session->has('event_location')) {
            $this->session->forget('event_location');
        }

        $filters = $this->filters($request, $categories, $cities, $location !== null);

        if ($location !== null) {
            $location['radius'] = $this->locations->radius($request->query('radius', $location['radius']));
            $filters = array_merge($filters, $location, $this->locations->bounds($location));
        }

        $matches = $this->events->publicSearch($filters);
        $favoriteStates = $this->favoriteStates($matches);
        $events = array_map(
            fn (array $event): array => $this->presentEvent(
                $event,
                isset($favoriteStates[(int) ($event['id'] ?? 0)]),
                ($event['location_visibility'] ?? 'public') === 'public',
            ),
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
            'location' => $location,
            'radii' => self::RADII,
            'mapConfig' => $this->mapConfig(),
            'mapPayload' => [
                'config' => $this->mapConfig(),
                'markers' => $this->publicMarkers($matches),
            ],
            'leafletEnabled' => true,
            'distanceSortAvailable' => $location !== null,
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
            || !in_array(($event['status'] ?? null), ['published', 'completed'], true)
            || !empty($event['deleted_at'])) {
            return $this->notFound();
        }

        $exactLocationVisible = $this->canViewExactLocation($event);
        $favoriteStates = $this->favoriteStates([$event]);
        $event = $this->presentEvent(
            $event,
            isset($favoriteStates[(int) ($event['id'] ?? 0)]),
            $exactLocationVisible,
        );
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

        $publishedReviews = $this->reviews?->publicForEvent((int) $event['id']) ?? [];
        $reviewSummary = $this->reviews?->summaryForEvent((int) $event['id']) ?? ['count' => 0, 'average' => null];

        return $this->render('events/show', [
            'pageTitle' => (string) $event['title'],
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'openGraph' => $openGraph,
            'jsonLd' => $exactLocationVisible
                ? $this->jsonLd($event, $canonicalUrl, $images, $reviewSummary)
                : null,
            'event' => $event,
            'mapPayload' => $this->detailMapPayload($event),
            'leafletEnabled' => $exactLocationVisible && $this->validCoordinates(
                $event['venue_latitude'] ?? null,
                $event['venue_longitude'] ?? null,
            ),
            'gallery' => $gallery,
            'registrationAction' => $this->registrationAction($event),
            'reviews' => $publishedReviews,
            'reviewSummary' => $reviewSummary,
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

        if (($event['status'] ?? null) === 'completed') {
            return ['label' => 'Event ended', 'description' => 'This event has already ended.', 'href' => null];
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

        $isFree = Money::isFree($event['ticket_price'] ?? null);
        $label = $isFree ? 'Register free' : 'Register and pay';

        if ($userId !== null && !$this->auth->hasRole('participant')) {
            return [
                'label' => 'Participant account required',
                'description' => 'Registration is available to participant accounts.',
                'href' => null,
            ];
        }

        $description = match (true) {
            $this->auth->guest() => 'Sign in with a participant account to reserve one place.',
            $isFree => 'Confirm one free place for this event.',
            default => 'Review the total and submit your payment reference.',
        };

        return [
            'label' => $label,
            'description' => $description,
            'href' => $this->auth->guest()
                ? '/login?return_to=' . rawurlencode('/events/' . (string) $event['slug'])
                : '/participant/events/' . rawurlencode((string) $event['slug']) . '/register',
        ];
    }

    private function filters(Request $request, array $categories, array $cities, bool $distanceSortAvailable = false): array
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

        $sorts = $distanceSortAvailable ? [...self::SORT_FILTERS, 'distance'] : self::SORT_FILTERS;

        return [
            'search' => trim($this->stringValue($request->query('search'))),
            'category' => $categoryOptions[$category] ?? '',
            'city' => $cityOptions[mb_strtolower($city)] ?? '',
            'date' => in_array($date, self::DATE_FILTERS, true) ? $date : 'upcoming',
            'price' => in_array($price, self::PRICE_FILTERS, true) ? $price : '',
            'sort' => in_array($sort, $sorts, true) ? $sort : 'soonest',
        ];
    }

    private function presentEvent(
        array $event,
        bool $isFavorited = false,
        ?bool $exactLocationVisible = null,
    ): array
    {
        $start = $this->date((string) $event['start_date']);
        $end = $this->date((string) $event['end_date']);
        $deadline = $this->date((string) $event['registration_deadline']);
        $exactLocationVisible ??= ($event['location_visibility'] ?? 'public') === 'public';
        $addressParts = $exactLocationVisible ? [
            trim((string) ($event['venue_name'] ?? '')),
            trim((string) ($event['venue_address_line'] ?? '')),
            trim((string) ($event['venue_city'] ?? '')),
            trim((string) ($event['venue_postal_code'] ?? '')),
            trim((string) ($event['venue_country'] ?? '')),
        ] : [
            trim((string) ($event['venue_city'] ?? '')),
            trim((string) ($event['venue_country'] ?? '')),
        ];
        $address = array_values(array_filter($addressParts, static fn (string $value): bool => $value !== ''));
        $isFree = Money::isFree($event['ticket_price'] ?? null);
        $distanceExact = ($event['location_visibility'] ?? 'public') === 'public';
        $directionsUrl = $exactLocationVisible
            ? ($this->locations ?? new LocationService())->directionsUrl([
                'map_url' => $event['venue_map_url'] ?? $event['map_url'] ?? null,
                'latitude' => $event['venue_latitude'] ?? null,
                'longitude' => $event['venue_longitude'] ?? null,
            ])
            : null;

        $presented = array_merge($event, [
            'start_iso' => $start->format(DATE_ATOM),
            'start_date_display' => $start->format('M j, Y'),
            'start_time_display' => $start->format('g:i A'),
            'end_iso' => $end->format(DATE_ATOM),
            'end_display' => $end->format('M j, Y, g:i A'),
            'deadline_iso' => $deadline->format(DATE_ATOM),
            'deadline_display' => $deadline->format('M j, Y, g:i A'),
            'address' => $address === [] ? 'Venue to be announced' : implode(', ', $address),
            'price_display' => $isFree
                ? 'Free'
                : Money::format($event['ticket_price'] ?? null, (string) ($event['currency'] ?? 'BDT')),
            'is_free' => $isFree,
            'distance_label' => $this->locations?->distanceLabel($event['distance_km'] ?? null, $distanceExact),
            'exact_location_visible' => $exactLocationVisible,
            'directions_url' => $directionsUrl,
            'banner_display' => (string) (($event['banner'] ?? '') ?: '/assets/images/event-creative.webp'),
            'banner_alt' => 'Banner for ' . (string) $event['title'],
            'favorite' => [
                'is_participant' => $this->auth->hasRole('participant'),
                'is_guest' => $this->auth->guest(),
                'is_saved' => $isFavorited,
            ],
        ]);

        if (!$exactLocationVisible) {
            foreach ([
                'venue_name',
                'venue_address_line',
                'venue_postal_code',
                'venue_latitude',
                'venue_longitude',
                'venue_map_url',
                'map_url',
                'arrival_notes',
                'latitude',
                'longitude',
            ] as $field) {
                unset($presented[$field]);
            }
        }

        return $presented;
    }

    private function canViewExactLocation(array $event): bool
    {
        if (($event['location_visibility'] ?? 'public') === 'public') {
            return true;
        }

        $userId = $this->auth->id();
        if ($userId === null) {
            return false;
        }

        if ($this->auth->hasRole('super-admin')) {
            return true;
        }

        if ($this->auth->hasRole('organizer')
            && $userId === (int) ($event['organizer_user_id'] ?? 0)) {
            return true;
        }

        $registration = $this->auth->hasRole('participant')
            ? $this->registrations->findForParticipantEvent($userId, (int) $event['id'])
            : null;
        $status = $registration['registration_status'] ?? $registration['status'] ?? null;

        return $status === 'confirmed';
    }

    private function mapConfig(): array
    {
        return [
            'tile_url' => (string) $this->config->get('map.tile_url', ''),
            'tile_attribution' => (string) $this->config->get('map.tile_attribution', ''),
            'default_lat' => (float) $this->config->get('map.default_lat', 23.8103),
            'default_lng' => (float) $this->config->get('map.default_lng', 90.4125),
            'default_zoom' => (int) $this->config->get('map.default_zoom', 11),
        ];
    }

    private function publicMarkers(array $events): array
    {
        $markers = [];

        foreach ($events as $event) {
            if (($event['status'] ?? null) !== 'published'
                || !empty($event['deleted_at'])
                || ($event['location_visibility'] ?? 'public') !== 'public'
                || !$this->validCoordinates($event['venue_latitude'] ?? null, $event['venue_longitude'] ?? null)) {
                continue;
            }

            $markers[] = [
                'id' => (int) ($event['id'] ?? 0),
                'title' => (string) ($event['title'] ?? 'Event'),
                'href' => '/events/' . rawurlencode((string) ($event['slug'] ?? '')),
                'latitude' => (string) $event['venue_latitude'],
                'longitude' => (string) $event['venue_longitude'],
            ];
        }

        return $markers;
    }

    private function detailMapPayload(array $event): ?array
    {
        if (empty($event['exact_location_visible'])
            || !$this->validCoordinates($event['venue_latitude'] ?? null, $event['venue_longitude'] ?? null)) {
            return null;
        }

        return [
            'config' => $this->mapConfig(),
            'markers' => [[
                'id' => (int) ($event['id'] ?? 0),
                'title' => (string) ($event['title'] ?? 'Event'),
                'href' => '/events/' . rawurlencode((string) ($event['slug'] ?? '')),
                'latitude' => (string) $event['venue_latitude'],
                'longitude' => (string) $event['venue_longitude'],
            ]],
        ];
    }

    private function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        return is_finite($latitude)
            && is_finite($longitude)
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
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

    private function jsonLd(array $event, string $url, array $images, array $reviewSummary = []): array
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
                    'streetAddress' => (string) ($event['venue_address_line'] ?? ''),
                    'addressLocality' => (string) ($event['venue_city'] ?? ''),
                    'postalCode' => (string) ($event['venue_postal_code'] ?? ''),
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

        if (!empty($event['exact_location_visible'])
            && $this->validCoordinates($event['venue_latitude'] ?? null, $event['venue_longitude'] ?? null)) {
            $json['location']['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $event['venue_latitude'],
                'longitude' => (float) $event['venue_longitude'],
            ];
        }

        if (trim((string) ($event['speaker'] ?? '')) !== '') {
            $json['performer'] = [
                '@type' => 'Person',
                'name' => trim((string) $event['speaker']),
            ];
        }

        if ((int) ($reviewSummary['count'] ?? 0) > 0 && ($reviewSummary['average'] ?? null) !== null) {
            $json['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $reviewSummary['average'], 2),
                'ratingCount' => (int) $reviewSummary['count'],
                'bestRating' => 5,
                'worstRating' => 1,
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
