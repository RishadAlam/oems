# Global Dashboard Page Header System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the unstyled and misaligned dashboard page headers by consolidating every administrator, organizer, and participant page onto the existing responsive OEMS heading component.

**Architecture:** Treat `.dashboard-page-heading` as the single internal page-header root. Add source-level structural and compiled-style regression tests, migrate the 19 unsupported `.dashboard-page-header` consumers without changing behavior, and browser-check representative pages against the 38 already-canonical consumers.

**Tech Stack:** PHP 8.2 view templates, Tailwind CSS 4 component layer, PHPUnit-style local test harness, in-app browser geometry checks.

## Global constraints

- Preserve every page title, description, icon, route, action, form, authorization condition, and semantic wrapper.
- Reuse the existing OEMS tokens and compiled stylesheet; add no JavaScript, dependency, decorative surface, or CSS compatibility alias.
- Keep public marketing, public event/article, authentication, verification, maintenance, and error headings context-specific.
- Maintain exactly one H1 per routed internal page and at least 44-pixel action targets.
- Commit the specification, plan, failing regression test, and working migration as separate completed checkpoints.
- Stage only files named by this plan and preserve unrelated workspace changes.

---

### Task 1: Lock the global header regression contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: internal view files under `admin`, `dashboard`, `organizer`, `participant`, `profile`, and the password-settings view.
- Produces: one DOM contract and one source/compiled responsive-style contract.

- [ ] **Step 1: Add a failing structural test**

Discover internal PHP views that contain an H1. Strip executable PHP before DOM parsing, then assert each view has exactly one `.dashboard-page-heading`, exactly one H1 inside that root, and no `.dashboard-page-header` token anywhere in application views. Aggregate all violations so the RED result names every affected file.

- [ ] **Step 2: Add the responsive style contract**

Assert source CSS preserves the canonical column-to-row flex utilities, 30/36-pixel H1 hierarchy, 14/24-pixel lead typography, and muted theme token. Assert compiled CSS preserves the base column layout, the 40rem bottom-aligned space-between row, responsive title size, and absence of the obsolete selector.

- [ ] **Step 3: Prove RED and commit**

Run:

```bash
rtk php tests/run.php UiLayoutTest
```

Expected: one aggregated structural failure naming the 19 legacy header views; the existing CSS contract passes.

Commit only the test change with `test: capture dashboard header hierarchy regression`.

### Task 2: Migrate the 19 broken header consumers

**Files:**
- Modify: `app/Views/admin/blog/form.php`
- Modify: `app/Views/admin/blog/index.php`
- Modify: `app/Views/admin/blog/preview.php`
- Modify: `app/Views/admin/contact/index.php`
- Modify: `app/Views/admin/contact/show.php`
- Modify: `app/Views/admin/events/trash.php`
- Modify: `app/Views/admin/newsletter/campaign-form.php`
- Modify: `app/Views/admin/newsletter/index.php`
- Modify: `app/Views/admin/operations/index.php`
- Modify: `app/Views/organizer/coupons/form.php`
- Modify: `app/Views/organizer/coupons/index.php`
- Modify: `app/Views/organizer/events/trash.php`
- Modify: `app/Views/participant/certificates/index.php`
- Modify: `app/Views/participant/registrations/index.php`
- Modify: `app/Views/participant/registrations/register.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Views/participant/tickets/index.php`
- Modify: `app/Views/participant/tickets/show.php`
- Modify: `app/Views/participant/waitlist/index.php`

**Interfaces:**
- Consumes: the canonical `.dashboard-page-heading` stylesheet and Task 1 test.
- Produces: 57 internal pages using one heading root.

- [ ] **Step 1: Replace only the unsupported root class**

Change `.dashboard-page-header` to `.dashboard-page-heading` in all 19 views. Keep every semantic `<header>` tag and every child unchanged. Do not add an alias or modify global typography.

- [ ] **Step 2: Prove GREEN**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk npm run build:css
rtk git diff --check
```

Expected: the focused suite passes; rebuilding the already-canonical styles produces no unintended source-contract change; no legacy class remains.

- [ ] **Step 3: Commit the migration**

Stage only the 19 view files and commit with `fix: standardize dashboard page headers`.

### Task 3: Verify responsive hierarchy end to end

**Files:**
- Modify only if verification reveals an in-scope header defect.

**Interfaces:**
- Consumes: the migrated markup and existing compiled component.
- Produces: automated and visual evidence across internal header variants.

- [ ] **Step 1: Browser-check representative pages**

Inspect `/admin/newsletter`, `/admin/blog`, and a previously-canonical admin page at 390, 768, 1280, and 2048 pixels. In both available themes verify one visible H1, title dominance, correct action placement, natural long-copy wrapping, no overlap, and no horizontal overflow. Role-restricted participant and organizer variants are covered by the identical DOM/style contract without changing the signed-in account.

- [ ] **Step 2: Run project verification**

Run:

```bash
rtk composer check:syntax
rtk npm run test:assets
rtk npm run test:forms
rtk composer test
```

Expected: every command exits zero.

- [ ] **Step 3: Review the scoped diff**

Inspect `rtk git diff --check`, the scoped diff, and repository status. Confirm no unrelated file is staged or modified by this task.

- [ ] **Step 4: Commit only verification-driven corrections**

If browser or full-suite verification requires an in-scope correction, add a focused regression first and commit the correction separately. Do not create an empty commit.
