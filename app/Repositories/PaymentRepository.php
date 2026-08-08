<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use JsonException;
use OEMS\App\Contracts\PaymentRepositoryInterface;
use PDO;

final class PaymentRepository implements PaymentRepositoryInterface
{
    private const ADMIN_STATUSES = ['pending', 'paid', 'failed', 'refunded', 'all'];

    private const REVIEW_ASSIGNMENTS = [
        'paid' => 'paid_at = CURRENT_TIMESTAMP',
        'failed' => 'paid_at = NULL',
    ];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findActiveMethodBySlug(string $slug): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, slug, configuration, is_active
             FROM payment_methods
             WHERE slug = :slug AND is_active = :is_active
             LIMIT 1',
        );
        $statement->execute(['slug' => $slug, 'is_active' => 1]);
        $method = $statement->fetch();

        return is_array($method) ? $method : null;
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

    public function findForRegistrationCurrent(int $registrationId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . ' WHERE payments.registration_id = :registration_id'
            . ' ORDER BY payments.created_at DESC, payments.id DESC LIMIT 1'
            . $this->lockingClause(),
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $this->hydrate($statement->fetch());
    }

    public function pendingForAdmin(): array
    {
        return $this->forAdmin(['status' => 'pending'], 100, 0);
    }

    public function forAdmin(array $filters, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->adminFilter($filters);
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . $where
            . " ORDER BY CASE WHEN payments.status = 'pending' THEN 0 ELSE 1 END ASC,
                         CASE WHEN payments.status = 'pending' THEN payments.created_at END ASC,
                         CASE WHEN payments.status = 'pending' THEN payments.id END ASC,
                         CASE WHEN payments.status <> 'pending' THEN payments.created_at END DESC,
                         CASE WHEN payments.status <> 'pending' THEN payments.id END DESC
                LIMIT :admin_limit OFFSET :admin_offset",
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }
        $statement->bindValue(':admin_limit', min(100, max(1, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':admin_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): array => $this->hydrate($row) ?? [], $statement->fetchAll());
    }

    public function countForAdmin(array $filters): int
    {
        [$where, $parameters] = $this->adminFilter($filters);
        $statement = $this->connection->prepare(
            'SELECT COUNT(*)'
            . $this->adminJoins()
            . $where,
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function findForAdmin(int $paymentId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect() . ' WHERE payments.id = :payment_id LIMIT 1',
        );
        $statement->execute(['payment_id' => $paymentId]);

        return $this->hydrate($statement->fetch());
    }

    public function findForAdminCurrent(int $paymentId): ?array
    {
        $statement = $this->connection->prepare(
            $this->adminSelect()
            . ' WHERE payments.id = :payment_id LIMIT 1'
            . $this->lockingClause(),
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

    public function cancelForRegistration(int $registrationId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE payments
             SET refunded_at = CASE WHEN status = 'paid' THEN CURRENT_TIMESTAMP ELSE refunded_at END,
                 status = CASE status
                    WHEN 'pending' THEN 'failed'
                    WHEN 'paid' THEN 'refunded'
                    ELSE status
                 END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = (
                 SELECT id FROM (
                     SELECT id FROM payments
                     WHERE registration_id = :registration_id
                     ORDER BY created_at DESC, id DESC
                     LIMIT 1
                 ) AS latest_payment
             )
               AND status IN ('pending', 'paid')",
        );
        $statement->execute(['registration_id' => $registrationId]);

        return $statement->rowCount() === 1;
    }

    public function summaryForParticipant(int $participantId): array
    {
        return $this->paymentSummary(
            'INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id',
            'WHERE registrations.user_id = :participant_user_id AND events.deleted_at IS NULL AND users.deleted_at IS NULL',
            ['participant_user_id' => $participantId],
        );
    }

    public function summaryForOrganizer(int $organizerUserId): array
    {
        return $this->paymentSummary(
            'INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN organizers ON organizers.id = events.organizer_id
             INNER JOIN users ON users.id = registrations.user_id',
            'WHERE organizers.user_id = :organizer_user_id AND events.deleted_at IS NULL AND users.deleted_at IS NULL',
            ['organizer_user_id' => $organizerUserId],
        );
    }

    public function summaryForAdmin(): array
    {
        return $this->paymentSummary(
            'INNER JOIN registrations ON registrations.id = payments.registration_id
             INNER JOIN events ON events.id = registrations.event_id
             INNER JOIN users ON users.id = registrations.user_id',
            'WHERE events.deleted_at IS NULL AND users.deleted_at IS NULL',
            [],
        );
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
                       payment_methods.slug AS payment_method_slug,
                       reviewer.name AS reviewer_name,
                       COALESCE(tickets.status, \'none\') AS ticket_status'
                . $this->adminJoins();
    }

    private function adminJoins(): string
    {
        return ' FROM payments
                INNER JOIN registrations ON registrations.id = payments.registration_id
                INNER JOIN users ON users.id = registrations.user_id
                INNER JOIN events ON events.id = registrations.event_id
                INNER JOIN organizers ON organizers.id = events.organizer_id
                LEFT JOIN payment_methods ON payment_methods.id = payments.payment_method_id
                LEFT JOIN users AS reviewer ON reviewer.id = payments.reviewed_by
                LEFT JOIN tickets ON tickets.registration_id = registrations.id';
    }

    private function adminFilter(array $filters): array
    {
        $requestedStatus = is_scalar($filters['status'] ?? null)
            ? mb_strtolower(trim((string) $filters['status']))
            : 'pending';
        $status = in_array($requestedStatus, self::ADMIN_STATUSES, true) ? $requestedStatus : 'pending';
        $search = is_scalar($filters['search'] ?? null) ? trim((string) $filters['search']) : '';
        $clauses = ['users.deleted_at IS NULL', 'events.deleted_at IS NULL'];
        $parameters = [];

        if ($status !== 'all') {
            $clauses[] = 'payments.status = :admin_status';
            $parameters['admin_status'] = $status;
        }

        if ($search !== '') {
            if (mb_strlen($search) > 120) {
                $clauses[] = '1 = 0';
            } else {
                $needle = '%' . strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
                $clauses[] = "(LOWER(users.name) LIKE :admin_search_participant_name ESCAPE '!'
                    OR LOWER(users.email) LIKE :admin_search_participant_email ESCAPE '!'
                    OR LOWER(events.title) LIKE :admin_search_event ESCAPE '!'
                    OR LOWER(COALESCE(payments.transaction_reference, '')) LIKE :admin_search_reference ESCAPE '!')";
                $parameters['admin_search_participant_name'] = $needle;
                $parameters['admin_search_participant_email'] = $needle;
                $parameters['admin_search_event'] = $needle;
                $parameters['admin_search_reference'] = $needle;
            }
        }

        return [' WHERE ' . implode(' AND ', $clauses), $parameters];
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

        $channel = null;
        try {
            $decoded = is_string($row['gateway_response'] ?? null) && $row['gateway_response'] !== ''
                ? json_decode($row['gateway_response'], true, 512, JSON_THROW_ON_ERROR)
                : null;
            $candidate = is_array($decoded) ? ($decoded['channel'] ?? null) : null;
            $channel = is_string($candidate) && mb_strlen($candidate) <= 50 ? $candidate : null;
        } catch (JsonException) {
            $channel = null;
        }

        unset($row['gateway_response']);
        $row['payment_channel'] = $channel;
        $row['amount'] = number_format((float) ($row['amount'] ?? 0), 2, '.', '');

        return $row;
    }

    private function lockingClause(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
    }
}
