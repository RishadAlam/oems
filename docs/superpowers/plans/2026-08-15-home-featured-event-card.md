# Homepage Featured Event Card Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the uneven homepage featured-event composition with two equal, compact, responsive marketplace cards.

**Architecture:** Keep the existing controller presentation data and semantic event markup. Change only the homepage card modifier/metadata presentation and the homepage-specific CSS contract, while retaining the shared event-card base used by discovery pages. Publish the compiled stylesheet through the existing asset pipeline and cache-version convention.

**Tech Stack:** PHP views, Tailwind CSS v4 `@apply`, compiled CSS, PHP unit/layout tests, Node asset/PWA tests.

## Global Constraints

- Preserve all routes, field names, event data, save actions, SEO semantics, and visible copy outside the featured cards.
- Use existing OEMS design tokens and Phosphor icons; add no dependency.
- Keep light/dark themes and reduced-motion behavior.
- Preserve unrelated dirty worktree files.
- Commit the RED regression test separately before production changes.

---

### Task 1: Capture the equal-card contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: `UiLayoutTest::renderHome()` fixture and source/compiled CSS.
- Produces: a regression contract for equal card markup, semantic metadata, responsive grid, media ratio, title resilience, and footer anchoring.

- [ ] **Step 1: Write the failing test**

Add a test that renders two intentionally uneven events and asserts: two featured cards, no `event-card--wide`, a shared `home-event-card` class, direct semantic metadata rows, and source/compiled CSS selectors for equal columns, 16:9 media, two-line title clamping, overflow protection, and an auto-anchored footer.

- [ ] **Step 2: Run the test to verify RED**

Run: `rtk php tests/run.php UiLayoutTest`

Expected: failure because the current homepage still emits `event-card--wide` and lacks the new shared contract.

- [ ] **Step 3: Commit the RED test**

Commit only `tests/Unit/UiLayoutTest.php` with `test: capture balanced homepage event cards`.

### Task 2: Implement the balanced featured cards

**Files:**
- Modify: `app/Views/home/index.php`
- Modify: `resources/css/app.css`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/js/pwa.test.mjs`
- Generate: `public/assets/css/app.css`

**Interfaces:**
- Consumes: existing `$featuredEvents` presentation data and favorite action contract.
- Produces: equal responsive homepage cards and a new static-asset revision.

- [ ] **Step 1: Implement the minimal markup change**

Remove the index-based wide modifier, add `home-event-card`, and replace redundant nested labels with two compact metadata rows while keeping `time`, `address`, links, forms, ARIA labels, and visible values.

- [ ] **Step 2: Implement the homepage CSS contract**

Use one column by default and two equal columns from 1024px. Give both media elements 16:9 geometry, clamp titles to two lines, allow metadata to wrap safely, and keep the footer at the bottom. Remove obsolete homepage wide-card rules.

- [ ] **Step 3: Publish assets**

Run: `rtk npm run build:css`

Bump the four layout CSS URLs and service-worker/PWA assertions to one `20260815-home-event-card-v1` revision.

- [ ] **Step 4: Verify GREEN**

Run: `rtk php tests/run.php UiLayoutTest`

Expected: all tests pass.

Run the PWA, organizer venue, CSS asset, form, and syntax suites, then the full PHP suite.

- [ ] **Step 5: Commit production changes**

Commit only the scoped implementation/build/version files with `fix: balance homepage featured event cards`.

### Task 3: Responsive visual verification

**Files:**
- No production files unless browser evidence identifies a defect.

**Interfaces:**
- Consumes: the real homepage and compiled CSS.
- Produces: browser evidence for responsive geometry and both themes.

- [ ] **Step 1: Test representative widths**

Verify 390, 768, 1280, and 2048 CSS pixels in light and dark themes. Confirm equal tracks at desktop, single-column mobile, stable footer alignment, no clipped title/venue/actions, and zero horizontal overflow.

- [ ] **Step 2: Test interaction and accessibility**

Keyboard through image/title/save/view controls, confirm one meaningful focus target per action, verify touch-target size, and inspect reduced-motion behavior.

- [ ] **Step 3: Run final gates**

Run fresh focused/full suites and `rtk git diff --check`, then report any environment limitation honestly.

## Self-review

- Spec coverage: every acceptance criterion maps to Task 1, 2, or 3.
- Placeholder scan: no incomplete or deferred implementation steps.
- Type consistency: no new PHP interfaces or controller fields are introduced.
