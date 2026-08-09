<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class DemoSeedIntegrityTest extends TestCase
{
    private string $seed;

    private string $baseSeed;

    protected function setUp(): void
    {
        $seed = file_get_contents(base_path('database/demo_seed.sql'));

        if (!is_string($seed)) {
            throw new RuntimeException('Unable to read database/demo_seed.sql.');
        }

        $this->seed = $seed;

        $baseSeed = file_get_contents(base_path('database/seed.sql'));
        if (!is_string($baseSeed)) {
            throw new RuntimeException('Unable to read database/seed.sql.');
        }

        $this->baseSeed = $baseSeed;
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
            $status = $this->literal($row[18] ?? '');

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
        $this->assertSame((int) $techEvent[13] - $confirmed, (int) $techEvent[14]);
    }

    public function testEveryDemoEventAvailableSeatCountMatchesActiveRegistrations(): void
    {
        $activeRegistrations = [];

        foreach ($this->insertRows('registrations') as $row) {
            if (in_array($this->literal($row[3] ?? ''), ['pending', 'confirmed'], true)) {
                $activeRegistrations[$row[0]] = ($activeRegistrations[$row[0]] ?? 0) + 1;
            }
        }

        foreach ($this->insertRows('events') as $row) {
            $eventVariable = $this->eventVariableForSlug($this->literal($row[4] ?? ''));
            $expectedAvailable = (int) ($row[13] ?? 0) - ($activeRegistrations[$eventVariable] ?? 0);

            $this->assertSame(
                $expectedAvailable,
                (int) ($row[14] ?? 0),
                'Demo event seat counts must equal capacity minus active registrations.',
            );
        }
    }

    public function testManualPaymentDemoConfigurationIsRepeatableActiveAndClearlyFictional(): void
    {
        $this->assertTrue(str_contains($this->seed, "INSERT INTO payment_methods"));
        $this->assertTrue(str_contains($this->seed, "'manual'"));
        $this->assertTrue(str_contains($this->seed, 'ON DUPLICATE KEY UPDATE'));
        $this->assertTrue(str_contains($this->seed, "'DEMO ONLY"));
        $this->assertTrue(str_contains($this->seed, 'is_active = TRUE'));
        $this->assertFalse(str_contains($this->seed, 'gateway verified'));
        $this->assertFalse(str_contains($this->seed, 'automatic payment'));
    }

    public function testBaseSeedKeepsDemoOnlyManualPaymentInactive(): void
    {
        $this->assertTrue(str_contains($this->baseSeed, "'Manual payment'"));
        $this->assertTrue(str_contains($this->baseSeed, "'DEMO ONLY"));
        $this->assertTrue(str_contains($this->baseSeed, "        FALSE,\n        20"));
    }

    public function testDemoSeedReconcilesAvailableSeatsAfterRegistrationUpserts(): void
    {
        $registrationUpsert = strpos($this->seed, 'INSERT INTO registrations');
        $reconciliation = strpos($this->seed, 'UPDATE events AS demo_event');

        $this->assertTrue(is_int($registrationUpsert));
        $this->assertTrue(is_int($reconciliation));
        $this->assertTrue($reconciliation > $registrationUpsert);
        $this->assertTrue(str_contains($this->seed, 'demo_registration.event_id = demo_event.id'));
        $this->assertTrue(str_contains($this->seed, "demo_registration.status IN ('pending', 'confirmed')"));
        $this->assertTrue(str_contains($this->seed, 'GREATEST('));
    }

    public function testDemoReviewsBelongToConfirmedRegistrationsForCompletedEvents(): void
    {
        $completedEvents = [];
        foreach ($this->insertRows('events') as $row) {
            if ($this->literal($row[18] ?? '') === 'completed') {
                $completedEvents[$this->eventVariableForSlug($this->literal($row[4] ?? ''))] = true;
            }
        }

        $eligible = [];
        foreach ($this->insertRows('registrations') as $row) {
            if ($this->literal($row[3] ?? '') === 'confirmed' && isset($completedEvents[$row[0] ?? ''])) {
                $eligible[($row[0] ?? '') . ':' . ($row[1] ?? '')] = true;
            }
        }

        foreach ($this->insertRows('reviews') as $row) {
            $this->assertTrue(
                isset($eligible[($row[0] ?? '') . ':' . ($row[1] ?? '')]),
                'Demo reviews must belong to confirmed attendees of completed events.',
            );
        }
    }

    public function testPaidConfirmedRegistrationsHavePaidPaymentsAndTickets(): void
    {
        $payments = [];
        foreach ($this->insertRows('payments') as $row) {
            $payments[$this->selectedIdentifier($row[0] ?? '', 'registration_number')] = $row;
        }

        $tickets = [];
        foreach ($this->insertRows('tickets') as $row) {
            $tickets[$this->selectedIdentifier($row[0] ?? '', 'registration_number')] = $row;
        }

        foreach ($this->insertRows('registrations') as $registration) {
            $number = $this->literal($registration[2] ?? '');
            $confirmed = $this->literal($registration[3] ?? '') === 'confirmed';
            $paid = (float) ($registration[4] ?? 0) > 0;
            if (!$confirmed || !$paid) {
                continue;
            }

            $this->assertTrue(isset($payments[$number]), "{$number} must have a payment.");
            $this->assertSame('paid', $this->literal($payments[$number][5] ?? ''));
            $this->assertSame((float) ($registration[4] ?? 0), (float) ($payments[$number][3] ?? -1));
            $this->assertTrue(isset($tickets[$number]), "{$number} must have a ticket.");
            $this->assertTrue(in_array($this->literal($tickets[$number][5] ?? ''), ['valid', 'used'], true));
        }
    }

    public function testPaidPendingRegistrationsHavePendingPaymentsAndNoTickets(): void
    {
        $payments = [];
        foreach ($this->insertRows('payments') as $row) {
            $payments[$this->selectedIdentifier($row[0] ?? '', 'registration_number')] = $row;
        }

        $tickets = [];
        foreach ($this->insertRows('tickets') as $row) {
            $tickets[$this->selectedIdentifier($row[0] ?? '', 'registration_number')] = $row;
        }

        foreach ($this->insertRows('registrations') as $registration) {
            $number = $this->literal($registration[2] ?? '');
            $pending = $this->literal($registration[3] ?? '') === 'pending';
            $paid = (float) ($registration[4] ?? 0) > 0;
            if (!$pending || !$paid) {
                continue;
            }

            $this->assertTrue(isset($payments[$number]), "{$number} must have a pending payment.");
            $this->assertSame('pending', $this->literal($payments[$number][5] ?? ''));
            $this->assertSame((float) ($registration[4] ?? 0), (float) ($payments[$number][3] ?? -1));
            $this->assertFalse(isset($tickets[$number]), "{$number} must not have an issued ticket.");
        }
    }

    public function testEveryUsedDemoTicketHasMatchingAttendance(): void
    {
        $attendance = [];
        foreach ($this->insertRows('attendance') as $row) {
            $registrationNumber = $this->selectedIdentifier($row[0] ?? '', 'registration_number');
            $ticketNumber = $this->selectedIdentifier($row[1] ?? '', 'ticket_number');
            $attendance[$registrationNumber . ':' . $ticketNumber] = $row;
        }

        foreach ($this->insertRows('tickets') as $ticket) {
            if ($this->literal($ticket[5] ?? '') !== 'used') {
                continue;
            }

            $registrationNumber = $this->selectedIdentifier($ticket[0] ?? '', 'registration_number');
            $ticketNumber = $this->literal($ticket[1] ?? '');
            $this->assertTrue(
                isset($attendance[$registrationNumber . ':' . $ticketNumber]),
                "Used ticket {$ticketNumber} must have matching attendance.",
            );
            $attendanceRow = $attendance[$registrationNumber . ':' . $ticketNumber] ?? [];
            $this->assertSame('present', $this->literal($attendanceRow[3] ?? ''));
            $this->assertTrue(str_starts_with((string) ($attendanceRow[2] ?? ''), '@'));
        }
    }

    public function testFutureTicketRowsDoNotClaimGeneratedMediaFiles(): void
    {
        $ticketRows = $this->insertRows('tickets');

        $this->assertSame(11, count($ticketRows));

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

    public function testDemoEventsSetPublicLocationVisibilityAndBoundedArrivalNotes(): void
    {
        foreach ($this->insertRows('events') as $row) {
            $this->assertSame('public', $this->literal($row[7] ?? ''));
            $this->assertTrue(strlen($this->literal($row[8] ?? '')) <= 500);
        }
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

    private function selectedIdentifier(string $expression, string $column): string
    {
        $matched = preg_match(
            "/SELECT id FROM [a-z_]+ WHERE " . preg_quote($column, '/') . " = '([^']+)'/",
            $expression,
            $matches,
        );

        if ($matched !== 1) {
            throw new RuntimeException("Unable to parse selected {$column}.");
        }

        return $matches[1];
    }

    private function eventVariableForSlug(string $slug): string
    {
        return match ($slug) {
            'dhaka-tech-summit-2026' => '@tech_event_id',
            'startup-growth-forum-2026' => '@startup_event_id',
            'community-arts-night-2026' => '@arts_event_id',
            'future-skills-workshop-2026' => '@skills_event_id',
            'wellness-weekend-dhaka-2026' => '@wellness_event_id',
            'product-leaders-meetup-july-2026' => '@product_event_id',
            default => '@unknown_event_id',
        };
    }
}
