<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\AdminPeopleRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class AdminPeopleService
{
    public function __construct(
        private readonly AdminPeopleRepositoryInterface $people,
        private readonly ?NotificationService $notifications = null,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function users(array $input): array
    {
        $roles = ['super-admin', 'organizer', 'participant'];
        $statuses = ['active', 'inactive', 'suspended'];
        if (!$this->validTextFilter($input['search'] ?? null)
            || !$this->validChoiceFilter($input['role'] ?? null, $roles)
            || !$this->validChoiceFilter($input['status'] ?? null, $statuses)) {
            return $this->emptyResult($this->perPage($input['per_page'] ?? null));
        }

        return $this->people->users([
            'search' => mb_substr($this->scalar($input['search'] ?? null), 0, 100),
            'role' => $this->allow($input['role'] ?? null, $roles),
            'status' => $this->allow($input['status'] ?? null, $statuses),
        ], $this->page($input['page'] ?? null), $this->perPage($input['per_page'] ?? null));
    }

    public function organizers(array $input): array
    {
        $approvalStatuses = ['pending', 'approved', 'rejected'];
        if (!$this->validTextFilter($input['search'] ?? null)
            || !$this->validChoiceFilter($input['approval_status'] ?? null, $approvalStatuses)) {
            return $this->emptyResult($this->perPage($input['per_page'] ?? null));
        }

        return $this->people->organizers([
            'search' => mb_substr($this->scalar($input['search'] ?? null), 0, 100),
            'approval_status' => $this->allow($input['approval_status'] ?? null, $approvalStatuses),
        ], $this->page($input['page'] ?? null), $this->perPage($input['per_page'] ?? null));
    }

    public function suspend(int $actorId, int $userId, array $context): array
    {
        return $this->changeStatus($actorId, $userId, ['active'], 'suspended', $context);
    }

    public function deactivate(int $actorId, int $userId, array $context): array
    {
        return $this->changeStatus($actorId, $userId, ['active'], 'inactive', $context);
    }

    public function reactivate(int $actorId, int $userId, array $context): array
    {
        return $this->changeStatus($actorId, $userId, ['suspended', 'inactive'], 'active', $context);
    }

    public function approveOrganizer(int $actorId, int $organizerId, array $context = []): array
    {
        return $this->changeApproval($actorId, $organizerId, 'approved', null, $context);
    }

    public function rejectOrganizer(
        int $actorId,
        int $organizerId,
        mixed $reason,
        array $context = [],
    ): array {
        $reason = $this->scalar($reason);
        $errors = [];

        if ($reason === '') {
            $errors['reason'][] = 'A rejection reason is required.';
        } elseif (mb_strlen($reason) > 500) {
            $errors['reason'][] = 'The rejection reason may not be greater than 500 characters.';
        }

        return $errors === []
            ? $this->changeApproval($actorId, $organizerId, 'rejected', $reason, $context)
            : $this->failure('validation', $errors);
    }

    private function changeStatus(
        int $actorId,
        int $userId,
        array $expectedStatuses,
        string $status,
        array $context,
    ): array {
        $user = $this->people->findUser($userId);

        if ($user === null) {
            return $this->failure('not_found', ['user' => ['User not found.']]);
        }

        if ($actorId === $userId || ($user['role_slug'] ?? null) === 'super-admin') {
            return $this->failure('forbidden', ['user' => ['Super administrator accounts cannot be changed here.']]);
        }

        if (!in_array((string) ($user['role_slug'] ?? ''), ['participant', 'organizer'], true)) {
            return $this->failure('forbidden', ['user' => ['This account role cannot be changed here.']]);
        }

        $expectedStatus = (string) ($user['status'] ?? '');
        if (!in_array($expectedStatus, $expectedStatuses, true)) {
            return $this->failure('conflict', ['user' => ['The account status has already changed.']]);
        }

        try {
            $changed = $this->people->changeUserStatus(
                $actorId,
                $userId,
                $expectedStatus,
                $status,
                $this->safeContext($context),
            );
        } catch (Throwable $exception) {
            $this->logFailure('user_status', $actorId, $userId, $exception);

            return $this->failure('persistence', ['user' => ['The account status could not be changed.']]);
        }

        return $changed
            ? ['success' => true, 'code' => 'ok', 'errors' => []]
            : $this->failure('conflict', ['user' => ['The account status has already changed.']]);
    }

    private function changeApproval(
        int $actorId,
        int $organizerId,
        string $status,
        ?string $reason,
        array $context,
    ): array {
        $organizer = $this->people->findOrganizer($organizerId);

        if ($organizer === null) {
            return $this->failure('not_found', ['organizer' => ['Organizer not found.']]);
        }

        $current = (string) ($organizer['approval_status'] ?? '');
        if ($current === $status && $this->approvalStateMatches($organizer, $status, $reason)) {
            return ['success' => true, 'code' => 'ok', 'errors' => [], 'idempotent' => true];
        }
        if ($current === $status) {
            return $this->failure('conflict', ['organizer' => ['The organizer application has already changed.']]);
        }

        $allowed = $status === 'approved' ? ['pending', 'rejected'] : ['pending', 'approved'];
        $organizerIdentity = ($organizer['role_slug'] ?? null) === 'organizer';
        $eligibleForApproval = $organizerIdentity
            && ($organizer['user_status'] ?? null) === 'active'
            && !empty($organizer['email_verified_at']);

        if (!$organizerIdentity
            || ($status === 'approved' && !$eligibleForApproval)
            || !in_array($current, $allowed, true)) {
            return $this->failure('conflict', ['organizer' => ['The organizer is no longer eligible for this action.']]);
        }

        try {
            $changed = $this->people->changeOrganizerApproval(
                $actorId,
                $organizerId,
                $current,
                $status,
                $reason,
                $this->safeContext($context),
            );
        } catch (Throwable $exception) {
            $this->logFailure('organizer_approval', $actorId, $organizerId, $exception);

            return $this->failure('persistence', ['organizer' => ['The organizer application could not be changed.']]);
        }

        if ($changed === null) {
            $winner = $this->people->findOrganizer($organizerId);
            if ($winner !== null && $this->approvalStateMatches($winner, $status, $reason)) {
                return ['success' => true, 'code' => 'ok', 'errors' => [], 'idempotent' => true];
            }

            return $this->failure('conflict', ['organizer' => ['The organizer application has already changed.']]);
        }

        $this->notifyOrganizer($changed, $status, $reason);

        return ['success' => true, 'code' => 'ok', 'errors' => []];
    }

    private function approvalStateMatches(array $organizer, string $status, ?string $reason): bool
    {
        if (($organizer['approval_status'] ?? null) !== $status) {
            return false;
        }

        return $status !== 'rejected'
            || hash_equals((string) ($organizer['rejection_reason'] ?? ''), (string) $reason);
    }

    private function notifyOrganizer(array $organizer, string $status, ?string $reason): void
    {
        $userId = (int) ($organizer['user_id'] ?? 0);
        $organization = trim((string) ($organizer['organization_name'] ?? 'Your organization'));

        if ($status === 'approved') {
            $this->notifications?->notify(
                $userId,
                'organizer_application_approved',
                'Organizer application approved',
                $organization . ' can now submit events for review.',
                '/organizer/dashboard',
            );

            return;
        }

        $this->notifications?->notify(
            $userId,
            'organizer_application_rejected',
            'Organizer application needs changes',
            'Review the administrator feedback before applying again: ' . (string) $reason,
            '/organizer/dashboard',
        );
    }

    private function safeContext(array $context): array
    {
        return [
            'ip_address' => is_string($context['ip_address'] ?? null)
                ? mb_substr($context['ip_address'], 0, 45)
                : null,
            'user_agent' => is_string($context['user_agent'] ?? null)
                ? mb_substr($context['user_agent'], 0, 500)
                : null,
        ];
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function allow(mixed $value, array $allowed): string
    {
        $value = $this->scalar($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function validTextFilter(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    private function validChoiceFilter(mixed $value, array $allowed): bool
    {
        return $value === null
            || (is_string($value) && ($value === '' || in_array($value, $allowed, true)));
    }

    private function emptyResult(int $perPage): array
    {
        return [
            'items' => [],
            'pagination' => [
                'page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
        ];
    }

    private function page(mixed $value): int
    {
        $value = $this->scalar($value);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : 1;
    }

    private function perPage(mixed $value): int
    {
        $value = $this->scalar($value);

        return in_array($value, ['10', '25', '50'], true) ? (int) $value : 10;
    }

    private function failure(string $code, array $errors): array
    {
        return ['success' => false, 'code' => $code, 'errors' => $errors];
    }

    private function logFailure(string $operation, int $actorId, int $subjectId, Throwable $exception): void
    {
        try {
            $this->logger?->error('Administrator people operation failed.', [
                'operation' => $operation,
                'actor_id' => $actorId,
                'subject_id' => $subjectId,
                'exception' => $exception::class,
            ]);
        } catch (Throwable) {
        }
    }
}
