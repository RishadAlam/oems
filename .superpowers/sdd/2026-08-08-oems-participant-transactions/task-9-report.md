# Task 9 Report: Payment Administration and Dashboard Metrics

## Status

Complete on foundation `8ead15b`.

## RED evidence

- `PaymentRepositoryTest` initially failed because the real paginated administrator queue, count API, deterministic ordering, and safe channel-only hydration did not exist.
- `AdminPaymentControllerTest` initially failed all six scenarios because the controller, routes, and views did not exist.
- `DashboardMetricsRepositoryTest` initially failed because scoped SQL review summaries did not exist and deleted organizers were included in platform totals.
- `DashboardLayoutTest` initially failed because every role still rendered placeholder transaction values and the administrator payment navigation was absent.
- New deleted-event and deleted-user fixtures initially changed registration, payment, ticket, and review aggregate totals, demonstrating that soft-deleted data was counted before the SQL joins were tightened.
- A rejection test exposed a real null-note `TypeError` in `RegistrationService::boundedNote()`. The transaction rolled back; the helper was corrected to handle `null` before truncation.

## GREEN evidence

- `PaymentRepositoryTest`: 6 tests, 69 assertions, 0 failures.
- `RegistrationRepositoryTest`: 11 tests, 98 assertions, 0 failures.
- `TicketRepositoryTest`: 9 tests, 68 assertions, 0 failures.
- `RegistrationServiceTest`: 19 tests, 158 assertions, 0 failures.
- `AdminPaymentControllerTest`: 6 tests, 62 assertions, 0 failures.
- `DashboardMetricsRepositoryTest`: 2 tests, 6 assertions, 0 failures.
- `DashboardLayoutTest`: 18 tests, 83 assertions, 0 failures, rerun after the CSS build.
- `ProfileRouteSecurityTest`: 5 tests, 32 assertions, 0 failures.
- Full suite: 362 tests, 2,195 assertions, 0 failures.
- `composer check:syntax`: clean.
- `npm run build:css`: successful with Tailwind CSS 4.3.3.
- `git diff --check`: clean.

## Queue and evidence handling

- Administrator queue statuses are allow-listed to `pending`, `paid`, `failed`, `refunded`, and `all`; invalid values fall back to `pending`.
- Search is bounded to 120 characters and covers participant name/email, event title, and transaction reference with prepared, unique placeholders and escaped wildcard characters.
- Pagination uses a real SQL count plus bounded limit/offset. Pending records are oldest first with ID tie-breaking; terminal history is newest first with ID tie-breaking.
- Queue and detail hydration expose only settlement evidence needed by administrators. The safe channel is extracted and the raw gateway response is removed before results leave the repository. Ticket digests and QR material are never selected or rendered.
- Views issue no queries, escape dynamic values, provide responsive table labels, accessible empty/pagination states, and present evidence before two explicit non-JavaScript actions.

## Authorization and settlement evidence

- All four routes require `super-admin`; both mutation routes also require CSRF. Participant and organizer access is forbidden, known paths with wrong methods return 405, and malformed, non-positive, or missing IDs return 404.
- Optional notes accept scalar input only, trim blanks to `null`, and enforce the 500-character bound. Only allow-listed queue filters and the bounded note are preserved.
- Verify and reject delegate to the reviewed atomic `RegistrationService` flows. Same-state repeats report the truthful terminal outcome; attempts to reverse an opposite terminal state render a clear 409 conflict.
- The detail page reports the resulting registration, ticket, and seat impact without displaying raw QR data, gateway payloads, credentials, SQL, or internal secrets.

## Metric evidence

- Participant, organizer, and administrator dashboards call registration, payment, and ticket SQL summary APIs directly; they never hydrate lists to count them.
- Narrow review aggregates were added for participant, organizer, and administrator scopes.
- Participant metrics include active/pending/confirmed registrations, pending/paid payment counts, normalized paid total, issued/checked-in tickets, and review counts.
- Organizer metrics use owner-scoped aggregates alongside the existing event summary and recent events. Administrator metrics use global visible aggregates and include pending payment and review work queues.
- Deleted participants and events are excluded from transaction and review summaries. Organizer scope is enforced in SQL. Paid totals are normalized to two-decimal strings.
- No Task 10 notification behavior was introduced.

## Staged-file audit

`git diff --cached --name-only` confirmed that only the following 25 Task 9 paths were staged. `git diff --cached --check` was clean. Unrelated presentation, temporary, document, and package-manager files remain untracked and unstaged:

- `app/Contracts/PaymentRepositoryInterface.php`
- `app/Controllers/AdminPaymentController.php`
- `app/Controllers/DashboardController.php`
- `app/Repositories/DashboardMetricsRepository.php`
- `app/Repositories/PaymentRepository.php`
- `app/Repositories/RegistrationRepository.php`
- `app/Repositories/TicketRepository.php`
- `app/Services/RegistrationService.php`
- `app/Views/admin/payments/index.php`
- `app/Views/admin/payments/show.php`
- `app/Views/dashboard/admin.php`
- `app/Views/dashboard/organizer.php`
- `app/Views/dashboard/participant.php`
- `app/Views/layouts/dashboard.php`
- `public/assets/css/app.css`
- `routes/web.php`
- `tests/Support/FakePaymentRepository.php`
- `tests/Unit/AdminPaymentControllerTest.php`
- `tests/Unit/DashboardLayoutTest.php`
- `tests/Unit/DashboardMetricsRepositoryTest.php`
- `tests/Unit/PaymentRepositoryTest.php`
- `tests/Unit/RegistrationRepositoryTest.php`
- `tests/Unit/RegistrationServiceTest.php`
- `tests/Unit/TicketRepositoryTest.php`
- `.superpowers/sdd/2026-08-08-oems-participant-transactions/task-9-report.md`

Commit message: `feat: add payment administration and metrics`.
