<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\PaymentRepositoryInterface;

final class FakePaymentRepository implements PaymentRepositoryInterface
{
    public ?\ArrayObject $lockTrace = null;

    public array $payments = [];

    public bool $failCreate = false;

    public bool $failReview = false;

    public array $methods = [];

    public function findActiveMethodBySlug(string $slug): ?array
    {
        $method = $this->methods[$slug] ?? null;

        return is_array($method) && !empty($method['is_active']) ? $method : null;
    }

    public function createForRegistration(int $registrationId, array $attributes): int
    {
        if ($this->failCreate) {
            return 0;
        }

        $id = $this->payments === [] ? 1 : max(array_keys($this->payments)) + 1;
        $this->payments[$id] = array_merge($attributes, [
            'id' => $id,
            'registration_id' => $registrationId,
            'status' => $attributes['status'] ?? 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);

        return $id;
    }

    public function findForRegistration(int $registrationId): ?array
    {
        $payments = array_values(array_filter(
            $this->payments,
            static fn (array $payment): bool => (int) $payment['registration_id'] === $registrationId,
        ));
        usort($payments, static fn (array $left, array $right): int => [
            $right['created_at'] ?? '',
            $right['id'],
        ] <=> [
            $left['created_at'] ?? '',
            $left['id'],
        ]);

        return isset($payments[0]) ? $this->withAliases($payments[0]) : null;
    }

    public function findForRegistrationCurrent(int $registrationId): ?array
    {
        return $this->findForRegistration($registrationId);
    }

    public function pendingForAdmin(): array
    {
        $payments = array_values(array_filter(
            $this->payments,
            static fn (array $payment): bool => $payment['status'] === 'pending',
        ));
        usort($payments, static fn (array $left, array $right): int => [
            $left['created_at'] ?? '',
            $left['id'],
        ] <=> [
            $right['created_at'] ?? '',
            $right['id'],
        ]);

        return array_map($this->withAliases(...), $payments);
    }

    public function forAdmin(array $filters, int $limit, int $offset): array
    {
        $status = is_scalar($filters['status'] ?? null) ? (string) $filters['status'] : 'pending';
        $allowed = ['pending', 'paid', 'failed', 'refunded', 'all'];
        $status = in_array($status, $allowed, true) ? $status : 'pending';
        $search = is_scalar($filters['search'] ?? null) ? trim((string) $filters['search']) : '';
        if (mb_strlen($search) > 120) {
            return [];
        }

        $payments = array_values(array_filter($this->payments, static function (array $payment) use ($status, $search): bool {
            if ($status !== 'all' && ($payment['status'] ?? null) !== $status) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            return str_contains(mb_strtolower(implode(' ', [
                $payment['participant_name'] ?? '',
                $payment['participant_email'] ?? '',
                $payment['event_title'] ?? '',
                $payment['transaction_reference'] ?? '',
            ])), mb_strtolower($search));
        }));
        usort($payments, static function (array $left, array $right): int {
            $leftPending = ($left['status'] ?? null) === 'pending';
            $rightPending = ($right['status'] ?? null) === 'pending';
            if ($leftPending !== $rightPending) {
                return $leftPending ? -1 : 1;
            }

            return $leftPending
                ? [($left['created_at'] ?? ''), ($left['id'] ?? 0)] <=> [($right['created_at'] ?? ''), ($right['id'] ?? 0)]
                : [($right['created_at'] ?? ''), ($right['id'] ?? 0)] <=> [($left['created_at'] ?? ''), ($left['id'] ?? 0)];
        });

        return array_map($this->withAliases(...), array_slice($payments, max(0, $offset), min(100, max(1, $limit))));
    }

    public function countForAdmin(array $filters): int
    {
        return count($this->forAdmin($filters, 100, 0));
    }

    public function findForAdmin(int $paymentId): ?array
    {
        return isset($this->payments[$paymentId])
            ? $this->withAliases($this->payments[$paymentId])
            : null;
    }

    public function findForAdminCurrent(int $paymentId): ?array
    {
        $this->lockTrace?->append('payment');

        return $this->findForAdmin($paymentId);
    }

    public function review(int $paymentId, int $administratorId, string $status, ?string $note): ?array
    {
        if ($this->failReview
            || !in_array($status, ['paid', 'failed'], true)
            || ($this->payments[$paymentId]['status'] ?? null) !== 'pending') {
            return null;
        }

        $this->payments[$paymentId]['status'] = $status;
        $this->payments[$paymentId]['reviewed_by'] = $administratorId;
        $this->payments[$paymentId]['reviewed_at'] = 'now';
        $this->payments[$paymentId]['review_note'] = $note;
        $this->payments[$paymentId]['paid_at'] = $status === 'paid' ? 'now' : null;

        return $this->withAliases($this->payments[$paymentId]);
    }

    public function cancelForRegistration(int $registrationId): bool
    {
        foreach (array_reverse(array_keys($this->payments)) as $id) {
            if ((int) $this->payments[$id]['registration_id'] !== $registrationId
                || !in_array($this->payments[$id]['status'], ['pending', 'paid'], true)) {
                continue;
            }

            $this->payments[$id]['status'] = $this->payments[$id]['status'] === 'paid' ? 'refunded' : 'failed';

            return true;
        }

        return false;
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->paymentSummary(
            static fn (array $payment): bool => (int) ($payment['participant_id'] ?? 0) === $participantId,
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->paymentSummary(
            static fn (array $payment): bool => (int) ($payment['organizer_user_id'] ?? 0) === $organizerUserId,
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->paymentSummary(static fn (array $payment): bool => true);
    }

    private function paymentSummary(callable $scope): array
    {
        $pending = 0;
        $paid = 0;
        $paidTotal = 0.0;

        foreach ($this->payments as $payment) {
            if (!$scope($payment)) {
                continue;
            }

            if ($payment['status'] === 'pending') {
                $pending++;
            } elseif ($payment['status'] === 'paid') {
                $paid++;
                $paidTotal += (float) $payment['amount'];
            }
        }

        return [
            'pending' => $pending,
            'paid' => $paid,
            'paid_total' => number_format($paidTotal, 2, '.', ''),
        ];
    }

    private function withAliases(array $payment): array
    {
        $payment['payment_status'] = $payment['status'];
        $response = is_array($payment['gateway_response'] ?? null) ? $payment['gateway_response'] : [];
        $payment['payment_channel'] = is_string($response['channel'] ?? null) ? $response['channel'] : null;
        unset($payment['gateway_response']);

        return $payment;
    }
}
