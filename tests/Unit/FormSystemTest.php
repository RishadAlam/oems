<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;

final class FormSystemTest extends TestCase
{
    public function testSharedFieldAttributesKeepHelpAndServerErrorRelationshipsTogether(): void
    {
        $attributes = function_exists('form_control_attributes')
            ? form_control_attributes(
                ['email' => ['Enter a valid email address.']],
                'email',
                ['email-help'],
            )
            : '';

        $this->assertSame(
            ' aria-invalid="true" aria-describedby="email-help email-error"',
            $attributes,
        );
    }

    public function testErrorEntriesMapHumanLabelsToRealControlTargets(): void
    {
        $entries = function_exists('form_error_entries')
            ? form_error_entries(
                ['password_confirmation' => ['Passwords do not match.']],
                ['password_confirmation' => 'password_confirmation'],
                ['password_confirmation' => 'Confirm password'],
            )
            : [];

        $this->assertSame([
            [
                'field' => 'password_confirmation',
                'target' => 'password_confirmation',
                'label' => 'Confirm password',
                'message' => 'Passwords do not match.',
            ],
        ], $entries);
    }

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

    public function testRegistrationFormMatchesAccountValidationBoundaries(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('auth/register', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => [
                'name' => ['Name is required.'],
                'email' => ['Email is required.'],
                'password_confirmation' => ['Passwords do not match.'],
                'terms' => ['Accept the terms to continue.'],
            ],
            'old' => [],
            'pageTitle' => 'Create account',
        ], 'auth');

        $this->assertTrue(str_contains($html, 'data-form-kind="entry"'));
        $this->assertFalse(str_contains($html, ' novalidate'));
        $this->assertTrue(str_contains($html, 'name="name" type="text"') && str_contains($html, 'minlength="2" maxlength="100"'));
        $this->assertTrue(str_contains($html, 'name="email" type="email"') && str_contains($html, 'maxlength="190"'));
        $this->assertTrue(str_contains($html, 'name="password" type="password"') && str_contains($html, 'minlength="8" maxlength="128"'));
        $this->assertTrue(str_contains($html, 'data-match-field="password"'));
        $this->assertTrue(str_contains($html, 'name="role" value="participant" required'));
        $this->assertTrue(str_contains($html, 'href="#password_confirmation"'));
        $this->assertTrue(str_contains($html, 'href="#terms"'));
        $this->assertTrue(str_contains($html, 'data-submit-label="Creating account…"'));
    }

    public function testPasswordRecoveryFormsUseTheSameValidatedPasswordContract(): void
    {
        $view = new View(base_path('app/Views'));
        $shared = [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => ['password_confirmation' => ['Passwords do not match.']],
            'old' => [],
            'pageTitle' => 'Password',
        ];
        $forgot = $view->render('auth/forgot-password', $shared + [
            'errors' => ['email' => ['Enter a valid email address.']],
        ], 'auth');
        $reset = $view->render('auth/reset-password', $shared + ['token' => 'safe-token'], 'auth');
        $change = $view->render('auth/change-password', $shared + [
            'currentUser' => [
                'id' => 7,
                'name' => 'Participant',
                'email' => 'participant@example.test',
                'role_name' => 'Participant',
                'role_slug' => 'participant',
            ],
        ], 'dashboard');

        foreach ([$forgot, $reset, $change] as $html) {
            $this->assertTrue(str_contains($html, 'data-form-kind="entry"'));
            $this->assertFalse(str_contains($html, ' novalidate'));
            $this->assertTrue(str_contains($html, 'data-form-error-summary'));
        }
        $this->assertTrue(str_contains($forgot, 'name="email" type="email"') && str_contains($forgot, 'maxlength="190"'));
        foreach ([$reset, $change] as $html) {
            $this->assertTrue(str_contains($html, 'minlength="8" maxlength="128"'));
            $this->assertTrue(str_contains($html, 'data-match-field="password"'));
        }
    }

    public function testContactAndNewsletterFormsMirrorTheirServerLengthRules(): void
    {
        $view = new View(base_path('app/Views'));
        $data = [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => ['message' => ['Enter a message using 10 to 4000 characters.']],
            'old' => [],
            'pageTitle' => 'Contact',
            'page' => ['title' => 'Contact OEMS'],
            'copy' => '',
        ];
        $contact = $view->render('pages/contact', $data, 'public');

        $this->assertTrue(str_contains($contact, 'action="/contact/submit" method="post" data-form-kind="entry"'));
        $this->assertTrue(preg_match('/<input\b(?=[^>]*\bname="name")(?=[^>]*\btype="text")(?=[^>]*\bminlength="2")(?=[^>]*\bmaxlength="100")[^>]*>/s', $contact) === 1);
        $this->assertTrue(preg_match('/<input\b(?=[^>]*\bname="subject")(?=[^>]*\btype="text")(?=[^>]*\bminlength="3")(?=[^>]*\bmaxlength="180")[^>]*>/s', $contact) === 1);
        $this->assertTrue(str_contains($contact, 'name="message" minlength="10" maxlength="4000"'));
        $this->assertTrue(str_contains($contact, 'data-submit-label="Sending message…"'));

        $newsletter = $view->render('errors/404', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => null,
            'flash' => [],
            'errors' => ['email' => ['Enter a valid email address.']],
            'old' => ['newsletter_email' => 'invalid'],
            'pageTitle' => 'Not found',
        ], 'public');
        $this->assertTrue(str_contains($newsletter, 'action="/newsletter/subscribe" method="post" data-form-kind="entry"'));
        $this->assertFalse(str_contains($newsletter, 'action="/newsletter/subscribe" method="post" novalidate'));
        $this->assertTrue(str_contains($newsletter, 'data-submit-label="Requesting confirmation…"'));
    }

    public function testEveryAdministratorFormDeclaresItsInteractionPattern(): void
    {
        $directory = base_path('app/Views/admin');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $forms = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname()) ?: '';
            preg_match_all('/<form\b(?:(?:<\?[\s\S]*?\?>)|[^>])*?>/i', $source, $matches);
            foreach ($matches[0] as $form) {
                $forms[] = [$file->getPathname(), $form];
            }
        }

        $this->assertTrue(count($forms) >= 35);
        foreach ($forms as [$path, $form]) {
            $this->assertTrue(
                str_contains($form, 'data-form-kind='),
                $path . ' contains an unclassified form.',
            );
            $this->assertFalse(str_contains($form, 'novalidate'));
        }
    }

    public function testAdministratorEditorsMirrorTheirServerBoundariesAndProgressStates(): void
    {
        $blog = file_get_contents(base_path('app/Views/admin/blog/form.php')) ?: '';
        $banner = file_get_contents(base_path('app/Views/admin/cms/banner-form.php')) ?: '';
        $campaign = file_get_contents(base_path('app/Views/admin/newsletter/campaign-form.php')) ?: '';
        $settings = file_get_contents(base_path('app/Views/admin/settings/edit.php')) ?: '';
        $contact = file_get_contents(base_path('app/Views/admin/contact/show.php')) ?: '';

        $this->assertTrue(str_contains($blog, 'data-form-kind="entry"'));
        $this->assertTrue(str_contains($blog, "['title','Title','text',3,180"));
        $this->assertTrue(str_contains($blog, "['body','Body',40,50000"));
        $this->assertTrue(str_contains($blog, 'data-max-bytes="5242880"'));
        $this->assertTrue(str_contains($banner, 'data-after-field="starts_at"'));
        $this->assertTrue(str_contains($banner, 'data-max-bytes="5242880"'));
        $this->assertTrue(str_contains($campaign, 'name="subject" minlength="3" maxlength="180"'));
        $this->assertTrue(str_contains($campaign, 'name="message" rows="10" minlength="10" maxlength="4000"'));
        $this->assertTrue(str_contains($settings, 'data-submit-label="Saving settings…"'));
        $this->assertTrue(str_contains($contact, 'name="reply" rows="7" minlength="2" maxlength="4000"'));
        $this->assertTrue(str_contains($contact, 'data-submit-label="Queueing reply…"'));
    }

    public function testEveryRenderedFormUsesTheSharedInteractionAndSecurityContract(): void
    {
        $directory = base_path('app/Views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $forms = [];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname()) ?: '';
            preg_match_all(
                '/<form\b(?:(?:<\?[\s\S]*?\?>)|[^>])*?>[\s\S]*?<\/form>/i',
                $source,
                $matches,
            );
            foreach ($matches[0] as $form) {
                preg_match('/^<form\b(?:(?:<\?[\s\S]*?\?>)|[^>])*?>/i', $form, $openingTag);
                $forms[] = [$file->getPathname(), $openingTag[0] ?? '', $form];
            }
        }

        $this->assertSame(86, count($forms));
        foreach ($forms as [$path, $openingTag, $form]) {
            $this->assertTrue(
                str_contains($openingTag, 'data-form-kind='),
                $path . ' contains an unclassified form.',
            );
            $this->assertFalse(str_contains($openingTag, 'novalidate'));

            if (!str_contains(strtolower($openingTag), 'method="post"')) {
                continue;
            }

            $this->assertTrue(
                str_contains($form, 'name="_token"'),
                $path . ' contains a POST form without a CSRF token.',
            );

            if (!str_contains($openingTag, 'data-form-kind="special"')) {
                $this->assertTrue(
                    str_contains($form, 'data-submit-label='),
                    $path . ' contains a state-changing form without progress feedback.',
                );
            }
        }
    }
}
