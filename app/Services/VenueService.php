<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\VenueRepositoryInterface;
use OEMS\Core\Logger;
use OEMS\Core\Validator;
use Throwable;

final class VenueService
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
        private readonly VenueRepositoryInterface $venues,
        private readonly ?Logger $logger = null,
        private readonly ?LocationService $locations = null,
    ) {
    }

    public function create(int $userId, array $data): array
    {
        [$attributes, $errors] = $this->venueAttributes($data);

        if ($errors !== []) {
            return $this->failure($errors);
        }

        try {
            $venueId = $this->venues->createForUser($userId, $attributes);
        } catch (Throwable) {
            $this->logPersistenceFailure('create', $userId);
            $venueId = null;
        }

        if ($venueId === null) {
            return $this->failure(['venue' => ['The venue could not be created.']]);
        }

        return $this->success(['venue_id' => $venueId]);
    }

    public function update(int $userId, int $venueId, array $data): array
    {
        if ($this->venues->findOwned($userId, $venueId) === null) {
            return $this->notFound();
        }

        [$attributes, $errors] = $this->venueAttributes($data);

        if ($errors !== []) {
            return $this->failure($errors);
        }

        try {
            $updated = $this->venues->updateOwned($userId, $venueId, $attributes);
        } catch (Throwable) {
            $this->logPersistenceFailure('update', $userId, $venueId);
            $updated = false;
        }

        if (!$updated) {
            return $this->failure(['venue' => ['The venue could not be updated.']]);
        }

        return $this->success(['venue_id' => $venueId]);
    }

    public function delete(int $userId, int $venueId): array
    {
        if ($this->venues->findOwned($userId, $venueId) === null) {
            return $this->notFound();
        }

        try {
            $deleted = $this->venues->deleteOwnedIfUnused($userId, $venueId);
        } catch (Throwable) {
            $this->logPersistenceFailure('delete', $userId, $venueId);
            $deleted = false;
        }

        if (!$deleted) {
            return $this->failure([
                'venue' => ['This venue cannot be deleted while an event uses it.'],
            ]);
        }

        return $this->success(['venue_id' => $venueId]);
    }

    private function venueAttributes(array $data): array
    {
        $attributes = [];

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : '';
            $attributes[$field] = $value === '' ? null : $value;
        }

        foreach (['name', 'address_line', 'city', 'country'] as $required) {
            $attributes[$required] = (string) ($attributes[$required] ?? '');
        }

        $errors = Validator::validate($attributes, [
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

        $hasLatitude = $attributes['latitude'] !== null;
        $hasLongitude = $attributes['longitude'] !== null;

        if ($hasLatitude !== $hasLongitude) {
            $message = 'Enter both latitude and longitude, or leave both blank.';
            $errors['latitude'][] = $message;
            $errors['longitude'][] = $message;
        }

        if ($attributes['map_url'] !== null
            && strtolower((string) parse_url($attributes['map_url'], PHP_URL_SCHEME)) !== 'https') {
            $errors['map_url'][] = 'The map URL must use HTTPS.';
        } elseif ($attributes['map_url'] !== null
            && !($this->locations ?? new LocationService())->isTrustedDirectionsUrl($attributes['map_url'])) {
            $errors['map_url'][] = 'Use a directions URL from a trusted map provider.';
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        foreach (['latitude', 'longitude'] as $coordinate) {
            if ($attributes[$coordinate] !== null) {
                $attributes[$coordinate] = number_format((float) $attributes[$coordinate], 7, '.', '');
            }
        }

        $attributes['capacity'] = $attributes['capacity'] === null
            ? null
            : (int) $attributes['capacity'];

        return [$attributes, []];
    }

    private function logPersistenceFailure(string $operation, int $userId, ?int $venueId = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = ['operation' => $operation, 'user_id' => $userId];

        if ($venueId !== null) {
            $context['venue_id'] = $venueId;
        }

        try {
            $this->logger->error('Venue persistence operation failed.', $context);
        } catch (Throwable) {
            // Logging must not replace the stable user-facing persistence error.
        }
    }

    private function success(array $data = []): array
    {
        return array_merge([
            'success' => true,
            'not_found' => false,
            'errors' => [],
        ], $data);
    }

    private function failure(array $errors): array
    {
        return [
            'success' => false,
            'not_found' => false,
            'errors' => $errors,
        ];
    }

    private function notFound(): array
    {
        return [
            'success' => false,
            'not_found' => true,
            'errors' => [],
        ];
    }
}
