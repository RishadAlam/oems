<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\AdminPeopleRepositoryInterface;
use PDO;
use Throwable;

final class AdminPeopleRepository implements AdminPeopleRepositoryInterface
{
    private const ROLES = ['super-admin', 'organizer', 'participant'];

    private const USER_STATUSES = ['active', 'inactive', 'suspended'];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function users(array $filters, int $page, int $perPage): array
    {
        $perPage = min(50, max(1, $perPage));
        $clauses = ['users.deleted_at IS NULL'];
        $parameters = [];
        $search = trim(is_string($filters['search'] ?? null) ? $filters['search'] : '');

        if ($search !== '') {
            $clauses[] = '(LOWER(users.name) LIKE :search_name OR LOWER(users.email) LIKE :search_email)';
            $searchValue = '%' . strtolower(mb_substr($search, 0, 100)) . '%';
            $parameters['search_name'] = $searchValue;
            $parameters['search_email'] = $searchValue;
        }

        $role = is_string($filters['role'] ?? null) ? $filters['role'] : '';
        if (in_array($role, self::ROLES, true)) {
            $clauses[] = 'roles.slug = :role';
            $parameters['role'] = $role;
        }

        $status = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if (in_array($status, self::USER_STATUSES, true)) {
            $clauses[] = 'users.status = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $clauses);
        $count = $this->connection->prepare(
            'SELECT COUNT(*) FROM users INNER JOIN roles ON roles.id = users.role_id WHERE ' . $where,
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $statement = $this->connection->prepare(
            'SELECT users.id, users.name, users.email, users.phone, users.avatar, users.status,
                    users.email_verified_at, users.last_login_at, users.created_at,
                    roles.name AS role_name, roles.slug AS role_slug,
                    profiles.city, profiles.country,
                    organizers.id AS organizer_id, organizers.organization_name,
                    organizers.approval_status AS organizer_approval_status
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             LEFT JOIN profiles ON profiles.user_id = users.id
             LEFT JOIN organizers ON organizers.user_id = users.id
             WHERE ' . $where . '
             ORDER BY users.created_at DESC, users.id DESC
             LIMIT :limit OFFSET :offset',
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();

        return [
            'items' => is_array($items) ? $items : [],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function findUser(int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT users.id, users.name, users.email, users.phone, users.avatar, users.status,
                    users.email_verified_at, users.last_login_at, users.created_at,
                    roles.name AS role_name, roles.slug AS role_slug,
                    profiles.bio, profiles.city, profiles.country, profiles.timezone,
                    organizers.id AS organizer_id, organizers.organization_name,
                    organizers.approval_status AS organizer_approval_status,
                    (SELECT COUNT(*) FROM sessions
                     WHERE sessions.user_id = users.id AND sessions.expires_at > CURRENT_TIMESTAMP) AS session_count,
                    (SELECT COUNT(*) FROM registrations WHERE registrations.user_id = users.id) AS registration_count
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             LEFT JOIN profiles ON profiles.user_id = users.id
             LEFT JOIN organizers ON organizers.user_id = users.id
             WHERE users.id = :user_id AND users.deleted_at IS NULL
             LIMIT 1',
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        $user['session_count'] = (int) ($user['session_count'] ?? 0);
        $user['registration_count'] = (int) ($user['registration_count'] ?? 0);

        return $user;
    }

    public function organizers(array $filters, int $page, int $perPage): array
    {
        $perPage = min(50, max(1, $perPage));
        $clauses = ['users.deleted_at IS NULL', "roles.slug = 'organizer'"];
        $parameters = [];
        $search = trim(is_string($filters['search'] ?? null) ? $filters['search'] : '');

        if ($search !== '') {
            $clauses[] = '(LOWER(organizers.organization_name) LIKE :search_organization OR LOWER(users.name) LIKE :search_name OR LOWER(users.email) LIKE :search_email)';
            $searchValue = '%' . strtolower(mb_substr($search, 0, 100)) . '%';
            $parameters['search_organization'] = $searchValue;
            $parameters['search_name'] = $searchValue;
            $parameters['search_email'] = $searchValue;
        }

        $approval = is_string($filters['approval_status'] ?? null) ? $filters['approval_status'] : '';
        if (in_array($approval, ['pending', 'approved', 'rejected'], true)) {
            $clauses[] = 'organizers.approval_status = :approval_status';
            $parameters['approval_status'] = $approval;
        }

        $where = implode(' AND ', $clauses);
        $from = ' FROM organizers INNER JOIN users ON users.id = organizers.user_id INNER JOIN roles ON roles.id = users.role_id ';
        $count = $this->connection->prepare('SELECT COUNT(*)' . $from . 'WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $statement = $this->connection->prepare(
            'SELECT organizers.id, organizers.user_id, organizers.organization_name,
                    organizers.approval_status, organizers.rejection_reason,
                    organizers.approved_at, organizers.created_at,
                    users.name, users.email, users.status AS user_status, users.email_verified_at,
                    roles.slug AS role_slug,
                    (SELECT COUNT(*) FROM events WHERE events.organizer_id = organizers.id AND events.deleted_at IS NULL) AS event_count'
            . $from . 'WHERE ' . $where
            . ' ORDER BY organizers.created_at DESC, organizers.id DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();
        $items = is_array($items) ? array_map(static function (array $item): array {
            $item['event_count'] = (int) ($item['event_count'] ?? 0);

            return $item;
        }, $items) : [];

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function findOrganizer(int $organizerId): ?array
    {
        $page = $this->organizerSelect(
            'organizers.id = :organizer_id AND users.deleted_at IS NULL AND roles.slug = \'organizer\'',
            ['organizer_id' => $organizerId],
        );

        return $page[0] ?? null;
    }

    public function changeUserStatus(
        int $actorId,
        int $userId,
        string $expectedStatus,
        string $status,
        array $context,
    ): bool {
        if ($actorId <= 0
            || $userId <= 0
            || $actorId === $userId
            || !in_array([$expectedStatus, $status], [
                ['active', 'suspended'],
                ['active', 'inactive'],
                ['suspended', 'active'],
                ['inactive', 'active'],
            ], true)) {
            return false;
        }

        return $this->transactional(function () use ($actorId, $userId, $expectedStatus, $status, $context): bool {
            $statement = $this->connection->prepare(
                'UPDATE users
                 SET status = :status, updated_at = CURRENT_TIMESTAMP
                 WHERE users.id = :user_id
                   AND users.deleted_at IS NULL
                   AND users.status = :expected_status
                   AND users.role_id IN (SELECT id FROM roles WHERE slug IN (\'participant\', \'organizer\'))',
            );
            $statement->execute([
                'status' => $status,
                'user_id' => $userId,
                'expected_status' => $expectedStatus,
            ]);

            if ($statement->rowCount() !== 1) {
                return false;
            }

            if ($status !== 'active') {
                $sessions = $this->connection->prepare('DELETE FROM sessions WHERE user_id = :user_id');
                $sessions->execute(['user_id' => $userId]);
                $resets = $this->connection->prepare(
                    'DELETE FROM password_resets WHERE email = (SELECT email FROM users WHERE id = :user_id)',
                );
                $resets->execute(['user_id' => $userId]);
            }

            $this->writeActivity(
                $actorId,
                'user.' . $status,
                'user',
                $userId,
                'User status changed to ' . $status . '.',
                ['from' => $expectedStatus, 'to' => $status],
                $context,
            );

            return true;
        });
    }

    public function changeOrganizerApproval(
        int $actorId,
        int $organizerId,
        string $expectedStatus,
        string $status,
        ?string $reason,
        array $context,
    ): ?array {
        $allowed = $status === 'approved'
            ? ['pending', 'rejected']
            : ($status === 'rejected' ? ['pending', 'approved'] : []);
        if ($actorId <= 0 || $organizerId <= 0 || !in_array($expectedStatus, $allowed, true)) {
            return null;
        }

        return $this->transactional(function () use (
            $actorId,
            $organizerId,
            $expectedStatus,
            $status,
            $reason,
            $context,
        ): ?array {
            $statement = $this->connection->prepare(
                'UPDATE organizers
                 SET approval_status = :status,
                     approved_by = :approved_by,
                     approved_at = :approved_at,
                     rejection_reason = :rejection_reason,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE organizers.id = :organizer_id
                   AND organizers.approval_status = :expected_status
                   AND EXISTS (
                       SELECT 1
                       FROM users
                       INNER JOIN roles ON roles.id = users.role_id
                       WHERE users.id = organizers.user_id
                         AND users.deleted_at IS NULL
                         AND roles.slug = \'organizer\'
                         AND (
                             :approval_action = \'rejected\'
                             OR (users.status = \'active\' AND users.email_verified_at IS NOT NULL)
                         )
                   )',
            );
            $statement->execute([
                'status' => $status,
                'approved_by' => $actorId,
                'approved_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $status === 'rejected' ? $reason : null,
                'organizer_id' => $organizerId,
                'expected_status' => $expectedStatus,
                'approval_action' => $status,
            ]);

            if ($statement->rowCount() !== 1) {
                return null;
            }

            $this->writeActivity(
                $actorId,
                'organizer.' . $status,
                'organizer',
                $organizerId,
                'Organizer application changed to ' . $status . '.',
                ['from' => $expectedStatus, 'to' => $status, 'reason' => $reason],
                $context,
            );
            $lookup = $this->connection->prepare(
                'SELECT organizers.id, organizers.user_id, organizers.organization_name,
                        organizers.approval_status, organizers.rejection_reason
                 FROM organizers WHERE organizers.id = :organizer_id LIMIT 1',
            );
            $lookup->execute(['organizer_id' => $organizerId]);
            $organizer = $lookup->fetch();

            return is_array($organizer) ? $organizer : null;
        });
    }

    private function writeActivity(
        int $actorId,
        string $action,
        string $subjectType,
        int $subjectId,
        string $description,
        array $properties,
        array $context,
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO activity_logs
                (user_id, action, subject_type, subject_id, description, properties, ip_address, user_agent, created_at)
             VALUES
                (:user_id, :action, :subject_type, :subject_id, :description, :properties, :ip_address, :user_agent, CURRENT_TIMESTAMP)',
        );
        $statement->execute([
            'user_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ]);
    }

    private function organizerSelect(string $where, array $parameters): array
    {
        $statement = $this->connection->prepare(
            'SELECT organizers.id, organizers.user_id, organizers.organization_name,
                    organizers.description, organizers.logo, organizers.tax_identifier,
                    organizers.approval_status, organizers.rejection_reason,
                    organizers.approved_at, organizers.created_at,
                    users.name, users.email, users.phone, users.status AS user_status,
                    users.email_verified_at, users.last_login_at,
                    roles.slug AS role_slug,
                    profiles.city, profiles.country,
                    (SELECT COUNT(*) FROM events WHERE events.organizer_id = organizers.id AND events.deleted_at IS NULL) AS event_count
             FROM organizers
             INNER JOIN users ON users.id = organizers.user_id
             INNER JOIN roles ON roles.id = users.role_id
             LEFT JOIN profiles ON profiles.user_id = users.id
             WHERE ' . $where . '
             LIMIT 1',
        );
        $statement->execute($parameters);
        $organizer = $statement->fetch();

        if (!is_array($organizer)) {
            return [];
        }

        $organizer['event_count'] = (int) ($organizer['event_count'] ?? 0);

        return [$organizer];
    }

    private function transactional(callable $callback): mixed
    {
        $started = !$this->connection->inTransaction();

        if ($started) {
            $this->connection->beginTransaction();
        }

        try {
            $result = $callback();
            if ($started) {
                $this->connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
