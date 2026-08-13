# Role-aware Organizer Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep organizer signup available to guests while sending every authenticated role from the public organizer menu to its own dashboard.

**Architecture:** The public layout chooses only between guest signup and the authenticated `/dashboard` entry point. `DashboardController::index()` remains the role dispatcher, so presentation code never duplicates role-to-route authorization logic.

**Tech Stack:** PHP 8.2 views and controllers, OEMS custom router/view layer, OEMS PHP unit test runner.

## Global Constraints

- Guest desktop and mobile menu items use `For organizers` and `/register?role=organizer`.
- Authenticated desktop and mobile menu items use `Dashboard` and `/dashboard`.
- Each authenticated responsive header contains exactly one dashboard entry.
- `/dashboard` routes super administrators, organizers, and participants to their existing role dashboards.
- No JavaScript, middleware, homepage-marketing, or CSS changes.
- Preserve all unrelated dirty and untracked files.

---

### Task 1: Capture the navigation and dispatcher contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`

**Interfaces:**
- Consumes: `UiLayoutTest::renderHome(array $overrides = []): string`, `DashboardLayoutTest::dashboardController(string $role, int $userId): array`
- Produces: regression coverage for guest/authenticated header links and role-aware `/dashboard` redirects

- [ ] **Step 1: Write the failing public-navigation test**

Render the home page as a guest and as `super-admin`, `organizer`, and `participant`. Assert the guest desktop/mobile items retain `/register?role=organizer` with `For organizers`, while authenticated variants use `/dashboard` with `Dashboard` in both menus. Parse each authenticated header and require exactly one dashboard link in the desktop primary navigation and one in the mobile navigation.

- [ ] **Step 2: Add dispatcher behavior coverage**

Call `DashboardController::index()` through the existing authenticated controller fixture for each role and assert literal locations `/admin/dashboard`, `/organizer/dashboard`, and `/participant/dashboard`.

- [ ] **Step 3: Run focused tests and verify RED**

Run: `php tests/run.php UiLayoutTest DashboardLayoutTest`

Expected: the new public-navigation assertions fail because both organizer menu links are still hard-coded to the registration URL; existing dispatcher assertions pass.

- [ ] **Step 4: Commit the RED tests**

```bash
git add tests/Unit/UiLayoutTest.php tests/Unit/DashboardLayoutTest.php
git commit -m "test: capture role-aware organizer navigation"
```

### Task 2: Make the shared menu session-aware

**Files:**
- Modify: `app/Views/layouts/public.php`

**Interfaces:**
- Consumes: layout variable `?array $currentUser`, authenticated route `/dashboard`
- Produces: scalar `$layoutOrganizerMenuHref`, `$layoutOrganizerMenuLabel`, and `$layoutOrganizerMenuIcon` used by desktop and mobile markup

- [ ] **Step 1: Derive shared menu values**

At the top of the public layout, derive guest values (`/register?role=organizer`, `For organizers`, `ph-microphone-stage`) and authenticated values (`/dashboard`, `Dashboard`, `ph-squares-four`) from whether `$currentUser` is an array.

- [ ] **Step 2: Apply the values to both menus**

Replace only the desktop primary-navigation and mobile-menu organizer anchors. Escape the derived destination, label, and icon class with `e()`.

Remove the redundant authenticated dashboard CTA from the desktop action group and mobile menu footer. Preserve guest Log in and Get started actions.

- [ ] **Step 3: Run focused tests and verify GREEN**

Run: `php tests/run.php UiLayoutTest DashboardLayoutTest`

Expected: all tests pass with no warnings.

- [ ] **Step 4: Run syntax and form-layout checks**

Run: `php -l app/Views/layouts/public.php`

Run: `npm run test:forms`

Expected: both pass.

- [ ] **Step 5: Commit the implementation**

```bash
git add app/Views/layouts/public.php
git commit -m "fix: route organizer menu by session"
```

### Task 3: Verify the complete navigation flow

**Files:**
- No production files expected

**Interfaces:**
- Consumes: guest and authenticated public layouts, `/dashboard` dispatcher
- Produces: automated and browser evidence that the navigation flow is correct

- [ ] **Step 1: Run complete automated verification**

Run: `composer test`

Run: `node --test tests/js/*.test.mjs`

Run: `composer check:syntax`

Expected: zero failures.

- [ ] **Step 2: Run real-browser checks**

At desktop and mobile widths, verify a guest sees `For organizers` linking to `/register?role=organizer`. In authenticated super-admin, organizer, and participant sessions, verify `Dashboard` links to `/dashboard` and resolves to the correct role dashboard without overflow or console errors.

- [ ] **Step 3: Audit scope and repository state**

Run: `git diff --check`

Run: `git status --short`

Expected: no uncommitted task files; unrelated pre-existing files remain untouched.
