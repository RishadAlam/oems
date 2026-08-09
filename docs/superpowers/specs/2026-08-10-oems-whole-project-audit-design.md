# OEMS Whole-Project Audit and Repair Design

Date: 2026-08-10
Status: Approved by the user's standing instruction to review and fix the entire frontend and backend without further questions

## Objective

Audit the complete OEMS application against its development specification, its existing behavior, official web-security guidance, WCAG 2.2 AA, and the supported PHP/MySQL platform. Reproduce and fix every confirmed project issue without adding unrelated product scope or weakening existing privacy, ownership, lifecycle, and accessibility guarantees.

## Product and design direction

OEMS remains a trust-first event platform for participants, organizers, and administrators. Preserve the existing cobalt and cool-neutral visual language, Manrope typography, Phosphor icon system, light and dark themes, information architecture, route names, form field names, and role workflows.

The audit may refine hierarchy, spacing, copy, status presentation, responsive behavior, and interaction feedback where a concrete usability or accessibility defect is found. It must not replace the interface with a generic template, introduce ornamental UI, or hide domain evidence behind decorative components.

Design dials:

- Visual variance: 4/10
- Motion intensity: 3/10
- Information density: 6/10
- Accessibility target: WCAG 2.2 AA
- Minimum interactive target: 24 by 24 CSS pixels where WCAG permits, with the existing 44-pixel product target retained for primary and mobile controls

## Standards baseline

- OWASP ASVS 5.0 and official OWASP cheat sheets guide authentication, authorization, session, input, output, upload, logging, and transaction checks.
- WCAG 2.2 guides keyboard access, focus visibility and obstruction, reflow, contrast, labels, errors, status messages, target size, and accessible authentication.
- PHP manual guidance governs password hashing, session cookie attributes, PDO prepared statements, file handling, and safe runtime behavior.
- MySQL constraints, indexes, transactions, locking, idempotency, and forward migrations must agree with service and repository rules.

## Audit workstreams

### 1. Security and framework boundaries

Review the custom router, middleware, session/authentication, CSRF, rate limiting, request/response, validation, logging/redaction, file upload/download, SMTP, HTTP client, and configuration bootstrapping. Exercise guest, participant, organizer, and super-administrator boundaries; malformed identifiers; unsupported methods; hostile input; session rotation; cookie policy; open redirects; content headers; upload signatures; path confinement; and sensitive-data exposure.

### 2. Domain and data integrity

Review every lifecycle from account creation through verification, event moderation/publication, registration/payment/ticket settlement, attendance, cancellation, favorites, notifications, reviews, and live location. Check ownership, visibility, state-transition compare-and-set behavior, transaction isolation, lock order, capacity reconciliation, money precision, idempotency, deletion/privacy behavior, and MySQL/SQLite agreement. Run fresh-schema, repeatable-seed, forward-migration, and native MySQL checks without mutating the configured database.

### 3. Frontend and accessibility

Audit all public, authentication, participant, organizer, and administrator pages at 320, 768, and 1440 CSS pixels in light and dark themes. Verify semantic structure, visible focus, natural keyboard order, label/help/error associations, live feedback, accessible names, contrast, reflow, no horizontal overflow, responsive tables/cards, empty/error/loading states, touch targets, reduced motion, map fallbacks, local assets, image behavior, and console diagnostics.

### 4. Release and operations

Audit dependency advisories and lock consistency, asset reproducibility, environment examples, production-safe defaults, migrations, seed truthfulness, README workflows, package/secret hygiene, runtime writable paths, HTTP security headers, and configured-server health. Public Git history must contain project files only; internal evidence and unrelated local artifacts remain untracked.

## Repair method

1. Establish a clean automated and live baseline.
2. Record a concrete failure with a behavioral test or deterministic acceptance probe.
3. Identify the root cause before changing production code.
4. Apply the smallest complete fix at the correct boundary.
5. Run focused tests, the full regression suite, syntax/assets/dependency gates, and relevant native/live checks.
6. Independently review each implementation slice; repeat RED/GREEN fix rounds for every valid Critical or Important finding.
7. Commit each verified slice with only its intended files.
8. Perform a final whole-project review and push only after all release gates are green.

## Acceptance criteria

- No confirmed Critical or Important issue remains in the audited scope.
- All automated tests pass with no warnings or diagnostics hidden by the harness.
- Native MySQL schema, migrations, seeds, and critical transactions pass in disposable databases.
- Complete guest and role-based HTTP journeys pass, including security boundaries and failure states.
- Browser QA passes at all target widths and both themes with no horizontal overflow, console errors, sampled WCAG contrast failure, keyboard trap, missing focus, broken label/error association, or privacy leak.
- Composer and npm advisories are clear, generated assets are reproducible, tracked secret/package scans are clean, and the configured server is healthy.
- Every completed step is committed, the tracked tree is clean, and the public GitHub branch matches the verified local commit.
