<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\AnnouncementRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class AnnouncementService
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcements,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function workspace(int $organizerUserId, int $eventId): ?array
    {
        $event = $this->announcements->findOwnedEvent($organizerUserId, $eventId);
        if ($event === null) {
            return null;
        }

        return [
            'event' => $event,
            'announcements' => $this->announcements->historyForOwnedEvent($organizerUserId, $eventId, 25),
            'can_send' => $this->eligible($event),
        ];
    }

    public function review(int $organizerUserId, int $eventId, mixed $subject, mixed $message): array
    {
        $event = $this->announcements->findOwnedEvent($organizerUserId, $eventId);
        if ($event === null) {
            return ['success' => false, 'code' => 'not_found', 'errors' => []];
        }

        if (!$this->eligible($event)) {
            return ['success' => false, 'code' => 'ineligible', 'errors' => []];
        }

        $validated = $this->validateContent($subject, $message);
        if (!$validated['success']) {
            return $validated;
        }

        return [
            'success' => true,
            'code' => 'ready',
            'data' => $validated['data'],
            'event' => $event,
        ];
    }

    public function send(
        int $organizerUserId,
        int $eventId,
        mixed $subject,
        mixed $message,
        mixed $requestKey,
        array $context,
    ): array {
        $review = $this->review($organizerUserId, $eventId, $subject, $message);
        if (!$review['success']) {
            return $review;
        }

        if (!is_scalar($requestKey)
            || preg_match('/\A[a-f0-9]{64}\z/D', (string) $requestKey) !== 1) {
            return [
                'success' => false,
                'code' => 'invalid_request',
                'errors' => ['request_key' => ['Review the announcement again before sending.']],
            ];
        }

        try {
            $announcement = $this->announcements->deliverToConfirmedParticipants(
                $organizerUserId,
                $eventId,
                (string) $review['data']['subject'],
                (string) $review['data']['message'],
                (string) $requestKey,
                $context,
            );
        } catch (Throwable $exception) {
            try {
                $this->logger?->error('announcement_delivery', [
                    'organizer_user_id' => $organizerUserId,
                    'event_id' => $eventId,
                    'exception' => $exception::class,
                ]);
            } catch (Throwable) {
            }

            return [
                'success' => false,
                'code' => 'persistence',
                'errors' => ['announcement' => ['The announcement could not be sent. Please try again.']],
            ];
        }

        $status = $announcement['status'] ?? null;
        if ($status === 'sent' || $status === 'replayed') {
            return [
                'success' => true,
                'code' => $status,
                'replayed' => $status === 'replayed',
                'announcement' => $announcement,
            ];
        }

        $message = match ($status) {
            'no_recipients' => 'No active, verified participants have a confirmed registration for this event.',
            'ineligible' => 'Announcements are available only for published or completed events from approved organizers.',
            'not_found' => 'The event could not be found.',
            default => 'The announcement could not be sent. Please try again.',
        };

        return [
            'success' => false,
            'code' => is_string($status) ? $status : 'persistence',
            'errors' => ['announcement' => [$message]],
        ];
    }

    private function validateContent(mixed $subject, mixed $message): array
    {
        $errors = [];
        $normalizedSubject = is_scalar($subject) ? trim((string) $subject) : '';
        $normalizedMessage = is_scalar($message) ? trim((string) $message) : '';

        if (!is_scalar($subject) || $normalizedSubject === '' || mb_strlen($normalizedSubject) > 180) {
            $errors['subject'] = ['Enter a subject of no more than 180 characters.'];
        }
        if (!is_scalar($message) || $normalizedMessage === '' || mb_strlen($normalizedMessage) > 1000) {
            $errors['message'] = ['Enter a message of no more than 1,000 characters.'];
        }

        return $errors === []
            ? [
                'success' => true,
                'data' => ['subject' => $normalizedSubject, 'message' => $normalizedMessage],
            ]
            : ['success' => false, 'code' => 'validation', 'errors' => $errors];
    }

    private function eligible(array $event): bool
    {
        return in_array($event['status'] ?? null, ['published', 'completed'], true)
            && ($event['organizer_approval_status'] ?? null) === 'approved'
            && ($event['organizer_user_status'] ?? null) === 'active'
            && !empty($event['organizer_email_verified_at'])
            && empty($event['organizer_deleted_at'])
            && ($event['organizer_role'] ?? null) === 'organizer';
    }
}
