# OEMS Participant Transactions Implementation Plan

> **Execution note:** Follow the test-driven-development skill for every behavior change. Run the focused RED test before implementation, make the smallest GREEN change, refactor only while green, then run the scoped regression and commit the exact task files.

**Goal:** Complete the event transaction milestone so organizers publish approved events, administrators approve and oversee events and payments, and participants discover, register, pay, favorite, receive tickets, attend, and review events.

**Architecture:** Add focused repositories for registration, payment, ticket, favorite, review, and notification persistence. Coordinate multi-table state changes in services backed by a shared PDO transaction. Expose role-scoped controllers and routes, then extend the existing OEMS dashboard and public design system. Preserve prepared statements, SQL-scoped ownership, CSRF, compare-and-set lifecycle updates, and sanitized logging.

**Stack:** PHP 8.2+, MySQL 8, PDO, custom MVC/router/container, Tailwind CSS v4, Vanilla JavaScript, PHPMailer, endroid/qr-code 5.x, setasign/fpdf 1.9.x, custom PHP test runner.

---

## Task 1: Establish transaction contracts, dependencies, and schema invariants

**Files:**

- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `database/schema.sql`
- Create: `app/Contracts/RegistrationRepositoryInterface.php`
- Create: `app/Contracts/PaymentRepositoryInterface.php`
- Create: `app/Contracts/TicketRepositoryInterface.php`
- Create: `app/Contracts/FavoriteRepositoryInterface.php`
- Create: `app/Contracts/ReviewRepositoryInterface.php`
- Create: `app/Contracts/NotificationRepositoryInterface.php`
- Create: `tests/Unit/TransactionSchemaTest.php`
- Modify: `tests/Unit/DemoSeedIntegrityTest.php`

### Steps

1. Write failing schema tests for registration/payment/ticket foreign keys, status constraints, unique participant-event registration, unique transaction references, unique ticket numbers, QR digests, one review per participant-event, one favorite per participant-event, and attendance uniqueness.
2. Add PHP 8.2-compatible QR and PDF packages with Composer and verify the installed package platform requirements.
3. Add only the missing non-destructive indexes or columns needed for queue, lookup, idempotency, or payment-settlement audit traceability. Keep current table names and demo rows compatible.
4. Define narrow interfaces for the data operations required by later services.
5. Run `rtk php tests/run.php TransactionSchemaTest`, `rtk php tests/run.php DemoSeedIntegrityTest`, `rtk composer validate --strict`, and `rtk composer check-platform-reqs`.
6. Commit: `build: prepare transaction domain`.

## Task 2: Build secure ticket artifact generation

**Files:**

- Create: `app/Services/TicketArtifactService.php`
- Create: `tests/Unit/TicketArtifactServiceTest.php`
- Modify: `.gitignore`
- Modify: `bootstrap/app.php`

### Steps

1. Write failing tests for opaque tokens, SHA-256 digest output, unique ticket numbers, QR generation, PDF generation, filename randomization, HTML-free PDF text, and confinement to `public/uploads/tickets`.
2. Implement a ticket-artifact value result that returns the raw one-time QR token, its digest, randomized relative QR/PDF paths, and ticket number.
3. Generate QR and PDF assets with the selected local Composer packages. Do not embed sequential IDs, email addresses, or secrets in the token.
4. Implement safe artifact cleanup and path resolution for later download endpoints.
5. Ignore generated ticket artifacts while keeping the directory available through a committed `.gitkeep` if needed.
6. Run `rtk php tests/run.php TicketArtifactServiceTest` and `rtk composer check:syntax`.
7. Commit: `feat: generate secure event tickets`.

## Task 3: Implement atomic registration, payment, and ticket persistence

**Files:**

- Create: `app/Repositories/RegistrationRepository.php`
- Create: `app/Repositories/PaymentRepository.php`
- Create: `app/Repositories/TicketRepository.php`
- Create: `tests/Support/FakeRegistrationRepository.php`
- Create: `tests/Support/FakePaymentRepository.php`
- Create: `tests/Support/FakeTicketRepository.php`
- Create: `tests/Unit/RegistrationRepositoryTest.php`
- Create: `tests/Unit/PaymentRepositoryTest.php`
- Create: `tests/Unit/TicketRepositoryTest.php`
- Modify: `bootstrap/app.php`

### Steps

1. Write failing SQLite integration tests for visible-event eligibility, participant ownership, duplicate prevention, reactivation, pending and confirmed counts, capacity-safe reservation, cancellation, payment reference uniqueness, payment queues, ticket lookup, token-digest lookup, and duplicate-safe check-in.
2. Implement prepared SQL with driver-safe date expressions and no interpolated user values.
3. Scope participant reads by user ID and organizer participant/check-in reads by organizer user ID in SQL.
4. Use eligible-status compare-and-set updates for cancellation, payment verification/rejection, ticket voiding, and attendance.
5. Add aggregate queries for dashboards rather than hydrating full records to count them.
6. Run each focused repository test and `rtk php tests/run.php EventRepositoryTest`.
7. Commit: `feat: add transaction repositories`.

## Task 4: Coordinate registration, settlement, cancellation, and issuance

**Files:**

- Create: `app/Services/RegistrationService.php`
- Create: `app/Services/TicketService.php`
- Create: `app/Services/TransactionMailer.php`
- Create: `tests/Unit/RegistrationServiceTest.php`
- Create: `tests/Unit/TicketServiceTest.php`
- Create: `tests/Unit/TransactionMailerTest.php`
- Modify: `bootstrap/app.php`

### Steps

1. Write failing service tests for participant role, published status, active category, deadline, event start, capacity, server price, free confirmation, paid pending state, transaction reference validation, idempotent retry, cancellation, payment verification, payment rejection, seat release, rollback, and sanitized logging.
2. Coordinate free registration inside one transaction: reserve seat, confirm registration, record zero payment, create ticket row, and commit before delivery side effects.
3. Coordinate paid registration inside one transaction: reserve seat, create pending registration, and create pending manual payment.
4. Coordinate administrator verification and rejection atomically. Verification confirms and issues once; rejection cancels and releases once.
5. Coordinate participant cancellation and ticket voiding with event-start and attendance guards.
6. Send email and in-app delivery only after commit. A mail failure must not change registration state.
7. Run the focused service tests, repository tests, and syntax check.
8. Commit: `feat: complete registration settlement flow`.

## Task 5: Ship participant checkout, registrations, and tickets

**Files:**

- Create: `app/Controllers/ParticipantRegistrationController.php`
- Create: `app/Controllers/ParticipantTicketController.php`
- Create: `app/Views/participant/registrations/index.php`
- Create: `app/Views/participant/registrations/show.php`
- Create: `app/Views/participant/registrations/register.php`
- Create: `app/Views/participant/tickets/index.php`
- Create: `app/Views/participant/tickets/show.php`
- Modify: `app/Controllers/PublicEventController.php`
- Modify: `app/Views/events/show.php`
- Modify: `routes/web.php`
- Modify: `app/Views/layouts/dashboard.php`
- Create: `tests/Unit/ParticipantRegistrationControllerTest.php`
- Create: `tests/Unit/ParticipantTicketControllerTest.php`
- Modify: `tests/Unit/PublicEventControllerTest.php`
- Modify: `tests/Unit/ProfileRouteSecurityTest.php`

### Steps

1. Write failing route/controller/view tests for guest redirect, participant-only access, CSRF, eligible checkout, free/paid copy, server totals, validation, IDOR-safe details, cancellation, QR ownership, PDF ownership, download headers, sold-out/deadline states, and 405 responses.
2. Add participant routes for checkout, registration history/detail/cancel, ticket list/detail, QR, and PDF.
3. Replace the Week 3 detail placeholder with context-aware registration actions and truthful existing-registration state.
4. Build a summary-first responsive checkout with visible labels, help/error associations, and no card-secret inputs.
5. Build registration and ticket history/detail screens with clear payment, ticket, event, and cancellation states.
6. Stream files only through ownership-scoped endpoints using the ticket artifact confinement checks.
7. Run the focused tests, route security tests, view tests, and syntax check.
8. Commit: `feat: add participant checkout and tickets`.

## Task 6: Add event favorites across discovery and the participant workspace

**Files:**

- Create: `app/Repositories/FavoriteRepository.php`
- Create: `app/Services/FavoriteService.php`
- Create: `app/Controllers/ParticipantFavoriteController.php`
- Create: `app/Views/participant/favorites/index.php`
- Create: `tests/Support/FakeFavoriteRepository.php`
- Create: `tests/Unit/FavoriteRepositoryTest.php`
- Create: `tests/Unit/FavoriteServiceTest.php`
- Create: `tests/Unit/ParticipantFavoriteControllerTest.php`
- Modify: `app/Controllers/PublicEventController.php`
- Modify: `app/Controllers/HomeController.php`
- Modify: `app/Views/home/index.php`
- Modify: `app/Views/events/index.php`
- Modify: `app/Views/events/show.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

### Steps

1. Write failing tests for participant-only add/remove, CSRF, published-event eligibility, SQL-scoped ownership, duplicate idempotency, unavailable-event history, list pagination, and accessible saved/unsaved state.
2. Implement prepared favorite writes and participant discovery queries.
3. Add explicit POST add and remove routes; never accept a user ID from the request.
4. Add favorite controls to event detail and appropriate cards while keeping guest actions as login links.
5. Add a participant favorite list with empty and unavailable states.
6. Run focused tests plus home, public event, security, and view regressions.
7. Commit: `feat: add participant event favorites`.

## Task 7: Add reviews, moderation, and organizer replies

**Files:**

- Create: `app/Repositories/ReviewRepository.php`
- Create: `app/Services/ReviewService.php`
- Create: `app/Controllers/ParticipantReviewController.php`
- Create: `app/Controllers/OrganizerReviewController.php`
- Create: `app/Controllers/AdminReviewController.php`
- Create: `app/Views/participant/reviews/form.php`
- Create: `app/Views/participant/reviews/index.php`
- Create: `app/Views/organizer/reviews/index.php`
- Create: `app/Views/admin/reviews/index.php`
- Create: `tests/Support/FakeReviewRepository.php`
- Create: `tests/Unit/ReviewRepositoryTest.php`
- Create: `tests/Unit/ReviewServiceTest.php`
- Create: `tests/Unit/ReviewControllerTest.php`
- Modify: `app/Views/events/show.php`
- Modify: `app/Controllers/PublicEventController.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

### Steps

1. Write failing tests for completed/past-event eligibility, confirmed registration, one review per participant-event, rating/text bounds, pending state, edit-to-pending, public-only published reviews, verified-attendee display, organizer ownership, escaped replies, and moderation compare-and-set transitions.
2. Implement SQL-scoped review reads, writes, aggregate ratings, queues, and replies.
3. Implement participant create/edit, organizer reply, and administrator publish/hide services and controllers.
4. Add published review summaries and replies to public event detail without exposing pending content.
5. Add role workspaces with accessible rating input, moderation controls, status explanations, and empty states.
6. Run focused tests plus public detail, role security, escaping, and view regressions.
7. Commit: `feat: add moderated event reviews`.

## Task 8: Add organizer publication, participants, export, and check-in

**Files:**

- Modify: `app/Contracts/EventRepositoryInterface.php`
- Modify: `app/Repositories/EventRepository.php`
- Modify: `app/Services/EventService.php`
- Modify: `app/Controllers/OrganizerEventController.php`
- Create: `app/Controllers/OrganizerParticipantController.php`
- Create: `app/Controllers/OrganizerCheckInController.php`
- Create: `app/Views/organizer/participants/index.php`
- Create: `app/Views/organizer/check-in/index.php`
- Modify: `app/Views/organizer/events/show.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `routes/web.php`
- Modify: `public/assets/js/app.js`
- Modify: `tests/Unit/EventServiceTest.php`
- Modify: `tests/Unit/OrganizerEventControllerTest.php`
- Create: `tests/Unit/OrganizerOperationsControllerTest.php`

### Steps

1. Write failing tests for approved-only organizer publication, atomic organizer approval recheck, event ownership, participant filter/search, formula-safe CSV export, valid check-in, duplicate scan, invalid/void token, foreign event/ticket, and manual code fallback.
2. Add the organizer publication transition and button without removing administrator emergency transitions.
3. Add organizer participant list and CSV export with SQL ownership scope and safe download headers.
4. Add check-in by QR token or ticket number. Use local camera APIs only as progressive enhancement; manual entry must always work.
5. Add participant and check-in links to owned event actions and dashboard navigation.
6. Run focused tests, lifecycle regressions, JavaScript syntax, and security tests.
7. Commit: `feat: add organizer fulfillment operations`.

## Task 9: Add administrator payment oversight and transaction metrics

**Files:**

- Create: `app/Controllers/AdminPaymentController.php`
- Create: `app/Views/admin/payments/index.php`
- Create: `app/Views/admin/payments/show.php`
- Modify: `app/Repositories/DashboardMetricsRepository.php`
- Modify: `app/Controllers/DashboardController.php`
- Modify: `app/Views/dashboard/admin.php`
- Modify: `app/Views/dashboard/organizer.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `routes/web.php`
- Create: `tests/Unit/AdminPaymentControllerTest.php`
- Modify: `tests/Unit/DashboardMetricsRepositoryTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`

### Steps

1. Write failing tests for payment queue filters, scoped detail, CSRF, verify/reject compare-and-set behavior, optional bounded note, idempotent repeat, paid totals, pending counts, registration counts, ticket counts, attendance counts, and review counts.
2. Add administrator payment routes and queue/detail views with participant, event, organizer, amount, channel, reference, age, and state.
3. Wire verify and reject actions to the registration service and surface the resulting ticket or released seat.
4. Replace placeholder dashboard values with aggregate queries for all roles.
5. Add accurate workspace navigation and status-aware empty states.
6. Run focused tests plus dashboard, route security, and service regressions.
7. Commit: `feat: add payment administration and metrics`.

## Task 10: Add notifications and participant dashboard integration

**Files:**

- Create: `app/Repositories/NotificationRepository.php`
- Create: `app/Services/NotificationService.php`
- Create: `app/Controllers/ParticipantNotificationController.php`
- Create: `app/Views/participant/notifications/index.php`
- Create: `tests/Support/FakeNotificationRepository.php`
- Create: `tests/Unit/NotificationRepositoryTest.php`
- Create: `tests/Unit/NotificationServiceTest.php`
- Create: `tests/Unit/ParticipantNotificationControllerTest.php`
- Modify: `app/Services/RegistrationService.php`
- Modify: `app/Services/ReviewService.php`
- Modify: `app/Controllers/DashboardController.php`
- Modify: `app/Views/dashboard/participant.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

### Steps

1. Write failing tests for participant ownership, unread counts, one/all read actions, CSRF, bounded payload, safe action URLs, registration/payment/ticket/review event delivery, and no transaction rollback on notification failure.
2. Implement notification persistence and a service with an allow-listed notification type and internal path format.
3. Dispatch notifications after successful domain commits for registration, settlement, cancellation, ticket issuance, moderation, and replies.
4. Add notification list/read routes and unread badge navigation.
5. Replace participant dashboard placeholders with upcoming registrations, payment status, tickets, favorites, review actions, and notifications.
6. Run focused tests plus registration, review, dashboard, security, and escaping regressions.
7. Commit: `feat: complete participant workspace notifications`.

## Task 11: Finish demo data, documentation, and visual system

**Files:**

- Modify: `database/seed.sql`
- Modify: `database/demo_seed.sql`
- Modify: `README.md`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `public/assets/js/app.js`
- Modify: relevant transaction views
- Modify: `tests/Unit/DemoSeedIntegrityTest.php`
- Create: `tests/Unit/TransactionUiTest.php`

### Steps

1. Write failing demo/UI tests for repeatable manual payment setup, internally consistent seat counts, nullable ungenerated assets, lifecycle-eligible reviews, dashboard route copy, accessible field associations, responsive table/card contracts, dark-theme tokens, no unavailable milestone copy, and no em/en dashes in visible transaction UI.
2. Make the demo seed idempotent and activate clearly fictional manual payment instructions.
3. Extend the existing OEMS component vocabulary for status steps, monetary summaries, ticket panels, QR frames, queues, rating controls, notification rows, and responsive operations tables.
4. Run the Tailwind build and update JavaScript only for camera enhancement, status toggles, and progressive interaction.
5. Document setup, demo accounts, manual payment workflow, SMTP, ticket storage, exact acceptance journey, and external-gateway limitation.
6. Run focused demo/UI tests, `rtk npm run build:css`, `rtk node --check public/assets/js/app.js`, and `rtk git diff --check`.
7. Commit: `feat: polish transaction milestone experience`.

## Task 12: Release verification, independent review, and public push

**Files:**

- Modify only files required by verified release findings
- Create ignored evidence reports under `.superpowers/sdd/2026-08-08-oems-participant-transactions/`

### Steps

1. Run `rtk composer test`, `rtk composer check:syntax`, `rtk composer validate --strict`, `rtk composer check-platform-reqs`, `rtk composer audit`, `rtk npm run build:css`, `rtk node --check public/assets/js/app.js`, and `rtk git diff --check`.
2. Import `database/schema.sql`, `database/seed.sql`, and `database/demo_seed.sql` into a disposable MySQL database twice. Verify stable counts, foreign keys, ownership, capacity, payment/registration/ticket status consistency, and nullable ungenerated media.
3. Run rollback-only native MySQL checks for competing final-seat registrations, repeated submission, free issuance, paid verification, paid rejection, cancellation, publication, check-in, and review moderation.
4. Exercise the full live HTTP journey: organizer submit, admin approve, organizer publish, participant find/favorite/register/pay, admin verify, participant QR/PDF, organizer check-in, participant review, admin publish, organizer reply.
5. Test 320, 768, and 1440 pixel viewports in light and dark themes. Verify keyboard order, visible focus, labels/help/errors, empty and error states, contrast, images, downloads, console output, and PHP diagnostics.
6. Request an independent code review against the design and fix every Critical or Important finding test-first. Re-run the complete matrix after fixes.
7. Commit verified fixes separately with a scope-specific subject.
8. Confirm only project files are tracked, scan tracked content for secrets, push `main` with `gh`/git, verify the public GitHub commit, restart the local server, and probe health.

## Final Acceptance Checklist

- Organizer approval, publication, participant registration, payment, favorite, ticket, check-in, review, moderation, and reply journeys all work through HTTP.
- No capacity oversell, duplicate settlement, duplicate ticket, duplicate attendance, cross-role access, IDOR, CSRF bypass, client price trust, raw QR secret persistence, unsafe file path, CSV formula, or sensitive log regression remains.
- Free and paid states are truthful and recoverable.
- All dashboards use real aggregate data and all transaction UI is accessible, responsive, and theme-complete.
- Full automated, native MySQL, browser, security, dependency, build, and syntax gates pass.
- Every task and review-fix round has its own commit.
- The public repository contains only project files and no secrets.
