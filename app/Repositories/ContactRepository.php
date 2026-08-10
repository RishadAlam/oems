<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\ContactRepositoryInterface;
use PDO;

final class ContactRepository implements ContactRepositoryInterface
{
    private const STATUSES = ['new', 'read', 'replied', 'archived'];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(array $attributes): ?array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO contact_messages (name, email, subject, message, status)
             VALUES (:name, :email, :subject, :message, \'new\')',
        );
        $statement->execute([
            'name' => (string) $attributes['name'], 'email' => (string) $attributes['email'],
            'subject' => (string) $attributes['subject'], 'message' => (string) $attributes['message'],
        ]);
        return $statement->rowCount() === 1 ? $this->findForAdmin((int) $this->connection->lastInsertId()) : null;
    }

    public function forAdmin(array $filters, int $limit, int $offset): array
    {
        [$where, $bindings] = $this->scope($filters);
        $statement = $this->connection->prepare(
            "SELECT id, name, email, subject, message, status, replied_by, replied_at, created_at
             FROM contact_messages {$where}
             ORDER BY CASE status WHEN 'new' THEN 0 WHEN 'read' THEN 1 WHEN 'replied' THEN 2 ELSE 3 END,
                      created_at DESC, id DESC
             LIMIT :row_limit OFFSET :row_offset",
        );
        foreach ($bindings as $key => $value) $statement->bindValue($key, $value);
        $statement->bindValue('row_limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue('row_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countForAdmin(array $filters): int
    {
        [$where, $bindings] = $this->scope($filters);
        $statement = $this->connection->prepare("SELECT COUNT(*) FROM contact_messages {$where}");
        $statement->execute($bindings);
        return (int) $statement->fetchColumn();
    }

    public function findForAdmin(int $id, bool $lock = false): ?array
    {
        $locking = $lock && $this->driver() === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare(
            'SELECT id, name, email, subject, message, status, replied_by, replied_at, created_at
             FROM contact_messages WHERE id = :contact_id LIMIT 1' . $locking,
        );
        $statement->execute(['contact_id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function setStatus(int $id, string $from, string $to, int $administratorId): bool
    {
        if (!in_array($from, self::STATUSES, true) || !in_array($to, self::STATUSES, true) || $from === $to) return false;
        $owns = !$this->connection->inTransaction();
        if ($owns) $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare('UPDATE contact_messages SET status = :new_status WHERE id = :contact_id AND status = :old_status');
            $statement->execute(['new_status' => $to, 'contact_id' => $id, 'old_status' => $from]);
            if ($statement->rowCount() !== 1) { if ($owns) $this->connection->rollBack(); return false; }
            $this->audit($administratorId, $id, 'contact_status_changed', ['status' => $from], ['status' => $to]);
            if ($owns) $this->connection->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($owns && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function markReplied(int $id, int $administratorId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE contact_messages SET status = 'replied', replied_by = :administrator_id, replied_at = CURRENT_TIMESTAMP
             WHERE id = :contact_id AND status IN ('new', 'read')",
        );
        $statement->execute(['administrator_id' => $administratorId, 'contact_id' => $id]);
        if ($statement->rowCount() !== 1) return false;
        $this->audit($administratorId, $id, 'contact_replied', [], ['status' => 'replied']);
        return true;
    }

    private function scope(array $filters): array
    {
        $where = []; $bindings = [];
        $status = is_scalar($filters['status'] ?? null) ? (string) $filters['status'] : '';
        if ($status !== '') {
            if (!in_array($status, self::STATUSES, true)) return ['WHERE 1 = 0', []];
            $where[] = 'status = :contact_status'; $bindings['contact_status'] = $status;
        }
        $search = is_scalar($filters['search'] ?? null) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            if (mb_strlen($search) > 100) return ['WHERE 1 = 0', []];
            $needle = '%' . mb_strtolower($search) . '%';
            $where[] = '(LOWER(name) LIKE :search_name OR LOWER(email) LIKE :search_email OR LOWER(subject) LIKE :search_subject)';
            $bindings += ['search_name' => $needle, 'search_email' => $needle, 'search_subject' => $needle];
        }
        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $bindings];
    }

    private function audit(int $userId, int $id, string $action, array $old, array $new): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO activity_logs (user_id, action, subject_type, subject_id, description, properties)
             VALUES (:user_id, :action, \'contact_message\', :subject_id, :description, :properties)',
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'subject_id' => $id,
            'description' => $action === 'contact_replied' ? 'Contact reply queued.' : 'Contact status changed.',
            'properties' => json_encode(['old' => $old, 'new' => $new], JSON_THROW_ON_ERROR),
        ]);
    }

    private function driver(): string { return (string) $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME); }
}
