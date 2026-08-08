<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use JsonException;
use OEMS\App\Contracts\PaymentRepositoryInterface;
use PDO;

final class PaymentRepository implements PaymentRepositoryInterface
{
    private const REVIEW_ASSIGNMENTS = [
        'paid' => 'paid_at = CURRENT_TIMESTAMP',
        'failed' => 'paid_at = NULL',
    ];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function createForRegistration(int $registrationId, array $attributes): int
    {
        $gatewayResponse = $attributes['gateway_response'] ?? null;

        if (is_array($gatewayResponse)) {
            $gatewayResponse = json_encode($gatewayResponse, JSON_THROW_ON_ERROR);
        }

        $statement = $this->connection->prepare(
            'INSERT INTO payments
                (registration_id, payment_method_id, transaction_reference, amount, currency, status, gateway_response, paid_at)
             VALUES
                (:registration_id, :payment_method_id, :transaction_reference, :amount, :currency, :payment_status, :gateway_response, :paid_at)',
        );
        $statement->execute([
            'registration_id' => $registrationId,
            'payment_method_id' => $attributes['payment_method_id'] ?? null,
            'transaction_reference' => $attributes['transaction_reference'] ?? null,
            'amount' => (string) $attributes['amount'],
            'currency' => (string) $attributes['currency'],
            'payment_status' => (string) $attributes['status'],
            'gateway_response' => $gatewayResponse,
            'paid_at' => $attributes['paid_at'] ?? null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findForRegistration(int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . ' WHERE payments.registration_id = :registration_id'
            . ' ORDER BY payments.created_at DESC, payments.id DESC LIMIT 1',
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $this->hydrate($statement->fetch());
    }

    public function pendingForAdmin(): array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . ' WHERE payments.status = :pending_status'
            . ' ORDER BY payments.created_at ASC, payments.id ASC',
        );
        $statement->execute(['pending_status' => 'pending']);

        return array_map(fn (array $row): array => $this->hydrate($row) ?? [], $statement->fetchAll());
    }

    public function findForAdmin(int $paymentId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect() . ' WHERE payments.id = :payment_id LIMIT 1',
        );
        $statement->execute(['payment_id' => $paymentId]);

        return $this->hydrate($statement->fetch());
    }

    public function review(int $paymentId, int $administratorId, string $status, ?string $note): ?array
    {
        $paidAtAssignment = self::REVIEW_ASSIGNMENTS[$status] ?? null;

        if ($paidAtAssignment === null) {
            return null;
        }

        $statement = $this->connection->prepare(
            "UPDATE payments
             SET status = :review_status,
                 {$paidAtAssignment},
                 reviewed_by = :reviewed_by,
                 reviewed_at = CURRENT_TIMESTAMP,
                 review_note = :review_note,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :payment_id
               AND status = 'pending'",
        );
        $statement->execute([
            'review_status' => $status,
            'reviewed_by' => $administratorId,
            'review_note' => $note,
            'payment_id' => $paymentId,
        ]);

        if ($statement->rowCount() !== 1) {
            return null;
        }

        return $this->findForAdmin($paymentId);
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->paymentSummary(
            'INNER JOIN registrations ON registrations.id = payments.registration_id',
            'WHERE registrations.user_id = :participant_user_id',
            ['participant_user_id' => $participantId],
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->paymentSummary(
            'INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id',
            'WHERE organizers.user_id = :organizer_user_id',
            ['organizer_user_id' => $organizerUserId],
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->paymentSummary('', '', []);
    }

    private function adminSelect(): string
    {
        return 'SELECT payments.id,
                       payments.registration_id,
                       payments.payment_method_id,
                       payments.transaction_reference,
                       payments.amount,
                       payments.currency,
                       payments.status,
                       payments.status AS payment_status,
                       payments.gateway_response,
                       payments.paid_at,
                       payments.refunded_at,
                       payments.reviewed_by,
                       payments.reviewed_at,
                       payments.review_note,
                       payments.created_at,
                       payments.updated_at,
                       registrations.registration_number,
                       registrations.status AS registration_status,
                       users.id AS participant_id,
                       users.name AS participant_name,
                       users.email AS participant_email,
                       events.id AS event_id,
                       events.title AS event_title,
                       events.slug AS event_slug,
                       organizers.id AS organizer_id,
                       organizers.user_id AS organizer_user_id,
                       organizers.organization_name AS organizer_name,
                       payment_methods.name AS payment_method_name,
                       payment_methods.slug AS payment_method_slug
                FROM payments
                INNER JOIN registrations ON registrations.id = payments.registration_id
                INNER JOIN users ON users.id = registrations.user_id
                INNER JOIN events ON events.id = registrations.event_id
                INNER JOIN organizers ON organizers.id = events.organizer_id
                LEFT JOIN payment_methods ON payment_methods.id = payments.payment_method_id';
    }

    private function paymentSummary(string $joins, string $where, array $bindings): array
    {
        $statement = $this->connection->prepare(
            "SELECT COALESCE(SUM(CASE WHEN payments.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN payments.status = 'paid' THEN 1 ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN payments.status = 'paid' THEN payments.amount ELSE 0 END), 0) AS paid_total
             FROM payments
             {$joins}
             {$where}",
        );
        $statement->execute($bindings);
        $summary = $statement->fetch();

        return [
            'pending' => (int) ($summary['pending'] ?? 0),
            'paid' => (int) ($summary['paid'] ?? 0),
            'paid_total' => number_format((float) ($summary['paid_total'] ?? 0), 2, '.', ''),
        ];
    }

    private function hydrate(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        if (!is_string($row['gateway_response'] ?? null) || $row['gateway_response'] === '') {
            $row['gateway_response'] = null;

            return $row;
        }

        try {
            $decoded = json_decode($row['gateway_response'], true, 512, JSON_THROW_ON_ERROR);
            $row['gateway_response'] = is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            $row['gateway_response'] = null;
        }

        return $row;
    }
}
