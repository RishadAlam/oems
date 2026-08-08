<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\OrganizerRepositoryInterface;
use OEMS\App\Contracts\VenueRepositoryInterface;
use OEMS\Core\Logger;
use OEMS\Core\Validator;
use Throwable;

final class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly CategoryRepositoryInterface $categories,
        private readonly VenueRepositoryInterface $venues,
        private readonly ImageUploadService $uploads,
        private readonly OrganizerRepositoryInterface $organizers,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function createDraft(int $userId, array $data, ?array $banner, array $gallery): array
    {
        [$attributes, $errors] = $this->eventAttributes($userId, $data, null, null);

        if (count($gallery) > 6) {
            $errors['gallery'][] = 'An event may have no more than six gallery images.';
        }

        if ($errors !== []) {
            return $this->failure($errors);
        }

        $media = $this->storeMedia($banner, $gallery);

        if (!$media['success']) {
            return $this->failure($media['errors']);
        }

        $attributes['banner'] = $media['banner'];

        try {
            $eventId = $this->events->createWithGalleryForUser($userId, $attributes, $media['gallery']);

            if ($eventId === null) {
                $this->deleteMedia($media['paths']);

                return $this->failure(['event' => ['The event could not be created.']]);
            }
        } catch (Throwable) {
            $this->deleteMedia($media['paths']);
            $this->logPersistenceFailure('create', $userId);

            return $this->failure(['event' => ['The event could not be created.']]);
        }

        return $this->success(['event_id' => $eventId]);
    }

    public function update(int $userId, int $eventId, array $data, ?array $banner, array $gallery): array
    {
        $event = $this->events->findOwned($userId, $eventId);

        if ($event === null) {
            return $this->failure(['event' => ['Event not found.']]);
        }

        if (!in_array((string) ($event['status'] ?? ''), ['draft', 'rejected'], true)) {
            return $this->failure(['status' => ['This event cannot be edited in its current state.']]);
        }

        [$attributes, $errors] = $this->eventAttributes($userId, $data, $eventId, $event);

        if (count($gallery) > 6) {
            $errors['gallery'][] = 'An event may have no more than six gallery images.';
        }

        if ($errors !== []) {
            return $this->failure($errors);
        }

        $media = $this->storeMedia($banner, $gallery);

        if (!$media['success']) {
            return $this->failure($media['errors']);
        }

        $attributes['banner'] = $media['banner'] ?? (is_string($event['banner'] ?? null) ? $event['banner'] : null);
        $galleryReplacement = $media['gallery'] === [] ? null : $media['gallery'];

        try {
            $priorMedia = $this->events->updateWithGalleryOwned(
                $userId,
                $eventId,
                $attributes,
                $galleryReplacement,
            );

            if ($priorMedia === null) {
                $this->deleteMedia($media['paths']);

                return $this->failure(['event' => ['The event could not be updated.']]);
            }
        } catch (Throwable) {
            $this->deleteMedia($media['paths']);
            $this->logPersistenceFailure('update', $userId, $eventId);

            return $this->failure(['event' => ['The event could not be updated.']]);
        }

        $priorBanner = $priorMedia['banner'] ?? null;
        $currentReferences = $galleryReplacement ?? ($priorMedia['gallery'] ?? []);

        if (is_string($attributes['banner'] ?? null)) {
            $currentReferences[] = $attributes['banner'];
        }

        if ($media['banner'] !== null
            && is_string($priorBanner)
            && !in_array($priorBanner, $currentReferences, true)) {
            $this->uploads->delete($priorBanner);
        }

        if ($galleryReplacement !== null) {
            foreach ($priorMedia['gallery'] ?? [] as $priorPath) {
                if (is_string($priorPath) && !in_array($priorPath, $currentReferences, true)) {
                    $this->uploads->delete($priorPath);
                }
            }
        }

        return $this->success(['event_id' => $eventId]);
    }

    public function submit(int $userId, int $eventId): array
    {
        $event = $this->events->findOwned($userId, $eventId);

        if ($event === null) {
            return $this->failure(['event' => ['Event not found.']]);
        }

        if ((string) ($event['status'] ?? '') !== 'draft') {
            return $this->failure(['status' => ['Only saved drafts may be submitted. Edit a rejected event before resubmitting it.']]);
        }

        if (!$this->organizerIsApproved($userId)) {
            return $this->failure(['status' => ['Organizer approval is required before submission.']]);
        }

        return $this->ownedTransition($userId, $eventId, 'pending');
    }

    public function cancel(int $userId, int $eventId): array
    {
        $event = $this->events->findOwned($userId, $eventId);

        if ($event === null) {
            return $this->failure(['event' => ['Event not found.']]);
        }

        if (!in_array((string) ($event['status'] ?? ''), ['approved', 'published'], true)) {
            return $this->failure(['status' => ['Only approved or published events may be cancelled.']]);
        }

        return $this->ownedTransition($userId, $eventId, 'cancelled');
    }

    public function publish(int $userId, int $eventId): array
    {
        $event = $this->events->findOwned($userId, $eventId);

        if ($event === null) {
            return $this->failure(['event' => ['Event not found.']]);
        }

        $status = (string) ($event['status'] ?? '');

        if ($status === 'published') {
            return $this->success(['event_id' => $eventId, 'status' => 'published']);
        }

        if ($status !== 'approved') {
            return $this->failure(['status' => ['Only approved events may be published.']]);
        }

        if (!$this->events->publishOwned($userId, $eventId, [])) {
            $winner = $this->events->findOwned($userId, $eventId);

            if (($winner['status'] ?? null) === 'published') {
                return $this->success(['event_id' => $eventId, 'status' => 'published']);
            }

            return $this->failure(['event' => ['The event status could not be changed.']]);
        }

        return $this->success(['event_id' => $eventId, 'status' => 'published']);
    }

    public function delete(int $userId, int $eventId): array
    {
        $event = $this->events->findOwned($userId, $eventId);

        if ($event === null) {
            return $this->failure(['event' => ['Event not found.']]);
        }

        if (!in_array((string) ($event['status'] ?? ''), ['draft', 'rejected', 'cancelled'], true)) {
            return $this->failure(['status' => ['This event cannot be deleted in its current state.']]);
        }

        if (!$this->events->softDeleteOwned($userId, $eventId, [])) {
            return $this->failure(['event' => ['The event could not be deleted.']]);
        }

        return $this->success(['event_id' => $eventId]);
    }

    public function moderate(int $userId, int $eventId, string $status, ?string $reason): array
    {
        $event = $this->events->findForAdmin($eventId);

        if ($event === null) {
            return $this->failure(['event' => ['Event not found.']]);
        }

        $status = strtolower(trim($status));
        $current = (string) ($event['status'] ?? '');
        $allowed = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['published', 'cancelled'],
            'published' => ['completed', 'cancelled'],
        ];

        if (!in_array($status, $allowed[$current] ?? [], true)) {
            return $this->failure(['status' => ['That event status transition is not allowed.']]);
        }

        $normalizedReason = $reason === null ? null : trim($reason);

        if ($status === 'rejected') {
            $errors = Validator::validate(
                ['reason' => $normalizedReason],
                ['reason' => 'required|string|max:500'],
            );

            if ($errors !== []) {
                return $this->failure($errors);
            }
        } else {
            $normalizedReason = null;
        }

        if (!$this->events->transitionAdmin($userId, $eventId, [], $status, $normalizedReason)) {
            return $this->failure(['event' => ['The event status could not be changed.']]);
        }

        return $this->success(['event_id' => $eventId, 'status' => $status]);
    }

    private function eventAttributes(
        int $userId,
        array $data,
        ?int $eventId,
        ?array $existing,
    ): array {
        $normalized = [
            'category_id' => $this->stringValue($data['category_id'] ?? null),
            'venue_id' => $this->stringValue($data['venue_id'] ?? null),
            'title' => $this->stringValue($data['title'] ?? null),
            'description' => $this->stringValue($data['description'] ?? null),
            'map_url' => $this->stringValue($data['map_url'] ?? null),
            'speaker' => $this->stringValue($data['speaker'] ?? null),
            'start_date' => $this->stringValue($data['start_date'] ?? null),
            'end_date' => $this->stringValue($data['end_date'] ?? null),
            'registration_deadline' => $this->stringValue($data['registration_deadline'] ?? null),
            'capacity' => $this->stringValue($data['capacity'] ?? null),
            'ticket_price' => $this->stringValue($data['ticket_price'] ?? null),
        ];
        $errors = Validator::validate($normalized, [
            'category_id' => 'required|integer|min_value:1',
            'venue_id' => 'nullable|integer|min_value:1',
            'title' => 'required|string|min:5|max:180',
            'description' => 'required|string|min:30|max:20000',
            'map_url' => 'nullable|url|max:500',
            'speaker' => 'nullable|string|max:190',
            'start_date' => 'required|datetime_local',
            'end_date' => 'required|datetime_local|after:start_date',
            'registration_deadline' => 'required|datetime_local|before_or_equal:start_date',
            'capacity' => 'required|integer|min_value:1|max_value:100000',
            'ticket_price' => 'required|numeric|min_value:0|max_value:9999999.99',
        ]);

        $category = $errors['category_id'] ?? null;

        if ($category === null) {
            $foundCategory = $this->categories->find((int) $normalized['category_id']);

            if ($foundCategory === null || empty($foundCategory['is_active'])) {
                $errors['category_id'][] = 'Select an active category.';
            }
        }

        $venue = null;

        if ($normalized['venue_id'] !== '' && !isset($errors['venue_id'])) {
            $venue = $this->venues->findOwned($userId, (int) $normalized['venue_id']);

            if ($venue === null) {
                $errors['venue_id'][] = 'Select a venue owned by your organizer account.';
            }
        }

        if ($venue !== null
            && !isset($errors['capacity'])
            && $venue['capacity'] !== null
            && (int) $normalized['capacity'] > (int) $venue['capacity']) {
            $errors['capacity'][] = 'Capacity may not exceed the selected venue capacity.';
        }

        $tags = $this->normalizeTags($data['tags'] ?? '');

        if (count($tags) > 12) {
            $errors['tags'][] = 'Enter no more than 12 tags.';
        }

        foreach ($tags as $tag) {
            if ($this->length($tag) > 40) {
                $errors['tags'][] = 'Each tag may not be longer than 40 characters.';
                break;
            }
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        $capacity = (int) $normalized['capacity'];
        $availableSeats = $capacity;

        if ($existing !== null) {
            $registered = max(0, (int) ($existing['capacity'] ?? 0) - (int) ($existing['available_seats'] ?? 0));

            if ($registered > $capacity) {
                return [[], ['capacity' => ['Capacity may not be lower than existing registrations.']]];
            }

            $availableSeats = $capacity - $registered;
        }

        return [[
            'category_id' => (int) $normalized['category_id'],
            'venue_id' => $normalized['venue_id'] === '' ? null : (int) $normalized['venue_id'],
            'title' => $normalized['title'],
            'slug' => $this->uniqueSlug($normalized['title'], $eventId),
            'description' => $normalized['description'],
            'map_url' => $normalized['map_url'] === '' ? null : $normalized['map_url'],
            'speaker' => $normalized['speaker'] === '' ? null : $normalized['speaker'],
            'start_date' => $this->databaseDateTime($normalized['start_date']),
            'end_date' => $this->databaseDateTime($normalized['end_date']),
            'registration_deadline' => $this->databaseDateTime($normalized['registration_deadline']),
            'capacity' => $capacity,
            'available_seats' => $availableSeats,
            'ticket_price' => number_format((float) $normalized['ticket_price'], 2, '.', ''),
            'currency' => 'BDT',
            'tags' => $tags,
            'is_featured' => (bool) ($existing['is_featured'] ?? false),
        ], []];
    }

    private function storeMedia(?array $banner, array $gallery): array
    {
        $storedPaths = [];
        $bannerResult = $this->uploads->store($banner);

        if (!$bannerResult['success']) {
            return [
                'success' => false,
                'banner' => null,
                'gallery' => [],
                'paths' => [],
                'errors' => ['banner' => [$bannerResult['error']]],
            ];
        }

        if ($bannerResult['path'] !== null) {
            $storedPaths[] = $bannerResult['path'];
        }

        $galleryPaths = [];

        foreach ($gallery as $image) {
            if ($image !== null && !is_array($image)) {
                $this->deleteMedia($storedPaths);

                return [
                    'success' => false,
                    'banner' => null,
                    'gallery' => [],
                    'paths' => [],
                    'errors' => ['gallery' => ['The gallery image upload is invalid.']],
                ];
            }

            $result = $this->uploads->store($image);

            if (!$result['success']) {
                $this->deleteMedia($storedPaths);

                return [
                    'success' => false,
                    'banner' => null,
                    'gallery' => [],
                    'paths' => [],
                    'errors' => ['gallery' => [$result['error']]],
                ];
            }

            if ($result['path'] !== null) {
                $galleryPaths[] = $result['path'];
                $storedPaths[] = $result['path'];
            }
        }

        return [
            'success' => true,
            'banner' => $bannerResult['path'],
            'gallery' => $galleryPaths,
            'paths' => $storedPaths,
            'errors' => [],
        ];
    }

    private function deleteMedia(array $paths): void
    {
        foreach ($paths as $path) {
            $this->uploads->delete(is_string($path) ? $path : null);
        }
    }

    private function normalizeTags(mixed $tags): array
    {
        $values = is_array($tags) ? $tags : explode(',', (string) $tags);
        $normalized = [];

        foreach ($values as $tag) {
            if (!is_scalar($tag)) {
                continue;
            }

            $tag = mb_strtolower(trim((string) $tag));

            if ($tag !== '' && !in_array($tag, $normalized, true)) {
                $normalized[] = $tag;
            }
        }

        return $normalized;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function uniqueSlug(string $title, ?int $exceptId): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $base = strtolower(is_string($ascii) ? $ascii : $title);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        $base = $base === '' ? 'event' : $base;
        $slug = $base;
        $suffix = 2;

        while ($this->events->slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function databaseDateTime(string $value): string
    {
        return str_replace('T', ' ', $value) . ':00';
    }

    private function organizerIsApproved(int $userId): bool
    {
        return $this->organizers->approvalStatusForUser($userId) === 'approved';
    }

    private function logPersistenceFailure(string $operation, int $userId, ?int $eventId = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = ['operation' => $operation, 'user_id' => $userId];

        if ($eventId !== null) {
            $context['event_id'] = $eventId;
        }

        try {
            $this->logger->error('Event persistence operation failed.', $context);
        } catch (Throwable) {
            // Logging must not replace the stable user-facing persistence error.
        }
    }

    private function ownedTransition(int $userId, int $eventId, string $status): array
    {
        if (!$this->events->transitionOwned($userId, $eventId, [], $status)) {
            return $this->failure(['event' => ['The event status could not be changed.']]);
        }

        return $this->success(['event_id' => $eventId, 'status' => $status]);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function success(array $data = []): array
    {
        return array_merge(['success' => true, 'errors' => []], $data);
    }

    private function failure(array $errors): array
    {
        return ['success' => false, 'errors' => $errors];
    }
}
