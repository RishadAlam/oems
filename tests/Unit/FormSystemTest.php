<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;

final class FormSystemTest extends TestCase
{
    public function testEntryFormProvidesProgressiveValidationAndServerErrorNavigation(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('auth/login', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => ['email' => ['Enter a valid email address.']],
            'old' => ['email' => 'invalid-address'],
            'pageTitle' => 'Sign in',
        ], 'auth');

        $this->assertTrue(str_contains($html, 'data-form-kind="entry"'));
        $this->assertFalse(str_contains($html, ' novalidate'));
        $this->assertTrue(str_contains($html, 'data-form-error-summary'));
        $this->assertTrue(str_contains($html, 'href="#email"'));
        $this->assertTrue(str_contains($html, 'aria-invalid="true"'));
        $this->assertTrue(str_contains($html, 'aria-describedby="email-help email-error"'));
    }

    public function testEntryFormExposesSpecificSubmissionProgressCopy(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('auth/login', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => [],
            'old' => [],
            'pageTitle' => 'Sign in',
        ], 'auth');

        $this->assertTrue(str_contains($html, 'data-submit-label="Signing in…"'));
    }
}
