# OEMS Project-Wide Quality Audit Design

## Objective

Review the complete OEMS application for reproducible correctness, security, validation, navigation, accessibility, responsive-layout, and interaction defects. Fix every issue found by the defined audit, protect each behavior with a regression test where practical, and preserve the existing product architecture, routes, brand, and data model unless a confirmed defect requires a focused change.

"Issue free" means the documented automated suite, structural audits, and critical browser journeys complete without known failures. It does not claim that undiscovered defects can never exist.

## Product and design direction

This is a preservation-focused audit of a trust-sensitive event platform. The existing native PHP MVC architecture, Tailwind CSS build, Manrope typography, Phosphor icons, blue accent, light public theme, and dark dashboard theme remain the design foundation.

- Design variance: 4. Layouts stay orderly and task-focused.
- Motion intensity: 2. Motion is limited to feedback and state transitions.
- Visual density: 5. Administrative views remain efficient without becoming crowded.
- Routes, field names, legal copy, logo treatment, and primary navigation labels remain stable unless they are the confirmed source of a defect.

The frontend taste skill applies only to public and marketing surfaces. Dashboards, data tables, and multi-step forms are audited with established product UI, form, and accessibility patterns because that skill explicitly excludes those surfaces.

## Recommended audit architecture

The audit uses three complementary layers:

1. Automated baseline: run the complete PHP and JavaScript suites, syntax validation, asset build, and repository whitespace checks.
2. Structural review: compare registered routes, navigation links, form methods, CSRF middleware, client and server validation rules, repository projections, status labels, empty states, and responsive CSS patterns.
3. Browser acceptance: exercise public, participant, organizer, pending-organizer, and administrator journeys at desktop and mobile widths, including invalid submissions and authorization boundaries.

An automated-only audit would miss visual and interaction defects. A browser-only audit would miss unvisited security branches and data-contract regressions. The layered approach provides the strongest evidence with the least architectural risk.

## Audit batches

### Baseline and inventory

- Record the current branch, working tree, recent commits, route inventory, test inventory, and build commands.
- Run the complete existing test suite before modifying production code.
- Preserve unrelated untracked files.

### Backend and security correctness

- Verify every state-changing route requires the expected authentication, role, and CSRF middleware.
- Verify resource mutations are scoped to the authenticated owner or administrator.
- Verify user-visible status labels come from persisted state rather than hardcoded assumptions.
- Verify redirects, flash errors, and authorization failures are actionable and do not disclose sensitive internals.
- Add a failing test before each production behavior change.

### Forms and navigation consistency

- Compare every form's method and action with the registered route.
- Compare required, range, format, conditional, file, and confirmation rules between browser markup, shared JavaScript validation, controller validation, service validation, and repository constraints.
- Verify each field has a visible label, associated help or error text, autocomplete semantics where applicable, and non-overlapping icon padding.
- Verify navigation contains no duplicate destinations under different labels unless the labels represent intentionally distinct views.
- Verify destructive actions require explicit confirmation and valid CSRF protection.

### Accessibility and responsive behavior

- Verify keyboard focus, focus restoration, landmarks, dialog semantics, error summaries, live status messages, contrast, touch targets, reduced-motion behavior, and screen-reader names.
- Verify public and authenticated layouts at mobile, tablet, desktop, zoomed text, empty states, validation states, long content, and file-selection states.
- Preserve one coherent radius, color, typography, and spacing system.

### Critical browser journeys

- Guest: browse, filter, view, contact, newsletter, authentication, password reset, location, blog, and certificate surfaces.
- Participant: dashboard, registration, payment reference, ticket, favorite, waitlist, notification, certificate, review, and profile surfaces.
- Organizer: dashboard, event lifecycle, venue, coupon, participant, check-in, announcement, review, analytics, and profile surfaces.
- Pending organizer: draft access, approval guidance, and blocked submission behavior.
- Administrator: moderation, organizer approval, user state, payment review, category, CMS, blog, newsletter, contact, report, analytics, settings, and operations surfaces.

Browser journeys must test successful rendering and representative invalid, empty, unauthorized, and narrow-screen states. Mutating demo data is avoided unless a transaction is safely reversible or a dedicated fixture is used.

## Data flow and error handling

Input is validated first by HTML and shared client JavaScript for immediate guidance, then independently by the controller or service before persistence. Client validation is never trusted as authorization or data integrity enforcement. Repository queries expose the persisted states required by the view, while views derive labels and available actions from those states. Invalid requests return the user to a safe screen with field-specific errors or a contextual alert.

## Test strategy

Every confirmed behavior defect follows red-green-refactor:

1. Add the smallest regression test that names the break.
2. Run it and confirm the expected failure.
3. Make the smallest production change that addresses the root cause.
4. Run the focused test and relevant neighboring suites.
5. Run the complete regression suite before committing the batch.

Generated CSS and PWA assets are rebuilt whenever their source or cache version changes. The final gate is the full PHP suite, all JavaScript tests, PHP syntax validation, CSS and asset build, repository diff check, and browser acceptance evidence.

## Commit discipline

Each completed, independently verifiable audit batch receives its own commit. Only files belonging to that batch are staged. Existing unrelated untracked files remain untouched.

