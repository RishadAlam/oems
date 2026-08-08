# Task 7 report: moderated event reviews

## Outcome

Implemented the complete moderated review lifecycle across participant, organizer, administrator, and public OEMS surfaces.

## RED evidence

- Initial repository/service run: 10 tests, 10 expected failures because `ReviewRepository` and `ReviewService` did not exist.
- Controller/route/view run: 16 tests with 6 expected failures because the three review controllers, routes, and views did not exist.
- Native prepare regression: duplicate binding test failed with 9 placeholders versus 7 unique names.
- Zero-changed-row regression: identical eligible update returned review ID `0` instead of the existing ID.
- Public ordering regression: an older review with a newer organizer reply incorrectly moved ahead of the newest review.
- Review follow-up RED: atomic first submission used two writes; first-review discovery, row-specific organizer validation, and single active navigation state failed before their fixes.

## GREEN evidence

- Focused review suite: 21 tests, 166 assertions, 0 failures.
- Public event controller suite: 10 tests, 83 assertions, 0 failures.
- Final full suite: 328 tests, 1,931 assertions, 0 failures.
- `composer check:syntax`: all application, framework, route, bootstrap, view, and test PHP files passed.
- `npm run build:css`: Tailwind v4.3.3 completed and local Manrope/Phosphor assets copied.
- `git diff --check`: clean.

## Eligibility and participant ownership

- Actor service checks require an active, email-verified participant role.
- Repository eligibility requires a confirmed registration for the same user and event, plus `completed` event status or `end_date` at or before one application-local clock value bound into the query.
- Attendance is used only for the `verified_attendee` signal and never substitutes for confirmed registration.
- MySQL and SQLite use one atomic upsert guarded by eligibility; the unique event/user key remains the final invariant.
- New and updated reviews return to `pending`; organizer replies are preserved on participant edits.
- Rating is a strict integer from 1 through 5. Trimmed review text is 10 through 2000 characters.
- The participant workspace lists existing reviews and eligible events ready for a first review.

## Organizer ownership and replies

- Organizer list and single-review reads are SQL-scoped through `events.organizer_id -> organizers.user_id` and exclude deleted events.
- Only published reviews can receive replies.
- Cross-organizer access returns the same 404 for valid and invalid payloads.
- Replies are trimmed to 2 through 1000 characters, replace the one current reply, and record `replied_at`.
- Service results expose a notification-safe recipient/type/review/event seam without writing notifications.
- Multiple reply forms use row-specific error IDs and repopulate only the submitted form.

## Moderation

- Admin filters accept only `pending`, `published`, or `hidden`; unsupported input becomes the unfiltered bounded queue.
- Queue order is pending first, then oldest `created_at`, then ID.
- Publish/hide are CSRF-protected compare-and-set transitions from pending.
- Repeating the requested terminal state is truthful and idempotent. A competing opposite terminal state returns HTTP 409.

## Public UI and security

- Public event details query published reviews and published-only aggregate data in a constant two queries, avoiding per-review queries.
- Reviews use deterministic newest-submission order (`created_at DESC, id DESC`); organizer replies do not reorder older reviews.
- Pending and hidden text and metadata are absent from public HTML and JSON-LD.
- Published count/average, verified-attendee labels, participant content, and organizer replies are escaped.
- JSON-LD adds `AggregateRating` only when published reviews exist and preserves existing event structured data.
- Participant, organizer, and admin routes enforce role middleware; every write requires CSRF and positive IDs.
- Views contain no database access, use OEMS Tailwind/Manrope/Phosphor patterns, dual-theme tokens, mobile layouts, 44px controls, accessible fieldsets/legends/help/errors, status copy, and empty states.
- No notifications, payment administration, export/check-in work, or unrelated dashboards were added.

## Review and staging evidence

- Independent code review found no Critical issues. Its three Important findings were fixed: atomic concurrent-safe upsert, discoverable first-review path, and row-specific organizer reply errors. Its dual-active-nav Minor finding was also fixed.
- Unrelated `.tmp`, presentation, lock/workspace, and documentation files were left untouched and unstaged.

## Fix Round 1

### RED

- The application-clock boundary regression failed while SQLite's database clock was after an event whose supplied application clock was still one second before its local end.
- The organizer identical-reply regression failed when a PDO statement reported zero changed rows.
- The review form and compiled stylesheet contracts failed because the visually clipped rating radios had no focus-visible label selector.

### GREEN

- `ReviewRepositoryTest`: 10 tests, 66 assertions, 0 failures.
- `ReviewControllerTest`: 9 tests, 66 assertions, 0 failures.
- Focused `ReviewServiceTest`, `PublicEventControllerTest`, `UiLayoutTest`, and `DashboardLayoutTest`: 40 tests, 236 assertions, 0 failures.
- Full suite: 331 tests, 1,946 assertions, 0 failures.
- `composer check:syntax`: every checked PHP file passed.
- `npm run build:css`: Tailwind v4.3.3 completed and copied seven local font files.

### Corrections

- Review eligibility now obtains one configured-timezone application clock value per repository operation and binds it into every eligibility read and atomic upsert. The same value is reused by the save postcondition, and all native prepared statement placeholders remain unique.
- Organizer replies now use an owner-scoped, published, nondeleted post-update read. An identical reply is truthful success even when the driver reports zero changed rows; hidden, foreign, and missing reviews remain undisclosed.
- Rating labels now expose a semantic focus target with an accent outline and two-pixel offset when an enclosed radio receives keyboard focus. The existing 44px target, checked state, fieldset, legend, and help/error associations remain intact.
