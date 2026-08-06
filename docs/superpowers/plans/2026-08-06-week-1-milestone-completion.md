# Week 1 Milestone Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the OEMS Week 1 account milestone with editable profiles and real verification and password-reset email delivery through configured SMTP.

**Architecture:** Add a focused profile repository and controller beside the existing authentication subsystem. Add an account-mail service behind transport and email-log interfaces so message composition and delivery outcomes can be tested without SMTP, while PHPMailer provides the production transport.

**Tech Stack:** PHP 8.2, custom OEMS MVC core, PDO MySQL, PHPMailer, Tailwind CSS 4, custom PHP test runner, Mailtrap SMTP

## Global Constraints

- Keep the current routes, role slugs, dashboard navigation labels, Tailwind tokens, dark theme, and MVC conventions unless this plan names a change.
- Store SMTP credentials only in the gitignored `.env`; commit placeholders in `.env.example`.
- Use Mailtrap port 2525 with STARTTLS in the local environment.
- Never log raw verification or password-reset tokens.
- Require authentication and CSRF validation for every profile write.
- Use the authenticated user ID for profile access; never accept a user ID from profile form input.
- Keep development verification and reset links only when `APP_DEBUG=true`.
- Preserve unrelated untracked files and stage only files named by the current task.
- Run tests before implementation to prove each new behavior fails, then run them again after the minimal implementation.
- Create and push one Git commit after each completed task.

---

## File Structure

- `app/Contracts/ProfileRepositoryInterface.php`: profile read and transactional update contract.
- `app/Repositories/ProfileRepository.php`: joined user/profile persistence using PDO.
- `app/Controllers/ProfileController.php`: authenticated profile display, validation, and update flow.
- `app/Views/profile/edit.php`: accessible responsive account settings form.
- `app/Contracts/MailTransportInterface.php`: delivery boundary for account messages.
- `app/Contracts/EmailLogRepositoryInterface.php`: persistence boundary for delivery outcomes.
- `app/Mail/EmailMessage.php`: immutable message data passed to transports.
- `app/Mail/PhpMailerTransport.php`: SMTP delivery through PHPMailer.
- `app/Repositories/EmailLogRepository.php`: records sent and failed attempts.
- `app/Services/AccountMailer.php`: composes verification and reset messages, catches delivery failures, and logs outcomes.
- `tests/Support/FakeProfileRepository.php`: controller-focused in-memory profile double.
- `tests/Support/FakeMailTransport.php`: deterministic mail transport double.
- `tests/Support/FakeEmailLogRepository.php`: captures email-log writes.
- `tests/Unit/ProfileRepositoryTest.php`: SQLite-backed persistence tests.
- `tests/Unit/ProfileControllerTest.php`: validation and ownership tests.
- `tests/Unit/AccountMailerTest.php`: composition and sent/failed logging tests.

### Task 1: Profile persistence and account settings UI

**Files:**
- Create: `app/Contracts/ProfileRepositoryInterface.php`
- Create: `app/Repositories/ProfileRepository.php`
- Create: `app/Controllers/ProfileController.php`
- Create: `app/Views/profile/edit.php`
- Create: `tests/Support/FakeProfileRepository.php`
- Create: `tests/Unit/ProfileRepositoryTest.php`
- Create: `tests/Unit/ProfileControllerTest.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`

**Interfaces:**
- Produces: `ProfileRepositoryInterface::findForUser(int $userId): ?array`
- Produces: `ProfileRepositoryInterface::updateForUser(int $userId, array $attributes): void`
- Produces: authenticated `GET /profile` and CSRF-protected `POST /profile`

- [ ] **Step 1: Write failing repository and controller tests**

Create SQLite test tables for `roles`, `users`, and `profiles`, then assert the repository returns the joined role data and updates only the requested user in one transaction. Add controller tests that assert missing names are rejected and that submitted user IDs are ignored.

```php
$profile = $repository->findForUser(7);
$this->assertSame('participant', $profile['role_slug']);

$repository->updateForUser(7, [
    'name' => 'Nusrat Jahan',
    'phone' => '+8801712345678',
    'bio' => 'Community event volunteer.',
    'date_of_birth' => '1997-04-13',
    'gender' => 'female',
    'address_line' => 'Dhanmondi 8A',
    'city' => 'Dhaka',
    'country' => 'Bangladesh',
    'postal_code' => '1209',
    'website' => 'https://example.test/nusrat',
    'locale' => 'en',
    'timezone' => 'Asia/Dhaka',
]);

$updated = $repository->findForUser(7);
$this->assertSame('Nusrat Jahan', $updated['name']);
$this->assertSame('Dhaka', $updated['city']);
```

- [ ] **Step 2: Run the new profile tests and verify failure**

Run: `rtk php tests/run.php Profile`

Expected: FAIL because the profile contract, repository, and controller do not exist.

- [ ] **Step 3: Implement the profile repository contract and transaction**

Define the contract signatures above. Implement `findForUser()` with an inner join from `users` to `roles` and a left join to `profiles`, scoped by `users.id` and `users.deleted_at IS NULL`. Implement `updateForUser()` with a transaction containing these statements:

```sql
UPDATE users
SET name = :name, phone = :phone, updated_at = CURRENT_TIMESTAMP
WHERE id = :id AND deleted_at IS NULL
```

```sql
UPDATE profiles
SET bio = :bio,
    date_of_birth = :date_of_birth,
    gender = :gender,
    address_line = :address_line,
    city = :city,
    country = :country,
    postal_code = :postal_code,
    website = :website,
    locale = :locale,
    timezone = :timezone,
    updated_at = CURRENT_TIMESTAMP
WHERE user_id = :user_id
```

At the start of the transaction, query the joined `users` and `profiles` rows for the authenticated ID and throw `RuntimeException('Profile not found.')` when they do not exist. Do not use update row counts as an existence check because saving unchanged values may legitimately affect zero rows.

- [ ] **Step 4: Implement routes, controller validation, and ownership**

Register the repository binding and these routes:

```php
$router->get('/profile', [ProfileController::class, 'edit'], ['auth'], 'profile.edit');
$router->post('/profile', [ProfileController::class, 'update'], ['auth', 'csrf'], 'profile.update');
```

Validate the exact fields with these rules:

```php
[
    'name' => 'required|string|max:100',
    'phone' => 'nullable|string|max:30',
    'bio' => 'nullable|string|max:2000',
    'date_of_birth' => 'nullable|date',
    'gender' => 'nullable|in:female,male,non-binary,prefer-not-to-say',
    'address_line' => 'nullable|string|max:190',
    'city' => 'nullable|string|max:100',
    'country' => 'nullable|string|max:100',
    'postal_code' => 'nullable|string|max:30',
    'website' => 'nullable|string|max:255',
    'locale' => 'required|in:en,bn',
    'timezone' => 'required|in:Asia/Dhaka,UTC',
]
```

Normalize trimmed empty optional values to `null`, obtain the ID only from `$this->auth->id()`, and redirect to `/profile` with `Profile updated successfully.` after persistence.

- [ ] **Step 5: Build the preserve-mode responsive profile UI**

Add Profile between Overview and Security in the existing sidebar. Derive the active state from the current request path so only the matching navigation item is active.

Build one `dashboard-panel` form split by headings into account details, personal details, address, and regional preferences. Use labels above controls, helper and error text below controls, read-only email and role fields, `aria-invalid` and `aria-describedby` on invalid controls, two columns only for related short fields at `sm` and above, and one full-width column on mobile. Do not add animation beyond existing hover and focus feedback.

- [ ] **Step 6: Run profile tests and frontend build**

Run: `rtk php tests/run.php Profile`

Expected: all profile tests PASS.

Run: `rtk npm run build:css`

Expected: Tailwind writes the production stylesheet without errors.

- [ ] **Step 7: Run the full regression suite and commit**

Run: `rtk composer test`

Run: `rtk composer check:syntax`

Expected: both commands exit 0.

Commit only Task 1 files with message `feat: add profile management` and push `main`.

### Task 2: SMTP transport, account messages, and delivery logging

**Files:**
- Create: `app/Contracts/MailTransportInterface.php`
- Create: `app/Contracts/EmailLogRepositoryInterface.php`
- Create: `app/Mail/EmailMessage.php`
- Create: `app/Mail/PhpMailerTransport.php`
- Create: `app/Repositories/EmailLogRepository.php`
- Create: `app/Services/AccountMailer.php`
- Create: `tests/Support/FakeMailTransport.php`
- Create: `tests/Support/FakeEmailLogRepository.php`
- Create: `tests/Unit/AccountMailerTest.php`
- Modify: `app/Controllers/AuthController.php`
- Modify: `app/Services/AuthService.php`
- Modify: `bootstrap/app.php`
- Modify: `config/app.php`
- Modify: `.env.example`
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `tests/Unit/AuthServiceTest.php`

**Interfaces:**
- Produces: `MailTransportInterface::send(EmailMessage $message): ?string`
- Produces: `EmailLogRepositoryInterface::record(array $attributes): void`
- Produces: `AccountMailer::sendVerification(int $userId, string $recipient, string $name, string $token): bool`
- Produces: `AccountMailer::sendPasswordReset(int $userId, string $recipient, string $name, string $token): bool`
- Consumes: `AuthService::register()` result keys `user_id` and `verification_token`
- Extends: `AuthService::requestPasswordReset()` result with `user_id`, `name`, and `email` when a reset token exists

- [ ] **Step 1: Install PHPMailer**

Run: `rtk composer require phpmailer/phpmailer:^7.0`

Expected: Composer updates `composer.json` and `composer.lock`, then autoload generation succeeds. If the installed PHP or dependency constraints require PHPMailer 6, use the newest Composer-resolved compatible stable release and record that exact version in the commit.

- [ ] **Step 2: Write failing account-mail tests**

Test the exact message subjects, absolute URLs, plain-text alternatives, recipient name, provider ID logging, and sanitized failure logging through fakes.

```php
$sent = $mailer->sendVerification(9, 'maliha@example.test', 'Maliha Rahman', str_repeat('a', 64));

$this->assertTrue($sent);
$this->assertSame('Verify your OEMS email', $transport->messages[0]->subject);
$this->assertTrue(str_contains($transport->messages[0]->htmlBody, 'http://localhost:8000/verify-email/'));
$this->assertSame('sent', $logs->records[0]['status']);
$this->assertSame('<mailtrap-message-id>', $logs->records[0]['provider_message_id']);
```

- [ ] **Step 3: Run account-mail tests and verify failure**

Run: `rtk php tests/run.php AccountMailer`

Expected: FAIL because the mail message, service, transport, and log repository do not exist.

- [ ] **Step 4: Implement message, transport, and logging boundaries**

Create immutable `EmailMessage` public properties for recipient email, recipient name, subject, HTML body, and text body. Configure PHPMailer with SMTP host, integer port, authentication only when a username is present, STARTTLS for `tls`, SMTPS for `ssl`, configured sender, HTML mode, and a 10-second timeout. Return `getLastMessageID()` after `send()`.

Implement `EmailLogRepository::record()` as one insert into `email_logs` with `user_id`, `recipient_email`, `template`, `subject`, `status`, `provider_message_id`, `error_message`, and `sent_at`. Use `CURRENT_TIMESTAMP` only for sent messages.

- [ ] **Step 5: Implement AccountMailer composition and failure isolation**

Build URLs with `rtrim($appUrl, '/')`, `rawurlencode($token)`, and the route paths. Use these subjects:

```text
Verify your OEMS email
Reset your OEMS password
```

Mention the one-hour expiry only in the reset message. Catch every `Throwable`, replace line breaks with spaces, remove control characters, truncate the stored message to 500 characters, write a failed email log, and return `false`. Never place the raw token in any log attribute.

- [ ] **Step 6: Connect registration and reset flows**

Inject `AccountMailer` into `AuthController`. After successful registration call `sendVerification()` with the returned user ID and token. After a reset token is created call `sendPasswordReset()` with the returned user data. Keep the existing generic browser responses and debug links.

Return this shape for a reset request without an eligible user:

```php
['success' => true, 'reset_token' => null, 'user_id' => null, 'name' => null, 'email' => null]
```

- [ ] **Step 7: Add non-secret configuration**

Add a `mail` array to `config/app.php` using `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME`. Add placeholder values to `.env.example`; do not add real credentials.

- [ ] **Step 8: Run focused and full verification**

Run: `rtk php tests/run.php AccountMailer`

Run: `rtk php tests/run.php AuthService`

Run: `rtk composer test`

Run: `rtk composer check:syntax`

Run: `rtk composer validate --strict`

Expected: all commands exit 0.

- [ ] **Step 9: Commit**

Commit only Task 2 files with message `feat: send account emails through smtp` and push `main`.

### Task 3: Local Mailtrap configuration and end-to-end acceptance

**Files:**
- Modify locally only: `.env`
- Modify: `README.md`
- Modify if browser QA finds a defect: files already listed in Task 1 or Task 2

**Interfaces:**
- Consumes: local `MAIL_*` environment values
- Verifies: registration, verification, login, profile update, role dashboard access, password reset, and logout

- [ ] **Step 1: Configure the gitignored local environment**

Write the supplied Mailtrap host and credentials only to `.env`, using port `2525`, `MAIL_ENCRYPTION=tls`, `MAIL_FROM_ADDRESS=no-reply@oems.local`, and `MAIL_FROM_NAME=OEMS`. Do not print the file or stage it.

- [ ] **Step 2: Confirm SMTP with a dedicated development message**

Run a small PHP command that boots the application container and calls `MailTransportInterface::send()` for a clearly labeled `OEMS SMTP configuration test` message addressed to the Mailtrap sandbox. Do not include credentials or tokens in command output.

Expected: PHPMailer returns successfully and provides a message ID.

- [ ] **Step 3: Exercise participant acceptance flow in the browser**

At desktop and mobile widths:

1. Register a new participant with a unique Mailtrap-safe address.
2. Confirm the generic registration response and debug verification link.
3. Open the verification link and confirm single-use behavior.
4. Log in and confirm participant dashboard access.
5. Open Profile, update every editable group, save, refresh, and confirm persistence.
6. Use keyboard navigation through the form and confirm visible focus.
7. Toggle light and dark modes and inspect contrast and layout.
8. Log out.

- [ ] **Step 4: Exercise organizer and password-reset acceptance**

Register and verify an organizer, log in, and confirm the organizer dashboard. Request a password reset, open the development reset link, set a new password, and log in with it. Confirm the reset request never reveals whether the address exists.

- [ ] **Step 5: Inspect persisted outcomes without exposing secrets**

Query only counts and non-sensitive columns from `email_logs` to confirm sent verification and reset records. Confirm new registration records exist in `users` and `profiles`, and organizer registration also created an `organizers` row. Do not select token hashes, SMTP credentials, or raw tokens.

- [ ] **Step 6: Update setup documentation**

Document Composer installation, CSS build, `php -S 127.0.0.1:8000 -t public public/router.php`, the `MAIL_*` placeholders, Mailtrap sandbox behavior, and the Week 1 acceptance flow. State that `pnpm dev` is not required; `npm run watch:css` is optional during CSS editing.

- [ ] **Step 7: Run final verification and commit**

Run: `rtk composer test`

Run: `rtk composer check:syntax`

Run: `rtk composer validate --strict`

Run: `rtk npm run build:css`

Run: `rtk git diff --check`

Expected: every command exits 0 and browser acceptance has no open defects.

Commit only tracked integration or documentation files with message `docs: complete week 1 account setup` and push `main`. Never stage `.env` or unrelated workspace files.
