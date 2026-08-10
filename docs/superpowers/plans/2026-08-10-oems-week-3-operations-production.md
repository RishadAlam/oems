# OEMS Week 3 Operations and Production Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver organizer coupons, reliable queued mail and reminders, contact/newsletter operations, privacy-safe calendar delivery, accessible analytics charts, and production health, maintenance, backup, and deployment controls.

**Architecture:** Extend the existing contract/repository/service/controller/view boundaries. Keep domain mutations transactional and enqueue email through a durable MySQL outbox processed by CLI workers. Preserve the existing design system, route structure, security middleware, exact-money rules, and single-node production contract.

**Tech Stack:** PHP 8.2+ strict OOP, custom MVC container/router, PDO with native prepares, MySQL 8+, PHPMailer, Tailwind CSS v4, Vanilla JavaScript, locally hosted Chart.js, Composer, npm, and the existing custom PHP/Node test harnesses.

## Global Constraints

- Use raw PHP, PDO, MySQL, Tailwind CSS, and Vanilla JavaScript only; no PHP framework or client framework.
- Follow strict TDD: write and observe each focused failure before production code.
- Use prepared statements with unique named placeholders compatible with `PDO::ATTR_EMULATE_PREPARES=false`.
- Use exact decimal strings and the existing `Money` helper for every coupon, registration, payment, and report decision.
- All state-changing web routes are POST, authenticated where required, role-scoped, CSRF-protected, rate-limited where public, and safe on repeated requests.
- Never trust client amount, discount, event ownership, role, redirect, file path, template name, or recipient state.
- Public output is escaped; JSON uses hex-safe encoding; CSV and calendar downloads use safe filenames and private/no-store where identity or restricted location is present.
- Maintain the existing cobalt/cool-neutral token system, Manrope, Phosphor icons, light/dark themes, semantic tables, 44 pixel mobile targets, and accessible labels/help/errors.
- Visible UI copy contains no em or en dash characters.
- Every task ends with focused verification, full regression, diff review, exact staging, and a scoped Git commit.
- Preserve unrelated untracked presentation, inspection, documentation, and pnpm artifacts.

---

### Task 1: Week 3 Schema and Durable Outbox Foundation

**Files:**
- Modify: `database/schema.sql`
- Modify: `database/seed.sql`
- Create: `database/migrations/2026-08-10-week-3-operations.sql`
- Create: `app/Contracts/MailOutboxRepositoryInterface.php`
- Create: `app/Repositories/MailOutboxRepository.php`
- Create: `app/Services/MailOutboxService.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Unit/Week3SchemaTest.php`
- Create: `tests/Unit/MailOutboxRepositoryTest.php`
- Create: `tests/Unit/MailOutboxServiceTest.php`
- Create: `tests/Support/FakeMailOutboxRepository.php`
- Create: `tests/verify-week-3-migration-mysql.sh`

**Interfaces:**
- Produces: `MailOutboxRepositoryInterface::enqueue(array $job): ?array`, `claimBatch(int $limit, string $lockToken, DateTimeImmutable $now): array`, `markSent(int $id, string $lockToken, ?string $providerId, DateTimeImmutable $sentAt): bool`, and `releaseFailed(int $id, string $lockToken, int $attempts, DateTimeImmutable $availableAt, string $error, bool $terminal): bool`.
- Produces: `MailOutboxService::enqueue(string $template, string $recipient, array $payload, string $idempotencyKey, ?DateTimeImmutable $availableAt = null): array` with `ok`, `job`, and `errors` keys.
- Consumes: existing `MailTransportInterface`, `EmailLogRepositoryInterface`, `Logger`, and container PDO.

- [ ] **Step 1: Write failing schema and repository tests**

Assert fresh schema and repeatable migration add `mail_outbox`, newsletter confirmation fields, `newsletter_campaigns`, coupon integrity/indexes, contact queue indexes, and private operational settings. Exercise real SQLite outbox enqueue idempotency, pending-order claim, stale-lock recovery, sent CAS, retry CAS, terminal failure, bounded batch sizes, and payload/template allowlists.

- [ ] **Step 2: Run focused RED tests**

Run `php tests/run.php Week3SchemaTest`, `php tests/run.php MailOutboxRepositoryTest`, and `php tests/run.php MailOutboxServiceTest`. Expected: failures identify missing tables, contracts, and classes rather than fixture or syntax errors.

- [ ] **Step 3: Implement the minimal schema, migration, repository, service, fakes, and DI**

Use a unique `idempotency_key CHAR(64)`, `status ENUM('queued','processing','sent','failed')`, attempts, `available_at`, lock token/time, sent time, bounded sanitized error, and deterministic `(status, available_at, id)` index. Hash idempotency material before storage. Accept only explicitly registered template names and template-specific scalar payload fields.

- [ ] **Step 4: Verify GREEN and native MySQL repeatability**

Run the three focused suites, `composer check:syntax`, and the opt-in verifier against a uniquely named disposable MySQL database. The verifier imports a populated current baseline, applies the migration twice, preserves row counts, proves FKs/indexes/constraints/native bindings, and removes only its own database.

- [ ] **Step 5: Commit**

Stage only Task 1 files and commit `build: prepare week 3 operations foundation`.

### Task 2: Mail Worker, Retry Policy, and Template Delivery

**Files:**
- Create: `app/Services/MailOutboxWorker.php`
- Create: `app/Services/QueuedMailTemplateService.php`
- Modify: `app/Services/TransactionMailer.php`
- Modify: `app/Services/AccountMailer.php`
- Modify: `app/Services/NotificationService.php`
- Modify: `bootstrap/app.php`
- Create: `scripts/process-mail-outbox.php`
- Create: `tests/Unit/MailOutboxWorkerTest.php`
- Create: `tests/Unit/QueuedMailTemplateServiceTest.php`
- Modify: `tests/Unit/TransactionMailerTest.php`
- Modify: `tests/Unit/AccountMailerTest.php`

**Interfaces:**
- Produces: `MailOutboxWorker::run(int $limit, DateTimeImmutable $now): array{claimed:int,sent:int,retried:int,failed:int}`.
- Produces: `QueuedMailTemplateService::render(string $template, array $payload): array{subject:string,text:string,html:?string}` with a closed template registry.
- Consumes: Task 1 outbox contract, existing PHPMailer transport, email log repository, and sanitized logger.

- [ ] **Step 1: Write worker and renderer RED tests**

Prove allow-listed rendering for registration, ticket, cancellation, announcement, reminder, contact reply, newsletter confirmation, newsletter campaign, and unsubscribe templates. Prove one provider call per claimed job, success audit, exponential retry bounds, terminal attempts, lock ownership, sanitized provider failure, and no raw tokens or message bodies in logs.

- [ ] **Step 2: Run RED tests**

Run `php tests/run.php MailOutboxWorkerTest` and `php tests/run.php QueuedMailTemplateServiceTest`. Expected: class-not-found or missing-behavior failures only.

- [ ] **Step 3: Implement worker, template registry, CLI entry point, and transactional-mail enqueue adapters**

The CLI accepts only `--limit=1..100`, loads the normal container, claims one bounded batch, exits zero only when processing completed, and prints aggregate counts without recipient data. Existing direct transactional mail remains available for account-critical verification/reset until its one-time link semantics are explicitly preserved; fan-out and reminders always use the outbox.

- [ ] **Step 4: Verify worker failure injection and full regression**

Run focused suites, transaction/account mailer suites, syntax, and the full PHP suite. Use a fake transport to prove provider unavailability does not change committed registration, notification, or announcement state.

- [ ] **Step 5: Commit**

Commit `feat: add durable mail delivery worker`.

### Task 3: Organizer Coupons and Transactional Redemption

**Files:**
- Create: `app/Contracts/CouponRepositoryInterface.php`
- Create: `app/Repositories/CouponRepository.php`
- Create: `app/Services/CouponService.php`
- Create: `app/Controllers/OrganizerCouponController.php`
- Create: `app/Views/organizer/coupons/index.php`
- Create: `app/Views/organizer/coupons/form.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/participant/registrations/checkout.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Contracts/RegistrationRepositoryInterface.php`
- Modify: `app/Repositories/RegistrationRepository.php`
- Modify: `app/Services/RegistrationService.php`
- Modify: `app/Controllers/ParticipantRegistrationController.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Unit/CouponRepositoryTest.php`
- Create: `tests/Unit/CouponServiceTest.php`
- Create: `tests/Unit/OrganizerCouponControllerTest.php`
- Modify: `tests/Unit/RegistrationRepositoryTest.php`
- Modify: `tests/Unit/RegistrationServiceTest.php`
- Modify: `tests/Unit/ParticipantRegistrationControllerTest.php`
- Create: `tests/Support/FakeCouponRepository.php`

**Interfaces:**
- Produces: owned coupon list/detail/create/update/activate/deactivate methods scoped through `organizers.user_id`.
- Produces: `CouponService::quoteForRegistration(int $userId, int $eventId, ?string $code, DateTimeImmutable $now): array` returning exact `base_amount`, `discount_amount`, `final_amount`, `currency`, and coupon identity without mutation.
- Extends: registration reservation to atomically revalidate and consume the quoted coupon, insert `coupon_usage`, increment `used_count`, and persist exact final amounts.

- [ ] **Step 1: Write coupon management and redemption RED tests**

Cover ownership, normalized uniqueness, percentage 1.00-100.00, positive fixed values, event price cap, active window boundaries, usage limit, one use per participant, free final amount, native decimal precision, concurrent final-use winner, transaction rollback, replay of an existing registration, role/CSRF/405/404/409, escaped UI, inline errors, and mobile labels.

- [ ] **Step 2: Run focused RED tests**

Run the six coupon/registration suites and record failures caused only by missing production behavior.

- [ ] **Step 3: Implement repository, service, controller, views, routes, DI, and registration integration**

Lock event, coupon, and existing registration in deterministic order. Re-read all eligibility during reservation. Ignore client quote fields. Preserve payment/ticket behavior when final amount becomes zero. Store applied coupon and discount evidence on registration detail without exposing other users or global usage.

- [ ] **Step 4: Verify GREEN, native concurrency, and UI build**

Run focused suites, full PHP tests, native MySQL final-use concurrency with rollback, CSS build, PHP/JS syntax, and diff check.

- [ ] **Step 5: Commit**

Commit `feat: add organizer coupons and redemption`.

### Task 4: Contact Inbox and Double-Opt-In Newsletter

**Files:**
- Create: `app/Contracts/ContactRepositoryInterface.php`
- Create: `app/Repositories/ContactRepository.php`
- Create: `app/Services/ContactService.php`
- Create: `app/Controllers/PublicContactController.php`
- Create: `app/Controllers/AdminContactController.php`
- Modify: `app/Views/pages/content.php`
- Create: `app/Views/admin/contact/index.php`
- Create: `app/Views/admin/contact/show.php`
- Create: `app/Contracts/NewsletterRepositoryInterface.php`
- Create: `app/Repositories/NewsletterRepository.php`
- Create: `app/Services/NewsletterService.php`
- Create: `app/Controllers/PublicNewsletterController.php`
- Create: `app/Controllers/AdminNewsletterController.php`
- Create: `app/Views/admin/newsletter/index.php`
- Create: `app/Views/admin/newsletter/campaign-form.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Unit/ContactRepositoryTest.php`
- Create: `tests/Unit/ContactServiceTest.php`
- Create: `tests/Unit/ContactControllerTest.php`
- Create: `tests/Unit/NewsletterRepositoryTest.php`
- Create: `tests/Unit/NewsletterServiceTest.php`
- Create: `tests/Unit/NewsletterControllerTest.php`
- Create: `tests/Support/FakeContactRepository.php`
- Create: `tests/Support/FakeNewsletterRepository.php`

**Interfaces:**
- Produces: bounded contact queue/detail/CAS status/reply methods and public `ContactService::submit(array $input, string $ip): array`.
- Produces: double-opt-in subscribe/confirm/unsubscribe token methods and campaign create/queue methods using Task 1 outbox.
- Consumes: trusted `Request::ip()`, CSRF, rate limiter, Task 1 outbox service, current view/provider, and admin role middleware.

- [ ] **Step 1: Write contact/newsletter RED tests**

Cover scalar validation, honeypot no-op, account/IP throttles, enumeration-safe responses, hostile escaping, bounded admin filters/search/page, status CAS/idempotency, queued reply acceptance, random token/hash storage, expiry, replay, subscriber privacy, campaign idempotency, active-confirmed-only recipients, zero-recipient no-op, outbox rollback, formula/PII exclusion, role/CSRF/405/404/409/422, and complete form error associations.

- [ ] **Step 2: Run RED tests**

Run all six focused suites and confirm expected missing-feature failures.

- [ ] **Step 3: Implement the minimum public/admin workflows and accessible views**

Use generic public success copy. Keep subscription forms in the footer and dedicated confirmation pages. Render admin evidence before state actions. Queue replies/campaigns without synchronous SMTP loops. Never expose token hashes, outbox payloads, or bulk recipient emails.

- [ ] **Step 4: Verify GREEN and delivery idempotency**

Run focused/full suites, syntax, CSS/assets, repeated confirmation/campaign tests, and native MySQL recipient fan-out without duplicate jobs.

- [ ] **Step 5: Commit**

Commit `feat: add contact and newsletter operations`.

### Task 5: Event Reminders and Privacy-Safe Calendar Delivery

**Files:**
- Create: `app/Services/EventReminderService.php`
- Create: `app/Services/CalendarService.php`
- Create: `app/Controllers/EventCalendarController.php`
- Create: `scripts/queue-event-reminders.php`
- Modify: `app/Contracts/EventRepositoryInterface.php`
- Modify: `app/Repositories/EventRepository.php`
- Modify: `app/Contracts/RegistrationRepositoryInterface.php`
- Modify: `app/Repositories/RegistrationRepository.php`
- Modify: `app/Views/events/show.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Views/participant/tickets/show.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Unit/EventReminderServiceTest.php`
- Create: `tests/Unit/CalendarServiceTest.php`
- Create: `tests/Unit/EventCalendarControllerTest.php`

**Interfaces:**
- Produces: `EventReminderService::queueDue(DateTimeImmutable $now, int $limit): array` with deterministic reminder idempotency.
- Produces: `CalendarService::forPublicEvent(array $event): string`, `forOwnedRegistration(array $registration): string`, and `googleUrl(array $event): string` from one privacy-normalized payload.
- Consumes: event/registration owner-scoped queries and Task 1 outbox.

- [ ] **Step 1: Write reminder/calendar RED tests**

Cover exactly one 24-hour reminder, repeated cron, deleted/inactive/unverified/cancelled/unpublished exclusion, bounded batching, timezone and UTC conversion, RFC escaping/folding, injection-resistant filename and header, guest restricted-location redaction, confirmed exact location, foreign 404, public/completed eligibility, private cache headers, and method guards.

- [ ] **Step 2: Run focused RED tests**

Run the three focused suites and confirm failures correspond to absent services/controllers.

- [ ] **Step 3: Implement services, queries, CLI, controller, routes, and links**

Generate deterministic UID values from application origin and stable event/registration identifiers. Do not store calendar OAuth credentials. Queue reminders with exact event/registration keys and no participant location payload.

- [ ] **Step 4: Verify GREEN and artifact parsing**

Run focused/full suites, parse emitted ICS in tests for CRLF and required fields, exercise repeated CLI runs in disposable MySQL, run syntax/CSS/diff checks.

- [ ] **Step 5: Commit**

Commit `feat: add event reminders and calendars`.

### Task 6: Accessible Local Analytics Charts

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `scripts/copy-fonts.mjs`
- Modify: `app/Contracts/AnalyticsRepositoryInterface.php`
- Modify: `app/Repositories/AnalyticsRepository.php`
- Modify: `app/Services/ReportService.php`
- Modify: `app/Controllers/OrganizerAnalyticsController.php`
- Modify: `app/Controllers/AdminAnalyticsController.php`
- Modify: `app/Views/organizer/analytics/index.php`
- Modify: `app/Views/admin/analytics/index.php`
- Modify: `app/Views/layouts/dashboard.php`
- Create: `public/assets/js/analytics-charts.js`
- Create: `tests/js/analytics-charts.test.mjs`
- Modify: `tests/js/assets.test.mjs`
- Modify: `tests/Unit/AnalyticsRepositoryTest.php`
- Modify: `tests/Unit/AnalyticsControllerTest.php`

**Interfaces:**
- Extends analytics summaries with bounded daily/monthly event, registration, verified-payment-by-currency, attendance, and category series.
- Produces a hex-safe aggregate chart payload with no user identifiers, names, emails, references, locations, or gateway data.

- [ ] **Step 1: Verify and install the pinned Chart.js dependency**

Confirm it is absent, inspect the current stable package metadata, install an exact compatible version, and copy its production UMD asset and license into `public/assets/vendor/chartjs` through the deterministic asset build.

- [ ] **Step 2: Write repository/controller/JS RED tests**

Prove inclusive date bounds, no join multiplication, native unique placeholders, exact currency strings, deleted historical aggregate inclusion, payload privacy, local-only asset loading, canvas fallback tables, theme colors, reduced motion, responsive lifecycle cleanup, and no-op behavior without chart data or library.

- [ ] **Step 3: Implement aggregate series, safe payloads, local assets, views, and chart lifecycle**

Keep existing semantic tables visible. Treat charts as progressive enhancement. Use one chart instance per canvas, destroy on page hide, recreate on persisted restoration, and read CSS variables for both themes.

- [ ] **Step 4: Verify GREEN and deterministic assets**

Run focused PHP tests, Node harness, Node syntax, CSS/assets build twice with no diff, native MySQL series checks, and full regression.

- [ ] **Step 5: Commit**

Commit `feat: add accessible analytics charts`.

### Task 7: Health, Maintenance, Backup, and Deployment Controls

**Files:**
- Create: `app/Services/HealthCheckService.php`
- Create: `app/Services/MaintenanceService.php`
- Create: `app/Middleware/MaintenanceMiddleware.php`
- Create: `app/Controllers/HealthController.php`
- Create: `app/Controllers/AdminOperationsController.php`
- Create: `app/Views/errors/maintenance.php`
- Create: `app/Views/admin/operations/index.php`
- Create: `scripts/backup-database.php`
- Modify: `app/Contracts/PlatformSettingsRepositoryInterface.php`
- Modify: `app/Repositories/PlatformSettingsRepository.php`
- Modify: `app/Services/PlatformSettingsService.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Modify: `public/index.php`
- Modify: `.env.example`
- Modify: `README.md`
- Create: `deploy/nginx/oems.conf`
- Create: `deploy/systemd/oems-mail-outbox.service`
- Create: `deploy/systemd/oems-mail-outbox.timer`
- Create: `deploy/systemd/oems-reminders.service`
- Create: `deploy/systemd/oems-reminders.timer`
- Create: `deploy/systemd/oems-backup.service`
- Create: `deploy/systemd/oems-backup.timer`
- Create: `tests/Unit/HealthCheckServiceTest.php`
- Create: `tests/Unit/MaintenanceMiddlewareTest.php`
- Create: `tests/Unit/AdminOperationsControllerTest.php`
- Create: `tests/Unit/BackupScriptTest.php`

**Interfaces:**
- Produces: process-only liveness and sanitized readiness component status.
- Produces: cached private maintenance state with super-admin bypass and `503` response contract.
- Produces: CLI backup command constrained to configured database and `storage/backups` with retention 1-30.

- [ ] **Step 1: Write health/maintenance/backup RED tests**

Prove liveness does not query DB, readiness 200/503 and no diagnostic leakage, required migration checks, writable directory checks, maintenance allowlist/bypass/cache invalidation/Retry-After, confirmation-bound admin action, role/CSRF/405, backup password not in argv/output, output confinement, safe permissions, non-empty verification, retention, failure cleanup, and no HTTP restore surface.

- [ ] **Step 2: Run focused RED tests**

Run all four suites and observe behavior failures only.

- [ ] **Step 3: Implement operational services, routes, middleware, UI, CLI, and deployment examples**

Make deployment files example-only with explicit replacement markers for absolute paths and Unix user, not executable secrets. Document migration, drain, backup, artifact migration, workers, health probes, rollback, and restore verification order.

- [ ] **Step 4: Verify GREEN and live operations**

Run focused/full suites, syntax, a disposable backup/restore check, live liveness/readiness, maintenance 503 and admin bypass, Nginx configuration syntax where available, systemd unit static checks where available, and package/secret scans.

- [ ] **Step 5: Commit**

Commit `feat: add production operations controls`.

### Task 8: Week 3 Release QA, Review, and Public Push

**Files:**
- Modify only files required by reproduced release findings.
- Modify: `README.md` for final Week 3 routes, workers, cron, deployment, acceptance journey, and limits.
- Modify: `database/demo_seed.sql` only for repeatable coupon/contact/newsletter demonstration data that contains no real recipient or provider data.
- Add focused regression tests for every release fix.

**Interfaces:**
- Consumes every Task 1-7 interface.
- Produces a reviewed, migrated, testable, documented, production-ready single-node release on public `main`.

- [ ] **Step 1: Run fresh automated gates**

Run full PHP tests, full PHP syntax, strict Composer validation, platform requirements, install dry-run, Composer audit, npm audit, deterministic CSS/assets, all JavaScript behavioral suites, Node syntax, diff check, and tracked secret/private-artifact scan.

- [ ] **Step 2: Run database and CLI gates**

Import schema, seed, and demo twice into a uniquely named disposable MySQL database. Upgrade a populated pre-Week-3 database twice. Verify native coupons, outbox concurrency, newsletter confirmation/campaign fan-out, contact reply, reminders, reports, backup/restore, integrity counts, and automatic exact cleanup.

- [ ] **Step 3: Run live HTTP acceptance**

Exercise organizer coupon creation, participant quote/redemption/free and paid settlement, contact submission/admin reply, newsletter subscribe/confirm/campaign/unsubscribe, reminder queue/worker, public and owned calendar privacy, analytics charts, health, maintenance, role, CSRF, IDOR, method, validation, rate-limit, stale, replay, and missing-resource boundaries.

- [ ] **Step 4: Run browser acceptance**

Use the in-app browser at 320, 768, and 1440 pixels in light and dark themes on public contact/newsletter, checkout coupon, calendar actions, organizer coupon management, admin contact/newsletter/analytics/operations, maintenance, empty/error/success states, and downloads. Verify zero horizontal overflow, console errors, sampled contrast failures, missing labels/error associations, broken focus, wrapped primary desktop CTAs, inaccessible chart-only information, or non-44-pixel scoped mobile controls.

- [ ] **Step 5: Review and fix**

Perform a whole-range Critical/Important security, concurrency, privacy, migration, deployment, and requirements review. Reproduce each valid finding with a failing test, implement one root-cause fix, and rerun the complete relevant gate. Record any truly external limitation precisely.

- [ ] **Step 6: Final commit and push**

Commit release fixes as `fix: close week 3 release findings`, verify local `HEAD` equals the reviewed tree, confirm the target GitHub repository is public `RishadAlam/oems` on `main`, push only tracked project files, and verify remote `main` equals local `HEAD`.

## Plan Self-Review

- Every Week 3 design requirement maps to Tasks 1-8.
- Schema and interfaces are defined before consumers.
- Every new behavior has an explicit RED and GREEN command.
- Native MySQL, exact money, concurrency, privacy, accessibility, CLI, deployment, and browser gates are represented.
- Every task has an independently reviewable commit.
- No task requires real payment-provider credentials, browser push, SMS, calendar OAuth, or multi-node infrastructure.
