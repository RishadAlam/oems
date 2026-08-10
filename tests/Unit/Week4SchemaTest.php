<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use RuntimeException;

final class Week4SchemaTest extends TestCase
{
    public function testFreshSchemaDefinesWaitlistQueueAndEventToggle(): void
    {
        $schema = $this->schema();
        $events = $this->extractCreateTable($schema, 'events');
        $registrations = $this->extractCreateTable($schema, 'registrations');

        $this->assertTrue(str_contains($events, 'waitlist_enabled BOOLEAN NOT NULL DEFAULT TRUE'));
        $this->assertTrue(str_contains($registrations, 'waitlisted_at DATETIME NULL'));
        $this->assertTrue(str_contains($registrations, 'promoted_at DATETIME NULL'));
        $this->assertTrue(str_contains(
            $registrations,
            'INDEX idx_registrations_event_waitlist (event_id, status, waitlisted_at, id)',
        ));
    }

    public function testFreshSchemaEnforcesCertificateIdentityAndPrivateArtifactState(): void
    {
        $connection = $this->sqliteConnection();
        $connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $connection->exec($this->sqliteStatementFor($this->schema(), 'event_certificates'));

        $connection->exec('INSERT INTO registrations (id) VALUES (10), (11)');
        $connection->exec('INSERT INTO users (id) VALUES (20)');
        $connection->exec(
            "INSERT INTO event_certificates
                (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at)
             VALUES
                (10, 20, 'OEMS-CERT-ONE', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                 'certificates/one.pdf', 'valid', '2026-08-10 12:00:00')",
        );

        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO event_certificates
                (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at)
             VALUES
                (10, 20, 'OEMS-CERT-TWO', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                 'certificates/two.pdf', 'valid', '2026-08-10 12:01:00')",
            'A registration must have at most one certificate.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO event_certificates
                (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at)
             VALUES
                (11, 20, 'OEMS-CERT-ONE', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
                 'certificates/three.pdf', 'valid', '2026-08-10 12:02:00')",
            'Certificate numbers must be unique.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO event_certificates
                (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at)
             VALUES
                (11, 20, 'OEMS-CERT-THREE', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                 'certificates/four.pdf', 'valid', '2026-08-10 12:03:00')",
            'Verification hashes must be unique.',
        );
    }

    public function testFreshSchemaDefinesPublishableSoftDeletedBlogPosts(): void
    {
        $connection = $this->sqliteConnection();
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $connection->exec($this->sqliteStatementFor($this->schema(), 'blog_posts'));
        $connection->exec('INSERT INTO users (id) VALUES (7)');
        $connection->exec(
            "INSERT INTO blog_posts
                (author_id, title, slug, excerpt, body, category, status)
             VALUES
                (7, 'Safer events', 'safer-events', 'Practical event safety.', 'Plain text body.', 'Guides', 'draft')",
        );

        $row = $connection->query(
            'SELECT title, slug, status, published_at, deleted_at FROM blog_posts WHERE id = 1',
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Safer events', $row['title'] ?? null);
        $this->assertSame('safer-events', $row['slug'] ?? null);
        $this->assertSame('draft', $row['status'] ?? null);
        $this->assertSame(null, $row['published_at'] ?? null);
        $this->assertSame(null, $row['deleted_at'] ?? null);

        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO blog_posts
                (author_id, title, slug, excerpt, body, category, status)
             VALUES
                (7, 'Duplicate', 'safer-events', 'Duplicate slug.', 'Plain text body.', 'Guides', 'published')",
            'Blog slugs must be unique.',
        );
    }

    public function testForwardMigrationIsGuardedAndDocumentsEveryWeek4Change(): void
    {
        $path = base_path('database/migrations/2026-08-10-week-4-growth-experience.sql');
        $migration = is_file($path) ? file_get_contents($path) : false;

        if (!is_string($migration)) {
            throw new RuntimeException('The Week 4 migration is missing.');
        }

        foreach ([
            "column_name = 'waitlist_enabled'",
            "column_name = 'waitlisted_at'",
            "column_name = 'promoted_at'",
            "index_name = 'idx_registrations_event_waitlist'",
            'CREATE TABLE IF NOT EXISTS event_certificates',
            'CREATE TABLE IF NOT EXISTS blog_posts',
        ] as $required) {
            $this->assertTrue(str_contains($migration, $required), "Migration is missing: {$required}");
        }
    }

    private function schema(): string
    {
        $schema = file_get_contents(base_path('database/schema.sql'));

        if (!is_string($schema)) {
            throw new RuntimeException('Unable to read the fresh database schema.');
        }

        return $schema;
    }

    private function sqliteConnection(): PDO
    {
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA foreign_keys = ON');

        return $connection;
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
