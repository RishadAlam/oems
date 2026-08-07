<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\Validator;
use OEMS\Tests\Support\TestCase;

final class ValidatorTest extends TestCase
{
    public function testReturnsErrorsForInvalidRegistrationInput(): void
    {
        $errors = Validator::validate(
            [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role' => 'super-admin',
            ],
            [
                'name' => 'required|string|max:100',
                'email' => 'required|email|max:190',
                'password' => 'required|min:8|confirmed',
                'role' => 'required|in:participant,organizer',
            ],
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testAcceptsValidRegistrationInput(): void
    {
        $errors = Validator::validate(
            [
                'name' => 'Nafisa Rahman',
                'email' => 'nafisa@example.com',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role' => 'participant',
            ],
            [
                'name' => 'required|string|max:100',
                'email' => 'required|email|max:190',
                'password' => 'required|min:8|confirmed',
                'role' => 'required|in:participant,organizer',
            ],
        );

        $this->assertSame([], $errors);
    }

    public function testNullableFieldsSkipNonRequiredRulesWhenEmpty(): void
    {
        $errors = Validator::validate(
            ['phone' => ''],
            ['phone' => 'nullable|min:8|max:20'],
        );

        $this->assertSame([], $errors);
    }

    public function testRejectsNonIsoAndImpossibleDates(): void
    {
        foreach (['tomorrow', '2026-02-30', '2026-8-6'] as $value) {
            $errors = Validator::validate(
                ['date_of_birth' => $value],
                ['date_of_birth' => 'date'],
            );

            $this->assertArrayHasKey('date_of_birth', $errors);
        }
    }

    public function testAcceptsAnExactIsoCalendarDate(): void
    {
        $errors = Validator::validate(
            ['date_of_birth' => '2024-02-29'],
            ['date_of_birth' => 'date'],
        );

        $this->assertSame([], $errors);
    }

    public function testRejectsMalformedOrImpossibleLocalDatetimes(): void
    {
        foreach (['2026-08-07', '2026-02-30T09:00', '2026-08-07T9:00', '2026-08-07T09:00:00'] as $value) {
            $errors = Validator::validate(
                ['start_date' => $value],
                ['start_date' => 'datetime_local'],
            );

            $this->assertArrayHasKey('start_date', $errors);
        }
    }

    public function testEventDatetimeAndOrderingRulesRejectImpossibleSchedule(): void
    {
        $errors = Validator::validate(
            [
                'start_date' => '2026-08-07T18:00',
                'end_date' => '2026-08-07T17:59',
                'registration_deadline' => '2026-08-07T18:01',
            ],
            [
                'start_date' => 'required|datetime_local',
                'end_date' => 'required|datetime_local|after:start_date',
                'registration_deadline' => 'required|datetime_local|before_or_equal:start_date',
            ],
        );

        $this->assertArrayHasKey('end_date', $errors);
        $this->assertArrayHasKey('registration_deadline', $errors);
    }

    public function testAcceptsAnOrderedEventScheduleIncludingAnEqualDeadline(): void
    {
        $errors = Validator::validate(
            [
                'start_date' => '2026-08-07T18:00',
                'end_date' => '2026-08-07T20:00',
                'registration_deadline' => '2026-08-07T18:00',
            ],
            [
                'start_date' => 'required|datetime_local',
                'end_date' => 'required|datetime_local|after:start_date',
                'registration_deadline' => 'required|datetime_local|before_or_equal:start_date',
            ],
        );

        $this->assertSame([], $errors);
    }

    public function testUrlRuleAcceptsHttpUrlsAndRejectsUnsafeSchemes(): void
    {
        $this->assertSame([], Validator::validate(
            ['map_url' => 'https://maps.example.test/place?id=42'],
            ['map_url' => 'url'],
        ));

        $errors = Validator::validate(
            ['map_url' => 'javascript:alert(1)'],
            ['map_url' => 'url'],
        );

        $this->assertArrayHasKey('map_url', $errors);
    }

    public function testNumericRangeRulesIncludeTheirBoundaryValues(): void
    {
        foreach ([1, 100000] as $capacity) {
            $this->assertSame([], Validator::validate(
                ['capacity' => $capacity],
                ['capacity' => 'numeric|min_value:1|max_value:100000'],
            ));
        }

        foreach ([0, 100001] as $capacity) {
            $errors = Validator::validate(
                ['capacity' => $capacity],
                ['capacity' => 'numeric|min_value:1|max_value:100000'],
            );

            $this->assertArrayHasKey('capacity', $errors);
        }
    }
}
