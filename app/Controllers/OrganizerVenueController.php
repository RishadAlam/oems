<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\VenueRepositoryInterface;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\Validator;
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
        ], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        [$data, $errors] = $this->validatedInput($request);

        if ($errors !== []) {
            return $this->redirectWithErrors('/organizer/venues/create', $errors, $data);
        }

        if ($this->venues->createForUser($userId, $this->normalize($data)) === null) {
            return $this->redirectWith('/organizer/venues/create', 'error', 'The venue could not be created.');
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
        ], 'dashboard');
    }

    public function update(Request $request): Response
    {
        $venueId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($venueId === null || $userId === null || $this->venues->findOwned($userId, $venueId) === null) {
            return $this->notFound();
        }

        [$data, $errors] = $this->validatedInput($request);

        if ($errors !== []) {
            return $this->redirectWithErrors(
                '/organizer/venues/' . $venueId . '/edit',
                $errors,
                $data,
            );
        }

        if (!$this->venues->updateOwned($userId, $venueId, $this->normalize($data))) {
            return $this->redirectWith(
                '/organizer/venues/' . $venueId . '/edit',
                'error',
                'The venue could not be updated.',
            );
        }

        return $this->redirectWith('/organizer/venues', 'success', 'Venue updated.');
    }

    public function delete(Request $request): Response
    {
        $venueId = $this->routeId($request);
        $userId = $this->auth->id();

        if ($venueId === null || $userId === null || $this->venues->findOwned($userId, $venueId) === null) {
            return $this->notFound();
        }

        if (!$this->venues->deleteOwnedIfUnused($userId, $venueId)) {
            return $this->redirectWith(
                '/organizer/venues',
                'error',
                'This venue cannot be deleted while an event uses it.',
            );
        }

        return $this->redirectWith('/organizer/venues', 'success', 'Venue deleted.');
    }

    private function validatedInput(Request $request): array
    {
        $data = array_filter(
            $request->only(self::FIELDS),
            static fn (mixed $value): bool => is_scalar($value),
        );
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:160',
            'address_line' => 'required|string|max:190',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'latitude' => 'nullable|numeric|min_value:-90|max_value:90',
            'longitude' => 'nullable|numeric|min_value:-180|max_value:180',
            'map_url' => 'nullable|url|max:500',
            'capacity' => 'nullable|integer|min_value:1|max_value:100000',
        ]);

        return [$data, $errors];
    }

    private function normalize(array $data): array
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $normalized[$field] = $value === '' ? null : $value;
        }

        foreach (['name', 'address_line', 'city', 'country'] as $required) {
            $normalized[$required] = (string) $normalized[$required];
        }

        $normalized['capacity'] = $normalized['capacity'] === null ? null : (int) $normalized['capacity'];

        return $normalized;
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
}
