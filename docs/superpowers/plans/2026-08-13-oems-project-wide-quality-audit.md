# OEMS Project-Wide Quality Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Find and fix reproducible correctness, security, validation, navigation, accessibility, responsive-layout, and interaction defects across OEMS.

**Architecture:** Preserve the native PHP MVC and Tailwind architecture. Audit in layers: automated baseline, static route and form contracts, focused test-driven fixes, and authenticated browser acceptance across roles and viewports. Treat client validation as guidance and server validation as authoritative.

**Tech Stack:** PHP 8.2, custom MVC router and middleware, PDO/MySQL, server-rendered PHP views, Tailwind CSS 4, vanilla JavaScript, custom PHP test runner, Node test runner.

## Global Constraints

- Preserve existing route slugs, field names, brand identity, legal copy, and primary information architecture unless a confirmed defect requires a focused correction.
- Write and observe a failing regression test before every production behavior change.
- Keep client-side and server-side validation independent and equivalent.
- Preserve unrelated tracked and untracked user files.
- Build generated CSS and PWA assets after source changes.
- Commit each completed, independently verifiable audit batch.

---

### Task 1: Establish the baseline and audit inventory

**Files:**
- Create: `docs/superpowers/specs/2026-08-13-oems-project-wide-quality-audit-design.md`
- Create: `docs/superpowers/plans/2026-08-13-oems-project-wide-quality-audit.md`
- Inspect: `composer.json`, `package.json`, `routes/web.php`, `app/`, `Core/`, `resources/`, `public/`, `tests/`

**Interfaces:**
- Consumes: current repository and local development configuration
- Produces: documented scope, baseline results, route inventory, and prioritized defect list

- [ ] **Step 1: Capture repository state and inventories**

Run: `rtk git status --short`, `rtk git log -8 --oneline`, `rtk rg --files app Core routes resources public tests`.

- [ ] **Step 2: Run the complete baseline**

Run: `rtk composer test`, `rtk node --test tests/js/*.test.mjs`, `rtk composer check:syntax`, `rtk npm run build:css`, and `rtk git diff --check`.

Expected: record every failure exactly. Do not edit production code during this step.

- [ ] **Step 3: Inventory structural contracts**

Map every route to its middleware, controller method, linked navigation item, submitting form, and server-side validation entry point. Record duplicate destinations, missing middleware, method mismatches, and orphaned actions.

- [ ] **Step 4: Commit the audit design and plan**

```bash
rtk git add docs/superpowers/specs/2026-08-13-oems-project-wide-quality-audit-design.md docs/superpowers/plans/2026-08-13-oems-project-wide-quality-audit.md
rtk git commit -m "docs: define project-wide quality audit"
```

### Task 2: Correct route, authorization, and state-contract defects

**Files:**
- Modify as evidence requires: `routes/web.php`, `app/Middleware/*.php`, `app/Controllers/*.php`, `app/Services/*.php`, `app/Repositories/*.php`, `app/Views/**/*.php`
- Test: focused `tests/Unit/*Test.php` files matching each changed component

**Interfaces:**
- Consumes: route inventory and persisted domain state
- Produces: protected state-changing operations and accurate user-visible state

- [ ] **Step 1: Write one failing test per confirmed defect**

Each test must fail when a route lacks required middleware, a mutation escapes ownership scope, or a view exposes an action contradicted by backend policy.

- [ ] **Step 2: Verify each test fails for the intended reason**

Run: `rtk php tests/run.php <FocusedTestName>`.

- [ ] **Step 3: Implement the smallest root-cause fix**

Change the authoritative route, policy, query projection, or state derivation rather than hiding the symptom in CSS or copy.

- [ ] **Step 4: Verify focused and neighboring suites**

Run the focused test plus route, security, repository, and controller suites affected by the change.

- [ ] **Step 5: Commit the verified batch**

Stage only the files changed by this batch and use a `fix:` commit describing the domain behavior.

### Task 3: Correct form-validation and submission-contract defects

**Files:**
- Modify as evidence requires: `app/Views/**/*.php`, `public/assets/js/app.js`, `Core/Validator.php`, `app/Controllers/*.php`, `app/Services/*.php`
- Test: `tests/Unit/FormSystemTest.php`, relevant controller and service tests, `tests/js/form-validation.test.mjs`

**Interfaces:**
- Consumes: route definitions, server validation rules, form markup, and shared client validation
- Produces: equivalent client and server constraints with accessible errors

- [ ] **Step 1: Generate the form contract inventory**

For every form, record action, method, CSRF field, required fields, type, min, max, pattern, conditional requirements, file limits, and server rule owner.

- [ ] **Step 2: Add failing tests for every confirmed mismatch**

Tests must exercise rendered behavior or submitted payload outcomes, not source-text presence alone.

- [ ] **Step 3: Verify red, implement minimal parity fixes, and verify green**

Run focused PHP and JavaScript tests after each behavior change.

- [ ] **Step 4: Rebuild assets and run form-related suites**

Run: `rtk npm run build:css`, `rtk npm run test:forms`, and focused PHP form/controller suites.

- [ ] **Step 5: Commit the verified batch**

Stage only form-contract files and use a `fix:` commit describing the validation behavior.

### Task 4: Correct navigation, accessibility, and responsive defects

**Files:**
- Modify as evidence requires: `app/Views/layouts/*.php`, `app/Views/components/*.php`, `app/Views/**/*.php`, `resources/css/app.css`, `public/assets/css/app.css`, `public/assets/js/*.js`, `public/service-worker.js`
- Test: layout, UI, PWA, and JavaScript interaction tests under `tests/Unit/` and `tests/js/`

**Interfaces:**
- Consumes: route inventory, design tokens, semantic markup, and interaction scripts
- Produces: unique navigation, accessible state feedback, stable responsive layouts, and versioned assets

- [ ] **Step 1: Audit global patterns and representative pages**

Check landmarks, heading order, accessible names, focus states, live regions, keyboard operation, touch targets, contrast, field icon padding, overflow, empty states, and destructive confirmations.

- [ ] **Step 2: Add failing tests for confirmed reusable-pattern defects**

Prefer tests at the shared layout, component, CSS token, or interaction-script boundary so one correction protects all consumers.

- [ ] **Step 3: Implement shared root-cause fixes and verify focused suites**

Avoid page-specific patches when the defect comes from a shared component or token.

- [ ] **Step 4: Rebuild CSS and update static cache versions when needed**

Run: `rtk npm run build:css` and the full JavaScript suite.

- [ ] **Step 5: Commit the verified batch**

Stage only navigation, UI, asset, and related test files.

### Task 5: Run browser acceptance across roles and viewports

**Files:**
- Modify only if a browser-reproduced defect requires a tested fix
- Test: add the smallest relevant PHP or JavaScript regression test before production edits

**Interfaces:**
- Consumes: local demo accounts, registered routes, rendered application, and completed automated fixes
- Produces: acceptance evidence for desktop and mobile critical journeys

- [ ] **Step 1: Exercise guest and authentication journeys**

Verify public navigation, discovery, event detail, location, calendar, blog, contact, newsletter, login, registration, verification guidance, password reset, empty and invalid states.

- [ ] **Step 2: Exercise participant journeys**

Verify dashboard, registration, payment, favorites, waitlist, notifications, tickets, certificates, reviews, and profile at desktop and mobile widths.

- [ ] **Step 3: Exercise organizer and pending-organizer journeys**

Verify event lifecycle, approval boundaries, venues, coupons, participants, check-in, announcements, reviews, analytics, profile, and mobile navigation.

- [ ] **Step 4: Exercise administrator journeys**

Verify moderation, organizer approval, users, payments, categories, CMS, blog, newsletter, contact, reports, analytics, settings, operations, and mobile navigation.

- [ ] **Step 5: Fix each reproduced defect with red-green-refactor and commit each coherent browser defect batch**

No production edit occurs without an observed failing automated regression test.

### Task 6: Complete the release-quality gate

**Files:**
- Modify: audit documentation only if final evidence or known limitations must be recorded

**Interfaces:**
- Consumes: all completed audit batches
- Produces: reproducible completion evidence and a clean tracked working tree

- [ ] **Step 1: Run the complete verification commands fresh**

Run: `rtk composer test`, `rtk node --test tests/js/*.test.mjs`, `rtk composer check:syntax`, `rtk npm run build:css`, and `rtk git diff --check`.

- [ ] **Step 2: Re-run critical browser smoke journeys**

Confirm public, participant, organizer, pending-organizer, and administrator layouts render and their primary navigation and policy boundaries behave correctly at desktop and mobile widths.

- [ ] **Step 3: Review repository scope**

Run: `rtk git status --short`, `rtk git diff --stat`, and `rtk git log --oneline` to verify only intended files were committed and unrelated files remain untouched.

- [ ] **Step 4: Report evidence and known environmental limits**

Report exact test counts, build results, browser journeys, commits, and any external-service or production-only checks that cannot be proven locally.

