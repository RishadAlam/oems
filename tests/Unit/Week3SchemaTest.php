<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use RuntimeException;

final class Week3SchemaTest extends TestCase
{
    public function testFreshSchemaProvidesDurableOutboxAndNewsletterCampaignIntegrity(): void
    {
        $schema = file_get_contents(base_path('database/schema.sql'));
        if (!is_string($schema)) {
            throw new RuntimeException('Unable to read the fresh database schema.');
        }

        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $connection->exec($this->sqliteStatementFor($schema, 'newsletter_campaigns'));
        $connection->exec($this->sqliteStatementFor($schema, 'mail_outbox'));
        $connection->exec('INSERT INTO users (id) VALUES (1)');

        $connection->exec(
            "INSERT INTO newsletter_campaigns
                (subject, message, status, created_by, recipient_count, queued_count, request_key, created_at)
             VALUES
                ('August events', 'Four new events are ready.', 'draft', 1, 0, 0,
                 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                 '2026-08-10 09:00:00')",
        );
        $connection->exec(
            "INSERT INTO mail_outbox
                (template, recipient_email, payload, idempotency_key, status, attempts, available_at, created_at, updated_at)
             VALUES
                ('event_reminder', 'person@example.com', '{}',
                 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                 'queued', 0, '2026-08-10 09:00:00', '2026-08-10 09:00:00', '2026-08-10 09:00:00')",
        );

        $this->assertSame(1, (int) $connection->query('SELECT COUNT(*) FROM newsletter_campaigns')->fetchColumn());
        $this->assertSame(1, (int) $connection->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn());
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO mail_outbox
                (template, recipient_email, payload, idempotency_key, status, attempts, available_at, created_at, updated_at)
             VALUES
                ('event_reminder', 'other@example.com', '{}',
                 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                 'queued', 0, '2026-08-10 09:00:00', '2026-08-10 09:00:00', '2026-08-10 09:00:00')",
            'Outbox idempotency keys must reject duplicate delivery jobs.',
        );
        $this->assertConstraintViolation(
            $connection,
            "INSERT INTO newsletter_campaigns
                (subject, message, status, created_by, recipient_count, queued_count, request_key, created_at)
             VALUES
                ('Replay', 'Must not duplicate.', 'draft', 1, 0, 0,
                 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                 '2026-08-10 09:01:00')",
            'Campaign request keys must reject duplicate campaigns.',
        );
    }

    public function testFreshSchemaEnforcesCouponUsageAndNewsletterConfirmationBoundaries(): void
    {
        $schema = file_get_contents(base_path('database/schema.sql'));
        if (!is_string($schema)) {
            throw new RuntimeException('Unable to read the fresh database schema.');
        }

        $newsletter = $this->extractCreateTable($schema, 'newsletter');
        $coupons = $this->extractCreateTable($schema, 'coupons');
        $usage = $this->extractCreateTable($schema, 'coupon_usage');

        foreach (['confirmation_token_hash', 'confirmation_expires_at', 'confirmed_at', 'unsubscribe_token_hash'] as $column) {
            $this->assertTrue(str_contains($newsletter, $column), "Newsletter schema is missing {$column}.");
        }
        $this->assertTrue(str_contains($newsletter, "ENUM('pending', 'subscribed', 'unsubscribed')"));
        $this->assertTrue(str_contains($coupons, 'chk_coupons_discount'));
        $this->assertTrue(str_contains($coupons, 'chk_coupons_usage'));
        $this->assertTrue(str_contains($coupons, 'chk_coupons_dates'));
        $this->assertTrue(str_contains($usage, 'uq_coupon_usage_coupon_user'));
    }

    private function sqliteStatementFor(string $schema, string $table): string
    {
        $statement = $this->extractCreateTable($schema, $table);
        $statement = preg_replace('/\bBIGINT UNSIGNED AUTO_INCREMENT\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:BIGINT|INT|TINYINT) UNSIGNED\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\bBOOLEAN\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:VARCHAR\(\d+\)|CHAR\(\d+\)|DATETIME|TIMESTAMP|JSON|LONGTEXT|TEXT)\b/', 'TEXT', $statement) ?? $statement;
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
        $statement = preg_replace('/^\s*(?:INDEX|UNIQUE KEY)\s+[^\n]+,?\s*$/m', '', $statement) ?? $statement;
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
        for ($index = $start, $length = strlen($schema); $index < $length; $index++) {
            if ($schema[$index] === '(') {
                $depth++;
            } elseif ($schema[$index] === ')') {
                $depth--;
                if ($depth === 0) {
                    $end = strpos($schema, ';', $index);
                    if ($end !== false) {
                        return substr($schema, $start, $end - $start + 1);
                    }
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
