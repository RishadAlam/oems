# OEMS Global Status System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every user-visible OEMS status use one accurate, restrained, theme-safe semantic color system across public, participant, organizer, and administrator surfaces.

**Architecture:** Keep domain state in server-rendered PHP and use the existing `status-chip` and `status-badge` components as the only compact status presentation. Centralize foreground, background, border, and state-to-tone mappings in `resources/css/app.css`; views keep truthful visible labels and choose either their real state suffix or an explicit contextual tone. OEMS `data-theme` variables remain the single theme authority.

**Tech Stack:** PHP 8.2, custom server-rendered MVC views, Tailwind CSS 4, CSS custom properties, DOM-based PHP tests, custom PHP test runner, Node test runner, service worker static cache.

## Global Constraints

- Preserve routes, database enum values, service transitions, form field names, filter values, and visible status wording.
- Keep every status understandable from its text; color is supplementary.
- Use only OEMS semantic variables for status color in server-rendered views.
- Keep identical states visually identical. Do not create decorative rainbow status colors.
- Maintain at least 4.5:1 foreground-to-soft-background contrast in light and dark themes.
- Unknown states must remain visible with the neutral base treatment.
- Do not introduce a new frontend dependency.
- Rebuild generated CSS and publish it under `20260813-global-status-v1`.
- Preserve unrelated tracked and untracked user files.
- Commit each independently verifiable checkpoint.

---

### Task 1: Define the global rendered-status contract with failing tests

**Files:**
- Modify: `tests/Unit/StatusUiTest.php`
- Modify: `tests/Unit/OrganizerOperationsControllerTest.php`

**Interfaces:**
- Consumes: real rendered PHP views, source CSS, compiled CSS, and the existing status component selectors
- Produces: failing regression coverage for the screenshot, theme-authority bypasses, incomplete taxonomy, unstyled fulfillment states, stale compiled CSS, and inaccessible token changes

- [ ] **Step 1: Strengthen the Operations render contract**

Render both healthy and unavailable readiness states. Parse the output with `DOMDocument` and assert these literal outcomes:

```php
$this->assertSame(1, $this->statusCount($available, 'status-badge--info', 'Ready'));
$this->assertSame(3, $this->statusCount($available, 'status-badge--success', 'Passing'));
$this->assertSame(1, $this->statusCount($restricted, 'status-badge--danger', 'Unavailable'));
$this->assertSame(3, $this->statusCount($restricted, 'status-badge--danger', 'Needs attention'));
$this->assertSame(1, $this->statusCount($available, 'status-badge--neutral', 'Inactive'));
$this->assertSame(1, $this->statusCount($restricted, 'status-badge--warning', 'Active'));
```

Also assert that neither rendered document contains `text-emerald-`, `text-red-`, or a `dark:` class token.

- [ ] **Step 2: Protect the complete taxonomy in source and compiled CSS**

Use hand-derived state groups:

```php
$groups = [
    'info' => ['active', 'published', 'valid', 'sent', 'read', 'info'],
    'success' => ['approved', 'confirmed', 'paid', 'completed', 'used', 'replied', 'subscribed', 'present', 'success'],
    'warning' => ['pending', 'waitlisted', 'queued', 'processing', 'new', 'partially_refunded', 'warning'],
    'danger' => ['rejected', 'failed', 'suspended', 'revoked', 'cancelled', 'danger'],
    'neutral' => ['draft', 'inactive', 'archived', 'hidden', 'refunded', 'absent', 'none', 'not_checked_in', 'unsubscribed', 'neutral', 'muted'],
];
```

For both `status-chip` and `status-badge`, assert every suffix resolves to the approved foreground and background in `resources/css/app.css` and `public/assets/css/app.css`. A missing selector, wrong token, or stale build must fail.

- [ ] **Step 3: Add a numeric contrast test for both themes**

Parse the literal `:root` and `[data-theme="dark"]` token blocks. Convert hex RGB to relative luminance and assert these pairs are at least 4.5:1 in both themes:

```php
[
    ['--info', '--info-soft'],
    ['--success', '--success-soft'],
    ['--warning', '--warning-soft'],
    ['--error', '--error-soft'],
    ['--ink-muted', '--surface-soft'],
]
```

Expected current ratios range from 4.69:1 to 7.48:1. The test must derive contrast independently from the CSS tokens.

- [ ] **Step 4: Reject theme-authority bypasses in status-bearing views**

Inspect every `app/Views/**/*.php` file. Assert that no view contains a raw Tailwind semantic palette class or a `dark:` class token. OEMS theme selection is controlled by `html[data-theme]`, so view-level operating-system media-query utilities are never an allowed theme authority.

The rejected palette families are `emerald`, `green`, `red`, `rose`, `amber`, `yellow`, `orange`, `blue`, `sky`, `cyan`, and `teal` when used through `text-*`, `bg-*`, or `border-*` numeric utilities.

- [ ] **Step 5: Require components for the organizer participant fulfillment fixture**

Extend `testParticipantWorkspaceAppliesFiltersAndEscapesOperationalRows()` to assert its existing real controller response contains exactly one status element for each literal pair:

```php
[
    ['status-chip--confirmed', 'Confirmed'],
    ['status-chip--paid', 'Paid'],
    ['status-chip--valid', 'Valid'],
    ['status-chip--not_checked_in', 'Not checked in'],
]
```

Keep the existing escaping, privacy, count, and overflow assertions unchanged.

- [ ] **Step 6: Add representative render coverage for the remaining plain-status surfaces**

Render fixtures for administrator organizer detail, administrator user detail, contact detail, payment detail, administrator analytics, participant favorites, participant registration detail, participant waitlist, and participant dashboard. For every visible state, assert there is a `status-chip` or `status-badge` ancestor with the expected state or contextual tone. Assert no state is represented only by a raw colored text class.

- [ ] **Step 7: Run the focused tests and verify RED**

Run:

```bash
rtk php tests/run.php StatusUiTest
rtk php tests/run.php OrganizerOperationsControllerTest
```

Expected: tests fail only because Operations still uses raw theme utilities, `Ready` is green, `read` and `partially_refunded` have the old taxonomy, the representative status surfaces are plain text, and compiled CSS does not yet contain the new complete contract.

- [ ] **Step 8: Commit the RED checkpoint**

```bash
rtk git add tests/Unit/StatusUiTest.php tests/Unit/OrganizerOperationsControllerTest.php
rtk git commit -m "test: define global status presentation contract"
```

---

### Task 2: Implement the shared semantic status system everywhere

**Files:**
- Modify: `resources/css/app.css`
- Modify: `app/Views/admin/operations/index.php`
- Modify: `app/Views/certificates/verify.php`
- Modify: `app/Views/admin/analytics/index.php`
- Modify: `app/Views/admin/contact/show.php`
- Modify: `app/Views/admin/events/show.php`
- Modify: `app/Views/admin/organizers/show.php`
- Modify: `app/Views/admin/payments/show.php`
- Modify: `app/Views/admin/users/show.php`
- Modify: `app/Views/dashboard/participant.php`
- Modify: `app/Views/organizer/participants/index.php`
- Modify: `app/Views/participant/favorites/index.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Views/participant/waitlist/index.php`
- Test: `tests/Unit/StatusUiTest.php`
- Test: `tests/Unit/OrganizerOperationsControllerTest.php`

**Interfaces:**
- Consumes: the RED rendered-status and taxonomy contracts from Task 1
- Produces: token-based shared status tags on every previously inconsistent or plain-status surface

- [ ] **Step 1: Give chips and badges one professional visual contract**

Keep `.status-chip` and `.status-badge` as the shared base. Give the base a neutral foreground/background/border, a 24px minimum height, 1px semantic border, 6px internal gap, nonwrapping text, compact padding, and tabular-friendly readable type. Use CSS custom properties so every tone changes the same three values:

```css
.status-chip,
.status-badge {
    --status-fg: var(--ink-muted);
    --status-bg: var(--surface-soft);
    --status-border: color-mix(in srgb, var(--ink-muted) 24%, var(--line));
    @apply inline-flex min-h-6 items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-bold leading-4;
    color: var(--status-fg);
    background: var(--status-bg);
    border-color: var(--status-border);
}
```

Each tone group sets only `--status-fg`, `--status-bg`, and `--status-border` from OEMS tokens.

- [ ] **Step 2: Apply the complete five-tone state mapping**

Map `read` to info, `partially_refunded` to warning, and every generic contextual suffix to both component families. Keep refunded neutral. Keep unknown classes neutral through the base rule.

- [ ] **Step 3: Fix Operations and certificate theme authority**

In Operations, render `Ready` with `status-badge--info`. Render each check value as a nested shared badge using `success` for `Passing` and `danger` for `Needs attention`. Retain danger for aggregate `Unavailable`, warning for active maintenance, and neutral for inactive maintenance.

In certificate verification, replace raw emerald and `dark:` utilities with `bg-[var(--success-soft)] text-[var(--success)]`.

- [ ] **Step 4: Migrate administrator evidence and analytics statuses**

- Administrator organizer detail: account state uses its real state suffix; email verification uses success or warning.
- Administrator user detail: email verification uses success or warning.
- Contact detail: message state uses its real suffix.
- Payment detail: header payment status and registration status use shared chips.
- Event detail: both plain `Current status` values use the existing event status chip.
- Administrator analytics top-event lifecycle uses a real event status chip.
- Organizer approval readiness requirement results use success for Completed and danger for Not completed.

- [ ] **Step 5: Migrate participant and organizer fulfillment statuses**

- Organizer participant records: registration, payment, ticket, and attendance values each use their real state suffix.
- Participant registration detail: the payment value becomes a state chip while its explanatory timeline copy remains unchanged.
- Waitlist uses `status-chip--waitlisted`, not the pending color proxy.
- Saved events use a neutral unavailable badge and a lifecycle chip for the stored event status.
- Participant dashboard recent ticket and upcoming payment rows separate identifiers/dates from compact real-state badges.

- [ ] **Step 6: Run focused GREEN verification**

Run:

```bash
rtk php tests/run.php StatusUiTest
rtk php tests/run.php OrganizerOperationsControllerTest
rtk php tests/run.php AdminPeopleControllerTest
rtk php tests/run.php AdminPaymentControllerTest
rtk php tests/run.php AnalyticsControllerTest
rtk php tests/run.php DashboardLayoutTest
rtk php tests/run.php ParticipantTransactionControllerTest
```

Expected: every focused suite passes with no warnings.

- [ ] **Step 7: Commit the source implementation**

Stage only the source CSS, status views, and any directly adjusted focused tests. Commit:

```bash
rtk git commit -m "fix: standardize status presentation globally"
```

---

### Task 3: Build and publish the status styles under a new cache revision

**Files:**
- Modify: `public/assets/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/js/pwa.test.mjs`

**Interfaces:**
- Consumes: Task 2 source CSS and view class inventory
- Produces: production CSS and service-worker references with the exact `20260813-global-status-v1` cache revision

- [ ] **Step 1: Replace the stylesheet cache revision consistently**

Change `20260813-result-summary-v1` to `20260813-global-status-v1` in all four layouts, the service-worker cache name and CSS precache URL, matching PHP test expectations, matching JavaScript expectations, and the UiLayout parser fixture.

- [ ] **Step 2: Rebuild the production stylesheet**

Run:

```bash
rtk npm run build:css
```

Expected: Tailwind writes `public/assets/css/app.css` successfully. The compiled file contains all status selectors and contains no compiled `dark:` palette utility originating from PHP views.

- [ ] **Step 3: Run asset and cache verification**

Run:

```bash
rtk php tests/run.php StatusUiTest
rtk php tests/run.php PwaStaticPolicyTest
rtk php tests/run.php OrganizerVenueControllerTest
rtk php tests/run.php UiLayoutTest
rtk node tests/js/pwa.test.mjs
rtk npm run test:assets
```

Expected: every suite passes and the service worker precaches the new stylesheet URL.

- [ ] **Step 4: Commit the built asset checkpoint**

Stage only the compiled stylesheet, four layouts, service worker, and exact cache tests. Commit:

```bash
rtk git commit -m "build: publish global status styles"
```

---

### Task 4: Verify the complete project and correct only evidence-based regressions

**Files:**
- Modify only if verification exposes a reproducible defect in files already owned by Tasks 1-3
- Create: `.superpowers/sdd/2026-08-13-global-status-system/final-report.md`

**Interfaces:**
- Consumes: the committed source and production status system
- Produces: responsive/theme/browser evidence, full-suite evidence, and a final clean scoped diff

- [ ] **Step 1: Run the core browser matrix**

Verify `/admin/operations`, `/admin/users`, `/admin/organizers`, `/admin/events`, `/admin/payments`, `/organizer/events/10/participants`, `/participant/dashboard`, and `/profile` at 390, 768, 1280, and 2048 pixels in both light and dark themes.

For every route assert:

- no horizontal overflow or clipped status text;
- each visible state remains understandable without color;
- info, success, warning, danger, and neutral computed colors are distinct;
- identical states use identical computed colors;
- noninteractive status tags are skipped by keyboard navigation.

- [ ] **Step 2: Verify theme-preference mismatch and reflow**

At 390 and 1280 pixels, test `/admin/operations` and a verified certificate in these combinations:

- operating system dark, saved OEMS light;
- operating system light, saved OEMS dark.

At 200% zoom, verify Operations, organizer participant records, participant dashboard, and profile remain readable with no collisions or horizontal scrolling.

- [ ] **Step 3: Sample every remaining status domain**

At 1280 pixels in both themes, inspect CMS, blog, contact, newsletter, reviews, organizer dashboard/analytics/coupons, and participant registrations/tickets/reviews/certificates/favorites. Confirm the correct semantic tone for each visible state and neutral fallback for unknown or unavailable content.

- [ ] **Step 4: Run full automated verification**

Run:

```bash
rtk composer check:syntax
rtk composer test
rtk node --test tests/js/*.test.mjs
rtk npm run test:assets
rtk npm run test:forms
rtk git diff --check
rtk git status --short
```

Expected: every command exits successfully. Any pre-existing unrelated dirty/untracked files remain unchanged.

- [ ] **Step 5: Commit only reproducible QA corrections**

If browser or full-suite evidence required corrections, add a failing regression assertion first, implement the smallest fix, rerun the affected and full checks, then commit:

```bash
rtk git commit -m "fix: harden global status presentation"
```

If no correction is needed, do not create an empty commit.

- [ ] **Step 6: Write the final report**

Record commit hashes, exact test counts, routes and dimensions checked, light/dark and operating-system mismatch evidence, contrast ratios, any corrections, and the preserved unrelated worktree list in `.superpowers/sdd/2026-08-13-global-status-system/final-report.md`.
