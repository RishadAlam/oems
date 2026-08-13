# OEMS Analytics Dashboard Redesign Implementation Plan

> **Execution requirement:** Follow strict RED/GREEN TDD, preserve unrelated worktree changes, and commit each completed checkpoint.

**Goal:** Make both analytics dashboards compact, informative, accessible, and responsive while preserving the existing reporting, filter, export, authorization, and aggregate-data contracts.

**Architecture:** Keep `ReportService` and `AnalyticsRepository` unchanged. Restructure the two role views and their shared chart component, add analytics-specific OEMS CSS, and refine the existing local Chart.js adapter. Tables remain the non-JavaScript source of truth inside native disclosures; visual charts consume the same aggregate JSON payload.

**Stack:** PHP 8 views/controllers, Tailwind CSS source and compiled asset, vanilla JavaScript, Chart.js, custom PHP/Node test runners.

---

## Task 1: Capture the broken analytics experience with RED tests

**Files:**

- Modify: `tests/Unit/AnalyticsChartTest.php`
- Modify: `tests/Unit/AnalyticsControllerTest.php`
- Modify: `tests/js/analytics-charts.test.mjs`

### Test behavior

Add rendered-response assertions for both roles that fail when:

- filters do not use the shared compact analytics contract;
- KPI summaries are generic unstructured panels;
- the performance section lacks two content-sized chart panels;
- truthful insight summaries are absent;
- source tables are not inside closed native disclosures with row counts;
- payment values disappear from the source table.

Add empty/sparse fixtures that fail when an all-zero count series renders a meaningless canvas or a one-category chart lacks bounded sizing metadata.

Update the real JavaScript harness so it fails when:

- the timeline includes payment datasets or a second money axis;
- x-axis ticks are not automatically bounded;
- category bars have no integer scale or maximum thickness;
- the success/failure status does not explain that source tables remain available.

### RED verification

Run:

```bash
rtk php tests/run.php AnalyticsChartTest
rtk php tests/run.php AnalyticsControllerTest
rtk node --test tests/js/analytics-charts.test.mjs
```

Confirm failures name only the missing new analytics contracts. Commit the RED tests.

## Task 2: Implement shared analytics information architecture

**Files:**

- Modify: `app/Views/components/analytics-charts.php`
- Modify: `app/Views/admin/analytics/index.php`
- Modify: `app/Views/organizer/analytics/index.php`

### Shared chart component

- Derive total periods, active periods, busiest period, category count, and leading category from the already-safe aggregate arrays.
- Render `Performance overview` with a clear range/activity summary.
- Render the timeline and category panels with dedicated analytics classes and independent content height.
- Keep payments out of visual chart copy while retaining each currency column in the timeline data table.
- Wrap both tables in native `<details>` disclosures with truthful row counts.
- Show empty states for no periods, all-zero activity, and no categories.
- Preserve safe JSON flags and aggregate-only payload.

### Role views

- Apply shared `analytics-filter`, `analytics-filter__form`, `analytics-kpi-grid`, and `analytics-kpi` markup.
- Preserve every existing field name, value, validation attribute, action, reset URL, export URL, and metric value.
- Add contextual icons and applied-range text without inventing metrics.

### GREEN verification

Run the focused PHP tests and `php -l` on all three views. Commit the semantic markup checkpoint.

## Task 3: Add the responsive analytics visual system and publish CSS

**Files:**

- Modify: `resources/css/app.css`
- Rebuild: `public/assets/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/js/pwa.test.mjs`

### CSS contract

- Style compact filters, four-column KPI cards, insight rows, chart headers, independent chart grid, responsive chart canvases, and disclosure summaries using existing OEMS tokens.
- Use single-column mobile, two-column tablet KPIs, four-column desktop KPIs, and a `1.6fr/0.8fr` chart grid at wide viewports.
- Use `items-start`, `min-width: 0`, tabular numerals, visible focus, and internal table scrolling.
- Do not add gradients, raw theme palette utilities, fixed page widths, or decorative motion.

### Asset publication

- Build with the repository CSS build command.
- Change the CSS/cache revision from `20260813-global-status-v1` to `20260814-analytics-dashboard-v1` everywhere in live layouts, the service worker, and exact asset-policy tests.
- Do not edit historical plan documents that mention the old version.

### Verification

Run the focused layout/PWA tests, asset tests, CSS build verification, and diff check. Commit the CSS and asset-revision checkpoint.

## Task 4: Refine chart behavior without changing data semantics

**Files:**

- Modify: `public/assets/js/analytics-charts.js`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `tests/js/analytics-charts.test.mjs`
- Modify: `tests/Unit/AnalyticsChartTest.php`
- Modify: `tests/Unit/AnalyticsControllerTest.php`

### Timeline behavior

- Render exactly Events, Registrations, and Attendance against one integer count axis.
- Remove payment datasets and `yMoney` from visual configuration.
- Bound x ticks with `autoSkip` and `maxTicksLimit`, keep accessible tooltips, and use reduced-motion-aware animation.

### Category behavior

- Render horizontal bars with integer ticks and bounded `barThickness`/`maxBarThickness`.
- Read chart height from component metadata derived from category count.

### Runtime behavior

- Preserve theme mutation recreation, page lifecycle cleanup, malformed JSON handling, and missing-Chart fallback.
- Version the analytics JavaScript URL as `20260814-analytics-dashboard-v1` so browsers do not reuse the old chart adapter.

Run focused PHP/JS tests, syntax checks, and asset checks. Commit the JavaScript behavior checkpoint.

## Task 5: End-to-end verification and correction

### Browser matrix

Use the in-app browser with real authenticated routes:

- `/admin/analytics`
- `/organizer/analytics`

Test 390, 768, 1280, and 2048 pixels in light and dark themes. Verify:

- one visible H1 and correct heading hierarchy;
- no document overflow, clipping, overlap, stretched category card, or giant single bar;
- filters and export/actions remain usable;
- KPI values and insights match visible source data;
- disclosure tables open with keyboard and retain internal scrolling;
- charts, tooltips, theme recreation, empty states, and reduced-motion behavior remain functional;
- no console warnings or errors.

### Automated gates

Run:

```bash
rtk composer test
rtk node --test tests/js/*.test.mjs
rtk npm run test:forms
rtk npm run build:css
rtk php tests/verify-assets.php
rtk git diff --check
```

If sandboxed loopback tests fail only because localhost sockets are blocked, rerun the same suite with the approved escalation and record both results.

### Review and handoff

- Review the scoped diff against the design specification.
- Correct any evidence-backed issue with a RED regression first.
- Commit only scoped corrections; leave unrelated dirty/untracked files untouched.
- Report commit hashes, tests/assertions, browser coverage, and any exact limitations.

