# Task 10 Report: Participant Workspace Notifications

## Scope delivered

- Added persistent, participant-scoped notifications with pagination, unread counts, one-read and all-read state changes.
- Added allow-listed notification types, bounded title/message/data payloads, and participant-internal action paths only.
- Added CSRF-protected participant notification routes and an unread badge in dashboard navigation when a count is available.
- Dispatched registration, payment, ticket, cancellation, review submission, moderation, and organizer reply notifications after domain work commits.
- Ensured notification delivery failures are caught, sanitised, logged by class and identifiers only, and cannot roll back committed domain state.
- Replaced the participant dashboard placeholder panel with real participant-scoped upcoming registrations, payment status, saved-favorite count, review-action count, and unread notification data.

## TDD evidence

1. Notification persistence/service/controller RED:
   `rtk composer test Notification` failed with missing `NotificationRepository`, `NotificationService`, and `ParticipantNotificationController` classes (5 failures).
2. Notification persistence/service/controller GREEN:
   `rtk composer test Notification` passed with 5 tests and 27 assertions.
3. Registration dispatch RED:
   `rtk composer test RegistrationService` failed because the expected notification type sequence was empty.
4. Registration dispatch GREEN:
   `rtk composer test RegistrationService` passed with 22 tests and 178 assertions.
5. Dashboard workspace RED:
   `rtk composer test DashboardMetrics` failed because `participantWorkspace()` did not exist.
6. Dashboard workspace GREEN:
   focused dashboard metrics and layout suites passed after implementation.
7. Latest-payment dashboard regression RED:
   the scoped workspace test failed with `Expected 1, received 2` after a second payment row was introduced.
8. Latest-payment dashboard regression GREEN:
   the query now joins only `MAX(payments.id)` per registration and the suite passed.

## Validation evidence

- `rtk composer test`: 379 tests, 2,286 assertions, 0 failures.
- `rtk composer check:syntax`: all scanned PHP files reported no syntax errors.
- `rtk npm run build:css`: completed after installing the declared frontend dev dependencies; committed stylesheet regenerated.
- `rtk node --test tests/js/check-in-camera.test.mjs`: 1 test passed, 0 failures.
- `git diff --check`: no whitespace errors.
- Security/string audit: no em/en dashes in affected visible copy; notification action paths are constrained by `NotificationService`; CSRF and role routes have focused coverage; notification failure logs omit messages, paths, and payloads.

## Independent review and fixes

- Independent read-only review found no Critical issues.
- Important: dashboard review-action eligibility initially used event start time instead of the review workflow's completed status or ended event condition. Added a RED regression for an ongoing event and aligned the aggregate with `events.status = 'completed' OR events.end_date <= CURRENT_TIMESTAMP`.
- Important: the pre-moderation lookup was outside the service error boundary. Added a RED regression for a thrown lookup, then moved it into the existing sanitized `try`/`catch` path.
- Minor: notification pagination previously showed page state without navigation. Added RED/GREEN coverage and Previous/Next links.

## Files intentionally changed

- `app/Controllers/DashboardController.php`
- `app/Controllers/ParticipantNotificationController.php`
- `app/Repositories/DashboardMetricsRepository.php`
- `app/Repositories/NotificationRepository.php`
- `app/Services/NotificationService.php`
- `app/Services/RegistrationService.php`
- `app/Services/ReviewService.php`
- `app/Views/dashboard/participant.php`
- `app/Views/layouts/dashboard.php`
- `app/Views/participant/notifications/index.php`
- `bootstrap/app.php`
- `routes/web.php`
- `public/assets/css/app.css`
- focused notification, registration, review, dashboard tests and `FakeNotificationRepository`.

## Fix Round 1

### Scope delivered

- Paid registrations now dispatch a distinct `registration_pending` update before the existing `payment_pending` update, only after the successful domain commit. The event uses the participant registration URL and an identifier-only context payload.
- The participant workspace now reads the three newest owned, visible ticket records and three newest owned notification records with deterministic `issued_at`/`created_at` and ID ordering. Deleted participants and deleted event tickets remain excluded.
- The participant dashboard renders those bounded records with ticket, notification, and item-level actions. Stored text is escaped and notification actions are constrained again at render time to the supported participant-internal paths.
- Dashboard-layout unread notification data is now supplied by one View-level provider for every participant dashboard render. It does not run for public layouts or non-participant dashboard users.

### TDD evidence

1. Paid registration pending-update RED:
   `rtk composer test -- tests/Unit/RegistrationServiceTest.php` failed because the dispatch sequence contained `payment_pending` but not `registration_pending`.
2. Paid registration pending-update GREEN:
   the same focused suite passed with 23 tests and 184 assertions, including a failing-notification persistence case for a paid registration.
3. Dashboard record query RED:
   `rtk composer test -- tests/Unit/DashboardMetricsRepositoryTest.php` failed with an undefined `tickets` workspace key.
4. Dashboard record query GREEN:
   the real SQLite repository suite passed with 3 tests and 17 assertions after participant-scoped bounded ticket and notification queries were added.
5. Dashboard record rendering RED:
   `rtk composer test -- tests/Unit/DashboardLayoutTest.php` failed because recent tickets and updates were absent, and the controller fixture exposed the new query dependency.
6. Dashboard record rendering GREEN:
   the layout/controller suite passed with 20 tests and 106 assertions after the panels, ownership/deleted-event fixtures, escaping, and action assertions were added.
7. Central unread badge RED:
   the layout suite failed because `DashboardLayoutDataProvider` did not exist.
8. Central unread badge GREEN:
   the layout suite passed with 22 tests and 113 assertions. Its View-boundary test verifies the participant badge, the non-participant dashboard absence, the public-layout no-op, and unsafe stored action fallback.

### Fix Round 1 validation evidence

- `rtk composer test`: 383 tests, 2,321 assertions, 0 failures.
- `rtk composer check:syntax`: all scanned PHP files reported no syntax errors.
- `rtk npm run build:css`: completed successfully; its generated-only stylesheet drift was restored so the commit contains source and test changes only.
- `rtk node --test tests/js/check-in-camera.test.mjs`: 1 test passed, 0 failures.
- `rtk git diff --check`: no whitespace errors.
- Security/string audit: paid event context is identifier-only; dashboard queries bind the authenticated participant ID and exclude deleted user/event records; dashboard notification links are allow-listed internal participant URLs; all new stored text output is escaped; no em or en dashes were added to visible copy.

### Fix Round 1 files intentionally changed

- `Core/View.php`
- `app/Controllers/DashboardController.php`
- `app/Controllers/ParticipantNotificationController.php`
- `app/Repositories/DashboardMetricsRepository.php`
- `app/Services/DashboardLayoutDataProvider.php`
- `app/Services/RegistrationService.php`
- `app/Views/dashboard/participant.php`
- `bootstrap/app.php`
- `tests/Unit/DashboardLayoutTest.php`
- `tests/Unit/DashboardMetricsRepositoryTest.php`
- `tests/Unit/RegistrationServiceTest.php`
