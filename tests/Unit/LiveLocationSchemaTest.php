<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use PDO;
use PDOException;
use RuntimeException;

final class LiveLocationSchemaTest extends TestCase
{
    public function testLiveLocationSchemaDefinesPrivacyCoordinatesAndGeocodingCache(): void
    {
        $schema = file_get_contents(base_path('database/schema.sql'));

        $this->assertTrue(is_string($schema));
        $this->assertTrue(str_contains($schema, "location_visibility ENUM('public', 'registered') NOT NULL DEFAULT 'public'"));
        $this->assertTrue(str_contains($schema, 'arrival_notes VARCHAR(500) NULL'));
        $this->assertTrue(str_contains($schema, 'INDEX idx_venues_coordinates (latitude, longitude)'));
        $this->assertTrue(str_contains($schema, 'CREATE TABLE geocoding_cache'));
        $this->assertTrue(str_contains($schema, 'query_hash CHAR(64) PRIMARY KEY'));

        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY)');
        $connection->exec($this->sqliteStatementFor($schema, 'venues'));
        $connection->exec($this->sqliteIndexFor($schema, 'venues', 'idx_venues_coordinates'));
        $connection->exec($this->sqliteStatementFor($schema, 'geocoding_cache'));

        $connection->exec("INSERT INTO venues (name, address_line, city, country) VALUES ('Hall', '1 Road', 'Dhaka', 'Bangladesh')");
        $connection->exec("INSERT INTO venues (name, address_line, city, country, latitude, longitude) VALUES ('Mapped Hall', '2 Road', 'Dhaka', 'Bangladesh', 23.8, 90.4)");

        try {
            $connection->exec("INSERT INTO venues (name, address_line, city, country, latitude) VALUES ('Broken Hall', '3 Road', 'Dhaka', 'Bangladesh', 23.8)");
            $this->assertTrue(false, 'Venue coordinates must be supplied as a latitude-longitude pair.');
        } catch (PDOException) {
            $this->assertTrue(true);
        }

        $indexColumns = [];
        foreach ($connection->query("PRAGMA index_info('idx_venues_coordinates')") as $column) {
            $indexColumns[] = $column['name'];
        }

        $this->assertSame(['latitude', 'longitude'], $indexColumns);
        $connection->exec("INSERT INTO geocoding_cache (query_hash, normalized_query, provider, response_json, expires_at) VALUES ('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Dhaka', 'test', '{}', '2026-08-10 00:00:00')");
    }

    public function testForwardMigrationContainsGuardedLiveLocationChanges(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026-08-09-live-location.sql'));

        $this->assertTrue(is_string($migration));
        $this->assertTrue(str_contains($migration, 'information_schema.COLUMNS'));
        $this->assertTrue(str_contains($migration, 'information_schema.STATISTICS'));
        $this->assertTrue(str_contains($migration, 'geocoding_cache'));
    }

    private function sqliteStatementFor(string $schema, string $table): string
    {
        $statement = $this->extractCreateTable($schema, $table);
        $statement = preg_replace('/\bBIGINT UNSIGNED AUTO_INCREMENT\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:BIGINT|INT|TINYINT) UNSIGNED\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\bDECIMAL\(\d+,\s*\d+\)/', 'NUMERIC', $statement) ?? $statement;
        $statement = preg_replace('/\b(?:VARCHAR\(\d+\)|CHAR\(\d+\)|DATETIME|TIMESTAMP|JSON)\b/', 'TEXT', $statement) ?? $statement;
        $statement = preg_replace('/\bBOOLEAN\b/', 'INTEGER', $statement) ?? $statement;
        $statement = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\s*INDEX\s+[^\n]+,?\s*$/m', '', $statement) ?? $statement;
        $statement = preg_replace('/\) ENGINE=[^;]+;$/', ');', trim($statement)) ?? $statement;
        $statement = preg_replace('/,\s*\);$/', ');', $statement) ?? $statement;

        return $statement;
    }

    private function sqliteIndexFor(string $schema, string $table, string $index): string
    {
        if (preg_match('/^\s*INDEX ' . preg_quote($index, '/') . '\s+\(([^)]+)\),?\s*$/m', $this->extractCreateTable($schema, $table), $match) !== 1) {
            throw new RuntimeException("Unable to locate {$index}.");
        }

        return "CREATE INDEX {$index} ON {$table} ({$match[1]})";
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

                    if ($end !== false) {
                        return substr($schema, $start, $end - $start + 1);
                    }
                }
            }
        }

        throw new RuntimeException("Unable to parse {$table} schema.");
    }
}
