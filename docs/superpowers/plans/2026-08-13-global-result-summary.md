# Global Result Summary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace every dashboard filtered-result sentence with one accessible mini-metric and ensure every displayed count reflects the active filters.

**Architecture:** Add a generic `.result-summary` primitive for seven dashboard result surfaces, retain `.filter-toolbar__summary` only as the five-toolbar layout adapter, and add a panel-heading adapter for Payments and Participants. Keep all existing routes and filter forms unchanged; organizer events derives its displayed count from the filtered, unpaginated event array.

**Tech Stack:** PHP 8.2 views and controllers, DOMDocument-based PHPUnit-style tests, Tailwind CSS v4 source utilities, compiled CSS asset, service-worker cache manifest, Node-based PWA tests.

## Global Constraints

- Use existing OEMS semantic tokens, Manrope typography, 12px component radii, and 18px container radii.
- Add no dependency, icon, animation, shadow, nested card, fixed result-summary width, or absolute positioning.
- Apply the result-summary primitive only to Admin Users, Admin Organizers, Admin Event Moderation, Admin Review Moderation, Organizer Events, Admin Payments, and Organizer Participants.
- Preserve every route, query name, form method, filter value, table, pagination contract, and public event counter.
- Expose exactly one complete result phrase through `role="status"`, `aria-live="polite"`, and `aria-atomic="true"`.
- Preserve correct zero, singular, plural, and large-count behavior.
- Use `count($events)` for the unpaginated Organizer Events result count so it reflects the active status filter.
- Rebuild `public/assets/css/app.css`; never edit the compiled file by hand.
- Prefix every shell command with `rtk`.
- Preserve all unrelated dirty and untracked workspace files.

---

### Task 1: Capture the global semantic, visual, and count-correctness contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php:103-369`
- Modify: `tests/Unit/AdminPeopleControllerTest.php:124-145`
- Modify: `tests/Unit/AdminPaymentControllerTest.php:108-130`
- Modify: `tests/Unit/OrganizerOperationsControllerTest.php:125-146`
- Modify: `tests/Unit/OrganizerEventControllerTest.php:245-263`

**Interfaces:**
- Consumes: the seven view paths and the existing CSS parser helpers in `UiLayoutTest`.
- Produces: one failing DOM contract for `.result-summary`, one failing source/compiled CSS contract, and a failing organizer filtered-count assertion.

- [ ] **Step 1: Strengthen the five-toolbar structural test**

Replace the direct `<strong>` requirement in `testEveryTopLevelFilterToolbarUsesTheSharedDirectChildContract()` with a direct summary requirement equivalent to:

```php
$summaries = $xpath->query('./p[
    contains(concat(" ", normalize-space(@class), " "), " result-summary ")
    and contains(concat(" ", normalize-space(@class), " "), " filter-toolbar__summary ")
]', $toolbar);

$this->assertSame('status', $summary->getAttribute('role'));
$this->assertSame('polite', $summary->getAttribute('aria-live'));
$this->assertSame('true', $summary->getAttribute('aria-atomic'));
```

Require exactly one direct `.result-summary__count`, `.result-summary__copy`, and `.sr-only`, plus one `.result-summary__context` and `.result-summary__subject` inside the copy. Require `aria-hidden="true"` on the visible count and copy so the full hidden phrase is not announced twice.

- [ ] **Step 2: Add one seven-view shared-component test**

Add `testEveryFilteredResultCountUsesTheSharedSemanticSummary()` with this inventory:

```php
$views = [
    'app/Views/admin/users/index.php',
    'app/Views/admin/organizers/index.php',
    'app/Views/admin/events/index.php',
    'app/Views/admin/reviews/index.php',
    'app/Views/organizer/events/index.php',
    'app/Views/admin/payments/index.php',
    'app/Views/organizer/participants/index.php',
];
```

For each source view, strip PHP blocks, parse it with `DOMDocument`, and assert exactly one `.result-summary[role="status"][aria-live="polite"][aria-atomic="true"]` with the five child classes defined in Step 1. Assert that the two panel views use `.dashboard-panel__heading--with-summary` and `.dashboard-panel__heading-main`, while the five toolbar views keep the summary as a direct toolbar child.

- [ ] **Step 3: Add the source and compiled visual contract test**

Add `testResultSummaryUsesOneSharedResponsiveVisualContract()` and require these source utilities:

```php
$sourceRules = [
    '.result-summary' => ['flex', 'min-h-12', 'w-full', 'min-w-0', 'items-center', 'gap-3', 'sm:w-auto'],
    '.result-summary__count' => ['grid', 'min-h-11', 'min-w-11', 'shrink-0', 'place-items-center', 'rounded-[12px]', 'bg-[var(--accent-soft)]', 'px-2.5', 'text-lg', 'font-bold', 'tabular-nums', 'text-[var(--accent)]'],
    '.result-summary__copy' => ['grid', 'min-w-0', 'gap-0.5'],
    '.result-summary__context' => ['text-xs', 'font-bold', 'text-[var(--ink-muted)]'],
    '.result-summary__subject' => ['text-sm', 'font-semibold', 'leading-5', 'text-[var(--ink)]'],
    '.dashboard-panel__heading--with-summary' => ['flex-col', 'gap-4', 'sm:flex-row', 'sm:items-center', 'sm:justify-between'],
    '.dashboard-panel__heading-main' => ['flex', 'min-w-0', 'items-start', 'gap-3'],
];
```

Require compiled declarations for flex/grid display, 44px minimum tile dimensions, accent tokens, 12px radius, bold 18px count, `font-variant-numeric:tabular-nums`, and the 40rem responsive widths/directions. Reject `position:absolute`, a literal fixed summary width, and any legacy `.filter-toolbar__summary strong` selector.

Update the existing toolbar CSS expectation from `align-items:center` to `align-items:flex-end` at 40rem.

- [ ] **Step 4: Replace brittle rendered-copy assertions with semantic behavior assertions**

Update controller tests to assert the shared status markup and independent literal phrases:

```php
$this->assertTrue(str_contains($body, 'class="result-summary'));
$this->assertTrue(str_contains($body, 'role="status"'));
$this->assertTrue(str_contains($body, '<span class="sr-only">3 matching users</span>'));
```

Add equivalent assertions for `3 matching payments` and the Participants fixture total. In `testIndexAcceptsOnlyKnownStatusFilters()`, assert the draft response contains `<span class="sr-only">1 matching event</span>` and the unfiltered response contains `<span class="sr-only">2 matching events</span>`.

- [ ] **Step 5: Run the focused tests and verify RED**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php AdminPeopleControllerTest
rtk php tests/run.php AdminPaymentControllerTest
rtk php tests/run.php OrganizerOperationsControllerTest
rtk php tests/run.php OrganizerEventControllerTest
```

Expected: failures identify the missing `.result-summary` markup/CSS, missing panel adapters, missing atomic role semantics, and the Organizer Events unfiltered-count defect. There must be no PHP parse errors or fixture errors.

- [ ] **Step 6: Commit the RED contract**

```bash
rtk git add tests/Unit/UiLayoutTest.php tests/Unit/AdminPeopleControllerTest.php tests/Unit/AdminPaymentControllerTest.php tests/Unit/OrganizerOperationsControllerTest.php tests/Unit/OrganizerEventControllerTest.php
rtk git commit -m "test: define global result summary contract"
```

---

### Task 2: Implement and publish the shared result summary on all seven surfaces

**Files:**
- Modify: `app/Views/admin/users/index.php:30`
- Modify: `app/Views/admin/organizers/index.php:28`
- Modify: `app/Views/admin/events/index.php:22`
- Modify: `app/Views/admin/reviews/index.php:12`
- Modify: `app/Views/organizer/events/index.php:23`
- Modify: `app/Views/admin/payments/index.php:23-24`
- Modify: `app/Views/organizer/participants/index.php:31-32`
- Modify: `resources/css/app.css:1232-1248,1600-1612`
- Modify: `app/Views/layouts/public.php:35`
- Modify: `app/Views/layouts/auth.php:11`
- Modify: `app/Views/layouts/dashboard.php:13`
- Modify: `app/Views/layouts/maintenance.php:9`
- Modify: `public/service-worker.js:3,8`
- Modify: `tests/Unit/PwaStaticPolicyTest.php:62`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php:184`
- Modify: `tests/js/pwa.test.mjs:90-121`
- Generate: `public/assets/css/app.css`

**Interfaces:**
- Consumes: `.result-summary` semantic and CSS contract from Task 1.
- Produces: seven consistent result-summary instances, a truthful Organizer Events filtered count, deployed compiled CSS, and cache key `20260813-result-summary-v1`.

- [ ] **Step 1: Migrate each toolbar summary**

Use this exact shape, substituting the count expression, context, subject, and singular/plural phrase for each view:

```php
<p class="result-summary filter-toolbar__summary" role="status" aria-live="polite" aria-atomic="true">
    <strong class="result-summary__count" aria-hidden="true"><?= e($total) ?></strong>
    <span class="result-summary__copy" aria-hidden="true">
        <span class="result-summary__context">Matching</span>
        <span class="result-summary__subject">Users</span>
    </span>
    <span class="sr-only"><?= e($total) ?> matching <?= $total === 1 ? 'user' : 'users' ?></span>
</p>
```

Use `In queue` and `Events` or `Reviews` for moderation queues. For Organizer Events, define `$eventCount = count($events);` at the start of the view and use only `$eventCount` in the component.

- [ ] **Step 2: Migrate Payments and Participants panel headings**

Wrap the existing icon and text in `.dashboard-panel__heading-main`, add `.dashboard-panel__heading--with-summary` to the outer heading, replace the old count helper with one functional explanatory sentence, and add the same `.result-summary` as the second direct child. Preserve the heading IDs.

```php
<div class="dashboard-panel__heading dashboard-panel__heading--with-summary">
    <div class="dashboard-panel__heading-main">
        <span class="dashboard-panel__icon"><i class="ph ph-list-magnifying-glass" aria-hidden="true"></i></span>
        <div><h2 id="payment-list-heading">Payment records</h2><p>Review the filtered settlement records below.</p></div>
    </div>
    <p class="result-summary" role="status" aria-live="polite" aria-atomic="true">
        <strong class="result-summary__count" aria-hidden="true"><?= e($total) ?></strong>
        <span class="result-summary__copy" aria-hidden="true"><span class="result-summary__context">Matching</span><span class="result-summary__subject">Payments</span></span>
        <span class="sr-only"><?= e($total) ?> matching payment<?= $total === 1 ? '' : 's' ?></span>
    </p>
</div>
```

Use `Payments` and `Registrations` as the visible subjects. Keep the existing filtered `$total` and singular/plural accessible phrases. The Participants helper sentence is `Review registration, payment, ticket, and attendance states.`

- [ ] **Step 3: Implement the shared CSS primitive and layout adapters**

Replace `.filter-toolbar__summary` and `.filter-toolbar__summary strong` with:

```css
.result-summary {
    @apply flex min-h-12 w-full min-w-0 items-center gap-3 sm:w-auto;
}

.result-summary__count {
    @apply grid min-h-11 min-w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] px-2.5 text-lg font-bold tabular-nums text-[var(--accent)];
}

.result-summary__copy {
    @apply grid min-w-0 gap-0.5;
}

.result-summary__context {
    @apply text-xs font-bold text-[var(--ink-muted)];
}

.result-summary__subject {
    @apply text-sm font-semibold leading-5 text-[var(--ink)];
}

.filter-toolbar__summary {
    @apply sm:flex-none;
}
```

Change `.filter-toolbar` from `sm:items-center` to `sm:items-end`.

Add:

```css
.dashboard-panel__heading--with-summary {
    @apply flex-col gap-4 sm:flex-row sm:items-center sm:justify-between;
}

.dashboard-panel__heading-main {
    @apply flex min-w-0 items-start gap-3;
}
```

- [ ] **Step 4: Bump the CSS and service-worker cache version**

Change the four stylesheet URLs to:

```html
/assets/css/app.css?v=20260813-result-summary-v1
```

Change the service-worker cache name to `oems-public-static-20260813-result-summary-v1` and its CSS manifest entry to the same query version. Update exact PHP and JavaScript assertions. Keep the separate `location.js?v=20260813-event-view-v1` URL unchanged.

- [ ] **Step 5: Rebuild the stylesheet**

Run:

```bash
rtk npm run build:css
```

Expected: Tailwind exits 0 and rewrites `public/assets/css/app.css` from `resources/css/app.css`.

- [ ] **Step 6: Verify GREEN on focused PHP and PWA suites**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php AdminPeopleControllerTest
rtk php tests/run.php AdminPaymentControllerTest
rtk php tests/run.php OrganizerOperationsControllerTest
rtk php tests/run.php OrganizerEventControllerTest
rtk php tests/run.php PwaStaticPolicyTest
rtk php tests/run.php OrganizerVenueControllerTest
rtk node tests/js/pwa.test.mjs
```

Expected: all focused suites pass with no warnings.

- [ ] **Step 7: Inspect the scoped diff and commit the implementation**

Run `rtk git diff --check` and confirm only the listed source, view, test-version, and compiled-asset files changed for this task. Then commit:

```bash
rtk git add app/Views/admin/users/index.php app/Views/admin/organizers/index.php app/Views/admin/events/index.php app/Views/admin/reviews/index.php app/Views/organizer/events/index.php app/Views/admin/payments/index.php app/Views/organizer/participants/index.php resources/css/app.css public/assets/css/app.css app/Views/layouts/public.php app/Views/layouts/auth.php app/Views/layouts/dashboard.php app/Views/layouts/maintenance.php public/service-worker.js tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php tests/js/pwa.test.mjs
rtk git commit -m "fix: standardize dashboard result summaries"
```

---

### Task 3: Browser QA, review, and full verification

**Files:**
- Modify only if evidence identifies a defect in the files from Tasks 1 and 2.

**Interfaces:**
- Consumes: the committed result-summary implementation and the existing authenticated local browser session.
- Produces: verified responsive, theme, accessibility, and regression evidence.

- [ ] **Step 1: Verify the browser matrix**

Use the in-app browser on:

- `/admin/users`
- `/admin/organizers`
- `/admin/events`
- `/admin/reviews`
- `/organizer/events`
- `/admin/payments`
- one owned `/organizer/events/{id}/participants` route

Check 390, 768, 1280, and 2048px in light and dark themes. At each representative width verify no document-level horizontal overflow, grouped count and copy, deliberate wrapping, count tile growth, no clipped labels, and no overlap with filter controls or panel headings.

- [ ] **Step 2: Verify behavior and accessibility**

Verify zero, singular, and plural states where fixtures allow. Apply an Organizer Events status filter and confirm the count equals the visible filtered rows. At 200 percent zoom, confirm no clipping or horizontal scroll. Confirm the accessibility tree exposes one atomic status phrase and the count is not duplicated. Confirm keyboard traversal skips the summary and reaches the first filter control next.

- [ ] **Step 3: Run the complete automated verification**

Run:

```bash
rtk php tests/run.php
rtk node --test tests/js/*.test.mjs
rtk git diff --check
rtk git status --short
```

Expected: every PHP and JavaScript test passes, the diff check is clean, and only the user's pre-existing unrelated files remain dirty or untracked.

- [ ] **Step 4: Request independent code review and correct evidence-backed findings**

Use `superpowers:requesting-code-review` against the RED commit and implementation commit. If a valid defect is found, add a failing regression assertion first, implement the smallest correction, rerun focused and full suites, and commit the exact affected files from the scoped list:

```bash
rtk git add app/Views/admin/users/index.php app/Views/admin/organizers/index.php app/Views/admin/events/index.php app/Views/admin/reviews/index.php app/Views/organizer/events/index.php app/Views/admin/payments/index.php app/Views/organizer/participants/index.php resources/css/app.css public/assets/css/app.css tests/Unit/UiLayoutTest.php tests/Unit/AdminPeopleControllerTest.php tests/Unit/AdminPaymentControllerTest.php tests/Unit/OrganizerOperationsControllerTest.php tests/Unit/OrganizerEventControllerTest.php tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php tests/js/pwa.test.mjs
rtk git commit -m "fix: harden result summary layout"
```

If review and browser QA are clean, do not create an empty commit.
