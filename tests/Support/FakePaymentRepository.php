<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\PaymentRepositoryInterface;

final class FakePaymentRepository implements PaymentRepositoryInterface
{
    public array $payments = [];

    public bool $failCreate = false;

    public bool $failReview = false;

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

    public function findForAdmin(int $paymentId): ?array
    {
        return isset($this->payments[$paymentId])
            ? $this->withAliases($this->payments[$paymentId])
            : null;
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

    private function withAliases(array $payment): array
    {
        $payment['payment_status'] = $payment['status'];

        return $payment;
    }
}
