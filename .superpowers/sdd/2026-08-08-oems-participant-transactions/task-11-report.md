# Task 11 Report: Demo, Documentation, and Transaction Visual System

## Scope delivered

- Made the demo manual-payment method repeatable, active only when the demo seed is imported, and explicitly fictional. The base seed keeps it inactive.
- Reconciled each demo event's available seats after registration upserts by counting every pending and confirmed registration, including registrations created before a demo-seed rerun.
- Preserved nullable future QR and PDF paths, unique transaction identifiers, approved event ownership, and review eligibility for confirmed attendees of completed events.
- Added a shared transaction component vocabulary for status steps, money summaries, ticket and QR panels, operations tables, moderation queues, rating controls, notification rows, and semantic transaction status chips.
- Added truthful terminal timeline states for rejected, refunded, cancelled, valid, and checked-in journeys without false success colors or `aria-current` state.
- Added explicit mobile table labels, accessible help and error associations, descriptive rating controls, unread notification semantics, and progressive notification-submit feedback.
- Rebuilt the Tailwind v4 stylesheet for the established Manrope, Phosphor, CSS-variable light/dark system. Existing route structure, brand, 12/18/24px radii, 44px controls, focus treatment, and single accent were preserved.
- Replaced stale milestone documentation with current setup, demo accounts, SMTP fields, fictional manual-payment limitations, ticket storage, and a complete participant/admin/organizer/review acceptance journey.

## TDD evidence

1. Initial demo RED:
   `rtk php tests/run.php DemoSeedIntegrityTest` failed because repeatable fictional manual-payment activation was missing.
2. Initial UI RED:
   `rtk php tests/run.php TransactionUiTest` produced six expected failures for transaction steps/money, cancellation help associations, ticket/QR semantics, responsive queue labels, rating/notification state, and transaction theme tokens.
3. Initial focused GREEN:
   the demo and UI suites passed after the seed, views, CSS, and progressive JavaScript changes.
4. Responsive operations RED/GREEN:
   a new mobile contract failed because the operations table retained its desktop minimum width; the mobile override now resets it before the table becomes labeled cards.
5. Semantic ticket-copy RED/GREEN:
   a used registration ticket initially rendered `Used`; the timeline now renders `Checked in`.
6. Independent-review RED:
   focused tests failed for a globally active demo-only payment method, false terminal timeline state, and missing post-upsert seat reconciliation.
7. Independent-review GREEN:
   `DemoSeedIntegrityTest` passed 11 tests and 67 assertions; `TransactionUiTest` passed 10 tests and 62 assertions; `ParticipantRegistrationControllerTest` passed 10 tests and 69 assertions.

## Validation evidence

- `rtk composer test`: 398 tests, 2,410 assertions, 0 failures.
- `rtk composer check:syntax`: all scanned PHP files reported no syntax errors.
- `rtk composer validate --strict`: valid.
- `rtk composer check-platform-reqs`: PHP 8.3 and all required extensions satisfied.
- `rtk composer audit`: no security vulnerability advisories found. The final audit used approved network access after sandbox DNS failed.
- `rtk npm run build:css`: completed successfully and regenerated `public/assets/css/app.css`.
- `rtk node --check public/assets/js/app.js`: passed.
- `rtk git diff --check`: passed.
- String/accessibility audit: no em or en dashes and no unavailable milestone copy in affected visible transaction views; field help/error IDs, `aria-invalid`, `aria-current`, ticket status labels, unread notification labels, and mobile `data-label` contracts were checked.
- Theme audit: transaction surfaces and rails have explicit light and dark tokens; QR contrast remains white in both themes; status meaning is conveyed by text and icons, not color alone.

## Independent review and fixes

- The independent read-only review found no Critical issues and three Important issues.
- Important: the base seed activated a demo-only manual method while checkout did not render its configuration. Fixed test-first by keeping the base method inactive and activating it only through `demo_seed.sql`.
- Important: rejected, refunded, and cancelled journeys could appear current or green-complete. Fixed test-first with explicit failed, terminal, unavailable, upcoming, current, and complete timeline states.
- Important: a demo-seed rerun could restore hard-coded seat counts after an acceptance-created registration. Fixed test-first with a derived post-upsert reconciliation across all pending and confirmed registrations.
- Independent re-review marked all three findings addressed and found no new Critical or Important regression.

## Files intentionally changed

- `README.md`
- `database/seed.sql`
- `database/demo_seed.sql`
- `resources/css/app.css`
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- participant registration, ticket, review, and notification views
- administrator payment and review queue views
- organizer dashboard and participant operations views
- `tests/Unit/DemoSeedIntegrityTest.php`
- `tests/Unit/ParticipantRegistrationControllerTest.php`
- `tests/Unit/TransactionUiTest.php`

Unrelated untracked presentation, temporary, documentation, and pnpm artifacts were preserved. No `.env` file was read, changed, or staged.

## Fix Round 1: truthful demo transaction journey

### RED evidence

- `rtk php tests/run.php ParticipantTicketControllerTest`: 5 tests, 1 failure. Ticket detail exposed QR and PDF controls when nullable or unsafe artifacts could not be served.
- `rtk php tests/run.php ParticipantRegistrationControllerTest`: 11 tests, 2 failures. Paid checkout omitted fictional manual-payment guidance and did not bound stored instructions; the free-flow exclusion already passed.
- `rtk php tests/run.php PaymentRepositoryTest`: 9 tests, 1 failure. Active method configuration remained an opaque JSON string.
- `rtk php tests/run.php DemoSeedIntegrityTest`: 13 tests, 3 failures. Paid confirmed registration 009 lacked a payment and ticket, used ticket 008 lacked attendance, and the expected coherent ticket set was incomplete.

### GREEN evidence

- `rtk php tests/run.php ParticipantTicketControllerTest`: 5 tests, 66 assertions, 0 failures. Null, QR-only, PDF-only, fully available, and unsafe artifact states are independent and truthful.
- `rtk php tests/run.php ParticipantRegistrationControllerTest`: 11 tests, 81 assertions, 0 failures. Paid guidance is escaped and bounded to its safe allowlist; free checkout excludes it and repository-only secrets never render.
- `rtk php tests/run.php PaymentRepositoryTest`: 9 tests, 78 assertions, 0 failures. Active manual configuration is decoded while inactive methods remain unavailable.
- `rtk php tests/run.php DemoSeedIntegrityTest`: 13 tests, 121 assertions, 0 failures. Parsed cross-table assertions verify every paid confirmed demo registration has a matching paid payment and ticket, and every used ticket has matching present attendance with a scanner relationship.
- `rtk php tests/run.php TransactionUiTest`: 10 tests, 62 assertions, 0 failures after the artifact-availability view contract was applied to the direct render fixture.
- `rtk npm run build:css`: completed successfully with Tailwind CSS v4.3.3.
- `rtk composer test`: 403 tests, 2,504 assertions, 0 failures on the final tree.
- `rtk composer check:syntax`: all scanned PHP files reported no syntax errors.
- `rtk composer validate --strict`: valid.
- `rtk composer check-platform-reqs`: PHP 8.3 and all required extensions satisfied.
- `rtk composer audit`: no security vulnerability advisories found.
- `rtk node --check public/assets/js/app.js` and `rtk git diff --check`: passed with no output.
- Fix-round string and secret audits found no visible dash glyph regression and no production occurrence of the hostile secret test values; only the assertions that prohibit dash glyphs contain those characters.

### Fix Round 1 independent review

- The independent read-only review found no Critical or Important issues.
- It verified confined independent QR/PDF availability, bounded and escaped manual-payment guidance with free-flow and secret exclusion, same-amount paid lifecycle relationships, and present attendance scanned by the event's owning organizer.
- Independent focused verification passed 48 tests and 408 assertions; JavaScript, diff, and compiled-CSS comparison checks were also green.
