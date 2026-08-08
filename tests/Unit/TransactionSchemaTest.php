<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use RuntimeException;

final class TransactionSchemaTest extends TestCase
{
    public function testTransactionRelationshipsStatusesAndIdempotencyAreEnforced(): void
    {
        $connection = $this->connection();
        $this->insertParents($connection);

        $this->execute(
            $connection,
            "INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at)\n"
            . "VALUES (1, 1, 'REG-001', 'pending', 0, 'BDT', '2026-08-08 10:00:00')",
        );

        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at)\n"
            . "VALUES (1, 1, 'REG-002', 'pending', 0, 'BDT', '2026-08-08 10:01:00')",
            'A participant must not receive a second registration for the same event.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at)\n"
            . "VALUES (99, 2, 'REG-003', 'pending', 0, 'BDT', '2026-08-08 10:02:00')",
            'Registrations must reference an event.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at)\n"
            . "VALUES (1, 99, 'REG-003', 'pending', 0, 'BDT', '2026-08-08 10:02:00')",
            'Registrations must reference a participant.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at)\n"
            . "VALUES (1, 2, 'REG-004', 'invalid', 0, 'BDT', '2026-08-08 10:03:00')",
            'Registrations must reject unknown statuses.',
        );

        $this->execute(
            $connection,
            "INSERT INTO payments (registration_id, payment_method_id, transaction_reference, amount, currency, status)\n"
            . "VALUES (1, 1, 'PAY-001', 0, 'BDT', 'pending')",
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO payments (registration_id, payment_method_id, transaction_reference, amount, currency, status)\n"
            . "VALUES (1, 1, 'PAY-001', 0, 'BDT', 'pending')",
            'Payment transaction references must be unique for idempotent retries.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO payments (registration_id, payment_method_id, transaction_reference, amount, currency, status)\n"
            . "VALUES (99, 1, 'PAY-002', 0, 'BDT', 'pending')",
            'Payments must reference a registration.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO payments (registration_id, payment_method_id, transaction_reference, amount, currency, status)\n"
            . "VALUES (1, 1, 'PAY-003', 0, 'BDT', 'invalid')",
            'Payments must reject unknown statuses.',
        );

        $this->execute(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (1, 'TKT-001', 'hash-001', 'valid', '2026-08-08 10:05:00')",
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (1, 'TKT-002', 'hash-002', 'valid', '2026-08-08 10:06:00')",
            'A registration must issue only one ticket.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (1, 'TKT-001', 'hash-003', 'valid', '2026-08-08 10:07:00')",
            'Ticket numbers must be unique.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (1, 'TKT-003', 'hash-001', 'valid', '2026-08-08 10:08:00')",
            'Stored QR token digests must be unique.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (1, 'TKT-004', 'hash-004', 'invalid', '2026-08-08 10:09:00')",
            'Tickets must reject unknown statuses.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (99, 'TKT-005', 'hash-005', 'valid', '2026-08-08 10:10:00')",
            'Tickets must reference a registration.',
        );

        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO reviews (event_id, user_id, rating, review, status)\n"
            . "VALUES (1, 2, 5, 'Invalid review status', 'invalid')",
            'Reviews must reject unknown statuses.',
        );

        $this->execute(
            $connection,
            "INSERT INTO reviews (event_id, user_id, rating, review, status)\n"
            . "VALUES (1, 1, 5, 'Great event', 'pending')",
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO reviews (event_id, user_id, rating, review, status)\n"
            . "VALUES (1, 1, 4, 'Duplicate review', 'pending')",
            'A participant must have only one review per event.',
        );
        $this->execute($connection, 'INSERT INTO favorites (user_id, event_id) VALUES (1, 1)');
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO favorites (user_id, event_id) VALUES (1, 1)",
            'A participant must have only one favorite per event.',
        );

        $this->execute(
            $connection,
            "INSERT INTO attendance (registration_id, ticket_id, scanned_by, status, scanned_at)\n"
            . "VALUES (1, 1, 1, 'present', '2026-08-08 10:10:00')",
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO attendance (registration_id, ticket_id, scanned_by, status, scanned_at)\n"
            . "VALUES (1, 1, 1, 'present', '2026-08-08 10:11:00')",
            'Attendance must be recorded only once per registration and ticket.',
        );

        $this->execute(
            $connection,
            "INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at)\n"
            . "VALUES (1, 2, 'REG-005', 'confirmed', 0, 'BDT', '2026-08-08 10:12:00')",
        );
        $this->execute(
            $connection,
            "INSERT INTO tickets (registration_id, ticket_number, qr_payload_hash, status, issued_at)\n"
            . "VALUES (2, 'TKT-006', 'hash-006', 'valid', '2026-08-08 10:13:00')",
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO attendance (registration_id, ticket_id, scanned_by, status, scanned_at)\n"
            . "VALUES (2, 2, 1, 'invalid', '2026-08-08 10:14:00')",
            'Attendance must reject unknown statuses.',
        );
    }

    public function testPaymentSettlementRetainsReviewerAuditData(): void
    {
        $columns = $this->tableColumns($this->connection(), 'payments');

        $this->assertTrue(
            isset($columns['reviewed_by'], $columns['reviewed_at'], $columns['review_note']),
            'Payment settlement must retain the reviewing administrator, timestamp, and internal note.',
        );
    }

    public function testReviewModerationQueueHasAnIndexedStatusTimestampLookup(): void
    {
        $connection = $this->connection();
        $indexes = $this->indexes($connection, 'reviews');

        $this->assertTrue(
            in_array(['status', 'created_at'], $indexes, true),
            'Review moderation queues need a status and creation-time index.',
        );
    }

    private function connection(): PDO
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE coupons (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE payment_methods (id INTEGER PRIMARY KEY)');

        foreach (['registrations', 'payments', 'tickets', 'attendance', 'reviews', 'favorites'] as $table) {
            $connection->exec($this->sqliteStatementFor($table));

            foreach ($this->sqliteIndexesFor($table) as $index) {
                $connection->exec($index);
            }
        }

        return $connection;
    }

    private function sqliteStatementFor(string $table): string
    {
        $schema = file_get_contents(base_path('database/schema.sql'));

        if (!is_string($schema)) {
            throw new RuntimeException('Unable to read database/schema.sql.');
        }

        $statement = $this->extractCreateTable($schema, $table);
        $statement = preg_replace('/\bBIGINT UNSIGNED AUTO_INCREMENT\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:BIGINT|INT|TINYINT) UNSIGNED\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\bDECIMAL\(\d+,\s*\d+\)/', 'NUMERIC', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:VARCHAR\(\d+\)|CHAR\(\d+\)|DATETIME|TIMESTAMP|JSON)\b/', 'TEXT', $statement) ?? $statement;
        $statement = preg_replace('/\bBOOLEAN\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\s*INDEX\s+[^\n]+,?\s*$/m', '', $statement) ?? $statement;
        $statement = preg_replace('/\bUNIQUE KEY\s+(\w+)\s*\(/', 'CONSTRAINT $1 UNIQUE (', $statement) ?? $statement;
        $statement = preg_replace_callback(
            '/\b(\w+)\s+ENUM\(([^)]*)\)([^,\n]*)/',
            static fn (array $match): string => sprintf(
                '%s TEXT%s CHECK (%s IN (%s))',
                $match[1],
                $match[3],
                $match[1],
                $match[2],
            ),
            $statement,
        ) ?? $statement;
        $statement = preg_replace('/\) ENGINE=[^;]+;$/', ');', trim($statement)) ?? $statement;

        return $statement;
    }

    private function extractCreateTable(string $schema, string $table): string
    {
        $prefix = "CREATE TABLE {$table} (";
        $start = strpos($schema, $prefix);

        if ($start === false) {
            throw new RuntimeException("Unable to locate {$table} schema.");
        }

        $depth = 0;
        $length = strlen($schema);

        for ($index = $start; $index < $length; $index++) {
            if ($schema[$index] === '(') {
                $depth++;
            } elseif ($schema[$index] === ')') {
                $depth--;

                if ($depth === 0) {
                    $end = strpos($schema, ';', $index);

                    if ($end === false) {
                        break;
                    }

                    return substr($schema, $start, $end - $start + 1);
                }
            }
        }

        throw new RuntimeException("Unable to parse {$table} schema.");
    }

    private function sqliteIndexesFor(string $table): array
    {
        $schema = file_get_contents(base_path('database/schema.sql'));

        if (!is_string($schema)) {
            throw new RuntimeException('Unable to read database/schema.sql.');
        }

        $indexes = [];
        preg_match_all(
            '/^\s*INDEX\s+(\w+)\s+\(([^)]+)\),?\s*$/m',
            $this->extractCreateTable($schema, $table),
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $indexes[] = sprintf('CREATE INDEX %s ON %s (%s)', $match[1], $table, $match[2]);
        }

        return $indexes;
    }

    private function insertParents(PDO $connection): void
    {
        foreach (['events', 'users', 'coupons', 'payment_methods'] as $table) {
            $connection->exec("INSERT INTO {$table} (id) VALUES (1)");
        }

        $connection->exec('INSERT INTO users (id) VALUES (2)');
    }

    private function execute(PDO $connection, string $sql): void
    {
        $connection->exec($sql);
    }

    private function assertConstraintViolation(
        PDO $connection,
        string $sql,
        string $message,
    ): void {
        try {
            $connection->exec($sql);
            $this->assertTrue(false, $message);
        } catch (PDOException) {
            $this->assertTrue(true, $message);
        }
    }

    private function tableColumns(PDO $connection, string $table): array
    {
        $columns = [];

        foreach ($connection->query("PRAGMA table_info({$table})") as $column) {
            $columns[$column['name']] = true;
        }

        return $columns;
    }

    private function indexes(PDO $connection, string $table): array
    {
        $indexes = [];

        foreach ($connection->query("PRAGMA index_list({$table})") as $index) {
            $columns = [];

            foreach ($connection->query("PRAGMA index_info({$index['name']})") as $column) {
                $columns[] = $column['name'];
            }

            $indexes[] = $columns;
        }

        return $indexes;
    }
}
