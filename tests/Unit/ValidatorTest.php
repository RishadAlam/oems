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
}

