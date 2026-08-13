# Homepage Journey Pattern Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the homepage's oversized split journey panel with a compact, theme-aware pair of scannable participant and organizer journey cards.

**Architecture:** The existing server-rendered homepage keeps its section order, routes, and copy. The journey view receives explicit semantic classes and meaningful Phosphor icons, while the shared stylesheet renders a token-based responsive card grid with vertical step connectors.

**Tech Stack:** PHP 8 view templates, Tailwind CSS v4 component layer, Phosphor Icons, PHPUnit-style project test runner, Node.js frontend tests.

## Global Constraints

- Preserve `#how-it-works`, the existing audience copy, `/events`, and `/register?role=organizer`.
- Use only existing semantic theme tokens and installed Phosphor icons.
- Keep both paths visible without JavaScript.
- Preserve semantic ordered lists and keyboard focus behavior.
- Support 320px viewports and both light and dark themes.

---

### Task 1: Encode the journey design contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: rendered `app/Views/home/index.php` HTML and `resources/css/app.css` source.
- Produces: a failing contract for `.home-journeys__label`, six `.home-journey__step-icon` markers, token-driven surfaces, a gapped responsive grid, and the absence of the old inverse eyebrow and hardcoded dark panel.

- [x] **Step 1: Write the failing test**

Add assertions to `testHomepageSeparatesDiscoveryParticipantAndOrganizerJourneys()` for the new label, accessible article headings, meaningful icon classes, and six step-icon markers. Update `testHomepageCssControlsResponsiveDensityAndLongAnnouncementCopy()` to require token-driven surfaces and a gapped two-column layout while rejecting the old hardcoded dark panel and split border rules.

- [x] **Step 2: Run test to verify it fails**

Run: `rtk php tests/run.php UiLayoutTest`

Expected: FAIL because the current markup still uses `eyebrow--inverse`, numeric markers, and the monolithic dark split-panel CSS.

- [x] **Step 3: Commit**

Run: `rtk git add tests/Unit/UiLayoutTest.php && rtk git commit -m "test: define homepage journey card pattern"`

### Task 2: Implement the responsive journey cards

**Files:**
- Modify: `app/Views/home/index.php`
- Modify: `resources/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/js/pwa.test.mjs`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`

**Interfaces:**
- Consumes: the Task 1 HTML/CSS contract and existing CSS cache-version references.
- Produces: the `.home-journeys__label`, `.home-journey__header`, `.home-journey__steps`, `.home-journey__step-icon`, and `.home-journey__cta` component pattern plus cache version `20260813-journeys-v1`.

- [x] **Step 1: Implement semantic markup**

Replace the inverse eyebrow with a compact section label; add stable heading IDs and `aria-labelledby` to both articles; replace `01`, `02`, and `03` with meaningful Phosphor icons wrapped by `.home-journey__step-icon`; and apply dedicated step and call-to-action classes.

- [x] **Step 2: Implement token-based styling**

Replace the hardcoded dark surface, shared dividers, and oversized intro with a semantic outer surface, stacked intro, gapped responsive card grid, theme-aware card variants, icon timeline connectors, and aligned calls to action.

- [x] **Step 3: Update the CSS cache key**

Replace `20260813-homepage-v4` with `20260813-journeys-v1` in all layout, service-worker, and cache-policy test references.

- [x] **Step 4: Build and run the focused tests**

Run: `rtk npm run build:css`

Run: `rtk php tests/run.php UiLayoutTest`

Run: `rtk php tests/run.php PwaStaticPolicyTest`

Run: `rtk php tests/run.php OrganizerVenueControllerTest`

Run: `rtk node tests/js/pwa.test.mjs`

Expected: all commands exit 0.

- [x] **Step 5: Commit**

Run: `rtk git add app/Views/home/index.php resources/css/app.css public/assets/css/app.css app/Views/layouts/public.php app/Views/layouts/auth.php app/Views/layouts/dashboard.php app/Views/layouts/maintenance.php public/service-worker.js tests/Unit/UiLayoutTest.php tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php tests/js/pwa.test.mjs && rtk git commit -m "feat: redesign homepage journey pattern"`

### Task 3: Verify the completed section

**Files:**
- Modify: `docs/superpowers/plans/2026-08-13-homepage-journey-pattern-redesign.md`

**Interfaces:**
- Consumes: the built homepage and complete test suites.
- Produces: recorded verification evidence and a final focused commit.

- [x] **Step 1: Run complete automated verification**

Run: `rtk php tests/run.php`

Run: `rtk zsh -lc 'for test_file in tests/js/*.test.mjs; do node "$test_file" || exit 1; done'`

Expected: all PHP and JavaScript tests pass.

- [x] **Step 2: Run visual verification**

Start the local server and inspect `#how-it-works` at desktop, mobile, and 320px widths in both themes. Confirm no horizontal overflow, clipping, inaccessible contrast, or hidden path content.

- [x] **Step 3: Record results and commit**

Mark the plan checkboxes complete, append the exact test/browser evidence, then run `rtk git add docs/superpowers/plans/2026-08-13-homepage-journey-pattern-redesign.md && rtk git commit -m "docs: record journey redesign verification"`.

## Verification results

Completed on 2026-08-13.

- Production CSS build: passed with Tailwind CSS 4.3.3.
- Focused PHP contracts: `UiLayoutTest` 21 tests and 145 assertions; `PwaStaticPolicyTest` 4 tests and 51 assertions; `OrganizerVenueControllerTest` 15 tests and 98 assertions; zero failures.
- Full PHP regression: 826 tests, 6,148 assertions, zero failures. The first restricted-sandbox run could not open loopback sockets; the permitted rerun completed every socket-dependent test successfully.
- Frontend regression: all 47 JavaScript checks passed across charts, assets, navigation, form validation, event location, PWA, and venue location behavior.
- Composer validation: `composer.json` is valid under `--strict`.
- Desktop browser QA at 1280×720: both light and dark themes rendered two equal 529×414 cards with zero horizontal overflow.
- Responsive browser QA: 390px light and 320px dark both rendered a single-column path, retained both audience cards and all calls to action, and reported zero horizontal overflow.
- Accessibility tree: the section heading, two labelled articles, six named actions, and both destination links remained present and correctly ordered.
- Temporary browser-QA files and the local verification server were removed after inspection.
