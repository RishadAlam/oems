# OEMS Remaining Capabilities Completion Implementation Plan

> **Superseded:** This Bangladesh-oriented plan is retained only as project history. Do not execute it. The authoritative replacement is [OEMS International Marketplace Completion Plan](./2026-08-10-oems-international-marketplace-completion.md), based on the [international marketplace design](../specs/2026-08-10-oems-international-marketplace-design.md).

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close every genuine gap identified after the four-week OEMS roadmap: indexed popularity search, automatic image optimization, database-enforced permissions, complete English/Bangla localization, hosted online payments and refunds, opted-in SMS and browser push, Google Calendar OAuth sync, encrypted administrator-managed SMTP overrides, QR round-trip verification, welcome mail, shared Redis operations, and automated PSR-12 enforcement.

**Architecture:** Extend the existing controller-service-repository structure without replacing working modules. Each external integration sits behind a narrow contract, stores credentials only in environment variables or encrypted database values, uses an outbox or callback boundary, and keeps the current manual/offline path available. Every slice owns its migration, tests, documentation, review, commit, and rollback story; the final gate proves all slices together.

**Tech Stack:** PHP 8.2+, raw PHP OOP MVC, PDO with native MySQL prepares, MySQL 8 FULLTEXT, Tailwind CSS 4, vanilla JavaScript, GD WebP, SSLCOMMERZ hosted checkout, Twilio PHP SDK 8.11, Minishlink Web Push 11, Google API PHP Client 2.19, Redis 7 through Predis, ZXing JavaScript 0.23 for test-only QR decoding, PHP_CodeSniffer 3.13 with PSR-12 rules.

## Global Constraints

- Preserve the custom raw-PHP MVC architecture, existing routes, database identifiers, role slugs, lifecycle states, light/dark themes, and public URL compatibility.
- Use TDD for every behavior: observe RED, implement the minimum GREEN change, refactor, run focused tests, obtain a Critical/Important review, then commit.
- Every browser-initiated state-changing HTTP route is POST, CSRF-protected, role/permission-scoped, scalar-bounded, rate-limited where abuse is possible, and returns 404 for hidden foreign resources. Provider callbacks are sessionless POST routes that require provider validation/signature checks, replay protection, exact database reconciliation, and generic responses instead of CSRF.
- MySQL production queries use `PDO::ATTR_EMULATE_PREPARES=false`; no named placeholder may be reused in one statement.
- Monetary values remain decimal strings; browser totals, gateway totals, and webhook values are never trusted over locked database values.
- External credentials remain in `.env`; database SMTP passwords are encrypted with a 32-byte `APP_KEY` and are never returned to HTML, logs, tests, exports, or health endpoints.
- No integration failure may roll back a previously committed registration, payment, notification, or profile update unless that integration is part of the same explicit atomic state transition.
- Preserve manual payment, ICS downloads, Google Calendar URL links, database notifications, email delivery, and single-node file-backed behavior as truthful fallbacks.
- Do not add Google Maps, SweetAlert2, DataTables, FullCalendar, CKEditor, or Font Awesome; their required product behavior already exists through Leaflet, semantic server-rendered UI, plain text, and Phosphor icons.
- Do not add offline transactions, HTTP database restore, permanent web purge, participant location tracking, or public private-artifact paths.
- All new visible controls meet the existing 44-pixel mobile target, 3-pixel focus indicator, semantic label/error contract, 320/768/1440 light/dark responsive matrix, and reduced-motion rules.
- Each task stages only its allow-listed project files, commits with the exact subject shown, and never stages preserved unrelated local artifacts.

---

### Task 1: Add the extension schema, provider configuration, and dependency floor

**Files:**
- Create: `database/migrations/2026-08-11-remaining-capabilities.sql`
- Create: `tests/Unit/RemainingCapabilitiesSchemaTest.php`
- Create: `tests/verify-remaining-capabilities-mysql.php`
- Create: `tests/verify-remaining-capabilities-mysql.sh`
- Modify: `database/schema.sql`
- Modify: `database/seed.sql`
- Modify: `.env.example`
- Modify: `config/app.php`
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `package.json`
- Modify: `package-lock.json`

**Interfaces:**
- Produces schema for `payment_gateway_attempts`, `payment_refunds`, `notification_preferences`, `phone_verifications`, `push_subscriptions`, `sms_outbox`, `push_outbox`, `oauth_connections`, `calendar_event_syncs`, encrypted SMTP override settings, role-permission revisioning, and MySQL FULLTEXT indexes.
- Produces config keys `payment.sslcommerz.*`, `sms.twilio.*`, `push.vapid.*`, `google_calendar.*`, `redis.*`, and `app_key`.
- Later tasks consume these tables and configuration keys without adding competing columns.

- [ ] **Step 1: Write the failing schema contract**

```php
public function testFreshAndForwardSchemaProvideEveryRemainingCapabilityBoundary(): void
{
    $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
    $migration = file_get_contents(__DIR__ . '/../../database/migrations/2026-08-11-remaining-capabilities.sql');

    foreach (['payment_gateway_attempts', 'payment_refunds', 'notification_preferences', 'phone_verifications', 'push_subscriptions', 'sms_outbox', 'push_outbox', 'oauth_connections', 'calendar_event_syncs'] as $table) {
        $this->assertStringContains("CREATE TABLE {$table}", $schema);
        $this->assertStringContains($table, $migration);
    }
    $this->assertStringContains('FULLTEXT INDEX ft_events_public_search', $schema);
    $this->assertStringContains('permission_revision', $schema);
}
```

- [ ] **Step 2: Run the focused test and observe RED**

Run: `php tests/run.php RemainingCapabilitiesSchemaTest`

Expected: FAIL because the migration and tables do not exist.

- [ ] **Step 3: Add guarded fresh and forward schema**

Use MySQL `information_schema` guards for every added column, index, and foreign key. Required invariants:

```sql
UNIQUE KEY uq_gateway_provider_reference (provider, provider_reference),
UNIQUE KEY uq_gateway_registration_attempt (registration_id, request_key),
UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
UNIQUE KEY uq_oauth_user_provider (user_id, provider),
UNIQUE KEY uq_calendar_sync_registration (registration_id, provider),
CHECK (channel IN ('email', 'sms', 'push')),
CHECK (status IN ('pending', 'processing', 'sent', 'failed', 'cancelled'))
```

Store OAuth access and refresh tokens, Twilio delivery identifiers, and SMTP overrides encrypted; store only push subscription endpoint/key material required by the Web Push protocol. Never store SSLCOMMERZ or Twilio credentials in MySQL.

- [ ] **Step 4: Add pinned compatible dependencies and environment placeholders**

Run:

```bash
composer require minishlink/web-push:^11.0 twilio/sdk:^8.11 google/apiclient:^2.19 predis/predis:^3.0
composer require --dev squizlabs/php_codesniffer:^3.13
npm install --save-dev @zxing/library@0.23.0
```

Add placeholder-only environment keys for SSLCOMMERZ sandbox/live endpoints, store credentials, Twilio SID/token/sender, VAPID keys/subject, Google OAuth client, Redis DSN, and a base64-encoded 32-byte `APP_KEY`.

- [ ] **Step 5: Verify a populated current database upgrades twice**

Run: `OEMS_REMAINING_TEST_MYSQL=1 tests/verify-remaining-capabilities-mysql.sh`

Expected: PASS after importing the current baseline, applying the migration twice, preserving table counts, and proving constraints/indexes with native prepares. The disposable database must be absent afterward.

- [ ] **Step 6: Run dependency and schema gates**

Run:

```bash
composer validate --strict
composer check-platform-reqs
composer audit
npm audit --audit-level=moderate
php tests/run.php RemainingCapabilitiesSchemaTest
git diff --check
```

- [ ] **Step 7: Commit**

```bash
git add .env.example composer.json composer.lock package.json package-lock.json config/app.php database/schema.sql database/seed.sql database/migrations/2026-08-11-remaining-capabilities.sql tests/Unit/RemainingCapabilitiesSchemaTest.php tests/verify-remaining-capabilities-mysql.php tests/verify-remaining-capabilities-mysql.sh
git commit -m "build: prepare remaining capability integrations"
```

### Task 2: Add indexed relevance search and deterministic popular sorting

**Files:**
- Modify: `app/Contracts/EventRepositoryInterface.php`
- Modify: `app/Repositories/EventRepository.php`
- Modify: `app/Controllers/PublicEventController.php`
- Modify: `app/Services/PublicEventApiService.php`
- Modify: `app/Views/events/index.php`
- Modify: `tests/Support/FakeEventRepository.php`
- Modify: `tests/Unit/EventRepositoryTest.php`
- Modify: `tests/Unit/PublicEventControllerTest.php`
- Modify: `tests/Unit/PublicEventApiServiceTest.php`
- Create: `tests/verify-search-popularity-mysql.php`

**Interfaces:**
- Produces `sort=popular` for HTML and `/api/v1/events`.
- `EventRepository::publicSearch(array $filters): array` keeps its signature and returns the current event shape.
- Popular order is confirmed-registration count DESC, favorite count DESC, published-review count DESC, start date ASC, event ID ASC.
- Text search uses MySQL FULLTEXT relevance when the normalized query has searchable tokens and the existing privacy-safe LIKE predicate as a short-token/stop-word fallback.

- [ ] **Step 1: Write RED repository and controller tests**

```php
public function testPopularSortUsesIndependentAggregatesWithoutJoinMultiplication(): void
{
    $rows = $this->repository->publicSearch(['sort' => 'popular']);
    $this->assertSame(['most-engaged', 'registration-led', 'favorite-led'], array_column($rows, 'slug'));
}
```

Also test inactive/deleted users, hidden events, cancelled registrations, hidden reviews, duplicate favorites, deterministic ties, short words, punctuation-only queries, and restricted venue-name privacy.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php EventRepositoryTest PublicEventControllerTest PublicEventApiServiceTest`

Expected: FAIL because `popular` is rejected and no FULLTEXT path exists.

- [ ] **Step 3: Implement aggregate-safe popular order and FULLTEXT relevance**

Use one-row-per-event derived aggregates, never a direct multi-table aggregate join. Bind every `MATCH ... AGAINST` query value once. Keep the existing LIKE fallback for queries under three Unicode letters or zero FULLTEXT matches.

- [ ] **Step 4: Add the HTML/API option and preserved filter behavior**

Add `<option value="popular">Popular</option>`, include it in both allowlists, and prove invalid/nested sort values still return the current safe default or JSON 422 as appropriate.

- [ ] **Step 5: Run focused and native MySQL verification**

Run:

```bash
php tests/run.php EventRepositoryTest PublicEventControllerTest PublicEventApiServiceTest
OEMS_SEARCH_TEST_MYSQL=1 php tests/verify-search-popularity-mysql.php
```

- [ ] **Step 6: Commit**

```bash
git add app/Contracts/EventRepositoryInterface.php app/Repositories/EventRepository.php app/Controllers/PublicEventController.php app/Services/PublicEventApiService.php app/Views/events/index.php tests/Support/FakeEventRepository.php tests/Unit/EventRepositoryTest.php tests/Unit/PublicEventControllerTest.php tests/Unit/PublicEventApiServiceTest.php tests/verify-search-popularity-mysql.php
git commit -m "feat: add popular and indexed event discovery"
```

### Task 3: Optimize uploaded images into bounded metadata-free WebP

**Files:**
- Modify: `app/Services/ImageUploadService.php`
- Modify: `bootstrap/app.php`
- Modify: `tests/Unit/ImageUploadServiceTest.php`
- Modify: `tests/Support/TestImage.php`
- Modify: `README.md`

**Interfaces:**
- `ImageUploadService::store(?array $upload): array` keeps its result shape.
- Valid JPEG, PNG, and WebP inputs are decoded, orientation-normalized when EXIF is available, resized to fit within 2000x2000 without enlargement, stripped of metadata, and encoded as WebP quality 82.
- Transparent PNG/WebP inputs preserve alpha. Animated inputs are rejected rather than silently flattened.

- [ ] **Step 1: Write image-behavior RED tests**

Test a 3000x1500 JPEG becomes 2000x1000 WebP, a small PNG keeps dimensions and alpha, EXIF/comments are absent, output begins with RIFF/WEBP, a decoder failure leaves no file, and replacement cleanup remains confined.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php ImageUploadServiceTest`

Expected: FAIL because the service currently renames original bytes unchanged.

- [ ] **Step 3: Implement atomic decode-resize-encode**

Write to a random `.tmp` inside the upload root, verify `getimagesize()` and the final byte ceiling, rename atomically to a random `.webp`, and remove both temporary and destination files on every failure. Do not use the original filename.

- [ ] **Step 4: Verify every existing upload consumer**

Run: `php tests/run.php ImageUploadServiceTest EventServiceTest BlogServiceTest CmsServiceTest`

Expected: PASS; existing event, Blog, and banner replacement semantics remain unchanged.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImageUploadService.php bootstrap/app.php tests/Unit/ImageUploadServiceTest.php tests/Support/TestImage.php README.md
git commit -m "feat: optimize public image uploads"
```

### Task 4: Make database permissions authoritative at runtime

**Files:**
- Create: `app/Contracts/PermissionRepositoryInterface.php`
- Create: `app/Repositories/PermissionRepository.php`
- Create: `app/Services/AuthorizationService.php`
- Create: `app/Middleware/PermissionMiddleware.php`
- Create: `app/Controllers/AdminPermissionController.php`
- Create: `app/Views/admin/permissions/edit.php`
- Create: `tests/Support/FakePermissionRepository.php`
- Create: `tests/Unit/PermissionRepositoryTest.php`
- Create: `tests/Unit/AuthorizationServiceTest.php`
- Create: `tests/Unit/AdminPermissionControllerTest.php`
- Modify: `Core/Router.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `tests/Unit/RoleMiddlewareTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`

**Interfaces:**
- Produces middleware declaration `permission:<slug>` alongside existing authentication and coarse role guards.
- `AuthorizationService::allows(int $userId, string $permission): bool` reads the canonical active user role and current `role_permissions` rows.
- Super-admin permissions remain complete and cannot be removed through HTTP. Organizer/participant permission changes are CAS-protected, audited, and increment `roles.permission_revision`.

- [ ] **Step 1: Write repository/service/controller RED tests**

Cover removed permission denial, active permission success, inactive/deleted user denial, unknown permission denial, organizer/participant editing, super-admin immutability, CSRF, stale revision 409, and audit rows.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php PermissionRepositoryTest AuthorizationServiceTest AdminPermissionControllerTest RoleMiddlewareTest`

- [ ] **Step 3: Implement the contract, repository, service, and middleware**

Use one prepared query joining `users`, `roles`, `role_permissions`, and `permissions`. Cache only within the current request; do not persist permission decisions in the PHP session.

- [ ] **Step 4: Apply permission guards without weakening role ownership**

Keep `role:organizer`, `role:participant`, and `role:super-admin`; add precise permissions to create/manage events, participants, reports, CMS, settings, payments, people, reviews, and newsletters. Ownership checks stay in repositories.

- [ ] **Step 5: Add the accessible administrator permission matrix**

Use named checkboxes, an explicit revision token, consequence copy, keyboard-accessible groups, and no ability to edit the super-admin role.

- [ ] **Step 6: Run focused/full security tests and commit**

```bash
php tests/run.php PermissionRepositoryTest AuthorizationServiceTest AdminPermissionControllerTest RoleMiddlewareTest ProfileRouteSecurityTest DashboardLayoutTest
git add app/Contracts/PermissionRepositoryInterface.php app/Repositories/PermissionRepository.php app/Services/AuthorizationService.php app/Middleware/PermissionMiddleware.php app/Controllers/AdminPermissionController.php app/Views/admin/permissions/edit.php tests/Support/FakePermissionRepository.php tests/Unit/PermissionRepositoryTest.php tests/Unit/AuthorizationServiceTest.php tests/Unit/AdminPermissionControllerTest.php Core/Router.php bootstrap/app.php routes/web.php app/Views/layouts/dashboard.php tests/Unit/RoleMiddlewareTest.php tests/Unit/DashboardLayoutTest.php
git commit -m "feat: enforce database role permissions"
```

### Task 5: Add complete English and Bangla localization

**Files:**
- Create: `Core/Translator.php`
- Create: `app/Middleware/LocaleMiddleware.php`
- Create: `resources/lang/en.php`
- Create: `resources/lang/bn.php`
- Create: `app/Controllers/LocaleController.php`
- Create: `tests/Unit/TranslatorTest.php`
- Create: `tests/Unit/LocaleMiddlewareTest.php`
- Create: `tests/Unit/LocalizationCoverageTest.php`
- Modify: `app/Helpers/helpers.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Controllers/ProfileController.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: all first-party templates under `app/Views/`
- Modify: `composer.json`
- Modify: `README.md`

**Interfaces:**
- Produces `trans(string $key, array $replace = []): string`, `trans_choice(string $key, int $count, array $replace = []): string`, and a POST `/locale` action.
- Locale order: authenticated `profiles.locale`, explicit session locale, supported `Accept-Language`, then `en`.
- Supported locales are exactly `en` and `bn`; unknown/nested values return 422 without changing state.

- [ ] **Step 1: Require and verify `ext-intl`**

Add `ext-intl` to Composer platform requirements for locale-aware dates, times, numbers, and currency display. Keep database money as decimal strings.

- [ ] **Step 2: Write translator and coverage RED tests**

```php
public function testEveryEnglishKeyHasABanglaTranslationAndNoViewContainsUnmigratedVisibleCopy(): void
{
    $this->assertSame(array_keys(require resource_path('lang/en.php')), array_keys(require resource_path('lang/bn.php')));
    $this->assertSame([], $this->untranslatedVisibleViewLiterals());
}
```

The coverage helper must ignore HTML attributes, CSS classes, IDs, data keys, proper nouns, and user/database content while flagging new visible hard-coded sentences.

- [ ] **Step 3: Implement translator, middleware, and locale persistence**

Use exact key lookup with English fallback and escaped output at the view boundary. Locale changes require CSRF and update the authenticated profile transactionally when signed in.

- [ ] **Step 4: Migrate visible application copy by domain**

Migrate layouts/errors/auth/profile first, then public discovery/calendar/Blog/CMS, participant workflows, organizer workspaces, and administrator workspaces. Translate validation/status/action copy through catalog keys instead of duplicating strings in controllers.

- [ ] **Step 5: Add locale-aware formatting and UI**

Add one compact language control to public/auth/dashboard layouts, set `<html lang>`, preserve the current URL through the same-origin return sanitizer, and format dates/times/currency through `IntlDateFormatter`/`NumberFormatter` without converting stored decimal values through floats.

- [ ] **Step 6: Run coverage, role workflow, and browser gates**

Run:

```bash
php tests/run.php TranslatorTest LocaleMiddlewareTest LocalizationCoverageTest UiLayoutTest DashboardLayoutTest TransactionUiTest
npm run build:css
```

Browser: 320/768/1440, light/dark, English/Bangla across home, event detail, registration, ticket, organizer event, and admin payment; require zero overflow, missing labels, console diagnostics, or clipped Bangla text.

- [ ] **Step 7: Commit**

```bash
git add Core/Translator.php app/Middleware/LocaleMiddleware.php resources/lang/en.php resources/lang/bn.php app/Controllers/LocaleController.php app/Helpers/helpers.php bootstrap/app.php routes/web.php app/Controllers/ProfileController.php app/Views composer.json composer.lock README.md tests/Unit/TranslatorTest.php tests/Unit/LocaleMiddlewareTest.php tests/Unit/LocalizationCoverageTest.php public/assets/css/app.css resources/css/app.css
git commit -m "feat: add complete English Bangla localization"
```

### Task 6: Add hosted SSLCOMMERZ checkout, validated callbacks, and refunds

**Files:**
- Create: `app/Contracts/PaymentGatewayInterface.php`
- Create: `app/Services/SslCommerzGateway.php`
- Create: `app/Services/GatewayPaymentService.php`
- Create: `app/Controllers/PaymentGatewayController.php`
- Create: `tests/Support/FakePaymentGateway.php`
- Create: `tests/Unit/SslCommerzGatewayTest.php`
- Create: `tests/Unit/GatewayPaymentServiceTest.php`
- Create: `tests/Unit/PaymentGatewayControllerTest.php`
- Create: `tests/verify-gateway-settlement-mysql.php`
- Modify: `app/Contracts/HttpClientInterface.php`
- Modify: `app/Support/StreamHttpClient.php`
- Modify: `app/Contracts/PaymentRepositoryInterface.php`
- Modify: `app/Repositories/PaymentRepository.php`
- Modify: `app/Services/RegistrationService.php`
- Modify: `app/Controllers/ParticipantRegistrationController.php`
- Modify: `app/Views/participant/registrations/checkout.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Views/admin/payments/show.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `README.md`

**Interfaces:**
- `PaymentGatewayInterface::initiate(array $order): array{ok:bool, redirect_url:?string, provider_reference:?string, error:?string}`.
- `PaymentGatewayInterface::validate(string $validationId): array{valid:bool, provider_reference:?string, amount:?string, currency:?string, bank_reference:?string, raw_code:?string}`.
- `PaymentGatewayInterface::refund(array $payment, string $amount, string $reason): array{accepted:bool, refund_reference:?string, status:string}`.
- `HttpClientInterface` gains one generic `request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array`; `get()` remains a compatibility wrapper.

- [ ] **Step 1: Write RED provider and service tests**

Cover hosted initiation, exact database amount/currency, HTTPS allowlisted provider endpoints, unique request keys, success/fail/cancel return pages, server-to-server IPN, mandatory Order Validation API confirmation, duplicate callback idempotence, callback amount mismatch, forged payloads, concurrent settlement, ticket issuance, and failure isolation.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php SslCommerzGatewayTest GatewayPaymentServiceTest PaymentGatewayControllerTest`

- [ ] **Step 3: Extend the bounded HTTP client**

Allow only HTTP/HTTPS, disable redirects, verify TLS, cap request/response bytes, enforce a whole-request deadline, strip header controls, and support `application/x-www-form-urlencoded` provider requests. Never log the store password or complete callback body.

- [ ] **Step 4: Implement hosted checkout and settlement**

Create the registration/payment atomically using locked server totals, commit, then initiate hosted checkout. The callback must call the SSLCOMMERZ validation endpoint and settle through the same ticket-issuing transaction used by administrator verification. Browser success URLs never settle a payment on their own.

- [ ] **Step 5: Implement refund attention and asynchronous refund state**

Participant cancellation of a paid gateway transaction creates one `pending` refund row after the cancellation commit. A bounded worker calls the refund API, records the provider reference/status, and never marks `refunded` until provider confirmation. Manual payments retain the existing “refund attention” state.

- [ ] **Step 6: Preserve manual payment as explicit fallback**

Checkout shows hosted payment only when gateway configuration and active payment method are available. Manual references remain separate and never pass through gateway callback routes.

- [ ] **Step 7: Run focused, native concurrency, and sandbox-contract tests**

```bash
php tests/run.php SslCommerzGatewayTest GatewayPaymentServiceTest PaymentGatewayControllerTest RegistrationServiceTest PaymentRepositoryTest TicketServiceTest
OEMS_GATEWAY_TEST_MYSQL=1 php tests/verify-gateway-settlement-mysql.php
```

- [ ] **Step 8: Commit**

```bash
git add app/Contracts/PaymentGatewayInterface.php app/Contracts/HttpClientInterface.php app/Support/StreamHttpClient.php app/Services/SslCommerzGateway.php app/Services/GatewayPaymentService.php app/Controllers/PaymentGatewayController.php app/Contracts/PaymentRepositoryInterface.php app/Repositories/PaymentRepository.php app/Services/RegistrationService.php app/Controllers/ParticipantRegistrationController.php app/Views/participant/registrations/checkout.php app/Views/participant/registrations/show.php app/Views/admin/payments/show.php bootstrap/app.php routes/web.php README.md tests/Support/FakePaymentGateway.php tests/Unit/SslCommerzGatewayTest.php tests/Unit/GatewayPaymentServiceTest.php tests/Unit/PaymentGatewayControllerTest.php tests/verify-gateway-settlement-mysql.php
git commit -m "feat: add hosted payment and refund workflow"
```

### Task 7: Add consent-based SMS and browser push delivery

**Files:**
- Create: `app/Contracts/SmsTransportInterface.php`
- Create: `app/Contracts/PushTransportInterface.php`
- Create: `app/Repositories/CommunicationPreferenceRepository.php`
- Create: `app/Repositories/PhoneVerificationRepository.php`
- Create: `app/Repositories/SmsOutboxRepository.php`
- Create: `app/Repositories/PushSubscriptionRepository.php`
- Create: `app/Repositories/PushOutboxRepository.php`
- Create: `app/Services/TwilioSmsTransport.php`
- Create: `app/Services/WebPushTransport.php`
- Create: `app/Services/CommunicationService.php`
- Create: `app/Services/SmsOutboxWorker.php`
- Create: `app/Services/PushOutboxWorker.php`
- Create: `app/Controllers/CommunicationPreferenceController.php`
- Create: `app/Controllers/PushSubscriptionController.php`
- Create: `app/Views/profile/communications.php`
- Create: `public/assets/js/push-notifications.js`
- Create: `scripts/process-sms-outbox.php`
- Create: `scripts/process-push-outbox.php`
- Create: `tests/Support/FakeSmsTransport.php`
- Create: `tests/Support/FakePushTransport.php`
- Create: `tests/Unit/CommunicationServiceTest.php`
- Create: `tests/Unit/SmsOutboxWorkerTest.php`
- Create: `tests/Unit/PushOutboxWorkerTest.php`
- Create: `tests/Unit/PushSubscriptionControllerTest.php`
- Create: `tests/js/push-notifications.test.mjs`
- Modify: `app/Services/NotificationService.php`
- Modify: `app/Services/EventReminderService.php`
- Modify: `app/Services/TransactionMailer.php`
- Modify: `app/Views/profile/edit.php`
- Modify: `public/service-worker.js`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `deploy/systemd/`
- Modify: `README.md`

**Interfaces:**
- `CommunicationService::dispatch(int $userId, string $template, array $payload, string $idempotencyKey): void` fans out only to opted-in eligible channels after the domain commit.
- SMS requires E.164 phone normalization, explicit consent, and verified ownership before activation.
- Push requires an authenticated same-origin subscription, endpoint hash uniqueness, VAPID authentication, and generic privacy-safe payloads.

- [ ] **Step 1: Write RED consent, outbox, transport, and JavaScript tests**

Cover opt-in/out, phone verification token hashing/expiry/rate limits, STOP/unsubscribe behavior, duplicate SMS idempotency, Twilio provider failure/backoff, push subscription replacement, expired endpoint deletion, notification click safe-relative URL, service-worker lifecycle, permission denial, and no prompting on page load.

- [ ] **Step 2: Observe RED**

Run:

```bash
php tests/run.php CommunicationServiceTest SmsOutboxWorkerTest PushOutboxWorkerTest PushSubscriptionControllerTest
node tests/js/push-notifications.test.mjs
```

- [ ] **Step 3: Implement verified phone and channel preferences**

Use hashed one-time phone verification codes with a ten-minute expiry and both account/IP rate limits. Never reveal whether Twilio accepted a verification message. A changed phone invalidates verification and SMS consent.

- [ ] **Step 4: Implement durable channel delivery**

SMS uses the existing retry/idempotency pattern with Twilio SID/token/sender from environment. Web Push uses VAPID keys from environment, a 24-hour maximum TTL, bounded JSON, safe action URLs, and removal of 404/410 subscriptions.

- [ ] **Step 5: Integrate only approved templates**

Enable registration confirmation, payment outcome, ticket ready, waitlist promotion, event cancellation, event reminder, and organizer announcement. Do not include QR tokens, payment references, exact restricted locations, reset tokens, or participant PII beyond a bounded display name.

- [ ] **Step 6: Add accessible opt-in UI and progressive JavaScript**

Provide separate email/SMS/push controls with current state, consequences, test-send status, and no automatic permission prompt. The server-rendered profile remains complete without JavaScript.

- [ ] **Step 7: Verify and commit**

```bash
php tests/run.php CommunicationServiceTest SmsOutboxWorkerTest PushOutboxWorkerTest PushSubscriptionControllerTest NotificationServiceTest EventReminderServiceTest TransactionMailerTest
node tests/js/push-notifications.test.mjs
node --check public/assets/js/push-notifications.js
npm run build:css
git add app/Contracts/SmsTransportInterface.php app/Contracts/PushTransportInterface.php app/Repositories/CommunicationPreferenceRepository.php app/Repositories/PhoneVerificationRepository.php app/Repositories/SmsOutboxRepository.php app/Repositories/PushSubscriptionRepository.php app/Repositories/PushOutboxRepository.php app/Services/TwilioSmsTransport.php app/Services/WebPushTransport.php app/Services/CommunicationService.php app/Services/SmsOutboxWorker.php app/Services/PushOutboxWorker.php app/Controllers/CommunicationPreferenceController.php app/Controllers/PushSubscriptionController.php app/Views/profile/communications.php public/assets/js/push-notifications.js scripts/process-sms-outbox.php scripts/process-push-outbox.php tests/Support/FakeSmsTransport.php tests/Support/FakePushTransport.php tests/Unit/CommunicationServiceTest.php tests/Unit/SmsOutboxWorkerTest.php tests/Unit/PushOutboxWorkerTest.php tests/Unit/PushSubscriptionControllerTest.php tests/js/push-notifications.test.mjs app/Services/NotificationService.php app/Services/EventReminderService.php app/Services/TransactionMailer.php app/Views/profile/edit.php public/service-worker.js app/Views/layouts/dashboard.php bootstrap/app.php routes/web.php deploy/systemd README.md resources/css/app.css public/assets/css/app.css
git commit -m "feat: add opted in sms and browser push"
```

### Task 8: Add Google Calendar OAuth synchronization

**Files:**
- Create: `app/Contracts/OAuthConnectionRepositoryInterface.php`
- Create: `app/Contracts/CalendarSyncRepositoryInterface.php`
- Create: `app/Repositories/OAuthConnectionRepository.php`
- Create: `app/Repositories/CalendarSyncRepository.php`
- Create: `app/Services/EncryptedValueService.php`
- Create: `app/Services/GoogleCalendarService.php`
- Create: `app/Controllers/GoogleCalendarController.php`
- Create: `app/Views/profile/integrations.php`
- Create: `tests/Support/FakeOAuthConnectionRepository.php`
- Create: `tests/Support/FakeCalendarSyncRepository.php`
- Create: `tests/Unit/EncryptedValueServiceTest.php`
- Create: `tests/Unit/GoogleCalendarServiceTest.php`
- Create: `tests/Unit/GoogleCalendarControllerTest.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Views/profile/edit.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `README.md`

**Interfaces:**
- `EncryptedValueService::encrypt(string): string` and `decrypt(string): string` use libsodium secretbox with `APP_KEY` and versioned ciphertext.
- `GoogleCalendarService::authorizationUrl(int $userId, string $returnTo): string` binds OAuth state to user/session/return path.
- `GoogleCalendarService::syncRegistration(int $userId, int $registrationId): array` inserts or updates one primary-calendar event using the same privacy-normalized `CalendarService` payload and the registration's `calendar_event_syncs` row.

- [ ] **Step 1: Write RED encryption/OAuth/privacy tests**

Cover tamper failure, key rotation version rejection, OAuth state replay, external return path rejection, minimum Calendar scope, token refresh, revoked grant, one event per registration, update instead of duplicate, participant ownership, cancelled event update, restricted exact location only for confirmed owner, and disconnect token deletion.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php EncryptedValueServiceTest GoogleCalendarServiceTest GoogleCalendarControllerTest`

- [ ] **Step 3: Implement OAuth connection and encryption boundaries**

Use Google OAuth authorization-code flow, encrypted access/refresh tokens, `state` stored as a one-time hashed session value, and the official `calendar.events` write scope. Never put tokens in logs or URLs after callback completion.

- [ ] **Step 4: Reuse the canonical calendar privacy presenter**

ICS, Google URL, and OAuth insertion must derive from the same `CalendarService` data. Store only the returned Google event ID and sync timestamp beside the encrypted connection.

- [ ] **Step 5: Add connect/sync/disconnect UI and verify**

```bash
php tests/run.php EncryptedValueServiceTest GoogleCalendarServiceTest GoogleCalendarControllerTest CalendarServiceTest EventCalendarControllerTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Contracts/OAuthConnectionRepositoryInterface.php app/Contracts/CalendarSyncRepositoryInterface.php app/Repositories/OAuthConnectionRepository.php app/Repositories/CalendarSyncRepository.php app/Services/EncryptedValueService.php app/Services/GoogleCalendarService.php app/Controllers/GoogleCalendarController.php app/Views/profile/integrations.php tests/Support/FakeOAuthConnectionRepository.php tests/Support/FakeCalendarSyncRepository.php tests/Unit/EncryptedValueServiceTest.php tests/Unit/GoogleCalendarServiceTest.php tests/Unit/GoogleCalendarControllerTest.php app/Views/participant/registrations/show.php app/Views/profile/edit.php bootstrap/app.php routes/web.php README.md
git commit -m "feat: add Google Calendar account sync"
```

### Task 9: Add encrypted administrator-managed SMTP overrides and welcome delivery

**Files:**
- Create: `app/Services/MailConfigurationService.php`
- Create: `tests/Unit/MailConfigurationServiceTest.php`
- Modify: `app/Services/EncryptedValueService.php`
- Modify: `app/Repositories/PlatformSettingsRepository.php`
- Modify: `app/Services/PlatformSettingsService.php`
- Modify: `app/Controllers/AdminSettingsController.php`
- Modify: `app/Views/admin/settings/edit.php`
- Modify: `app/Mail/PhpMailerTransport.php`
- Modify: `app/Services/AccountMailer.php`
- Modify: `app/Services/AuthService.php`
- Modify: `app/Services/MailOutboxService.php`
- Modify: `app/Services/QueuedMailTemplateService.php`
- Modify: `tests/Unit/PlatformSettingsServiceTest.php`
- Modify: `tests/Unit/PhpMailerTransportTest.php`
- Modify: `tests/Unit/AccountMailerTest.php`
- Modify: `tests/Unit/AuthServiceTest.php`
- Modify: `README.md`

**Interfaces:**
- `MailConfigurationService::resolved(): array` merges an explicit enabled encrypted database override over environment defaults.
- Password input is write-only: blank preserves current ciphertext; replacement encrypts; disable removes runtime use without exposing stored bytes.
- `AccountMailer::sendWelcome(...)` or the durable `welcome` outbox template runs once after successful email verification.

- [ ] **Step 1: Write RED secret-handling and welcome tests**

Test masked UI, no password hydration, invalid encryption fail-closed to environment, test-message confirmation intent, audit without secrets, unchanged-password save, wrong APP_KEY failure, one welcome email after verification, verification replay without duplicate welcome, and outbox retry behavior.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php MailConfigurationServiceTest PlatformSettingsServiceTest PhpMailerTransportTest AccountMailerTest AuthServiceTest`

- [ ] **Step 3: Implement encrypted overrides and mail test action**

Only super-admin can edit. Bind save/test actions to CSRF and a five-minute session intent. Never render host diagnostics beyond configured/not configured and successful/failed.

- [ ] **Step 4: Add the welcome outbox template after verification commit**

Use idempotency material `welcome:user:{id}:verified:{verified_at}`. Delivery failure does not undo verification.

- [ ] **Step 5: Verify and commit**

```bash
php tests/run.php MailConfigurationServiceTest PlatformSettingsServiceTest PhpMailerTransportTest AccountMailerTest AuthServiceTest CmsControllerTest
git add app/Services/MailConfigurationService.php app/Services/EncryptedValueService.php app/Repositories/PlatformSettingsRepository.php app/Services/PlatformSettingsService.php app/Controllers/AdminSettingsController.php app/Views/admin/settings/edit.php app/Mail/PhpMailerTransport.php app/Services/AccountMailer.php app/Services/AuthService.php app/Services/MailOutboxService.php app/Services/QueuedMailTemplateService.php tests/Unit/MailConfigurationServiceTest.php tests/Unit/PlatformSettingsServiceTest.php tests/Unit/PhpMailerTransportTest.php tests/Unit/AccountMailerTest.php tests/Unit/AuthServiceTest.php README.md
git commit -m "feat: add encrypted mail settings and welcome delivery"
```

### Task 10: Add shared Redis rate limits, cache, and worker coordination

**Files:**
- Create: `Core/Contracts/AtomicStoreInterface.php`
- Create: `Core/FileAtomicStore.php`
- Create: `Core/RedisAtomicStore.php`
- Create: `tests/Support/FakeAtomicStore.php`
- Create: `tests/Unit/RedisAtomicStoreTest.php`
- Modify: `Core/RateLimiter.php`
- Modify: `app/Services/MaintenanceService.php`
- Modify: `app/Services/MailOutboxWorker.php`
- Modify: `app/Services/SmsOutboxWorker.php`
- Modify: `app/Services/PushOutboxWorker.php`
- Modify: `bootstrap/app.php`
- Modify: `config/app.php`
- Modify: `tests/Unit/RateLimiterTest.php`
- Modify: `tests/Unit/MaintenanceMiddlewareTest.php`
- Modify: `tests/Unit/MailOutboxWorkerTest.php`
- Modify: `deploy/systemd/`
- Modify: `README.md`

**Interfaces:**
- `AtomicStoreInterface::increment(string $key, int $ttl): int`, `get(string $key): ?string`, `put(string $key, string $value, int $ttl): void`, `delete(string $key): void`, and `lock(string $key, int $ttl, callable $critical): mixed`.
- `REDIS_DSN` selects Redis; blank keeps the existing file store. Readiness reports only `shared_store: true|false`.

- [ ] **Step 1: Write RED atomicity/fallback tests**

Cover multi-process increments, TTL expiry, lock ownership token, stale lock recovery, Redis outage fail-closed for security limits, file fallback only when Redis is not configured, and no runtime failover from configured Redis to per-node files.

- [ ] **Step 2: Observe RED**

Run: `php tests/run.php RedisAtomicStoreTest RateLimiterTest MaintenanceMiddlewareTest MailOutboxWorkerTest`

- [ ] **Step 3: Implement the shared atomic store**

Use Redis `INCR` plus first-write `EXPIRE`, compare-and-delete lock release, random lock tokens, key prefix `oems:{environment}:`, bounded key hashes, and no serialization of arbitrary PHP objects.

- [ ] **Step 4: Integrate only cross-node coordination points**

Move request limits, provider throttles, maintenance cache invalidation, and worker singleton leases. Keep MySQL row locking/idempotency as the final source of truth for jobs and financial state.

- [ ] **Step 5: Verify Redis and file modes, then commit**

```bash
php tests/run.php RedisAtomicStoreTest RateLimiterTest MaintenanceMiddlewareTest MailOutboxWorkerTest SmsOutboxWorkerTest PushOutboxWorkerTest
OEMS_REDIS_TEST=1 php tests/run.php RedisAtomicStoreTest
git add Core/Contracts/AtomicStoreInterface.php Core/FileAtomicStore.php Core/RedisAtomicStore.php tests/Support/FakeAtomicStore.php tests/Unit/RedisAtomicStoreTest.php Core/RateLimiter.php app/Services/MaintenanceService.php app/Services/MailOutboxWorker.php app/Services/SmsOutboxWorker.php app/Services/PushOutboxWorker.php bootstrap/app.php config/app.php tests/Unit/RateLimiterTest.php tests/Unit/MaintenanceMiddlewareTest.php tests/Unit/MailOutboxWorkerTest.php deploy/systemd README.md
git commit -m "feat: add shared application coordination"
```

### Task 11: Add QR image round-trip decoding and automated PSR-12 enforcement

**Files:**
- Create: `phpcs.xml.dist`
- Create: `scripts/check-style.sh`
- Create: `tests/js/qr-roundtrip.test.mjs`
- Create: `tests/Unit/PublicApiDocumentationTest.php`
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `tests/Unit/TicketArtifactServiceTest.php`
- Modify: first-party PHP files reported by `vendor/bin/phpcbf --standard=phpcs.xml.dist`
- Modify: `README.md`

**Interfaces:**
- Adds `composer check:style` and `npm run test:qr` release gates.
- QR round-trip generates a real ticket PNG through `TicketArtifactService`, decodes it with ZXing in Node, and verifies the exact internal URL/token contract without printing the token.
- Public service/repository/controller interfaces modified by this program carry concise parameter, return-shape, exception, and security-boundary documentation; the documentation test rejects undocumented new public APIs.

- [ ] **Step 1: Write the RED QR harness**

The PHP fixture prints only the temporary PNG path and SHA-256 of the expected payload to the child process. The Node harness decodes, hashes, compares, removes the artifact, and never logs the decoded bearer value.

- [ ] **Step 2: Observe RED**

Run: `node tests/js/qr-roundtrip.test.mjs`

Expected: FAIL because the ZXing harness and script do not exist.

- [ ] **Step 3: Add and pass the round-trip test**

Run: `npm run test:qr`

Expected: PASS for a generated PNG and FAIL when one pixel region is deliberately corrupted beyond decoder recovery.

- [ ] **Step 4: Establish PSR-12 configuration and observe violations**

Configure `app`, `Core`, `bootstrap`, `config`, `public/*.php`, `routes`, `scripts/*.php`, and `tests` while excluding `vendor`, `node_modules`, generated assets, runtime storage, and migrations/seed SQL.

Run: `vendor/bin/phpcs --standard=phpcs.xml.dist`

Expected: RED on current formatting violations.

- [ ] **Step 5: Add the public API documentation contract**

Document every new public method introduced by Tasks 1-10 and every existing public method changed by those tasks. `PublicApiDocumentationTest` reflects those scoped classes and requires a non-empty doc comment containing `@param` for non-empty parameter lists and `@return` for non-void results; constructors and self-explanatory interface marker methods are excluded explicitly in the test data provider.

- [ ] **Step 6: Apply mechanical fixes, then manually review behavior-sensitive diffs**

Run `vendor/bin/phpcbf --standard=phpcs.xml.dist`, manually correct remaining findings, and rerun focused tests for any file where formatting could alter string literals, SQL, regular expressions, generated HTML, or closure binding.

- [ ] **Step 7: Run style, syntax, QR, documentation, and full tests**

```bash
composer check:style
composer check:syntax
npm run test:qr
php tests/run.php PublicApiDocumentationTest
composer test
git diff --check
```

- [ ] **Step 8: Commit**

```bash
git add phpcs.xml.dist scripts/check-style.sh tests/js/qr-roundtrip.test.mjs tests/Unit/PublicApiDocumentationTest.php composer.json composer.lock package.json package-lock.json tests/Unit/TicketArtifactServiceTest.php app Core bootstrap config public routes scripts tests README.md
git commit -m "build: enforce style and qr round trip quality"
```

### Task 12: Complete cross-feature acceptance, documentation, and public release

**Files:**
- Create: `docs/remaining-capabilities-operations.md`
- Create: `tests/verify-remaining-capabilities-release.sh`
- Modify: `README.md`
- Modify: `deploy/nginx/oems.conf`
- Modify: `deploy/php-fpm/oems.conf`
- Modify: `deploy/systemd/`
- Modify: `.gitignore`

**Interfaces:**
- Produces one repeatable release command and one operator guide for gateway, refunds, Redis, SMS, push, OAuth, localization, mail overrides, migrations, rollback, secrets, health, and incident handling.
- No production credential or deployment is required to complete the code release; provider sandbox/live activation remains environment-owned.

- [ ] **Step 1: Write the final requirements matrix before running it**

Map every item in this plan to an automated test, native MySQL/Redis/provider-fake check, live HTTP journey, browser page, documentation section, and commit hash. Any unmapped row is a release failure.

- [ ] **Step 2: Run complete automated gates**

```bash
composer test
composer check:syntax
composer check:style
composer validate --strict
composer check-platform-reqs
composer audit
npm audit --audit-level=moderate
npm run build:css
npm run test:assets
npm run test:qr
node tests/js/location.test.mjs
node tests/js/venue-map.test.mjs
node tests/js/check-in-camera.test.mjs
node tests/js/analytics-charts.test.mjs
node tests/js/dashboard-sidebar.test.mjs
node tests/js/pwa.test.mjs
node tests/js/push-notifications.test.mjs
git diff --check
```

- [ ] **Step 3: Run native state and concurrency gates**

Create unique disposable databases and Redis prefixes; run fresh schema/seed/demo twice, every migration twice, popular search, gateway callback/refund concurrency, permission revision CAS, SMS/push idempotency, OAuth encryption, outbox workers, waitlist, certificates, Blog, recovery, analytics, and ticket check-in. Prove every disposable schema/key prefix is removed.

- [ ] **Step 4: Run full live HTTP acceptance**

Exercise participant/organizer/admin workflows plus hosted gateway fake, callbacks, refund queue, Bangla locale, popular search, optimized uploads, permission denial, SMS/push preferences, Google OAuth fake, SMTP overrides, guest/role/CSRF/IDOR/method/rate-limit/replay/stale/failure boundaries, and private downloads.

- [ ] **Step 5: Run in-app browser acceptance**

Check 320/768/1440 in light/dark and English/Bangla across home, event search/popular results, event detail, checkout/return/refund, communication preferences, OAuth settings, participant ticket, organizer workspace, admin settings/permissions, offline PWA, and validation/empty/error states. Require zero overflow, sampled contrast failures, console warnings/errors, focus traps, missing names, undersized controls, or unlocalized visible strings.

- [ ] **Step 6: Perform final security/package audit**

Confirm no `.env`, APP_KEY, gateway credential, Twilio token, VAPID private key, OAuth secret/token, SMTP ciphertext/plaintext, payment callback body, phone verification code, push endpoint, QR token, or private artifact is tracked, logged, exported, or publicly routable.

- [ ] **Step 7: Obtain independent review and fix every Critical/Important finding**

Repeat RED/GREEN and create separate `fix:` commits for findings. Do not waive provider callback, money, consent, encryption, localization, permission, or private-data findings as optional.

- [ ] **Step 8: Commit documentation and release evidence**

```bash
git add README.md docs/remaining-capabilities-operations.md tests/verify-remaining-capabilities-release.sh deploy .gitignore
git commit -m "docs: complete remaining capability operations"
```

- [ ] **Step 9: Push only after all gates are green**

```bash
git status --short --branch
git log --oneline origin/main..HEAD
git push origin main
```

Expected: remote `main` equals local `HEAD`; no unrelated local artifacts are staged or pushed.

## Completion Definition

This program is complete only when:

- Public discovery supports privacy-safe relevance search and deterministic popular sorting.
- Every accepted public upload is metadata-free, bounded, and optimized as WebP.
- Database permissions are authoritative without weakening ownership or super-admin safety.
- Every visible application workflow is complete in English and Bangla.
- Paid participants can use validated SSLCOMMERZ hosted checkout; callbacks are authoritative; refunds are tracked truthfully; manual payment still works.
- Verified, opted-in users can receive bounded SMS and browser push with durable retries and immediate opt-out.
- Participants can connect, sync, and disconnect Google Calendar without exposing tokens or restricted event data.
- Administrators can manage encrypted SMTP overrides and a verified user receives one welcome message.
- Redis can coordinate limits/cache/workers across nodes while unconfigured single-node installs retain the documented file mode.
- Generated QR PNGs pass a real decoder round trip and all first-party PHP passes the PSR-12 gate.
- Fresh/full migrations, tests, native concurrency, HTTP, browser, privacy, package, dependency, and documentation gates pass with no unresolved Critical or Important findings.
- Every slice has its own reviewed Git commit and only project files are pushed.
