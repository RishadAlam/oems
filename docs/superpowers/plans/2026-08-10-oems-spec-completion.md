# OEMS Original Specification Completion Plan

> Execute each task with strict RED, GREEN, refactor. Commit each completed task independently. Preserve unrelated untracked files and never stage credentials or generated user artifacts.

**Goal:** Implement the remaining original-specification capabilities across admin management, organizer communication, analytics, reports, settings, and CMS while preserving the verified event transaction and live-location releases.

**Architecture:** Extend the custom PHP MVC application using its existing repository interfaces, domain services, constructor injection, role and CSRF middleware, server-rendered views, Tailwind assets, and real SQLite/MySQL integration-test patterns. Keep domain writes transactional and isolate notification/email delivery after commit.

**Stack:** PHP 8.2, PDO MySQL/SQLite tests, custom MVC, Tailwind CSS 4, vanilla JavaScript only when progressive enhancement is necessary, Composer and npm release gates.

---

## Task 1: Repair ticket migration and add schema foundation

**Files:**

- Modify: `app/Services/TicketArtifactService.php`
- Modify: `database/schema.sql`
- Add: `database/migrations/2026-08-10-spec-completion.sql`
- Modify: `database/seed.sql`
- Modify: `database/demo_seed.sql`
- Modify: `tests/Unit/TicketArtifactServiceTest.php`
- Add: `tests/Unit/SpecCompletionSchemaTest.php`
- Add: `tests/verify-spec-completion-migration-mysql.sh`
- Modify: `README.md`

**RED:**

- Prove ticket migration removes the tracked deny file today.
- Prove the announcements table and required indexes are absent from the forward migration.
- Prove the migration cannot yet run twice against a populated baseline.

**GREEN:**

- Preserve `.htaccess` and `.gitkeep` while migrating only generated PNG/PDF ticket artifacts.
- Add the announcements table to fresh schema and a repeatable populated-database migration.
- Seed only safe public setting defaults and repeatable demo announcement data if it represents a truthful delivered journey.
- Run focused tests, MySQL migration twice, full tests, syntax, diff check.

**Commit:** `fix: prepare specification completion foundation`

## Task 2: Admin users, organizers, and event deletion

**Files:**

- Add: `app/Contracts/AdminPeopleRepositoryInterface.php`
- Modify: `app/Contracts/EventRepositoryInterface.php`
- Add: `app/Repositories/AdminPeopleRepository.php`
- Modify: `app/Repositories/EventRepository.php`
- Add: `app/Services/AdminPeopleService.php`
- Add: `app/Controllers/AdminUserController.php`
- Add: `app/Controllers/AdminOrganizerController.php`
- Modify: `app/Controllers/AdminEventController.php`
- Add: `app/Views/admin/users/index.php`
- Add: `app/Views/admin/users/show.php`
- Add: `app/Views/admin/organizers/index.php`
- Add: `app/Views/admin/organizers/show.php`
- Modify: `app/Views/admin/events/show.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add: `tests/Support/FakeAdminPeopleRepository.php`
- Add: `tests/Unit/AdminPeopleRepositoryTest.php`
- Add: `tests/Unit/AdminPeopleServiceTest.php`
- Add: `tests/Unit/AdminPeopleControllerTest.php`
- Modify: `tests/Unit/AdminEventControllerTest.php`
- Modify: `tests/Unit/ProfileRouteSecurityTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`

**RED:**

- Prove bounded people filters, privacy, self-protection, super-admin protection, compare-and-swap actions, session revocation, organizer approval rules, and event-delete lifecycle rules are missing.
- Prove routes reject guest, wrong role, CSRF, wrong method, invalid ID, and stale action cases.
- Prove mobile table labels, action names, confirmation text, empty states, and error associations are missing.

**GREEN:**

- Implement paginated admin people repositories and transactional service operations.
- Add post-commit notifications and sanitized persistence logging.
- Add safe event soft deletion for terminal/editable statuses only.
- Add accessible admin pages that reuse the existing visual system at 320/768/1440.
- Run focused tests, native MySQL compare-and-swap checks, full suite, syntax, CSS build, route/UI audits.

**Commit:** `feat: add administrator people management`

## Task 3: Organizer announcements

**Files:**

- Add: `app/Contracts/AnnouncementRepositoryInterface.php`
- Add: `app/Repositories/AnnouncementRepository.php`
- Add: `app/Services/AnnouncementService.php`
- Add: `app/Controllers/OrganizerAnnouncementController.php`
- Add: `app/Views/organizer/announcements/index.php`
- Add: `app/Views/organizer/announcements/create.php`
- Modify: `app/Views/organizer/events/show.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add: `tests/Support/FakeAnnouncementRepository.php`
- Add: `tests/Unit/AnnouncementRepositoryTest.php`
- Add: `tests/Unit/AnnouncementServiceTest.php`
- Add: `tests/Unit/OrganizerAnnouncementControllerTest.php`
- Modify: `tests/Unit/OrganizerEventControllerTest.php`

**RED:**

- Prove owner scope, event lifecycle, organizer approval, recipient eligibility, field bounds, request replay, atomic delivery failure, output escaping, and recipient counts are absent.

**GREEN:**

- Persist an announcement, bulk in-app notifications, recipient count, and audit row transactionally.
- Use a unique request key for idempotent replay.
- Add a confirmation step and history workspace.
- Roll back the complete send if notification persistence fails.
- Run focused and full verification.

**Commit:** `feat: add organizer participant announcements`

## Task 4: Analytics and reports

**Files:**

- Add: `app/Contracts/AnalyticsRepositoryInterface.php`
- Add: `app/Repositories/AnalyticsRepository.php`
- Add: `app/Services/ReportService.php`
- Add: `app/Controllers/AdminAnalyticsController.php`
- Add: `app/Controllers/OrganizerAnalyticsController.php`
- Add: `app/Controllers/AdminReportController.php`
- Add: `app/Views/admin/analytics/index.php`
- Add: `app/Views/admin/reports/index.php`
- Add: `app/Views/organizer/analytics/index.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add: `tests/Unit/AnalyticsRepositoryTest.php`
- Add: `tests/Unit/ReportServiceTest.php`
- Add: `tests/Unit/AnalyticsControllerTest.php`
- Add: `tests/Unit/ReportControllerTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`

**RED:**

- Prove role/ownership scope, deleted-user privacy, deleted-event treatment, date validation, exact decimal totals, zero denominators, report allowlists, formula injection, pagination, and private CSV headers are absent.

**GREEN:**

- Implement server-side aggregates and bounded breakdowns.
- Export events, registrations, payments, attendance, and organizers with explicit safe columns.
- Build semantic summary pages and canonical tables with compact accessible mobile presentation.
- Run focused, native MySQL aggregate, full, CSS, security-string, and diff gates.

**Commit:** `feat: add operational analytics and reports`

## Task 5: Allowlisted settings and CMS content

**Files:**

- Add: `app/Contracts/SettingRepositoryInterface.php`
- Add: `app/Contracts/CmsRepositoryInterface.php`
- Add: `app/Repositories/SettingRepository.php`
- Add: `app/Repositories/CmsRepository.php`
- Add: `app/Services/SettingService.php`
- Add: `app/Services/CmsService.php`
- Add: `app/Controllers/AdminSettingController.php`
- Add: `app/Controllers/AdminCmsController.php`
- Add: `app/Controllers/PublicContentController.php`
- Add: `app/Views/admin/settings/edit.php`
- Add: `app/Views/admin/cms/index.php`
- Add: `app/Views/admin/cms/page-form.php`
- Add: `app/Views/admin/cms/faq-form.php`
- Add: `app/Views/admin/cms/banner-form.php`
- Add: `app/Views/pages/show.php`
- Add: `app/Views/pages/faq.php`
- Modify: `app/Controllers/HomeController.php`
- Modify: `app/Views/home/index.php`
- Modify: public and dashboard layouts
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Add: repository fakes under `tests/Support/`
- Add: `tests/Unit/SettingRepositoryTest.php`
- Add: `tests/Unit/SettingServiceTest.php`
- Add: `tests/Unit/CmsRepositoryTest.php`
- Add: `tests/Unit/CmsServiceTest.php`
- Add: `tests/Unit/AdminCmsControllerTest.php`
- Add: `tests/Unit/PublicContentControllerTest.php`
- Modify: `tests/Unit/HomeControllerTest.php`
- Modify: `tests/Unit/UiLayoutTest.php`

**RED:**

- Prove arbitrary setting keys, secret exposure, invalid type fallback, fixed-route mutation, raw HTML, draft exposure, FAQ activation, banner scheduling, link validation, upload cleanup, metadata, empty/error states, and theme/responsive UI contracts are not implemented.

**GREEN:**

- Add catalog-driven public settings with safe defaults.
- Add transactional fixed-page update/publish behavior, FAQ management, and scheduled home-banner management.
- Render public plain-text paragraphs, native FAQ disclosures, static scheduled banners, and safe metadata.
- Integrate home hero/footer copy without changing established primary navigation or event SEO.
- Run focused and full verification plus both-theme browser checks.

**Commit:** `feat: add platform settings and cms pages`

## Task 6: Release verification and final review

**Files:**

- Modify: `README.md`
- Modify: `.env.example`
- Add or modify release-test scripts only where they execute real behavior.
- Add ignored evidence under `.superpowers/sdd/2026-08-10-oems-spec-completion/`.

**Verification:**

1. Run the full PHP suite and syntax scan.
2. Run Composer strict validation, platform requirements, install dry-run, and advisory audit.
3. Run npm audit, deterministic CSS/assets build, Node syntax, and all JavaScript suites.
4. Import schema, seed, and demo data twice into a unique disposable MySQL database.
5. Upgrade a populated pre-completion database twice and prove preserved counts/data.
6. Run native MySQL compare-and-swap, money, privacy, announcement, settings, and aggregate checks.
7. Run HTTP journeys for every new route and its guest, role, CSRF, IDOR, method, validation, stale, and repeat boundaries.
8. Run in-app browser QA at 320/768/1440, light/dark, keyboard, focus, contrast, overflow, console, empty/error/success states, and CSV download.
9. Run package, tracked-project-file, secret, dash-copy, and diff audits.
10. Request independent whole-range code review. Fix every Critical and Important finding test-first in separate commits.
11. Confirm configured server health and clean tracked/index state.

**Commit:** `fix: close specification completion findings`

## Task 7: Public repository delivery

1. Verify every expected local commit exists and has a focused subject.
2. Verify only project files are tracked or staged.
3. Run final full suite and diff check after the last review fix.
4. Push `main` to `origin` using `gh` authentication.
5. Confirm the remote head equals the local head and the repository remains public.

No additional commit is created unless delivery verification finds a real project defect.
