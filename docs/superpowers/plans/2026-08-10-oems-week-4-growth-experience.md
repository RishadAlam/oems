# OEMS Week 4 Growth and Experience Completion Plan

> Execute each task with strict RED, GREEN, refactor. Commit every completed task independently. Preserve unrelated untracked files and never stage credentials or generated user artifacts.

**Goal:** Finish the attendee-growth and experience milestone with waitlists, calendar/API discovery, certificates, Blog publishing, recoverable event trash, PDF/Excel report exports, a privacy-safe PWA, and final production verification.

**Architecture:** Extend the existing PHP MVC contracts, repositories, services, controllers, server-rendered views, Tailwind system, and Vanilla JavaScript progressive enhancement. Reuse current event, registration, ticket, notification, CMS, analytics, upload, private-artifact, and security boundaries.

**Stack:** PHP 8.2, PDO MySQL/SQLite tests, custom MVC, FPDF, Tailwind CSS 4, Vanilla JavaScript, local assets only.

---

## Task 1: Add the Week 4 schema and migration foundation

**Files:**

- Modify: `database/schema.sql`
- Add: `database/migrations/2026-08-10-week-4-growth-experience.sql`
- Modify: `database/seed.sql`
- Modify: `database/demo_seed.sql`
- Add: `tests/Unit/Week4SchemaTest.php`
- Add: `tests/verify-week-4-migration-mysql.sh`
- Modify: `README.md`

**RED:** Prove waitlist queue fields/indexes, certificate storage, Blog posts, safe foreign keys, and repeatable populated-database migration are absent.

**GREEN:** Add fresh-schema and guarded forward-migration definitions, safe defaults, repeatable demo rows, and twice-run native MySQL verification with preserved Week 3 counts.

**Commit:** `build: prepare week 4 growth foundation`

## Task 2: Implement waitlist enrollment and atomic promotion

**Files:**

- Add: `app/Contracts/WaitlistRepositoryInterface.php`
- Add: `app/Repositories/WaitlistRepository.php`
- Add: `app/Services/WaitlistService.php`
- Add: `app/Controllers/ParticipantWaitlistController.php`
- Add: `app/Views/participant/waitlist/index.php`
- Modify: `app/Contracts/RegistrationRepositoryInterface.php`
- Modify: `app/Repositories/RegistrationRepository.php`
- Modify: `app/Services/RegistrationService.php`
- Modify: `app/Controllers/ParticipantRegistrationController.php`
- Modify: `app/Controllers/AdminPaymentController.php`
- Modify: event/participant/organizer views and dashboard navigation
- Modify: `app/Services/NotificationService.php`
- Modify: queued-mail template allowlists
- Add: `scripts/promote-waitlists.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add focused repository/service/controller/route/UI/native-MySQL tests and fakes

**RED:** Prove sold-out join, leave, duplicate truth, queue order, seat accounting, free/paid promotion, payment/ticket creation, concurrency, failure rollback, retry CLI, ownership, role, CSRF, and UI states are missing.

**GREEN:** Implement database-derived waitlisting and stable-lock promotion with post-commit notification/email isolation and CLI reconciliation.

**Commit:** `feat: add event waitlists and promotion`

## Task 3: Add calendar discovery and public events API

**Files:**

- Extend: `app/Contracts/EventRepositoryInterface.php`
- Extend: `app/Repositories/EventRepository.php`
- Add: `app/Services/PublicEventApiService.php`
- Add: `app/Controllers/PublicCalendarController.php`
- Add: `app/Controllers/ApiEventController.php`
- Add: `app/Views/events/calendar.php`
- Modify: public navigation and `app/Views/events/index.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add focused repository/service/controller/API/route/UI tests

**RED:** Prove strict month/range handling, lifecycle/category/organizer privacy, restricted-location scrubbing, stable JSON fields, exact money strings, pagination, validators, 404/405/422 behavior, semantic month/list UI, and mobile rendering are absent.

**GREEN:** Implement one privacy-safe date-range query consumed by the calendar page and read-only `/api/v1/events` endpoints.

**Commit:** `feat: add calendar discovery and public api`

## Task 4: Add private attendance certificates

**Files:**

- Add: `app/Contracts/CertificateRepositoryInterface.php`
- Add: `app/Repositories/CertificateRepository.php`
- Add: `app/Services/CertificateArtifactService.php`
- Add: `app/Services/CertificateService.php`
- Add: `app/Controllers/ParticipantCertificateController.php`
- Add: `app/Controllers/PublicCertificateController.php`
- Add: participant certificate views and public verification view
- Modify: participant ticket/registration/dashboard views and navigation
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add focused artifact/repository/service/controller/route/UI tests

**RED:** Prove completion/attendance eligibility, ownership, private storage, one-certificate idempotency, concurrency, rollback cleanup, random hashed verification, revoked/invalid behavior, disclosure bounds, and protected downloads are absent.

**GREEN:** Generate path-confined private FPDF certificates and a bounded public verification result backed by a hashed high-entropy token.

**Commit:** `feat: add verified attendance certificates`

## Task 5: Add Blog publishing and public reading

**Files:**

- Add: `app/Contracts/BlogRepositoryInterface.php`
- Add: `app/Repositories/BlogRepository.php`
- Add: `app/Services/BlogService.php`
- Add: `app/Controllers/AdminBlogController.php`
- Add: `app/Controllers/PublicBlogController.php`
- Add: `app/Views/admin/blog/*`
- Add: `app/Views/blog/index.php`
- Add: `app/Views/blog/show.php`
- Modify: public and dashboard navigation/layout metadata
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add focused repository/service/controller/route/SEO/upload/UI tests

**RED:** Prove fixed public routes, draft privacy, slug uniqueness, validation, idempotent publication, soft deletion, image safety/cleanup, plain-text escaping, SEO/Open Graph, pagination, noindex preview, and responsive states are absent.

**GREEN:** Add administrator-owned plain-text Blog CMS and public list/detail pages using existing image and design-system protections.

**Commit:** `feat: add blog publishing and discovery`

## Task 6: Add event trash recovery and report formats

**Files:**

- Extend: event repository contract/repository/service/controllers
- Add: `app/Views/admin/events/trash.php`
- Add: `app/Views/organizer/events/trash.php`
- Extend: `app/Services/ReportService.php`
- Extend: report controllers and views
- Add: `app/Services/ReportArtifactService.php`
- Modify: routes, DI, and role navigation
- Add focused trash/recovery/PDF/SpreadsheetML/controller/route/UI tests

**RED:** Prove scoped trash listing, no-history restore guard, approval preservation, CAS/audit, guest/role/CSRF/IDOR/method handling, PDF bounds, Excel formula safety, private headers, and format-consistent filtering are absent.

**GREEN:** Add recoverable event soft-delete workspaces and safe PDF/Excel-compatible exports backed by the existing report data.

**Commit:** `feat: add event recovery and report formats`

## Task 7: Add the privacy-safe PWA shell

**Files:**

- Add: `public/manifest.webmanifest`
- Add: `public/service-worker.js`
- Add: `public/offline.html`
- Add: `public/assets/js/pwa.js`
- Add: local brand icon assets
- Modify: public/auth/dashboard layouts as appropriate
- Modify: `public/router.php` and public-file policy only if required for correct MIME/cache headers
- Modify: build/copy scripts where deterministic assets require it
- Add: JavaScript behavior and static-policy tests
- Modify: `README.md`

**RED:** Prove manifest metadata, explicit scope/start URL, local icons, offline fallback, cache-version cleanup, static-only caching, private/API/auth/download exclusion, no-POST interception, lifecycle cleanup, CSP compatibility, and unsupported-browser fallback are absent.

**GREEN:** Add an installable shell whose service worker caches only allow-listed versioned public assets and a generic offline page.

**Commit:** `feat: add privacy safe progressive web app`

## Task 8: Complete Week 4 release verification

**Files:**

- Modify: `README.md`
- Modify: `.env.example` only for new nonsecret runtime settings
- Add/modify release verifiers that execute real behavior
- Store ignored evidence under `.superpowers/sdd/2026-08-10-oems-week-4-growth-experience/`

**Verification:**

1. Full PHP/syntax/Composer/platform/install/audit gates.
2. npm audit, deterministic asset build, Node syntax, all JavaScript harnesses.
3. Fresh and populated MySQL migration twice plus native concurrency/privacy checks.
4. End-to-end HTTP waitlist, certificate, calendar/API, Blog, restore, report, and PWA journeys.
5. Role/CSRF/IDOR/method/validation/rate-limit/stale/replay/failure injection.
6. In-app browser 320/768/1440 light/dark, keyboard, focus, contrast, overflow, empty/error/offline, and console checks.
7. Private artifact, service-worker cache, secret, license, package, and project-only Git audits.
8. Independent final review and closure of every Critical/Important finding.
9. Commit final fixes as `fix: close week 4 release findings`, push verified project-only commits, and confirm remote `main`.

**Completion:** Week 4 milestone and the four-week OEMS roadmap are complete only after this matrix is green.
