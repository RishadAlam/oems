<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\VenueRepositoryInterface;
use OEMS\App\Services\VenueService;
use OEMS\App\Services\LocationService;
use OEMS\App\Services\VenueGeocodingService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerVenueController extends Controller
{
    private const FIELDS = [
        'name',
        'address_line',
        'city',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'map_url',
        'capacity',
    ];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly VenueRepositoryInterface $venues,
        private readonly VenueService $venueService,
        private readonly VenueGeocodingService $geocoding,
        private readonly LocationService $locations,
        private readonly RateLimiter $rateLimiter,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        return $this->render('organizer/venues/index', [
            'pageTitle' => 'Venues',
            'venues' => $this->venues->forOrganizerUser($userId),
        ], 'dashboard');
    }

    public function create(Request $request): Response
    {
        return $this->render('organizer/venues/form', [
            'pageTitle' => 'Create venue',
            'venue' => null,
            'leafletEnabled' => true,
            'venueMapEnabled' => true,
            'mapConfig' => $this->mapConfig(),
        ], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        $data = $this->safeInput($request);
        $result = $this->venueService->create($userId, $data);

        if (!$result['success']) {
            if (isset($result['errors']['venue'])) {
                return $this->redirectWith(
                    '/organizer/venues/create',
                    'error',
                    $this->firstError($result['errors']),
                );
            }

            return $this->redirectWithErrors('/organizer/venues/create', $result['errors'], $data);
        }

        return $this->redirectWith('/organizer/venues', 'success', 'Venue created.');
    }

    public function edit(Request $request): Response
    {
        $venue = $this->ownedVenue($request);

        if ($venue === null) {
            return $this->notFound();
        }

        return $this->render('organizer/venues/form', [
            'pageTitle' => 'Edit venue',
            'venue' => $venue,
            'leafletEnabled' => true,
            'venueMapEnabled' => true,
            'mapConfig' => $this->mapConfig(),
        ], 'dashboard');
    }

    public function update(Request $request): Response
    {
        $venueId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($venueId === null || $userId === null) {
            return $this->notFound();
        }

        $data = $this->safeInput($request);
        $result = $this->venueService->update($userId, $venueId, $data);

        if ($result['not_found']) {
            return $this->notFound();
        }

        if (!$result['success'] && !isset($result['errors']['venue'])) {
            return $this->redirectWithErrors(
                '/organizer/venues/' . $venueId . '/edit',
                $result['errors'],
                $data,
            );
        }

        if (!$result['success']) {
            return $this->redirectWith(
                '/organizer/venues/' . $venueId . '/edit',
                'error',
                $this->firstError($result['errors']),
            );
        }

        return $this->redirectWith('/organizer/venues', 'success', 'Venue updated.');
    }

    public function delete(Request $request): Response
    {
        $venueId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($venueId === null || $userId === null) {
            return $this->notFound();
        }

        $result = $this->venueService->delete($userId, $venueId);

        if ($result['not_found']) {
            return $this->notFound();
        }

        if (!$result['success']) {
            return $this->redirectWith(
                '/organizer/venues',
                'error',
                $this->firstError($result['errors']),
            );
        }

        return $this->redirectWith('/organizer/venues', 'success', 'Venue deleted.');
    }

    public function geocode(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        $query = $request->input('query');
        $query = is_scalar($query) ? trim((string) $query) : '';
        $length = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);

        if ($length < 3 || $length > 160) {
            return Response::json([
                'errors' => ['location' => ['Enter an address between 3 and 160 characters.']],
            ], 422, ['Cache-Control' => 'private, no-store']);
        }

        $rateKey = 'organizer-venue-geocode:' . $userId . ':' . hash('sha256', $request->ip());

        if (!$this->rateLimiter->consumeAttempt($rateKey)) {
            return Response::json([
                'errors' => ['location' => ['Too many address searches. Try again later.']],
            ], 429, ['Cache-Control' => 'private, no-store']);
        }

        $result = $this->geocoding->search($query);

        if (!$result['success']) {
            return Response::json([
                'errors' => $result['errors'],
            ], 503, ['Cache-Control' => 'private, no-store']);
        }

        $results = [];

        foreach (array_slice($result['results'], 0, 5) as $candidate) {
            if (!is_array($candidate)
                || $this->locations->directionsUrl([
                    'latitude' => $candidate['latitude'] ?? null,
                    'longitude' => $candidate['longitude'] ?? null,
                ]) === null) {
                continue;
            }

            $results[] = [
                'label' => mb_substr((string) ($candidate['label'] ?? ''), 0, 255),
                'latitude' => number_format((float) $candidate['latitude'], 7, '.', ''),
                'longitude' => number_format((float) $candidate['longitude'], 7, '.', ''),
            ];
        }

        return Response::json([
            'results' => $results,
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    private function safeInput(Request $request): array
    {
        return array_filter(
            $request->only(self::FIELDS),
            static fn (mixed $value): bool => is_scalar($value),
        );
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_scalar($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'The venue action could not be completed.';
    }

    private function ownedVenue(Request $request): ?array
    {
        $venueId = $this->routeId($request);
        $userId = $this->auth->id();

        return $venueId === null || $userId === null
            ? null
            : $this->venues->findOwned($userId, $venueId);
    }

    private function routeId(Request $request): ?int
    {
        $value = $request->route('id');

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
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
}
