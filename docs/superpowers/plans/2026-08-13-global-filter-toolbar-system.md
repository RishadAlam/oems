# Global Filter Toolbar System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build one responsive top-level result/filter toolbar that fixes label, control, action, and summary alignment across administrator and organizer list pages.

**Architecture:** Replace the role-specific `organizer-toolbar` shell with a semantic `filter-toolbar` component made of summary, form, field, and action units. Migrate the five true top-level toolbars together, preserve every endpoint and query parameter, and publish the compiled stylesheet through the existing versioned asset and service-worker pipeline.

**Tech Stack:** PHP 8.2 view templates, Tailwind CSS 4 component layer, PHPUnit-style local test harness, Node PWA tests, browser-computed layout verification.

## Global Constraints

- Migrate only the five true top-level toolbars; purpose-built hero, discovery, analytics, payments, reports, blog, contact, and participant filter panels remain structurally unchanged.
- Reuse existing design tokens and dependencies; add no JavaScript or framework.
- Preserve current routes, methods, query names, values, auto-submit behavior, button copy, visible labels, and 44-pixel-or-larger touch targets.
- The summary must be polite live content, each filter form must expose a search landmark label, and every label/control must remain a complete field unit.
- Refresh the CSS asset version and service-worker cache key when publishing the stylesheet.
- Stage and commit only files listed by this plan; preserve unrelated workspace changes.

---

### Task 1: Lock the shared markup and responsive CSS contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: The five view paths and routes listed below.
- Produces: Regression tests for `.filter-toolbar`, `__summary`, `__form`, `__field`, and `__actions`.

- [ ] **Step 1: Add a failing markup contract test**

Add `testEveryTopLevelFilterToolbarUsesTheSharedDirectChildContract()`. For this matrix, parse source markup with `DOMDocument`/`DOMXPath` and assert one toolbar, one direct summary, one direct GET filter form with an `aria-label`, the expected number of direct field units, one direct action unit, no bare direct label/input/select, and no legacy toolbar class:

```php
$views = [
    'app/Views/admin/users/index.php' => ['/admin/users', 3],
    'app/Views/admin/organizers/index.php' => ['/admin/organizers', 2],
    'app/Views/admin/events/index.php' => ['/admin/events', 1],
    'app/Views/admin/reviews/index.php' => ['/admin/reviews', 1],
    'app/Views/organizer/events/index.php' => ['/organizer/events', 1],
];
```

- [ ] **Step 2: Replace the shallow compiled-CSS assertion**

Replace `testResponsiveToolbarBottomAlignsLabeledControlsAndUnlabeledActions()` with `testCompiledFilterToolbarUsesOneResponsiveAlignmentContract()`. Assert source and compiled CSS contain base full-width stacking rules plus `sm` row/wrap/end-alignment rules, a vertically centered summary, 48-pixel fields/actions, and no `.organizer-toolbar` selector.

- [ ] **Step 3: Run the focused test and prove RED**

Run:

```bash
rtk php tests/run.php UiLayoutTest
```

Expected: failures stating that the five views do not render `.filter-toolbar` and that the compiled selectors do not exist.

### Task 2: Migrate all top-level toolbar consumers

**Files:**
- Modify: `app/Views/admin/users/index.php`
- Modify: `app/Views/admin/organizers/index.php`
- Modify: `app/Views/admin/events/index.php`
- Modify: `app/Views/admin/reviews/index.php`
- Modify: `app/Views/organizer/events/index.php`

**Interfaces:**
- Consumes: The markup contract from Task 1.
- Produces: Five instances of the shared toolbar DOM without controller or behavior changes.

- [ ] **Step 1: Replace every outer shell and summary**

Use this structure on all five views:

```php
<div class="filter-toolbar mt-8">
    <p class="filter-toolbar__summary" aria-live="polite"><strong>...</strong> ...</p>
    <form class="filter-toolbar__form" ... aria-label="..." data-form-kind="filter">
        ...
    </form>
</div>
```

- [ ] **Step 2: Group every label and control**

Wrap each label and input/select in `<div class="filter-toolbar__field">`. Add `filter-toolbar__field--search` to user and organizer search fields. Do not leave bare direct form controls.

- [ ] **Step 3: Group every submit action**

Wrap each submit button in `<div class="filter-toolbar__actions">`. Preserve icons, copy, `data-auto-submit`, and `data-auto-submit-fallback` exactly.

- [ ] **Step 4: Run the focused test**

Run `rtk php tests/run.php UiLayoutTest`.

Expected: markup assertions pass; CSS assertions remain red.

### Task 3: Implement and publish the global toolbar styles

**Files:**
- Modify: `resources/css/app.css`
- Generate: `public/assets/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/js/pwa.test.mjs`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`

**Interfaces:**
- Consumes: The shared toolbar DOM from Task 2.
- Produces: Version `20260813-filter-toolbar-v1` and cache `oems-public-static-20260813-filter-toolbar-v1`.

- [ ] **Step 1: Replace the legacy CSS component**

Remove the `.organizer-toolbar` rules. Add source rules that:

- make `.filter-toolbar` a column surface by default and a wrapping, space-between row at `sm`;
- center `.filter-toolbar__summary` independently at `sm`;
- make `.filter-toolbar__form` a full-width one-column grid by default, two columns at `sm`, and an auto-width wrapping flex row at `lg`;
- keep `.filter-toolbar__field` a vertical grid with uniform label typography;
- set input/select/action controls to `min-h-12`;
- make mobile actions/buttons full width and large-screen actions auto width;
- give search fields a practical minimum desktop width without creating intermediate-width overflow.

- [ ] **Step 2: Build the production stylesheet**

Run:

```bash
rtk npm run build:css
```

Expected: Tailwind completes and `public/assets/css/app.css` contains the new compiled selectors.

- [ ] **Step 3: Refresh the published asset version**

Replace `20260813-organizer-approval-v1` with `20260813-filter-toolbar-v1` in all four layouts, the service worker, and version assertions. Do not change JavaScript asset versions.

- [ ] **Step 4: Run focused regression tests**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php PwaStaticPolicyTest
rtk node tests/js/pwa.test.mjs
```

Expected: all pass.

- [ ] **Step 5: Commit the working system**

Stage only the files listed in Tasks 1–3 and commit with `fix: standardize filter toolbar layouts`.

### Task 4: Verify representative pages and the complete project

**Files:**
- Modify only if verification reveals an in-scope toolbar regression.

**Interfaces:**
- Consumes: Published toolbar markup and CSS.
- Produces: Browser and automated evidence that the global system works.

- [ ] **Step 1: Browser-check the widest and broken variants**

At 2048 pixels and 390 pixels, inspect `/admin/users` and `/admin/events`; also smoke-check `/admin/organizers`, `/admin/reviews`, and `/organizer/events` when the current role permits. Verify:

- no horizontal overflow;
- labels sit above their own controls;
- all controls/actions share a lower edge at large widths;
- summary is vertically centered rather than bottom-aligned;
- narrow fields/actions fill the available width;
- light and dark token contrast remains unchanged.

- [ ] **Step 2: Run syntax, assets, form, and full PHP suites**

Run:

```bash
rtk composer check:syntax
rtk npm run test:assets
rtk npm run test:forms
rtk composer test
```

Expected: every command exits zero.

- [ ] **Step 3: Review the scoped diff and whitespace**

Run `rtk git diff --check` and inspect `rtk git diff --stat` plus the exact scoped diff. Confirm unrelated files are unstaged.

- [ ] **Step 4: Commit verification-driven corrections**

If verification required code corrections, commit them separately with `fix: finalize responsive filter toolbars`. If no correction was required, do not create an empty commit.
