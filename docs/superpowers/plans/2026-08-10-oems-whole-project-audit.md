# OEMS Whole-Project Audit and Repair Implementation Plan

> Execution method: subagent-driven development with a fresh implementation agent and a fresh review agent for each task. Each confirmed defect must follow RED, root-cause analysis, GREEN, focused regression, full regression, scoped staging, and a separate commit.

Goal: Verify and repair the entire OEMS frontend and backend against the project specification, official security guidance, WCAG 2.2 AA, PHP/MySQL behavior, and live role-based journeys.

Architecture: Preserve the custom PHP 8.2 MVC stack, repository/service boundaries, MySQL schema, Tailwind design system, and vanilla JavaScript progressive enhancement. Add behavior only where a reproducible gap exists. Keep tests close to the affected boundary, exercise real repositories with SQLite/MySQL where appropriate, and validate the final product through HTTP and the in-app browser.

Tech stack: PHP 8.2, custom MVC/OOP, PDO MySQL, Tailwind CSS 4, vanilla JavaScript, PHPMailer, FPDF, endroid/qr-code, Leaflet, custom PHP test runner, Node test runner.

Design reference: `docs/superpowers/specs/2026-08-10-oems-whole-project-audit-design.md`

## Task 1: Framework, authentication, and security boundary audit

Files in scope:

- Inspect/modify: `Core/*.php`, `app/Middleware/*.php`, `app/Controllers/AuthController.php`, `app/Services/AuthService.php`, `app/Repositories/UserRepository.php`, `app/Services/ImageUploadService.php`, `app/Support/StreamHttpClient.php`, `bootstrap/app.php`, `config/*.php`, `public/index.php`, `routes/web.php`, `.env.example`
- Tests: `tests/Unit/SecurityTest.php`, `tests/Unit/RouterTest.php`, `tests/Unit/RateLimiterTest.php`, `tests/Unit/AuthServiceTest.php`, `tests/Unit/AuthControllerMailTest.php`, `tests/Unit/ImageUploadServiceTest.php`, `tests/Unit/ResponseTest.php`, `tests/Unit/StreamHttpClientTest.php`, plus focused new security-contract tests where needed

Steps:

1. Inventory every route and middleware chain, then audit authentication, session rotation/cookie policy, CSRF, role checks, redirect normalization, rate limits, input shapes, error handling, security headers, upload/download confinement, HTTP client limits, and sensitive logging.
2. Reproduce every confirmed gap with a focused behavioral test; verify the test fails for the intended production reason.
3. Implement the smallest complete fix without duplicating business rules or weakening existing compatibility.
4. Run all focused tests, `composer test`, syntax, dependency, asset, and diff gates.
5. Obtain an independent security review; address every valid Critical or Important finding test-first.
6. Commit only Task 1 files as `fix: harden application security boundaries`.

## Task 2: Domain workflow, transaction, and database integrity audit

Files in scope:

- Inspect/modify: `app/Services/*.php`, `app/Repositories/*.php`, `app/Contracts/*.php`, transaction/event/location controllers where orchestration is affected, `database/schema.sql`, `database/seed.sql`, `database/demo_seed.sql`, `database/migrations/*.sql`, `bootstrap/app.php`
- Tests: repository and service suites under `tests/Unit/`, schema/seed integrity suites, and disposable native-MySQL verification scripts under `scripts/` when reusable automation is justified

Steps:

1. Trace account, organizer approval, venue/event lifecycle, moderation/publication, registration/payment/ticket settlement, cancellation, attendance, favorites, notifications, reviews, and live-location state machines from route to database.
2. Audit ownership, soft-deletion privacy, lifecycle compare-and-set predicates, lock order, transaction ownership, capacity accounting, money precision, idempotency, notification failure isolation, timestamps, and hidden/deleted relationship handling.
3. Reproduce confirmed gaps with real SQLite or native MySQL tests before fixing them.
4. Verify fresh schema, base seed, demo seed twice, forward migrations twice over populated supported baselines, indexes/constraints, and critical native MySQL concurrency/rollback behavior in uniquely named disposable databases. Never import into the configured database.
5. Run focused/full gates and independent domain/data review; fix every valid Critical or Important finding test-first.
6. Commit only Task 2 files as `fix: strengthen domain and data integrity`.

## Task 3: Public and authentication frontend audit

Files in scope:

- Inspect/modify: `app/Views/layouts/public.php`, `app/Views/layouts/auth.php`, `app/Views/home/`, `app/Views/events/`, `app/Views/auth/`, `app/Views/errors/`, relevant public/auth controllers, `resources/css/app.css`, `public/assets/js/app.js`, `public/assets/js/location.js`, related local assets
- Tests: `tests/Unit/UiLayoutTest.php`, `tests/Unit/HomeControllerTest.php`, `tests/Unit/PublicEventControllerTest.php`, `tests/Unit/PublicLocationControllerTest.php`, auth controller/view tests, `tests/js/location.test.mjs`, and focused UI/accessibility contracts

Steps:

1. Audit public discovery, event detail, location/map states, login, registration, verification/reset, and branded error states at 320/768/1440 pixels in light/dark themes.
2. Verify document landmarks, heading order, accessible names, native labels, help/error associations, focus visibility/order, status announcements, contrast, target sizes, reflow, keyboard behavior, reduced motion, local assets, image dimensions/fallbacks, canonical/structured metadata, truthful copy, empty states, and console diagnostics.
3. Capture deterministic RED tests for every confirmed defect, then repair the shared design-system boundary before page-local overrides where possible.
4. Rebuild CSS/assets and run focused JS/PHP, full regression, and browser matrices.
5. Obtain an independent frontend/accessibility review and fix every valid Critical or Important finding test-first.
6. Commit only Task 3 files as `fix: polish public and authentication experience`.

## Task 4: Participant, organizer, and administrator frontend audit

Files in scope:

- Inspect/modify: `app/Views/layouts/dashboard.php`, `app/Views/dashboard/`, `app/Views/profile/`, `app/Views/participant/`, `app/Views/organizer/`, `app/Views/admin/`, relevant controllers/data providers, `resources/css/app.css`, `public/assets/js/app.js`, `public/assets/js/check-in.js`, `public/assets/js/venue-map.js`
- Tests: `tests/Unit/DashboardLayoutTest.php`, `tests/Unit/TransactionUiTest.php`, role controller/view suites, `tests/Unit/OrganizerCheckInJavascriptTest.php`, `tests/js/venue-map.test.mjs`, and focused UI/accessibility contracts

Steps:

1. Audit every authenticated workspace and primary action at 320/768/1440 pixels in light/dark themes, including forms, queues, tables/cards, filters, exports, scanning, tickets, notifications, reviews, maps, profile, and empty/error/terminal states.
2. Verify role-specific navigation, active state, information hierarchy, semantic tables and mobile labels, destructive-action clarity, confirmation/feedback, keyboard operation, accessible dialogs/disclosures, error prevention, visible focus, contrast, target sizes, reflow, download behavior, and console diagnostics.
3. Add RED behavior tests, apply design-system-first repairs, rebuild assets, and repeat browser checks.
4. Run focused/full gates and independent frontend/accessibility review; fix every valid Critical or Important finding test-first.
5. Commit only Task 4 files as `fix: refine role workspace usability`.

## Task 5: End-to-end release, operations, and documentation audit

Files in scope:

- Inspect/modify: `README.md`, `.env.example`, `.gitignore`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `scripts/`, `database/`, deployment/runtime documentation, and any narrowly required production/test file exposed by release QA
- Tests: all PHP/JS suites plus reusable HTTP/native acceptance scripts where appropriate

Steps:

1. Start from a fresh disposable MySQL database and run the complete organizer submission, administrator approval, organizer publication, participant verification/favorite/registration/payment, administrator settlement, ticket/QR/PDF, organizer CSV/check-in, completion, review moderation/reply, notifications, cancellation, and privacy journeys.
2. Exercise guest/auth/role/CSRF/IDOR/405/404/422/429 boundaries, hostile form/search/upload values, repeat submissions, and terminal-state retries through real HTTP.
3. Repeat the complete browser matrix in the in-app browser for representative public/authenticated/error/empty/terminal pages; verify responsive/light/dark, keyboard, focus, contrast, labels, live feedback, downloads, maps, images, and empty console.
4. Audit Composer/npm advisories and lock reproducibility, production configuration defaults, security headers, migrations/readme order, seed truthfulness, writable runtime paths, tracked secrets, generated assets, ignored evidence, and public package contents.
5. Fix every newly confirmed issue test-first and commit the verified release slice as `fix: close whole project release findings`.
6. Run a fresh independent whole-project review against this plan and the design. Address all remaining Critical or Important findings in separate fix commits.
7. Run the final root verification matrix, restore the configured local server, confirm HTTP 200, verify the tracked tree/index are clean, push `main`, and confirm `origin/main` equals local HEAD.

## Required final verification

```bash
composer test
composer check:syntax
composer validate --strict
composer check-platform-reqs
composer audit
npm audit --audit-level=moderate
npm run build:css
node --check public/assets/js/app.js
node --check public/assets/js/location.js
node --check public/assets/js/venue-map.js
node tests/js/location.test.mjs
node tests/js/venue-map.test.mjs
git diff --check
git diff --cached --check
```

The final evidence must also include native MySQL migration/seed/transaction results, full HTTP acceptance, browser matrices, package/secret audits, configured-server health, commit hashes for every task, and the verified public repository URL.
