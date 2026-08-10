<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use RuntimeException;

final class SpecCompletionSchemaTest extends TestCase
{
    public function testFreshSchemaEnforcesAnnouncementOwnershipAudienceAndReplaySafety(): void
    {
        $schema = file_get_contents(base_path('database/schema.sql'));

        if (!is_string($schema)) {
            throw new RuntimeException('Unable to read the fresh database schema.');
        }

        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $connection->exec($this->sqliteStatementFor($schema, 'event_announcements'));
        $connection->exec($this->sqliteIndexFor(
            $schema,
            'event_announcements',
            'idx_event_announcements_event_sent',
        ));

        $connection->exec('INSERT INTO events (id) VALUES (1)');
        $connection->exec('INSERT INTO users (id) VALUES (2)');
        $connection->exec(
            "INSERT INTO event_announcements
                (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at)
             VALUES
                (1, 2, 'Doors open earlier', 'Please arrive by 08:30.', 'confirmed', 4,
                 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                 '2026-08-10 08:00:00')",
        );

        $announcement = $connection->query(
            'SELECT subject, audience, recipient_count FROM event_announcements WHERE id = 1',
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Doors open earlier', $announcement['subject'] ?? null);
        $this->assertSame('confirmed', $announcement['audience'] ?? null);
        $this->assertSame(4, (int) ($announcement['recipient_count'] ?? -1));

        $indexColumns = [];
        foreach ($connection->query("PRAGMA index_info('idx_event_announcements_event_sent')") as $column) {
            $indexColumns[] = $column['name'];
        }
        $this->assertSame(['event_id', 'sent_at'], $indexColumns);

        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO event_announcements
                (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at)
             VALUES
                (1, 2, 'Duplicate', 'Must not send twice.', 'confirmed', 4,
                 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                 '2026-08-10 08:01:00')",
            'Announcement request keys must make retries idempotent.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO event_announcements
                (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at)
             VALUES
                (1, 2, 'Wrong audience', 'This audience is unsupported.', 'all', 4,
                 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                 '2026-08-10 08:02:00')",
            'Announcements must target the supported confirmed audience only.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO event_announcements
                (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at)
             VALUES
                (99, 2, 'Unknown event', 'This must fail.', 'confirmed', 0,
                 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
                 '2026-08-10 08:03:00')",
            'Announcements must reference an event.',
        );
    }

    private function sqliteStatementFor(string $schema, string $table): string
    {
        $statement = $this->extractCreateTable($schema, $table);
        $statement = preg_replace('/\bBIGINT UNSIGNED AUTO_INCREMENT\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:BIGINT|INT|TINYINT) UNSIGNED\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:VARCHAR\(\d+\)|CHAR\(\d+\)|DATETIME|TIMESTAMP|JSON)\b/', 'TEXT', $statement) ?? $statement;
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
        $statement = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\s*INDEX\s+[^\n]+,?\s*$/m', '', $statement) ?? $statement;
        $statement = preg_replace('/\) ENGINE=[^;]+;$/', ');', trim($statement)) ?? $statement;
        $statement = preg_replace('/,\s*\);$/', ');', $statement) ?? $statement;

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
                continue;
            }

            if ($schema[$index] !== ')') {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                $end = strpos($schema, ';', $index);

                if ($end !== false) {
                    return substr($schema, $start, $end - $start + 1);
                }
            }
        }

        throw new RuntimeException("Unable to parse {$table} schema.");
    }

    private function sqliteIndexFor(string $schema, string $table, string $index): string
    {
        if (preg_match(
            '/^\s*INDEX ' . preg_quote($index, '/') . '\s+\(([^)]+)\),?\s*$/m',
            $this->extractCreateTable($schema, $table),
            $match,
        ) !== 1) {
            throw new RuntimeException("Unable to locate {$index}.");
        }

        return "CREATE INDEX {$index} ON {$table} ({$match[1]})";
    }

    private function assertConstraintViolation(PDO $connection, string $sql, string $message): void
    {
        try {
            $connection->exec($sql);
            $this->assertTrue(false, $message);
        } catch (PDOException) {
            $this->assertTrue(true);
        }
    }
}
