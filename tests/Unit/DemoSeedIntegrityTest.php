<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class DemoSeedIntegrityTest extends TestCase
{
    private string $seed;

    protected function setUp(): void
    {
        $seed = file_get_contents(base_path('database/demo_seed.sql'));

        if (!is_string($seed)) {
            throw new RuntimeException('Unable to read database/demo_seed.sql.');
        }

        $this->seed = $seed;
    }

    public function testEveryDemoEventUsesAnOwnedVenueAndLifecycleEligibleOrganizer(): void
    {
        $organizerRows = $this->insertRows('organizers');
        $approvedUsers = [];

        foreach ($organizerRows as $row) {
            if ($this->literal($row[3] ?? '') === 'approved') {
                $approvedUsers[$row[0]] = true;
            }
        }

        preg_match_all(
            '/SET\s+(?<organizer>@[a-z_]+_organizer_id)\s*=\s*\(SELECT id FROM organizers WHERE user_id = (?<user>@[a-z_]+_user_id)\);/',
            $this->seed,
            $organizerMatches,
            PREG_SET_ORDER,
        );
        $approvedOrganizers = [];

        foreach ($organizerMatches as $match) {
            if (isset($approvedUsers[$match['user']])) {
                $approvedOrganizers[$match['organizer']] = true;
            }
        }

        preg_match_all(
            "/SET\s+(?<venue>@[a-z_]+_id)\s*=\s*\(SELECT id FROM venues WHERE name = '[^']+' AND organizer_id = (?<organizer>@[a-z_]+_organizer_id) LIMIT 1\);/",
            $this->seed,
            $venueMatches,
            PREG_SET_ORDER,
        );
        $venueOwners = [];

        foreach ($venueMatches as $match) {
            $venueOwners[$match['venue']] = $match['organizer'];
        }

        foreach ($this->insertRows('events') as $row) {
            $organizer = $row[0] ?? '';
            $venue = $row[2] ?? '';
            $slug = $this->literal($row[4] ?? '');
            $status = $this->literal($row[16] ?? '');

            $this->assertSame(
                $organizer,
                $venueOwners[$venue] ?? null,
                "Demo event {$slug} must reference a venue owned by its organizer.",
            );

            if ($status !== 'draft') {
                $this->assertTrue(
                    isset($approvedOrganizers[$organizer]),
                    "Demo event {$slug} cannot be {$status} under a pending organizer.",
                );
            }
        }
    }

    public function testDemoSeedRestoresTheDocumentedAdministratorCredential(): void
    {
        $matched = preg_match("/SET @admin_password = '([^']+)';/", $this->seed, $matches);

        $this->assertSame(1, $matched, 'Demo seed must declare the local administrator password hash.');
        $this->assertTrue(password_verify('ChangeMe!2026', $matches[1] ?? ''));
        $this->assertTrue(str_contains(
            $this->seed,
            "UPDATE users\nSET password = @admin_password\nWHERE id = @admin_user_id;",
        ));
    }

    public function testDemoAccountCommentAndLifecycleDocumentationMatchBehavior(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertTrue(str_contains(
            $this->seed,
            '-- Every non-administrator demo account uses the password: DemoPass!2026',
        ));
        $this->assertTrue(is_string($readme));
        $this->assertTrue(str_contains($readme, '`rejected` → `draft` by saving edits'));
        $this->assertTrue(str_contains($readme, '`draft` → `pending` by submitting for review'));
    }

    public function testTechSummitAvailableSeatsMatchItsSeededConfirmedRegistrations(): void
    {
        $eventRows = $this->insertRows('events');
        $registrationRows = $this->insertRows('registrations');
        $techEvent = null;

        foreach ($eventRows as $row) {
            if ($this->literal($row[4] ?? '') === 'dhaka-tech-summit-2026') {
                $techEvent = $row;
                break;
            }
        }

        $confirmed = count(array_filter(
            $registrationRows,
            fn (array $row): bool => ($row[0] ?? null) === '@tech_event_id'
                && $this->literal($row[3] ?? '') === 'confirmed',
        ));

        $this->assertNotNull($techEvent);
        $this->assertSame((int) $techEvent[11] - $confirmed, (int) $techEvent[12]);
    }

    public function testFutureTicketRowsDoNotClaimGeneratedMediaFiles(): void
    {
        $ticketRows = $this->insertRows('tickets');

        $this->assertSame(8, count($ticketRows));

        foreach ($ticketRows as $row) {
            $this->assertSame('NULL', $row[3] ?? null);
            $this->assertSame('NULL', $row[4] ?? null);
        }

        $this->assertFalse(str_contains($this->seed, "'demo/qr/"));
        $this->assertFalse(str_contains($this->seed, "'demo/tickets/"));
    }

    public function testDemoTransactionIdentifiersRemainUnique(): void
    {
        $paymentReferences = array_map(
            fn (array $row): string => $this->literal($row[2] ?? ''),
            $this->insertRows('payments'),
        );
        $ticketNumbers = array_map(
            fn (array $row): string => $this->literal($row[1] ?? ''),
            $this->insertRows('tickets'),
        );

        $this->assertSame(count($paymentReferences), count(array_unique($paymentReferences)));
        $this->assertSame(count($ticketNumbers), count(array_unique($ticketNumbers)));
    }

    private function insertRows(string $table): array
    {
        $pattern = '/INSERT INTO ' . preg_quote($table, '/')
            . '\s*\([^;]+?\)\s*VALUES\s*(?<values>.*?)\s*ON DUPLICATE KEY UPDATE/s';

        if (preg_match($pattern, $this->seed, $match) !== 1) {
            throw new RuntimeException("Unable to parse {$table} seed rows.");
        }

        return array_map(fn (string $row): array => $this->splitValues($row), $this->tupleRows($match['values']));
    }

    private function tupleRows(string $values): array
    {
        $rows = [];
        $start = null;
        $depth = 0;
        $quoted = false;
        $length = strlen($values);

        for ($index = 0; $index < $length; $index++) {
            $character = $values[$index];

            if ($character === "'" && ($index === 0 || $values[$index - 1] !== '\\')) {
                $quoted = !$quoted;
                continue;
            }

            if ($quoted) {
                continue;
            }

            if ($character === '(') {
                if ($depth === 0) {
                    $start = $index + 1;
                }

                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $rows[] = substr($values, $start, $index - $start);
                    $start = null;
                }
            }
        }

        return $rows;
    }

    private function splitValues(string $row): array
    {
        $values = [];
        $start = 0;
        $depth = 0;
        $quoted = false;
        $length = strlen($row);

        for ($index = 0; $index < $length; $index++) {
            $character = $row[$index];

            if ($character === "'" && ($index === 0 || $row[$index - 1] !== '\\')) {
                $quoted = !$quoted;
                continue;
            }

            if ($quoted) {
                continue;
            }

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $values[] = trim(substr($row, $start, $index - $start));
                $start = $index + 1;
            }
        }

        $values[] = trim(substr($row, $start));

        return $values;
    }

    private function literal(string $value): string
    {
        return trim($value, "' \t\n\r\0\x0B");
    }
}
