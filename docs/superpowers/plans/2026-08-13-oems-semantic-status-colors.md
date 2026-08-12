# OEMS Semantic Status Colors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every known OEMS status an accurate, restrained semantic color in light and dark themes, with green reserved for positive decisions and completed outcomes.

**Architecture:** Keep status names in server-rendered PHP and centralize their visual meaning in the shared Tailwind source stylesheet. Both `status-chip` and `status-badge` use one neutral base and the same explicit domain-state mappings. Views render their true state name instead of borrowing an unrelated class for its color.

**Tech Stack:** PHP 8.2, server-rendered PHP views, Tailwind CSS 4, CSS custom properties, custom PHP test runner, Node test runner, service worker static cache.

## Global Constraints

- Preserve routes, database values, visible status labels, form behavior, typography, spacing, and component geometry.
- Keep visible text for every status so color is never the only signal.
- Use existing light-theme and dark-theme semantic tokens.
- Unknown status classes must retain the neutral base treatment.
- Write and observe failing regression tests before production changes.
- Preserve unrelated tracked and untracked user files.
- Rebuild generated CSS and version the cached stylesheet after source changes.
- Commit each completed, independently verifiable task.

---

### Task 1: Protect the semantic status contract with regression tests

**Files:**
- Create: `tests/Unit/StatusUiTest.php`
- Test: `resources/css/app.css`
- Test: `app/Views/admin/users/index.php`
- Test: `app/Views/admin/categories/index.php`
- Test: `app/Views/admin/cms/index.php`

**Interfaces:**
- Consumes: shared status selectors and representative rendered admin records
- Produces: a failing test for wrong semantic tokens, missing compact-badge styles, and color-proxy classes

- [ ] **Step 1: Add a stylesheet contract test with hand-derived expected groups**

Parse each status selector block from `resources/css/app.css` and assert these literal token pairs: informational uses `--info-soft` and `--info`; success uses `--success-soft` and `--success`; warning uses `--warning-soft` and `--warning`; danger uses `--error-soft` and `--error`; neutral uses `--surface-soft` and `--ink-muted`. Assert both `status-chip` and `status-badge` expose the same domain-state selectors.

- [ ] **Step 2: Add representative render tests for true status names**

Render active and suspended users, active and inactive categories, and published and draft CMS records. Assert their markup contains `status-chip--active`, `status-chip--suspended`, `status-chip--inactive`, `status-chip--published`, and `status-chip--draft` instead of approved, cancelled, or pending color proxies.

- [ ] **Step 3: Run the focused test and observe the intended failure**

Run: `rtk php tests/run.php StatusUiTest`

Expected: failure because several states have no mapping, published is currently green, compact badges have no shared source component, and representative views use proxy classes.

- [ ] **Step 4: Commit the red regression contract**

```bash
rtk git add tests/Unit/StatusUiTest.php
rtk git commit -m "test: define semantic status color contract"
```

### Task 2: Implement the shared status system and correct view semantics

**Files:**
- Modify: `resources/css/app.css`
- Modify: `app/Views/admin/users/index.php`
- Modify: `app/Views/admin/users/show.php`
- Modify: `app/Views/admin/categories/index.php`
- Modify: `app/Views/admin/cms/index.php`
- Modify: `app/Views/admin/contact/index.php`
- Modify: `app/Views/admin/newsletter/index.php`
- Modify: `app/Views/organizer/coupons/index.php`
- Modify: `app/Views/events/show.php`
- Test: `tests/Unit/StatusUiTest.php`

**Interfaces:**
- Consumes: real domain statuses already present in PHP data
- Produces: consistent informational, success, warning, danger, and neutral status components

- [ ] **Step 1: Implement one neutral base for chips and compact badges**

Make `.status-chip` and `.status-badge` share alignment, pill geometry, compact typography, neutral background, and neutral text. Keep unknown statuses neutral by default.

- [ ] **Step 2: Map every known state to its approved semantic tone**

Add explicit chip and badge selectors for the design specification groups: informational `active`, `published`, `valid`, `sent`; success `approved`, `confirmed`, `paid`, `completed`, `used`, `replied`, `subscribed`, `present`; warning `pending`, `waitlisted`, `queued`, `processing`, `new`, `read`; danger `rejected`, `failed`, `suspended`, `revoked`, `cancelled`; neutral `draft`, `inactive`, `archived`, `hidden`, `refunded`, `partially_refunded`, `absent`, `none`, `not_checked_in`, `unsubscribed`.

- [ ] **Step 3: Replace view color proxies with true status classes**

Render account, category, CMS, contact, newsletter, and coupon states with their actual state suffix. Give the verified-attendee badge the informational variant while keeping coupon-applied success and unavailable neutral.

- [ ] **Step 4: Run the focused test and verify green**

Run: `rtk php tests/run.php StatusUiTest`

Expected: all status contract and representative render tests pass.

- [ ] **Step 5: Commit the source implementation**

Stage only the shared stylesheet, corrected views, and focused test. Commit: `fix: apply semantic status colors globally`.

### Task 3: Build, version, and verify the production assets

**Files:**
- Modify: `public/assets/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/js/pwa.test.mjs`

**Interfaces:**
- Consumes: source Tailwind stylesheet and static-cache manifest
- Produces: minified production CSS delivered under a fresh cache key

- [ ] **Step 1: Update the stylesheet cache version consistently**

Use `20260813-semantic-status-colors` in every layout, service-worker cache name, service-worker asset URL, and matching test expectation.

- [ ] **Step 2: Rebuild production CSS**

Run: `rtk npm run build:css`

Expected: Tailwind completes successfully and writes `public/assets/css/app.css`.

- [ ] **Step 3: Run focused and full verification**

Run: `rtk php tests/run.php StatusUiTest`, `rtk php tests/run.php PwaStaticPolicyTest`, `rtk php tests/run.php OrganizerVenueControllerTest`, `rtk node tests/js/pwa.test.mjs`, `rtk composer test`, `rtk node --test tests/js/*.test.mjs`, `rtk composer check:syntax`, and `rtk git diff --check`.

Expected: every command exits successfully with no syntax, test, or whitespace failures.

- [ ] **Step 4: Commit the built and versioned assets**

Stage only the compiled stylesheet, layout cache references, service worker, and related tests. Commit: `build: publish semantic status styles`.
