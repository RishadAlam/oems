# Organizer Approval Workflow Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make organizer approval status, eligibility blockers, and pending review work visible and actionable on organizer and super-admin surfaces without weakening email verification.

**Architecture:** Extend the existing dashboard read repository with narrowly scoped organizer approval projections. Pass those projections through the existing dashboard controller and render them using semantic status cards and the current dashboard component system. Keep the existing service and SQL approval guards unchanged; the admin review page will expose their prerequisites instead of hiding the action without explanation.

**Tech Stack:** PHP 8.2, PDO, server-rendered PHP views, Tailwind CSS 4, PHPUnit-style project test runner, Playwright browser checks.

## Global Constraints

- An organizer can be approved only when the role is `organizer`, the account status is `active`, and `email_verified_at` is not null.
- Client-visible states must never imply that an unverified email is verified.
- Status meaning must use text and icons as well as color.
- All administrator POST actions retain CSRF and compare-and-set `expected_status` protection.
- No new runtime dependency or JavaScript is added.
- Use project tokens and existing dashboard patterns; do not introduce a second design system.
- Commit every independently verified task.

---

### Task 1: Approval dashboard read models

**Files:**
- Modify: `tests/Unit/DashboardMetricsRepositoryTest.php`
- Modify: `app/Repositories/DashboardMetricsRepository.php`

**Interfaces:**
- Produces: `DashboardMetricsRepository::organizerApprovalForUser(int $userId): array`
- Produces: `DashboardMetricsRepository::pendingOrganizerApplications(int $limit = 4): array`
- Extends: `DashboardMetricsRepository::totals(): array` with `pending_organizers`

- [ ] **Step 1: Write the failing repository tests**

Add SQLite fixtures with active, inactive, verified, unverified, approved, pending, and soft-deleted organizers. Assert:

```php
$this->assertSame('pending', $repository->organizerApprovalForUser(7)['approval_status']);
$this->assertSame(2, $repository->totals()['pending_organizers']);
$this->assertSame([12, 10], array_column($repository->pendingOrganizerApplications(2), 'id'));
$this->assertSame([], $repository->organizerApprovalForUser(999));
```

- [ ] **Step 2: Run the focused repository test and confirm RED**

Run: `rtk composer test -- tests/Unit/DashboardMetricsRepositoryTest.php`

Expected: failure because the projection methods and `pending_organizers` key do not exist.

- [ ] **Step 3: Implement the projections**

Add prepared PDO queries that return only non-deleted organizer accounts. Use `ORDER BY organizers.created_at ASC, organizers.id ASC` for pending review work, clamp the list limit to `1..20`, and cast count fields to integers.

- [ ] **Step 4: Run the focused repository test and confirm GREEN**

Run: `rtk composer test -- tests/Unit/DashboardMetricsRepositoryTest.php`

Expected: all tests pass.

- [ ] **Step 5: Commit the data layer**

```bash
rtk git add app/Repositories/DashboardMetricsRepository.php tests/Unit/DashboardMetricsRepositoryTest.php
rtk git commit -m "feat: expose organizer approval dashboard data"
```

### Task 2: Dashboard approval visibility

**Files:**
- Modify: `tests/Unit/DashboardLayoutTest.php`
- Modify: `app/Controllers/DashboardController.php`
- Modify: `app/Views/dashboard/organizer.php`
- Modify: `app/Views/dashboard/admin.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css` via the asset build

**Interfaces:**
- Consumes: `organizerApprovalForUser(int): array`
- Consumes: `pendingOrganizerApplications(int): array`
- Produces: `approval` view data for `dashboard/organizer`
- Produces: `pendingOrganizers` view data for `dashboard/admin`

- [ ] **Step 1: Write failing dashboard tests**

Assert that an unverified pending organizer sees `Organization approval pending`, `Email verification required`, and the valid `/profile` recovery link. Assert that the admin dashboard shows the pending organizer metric, `Organizer approval queue`, `/admin/organizers?approval_status=pending`, and a direct evidence link such as `/admin/organizers/20`.

- [ ] **Step 2: Run the focused layout tests and confirm RED**

Run: `rtk composer test -- tests/Unit/DashboardLayoutTest.php`

Expected: failure because the new approval panels and queue are absent.

- [ ] **Step 3: Pass the new controller data**

In `organizer()`, add:

```php
'approval' => $this->dashboardMetrics->organizerApprovalForUser($userId),
```

In `admin()`, render:

```php
'pendingOrganizers' => $this->dashboardMetrics->pendingOrganizerApplications(4),
```

- [ ] **Step 4: Render semantic approval panels**

Add a compact organizer status card with pending, approved, and rejected copy and the correct next action. Add the admin pending metric and a review queue that displays application readiness and direct review links. Escape every database value and use descriptive link names.

- [ ] **Step 5: Add responsive component styles and build assets**

Add `approval-overview`, `approval-readiness-list`, and `approval-queue` component classes using existing CSS variables. Build with:

`rtk npm run build:css`

Expected: `public/assets/css/app.css` contains the new compiled selectors.

- [ ] **Step 6: Run the focused layout tests and confirm GREEN**

Run: `rtk composer test -- tests/Unit/DashboardLayoutTest.php`

Expected: all tests pass.

- [ ] **Step 7: Commit dashboard visibility**

```bash
rtk git add app/Controllers/DashboardController.php app/Views/dashboard/organizer.php app/Views/dashboard/admin.php resources/css/app.css public/assets/css/app.css tests/Unit/DashboardLayoutTest.php
rtk git commit -m "feat: surface organizer approval workflows"
```

### Task 3: Administrator approval readiness controls

**Files:**
- Modify: `tests/Unit/AdminPeopleControllerTest.php`
- Modify: `app/Views/admin/organizers/show.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css` via the asset build

**Interfaces:**
- Preserves: `POST /admin/organizers/{id}/approve`
- Preserves: `POST /admin/organizers/{id}/reject`
- Preserves: hidden `_token` and `expected_status` fields

- [ ] **Step 1: Write failing action-readiness tests**

For an eligible pending organizer, assert the approval form and `Ready to approve`. For an unverified pending organizer, assert `Approval blocked`, `Email address verified`, `Not completed`, a disabled `Approve organizer` button, `aria-describedby="organizer-approval-readiness"`, no approve form action, and the existing rejection form.

- [ ] **Step 2: Run the focused people test and confirm RED**

Run: `rtk composer test -- tests/Unit/AdminPeopleControllerTest.php`

Expected: failure because blocked approval guidance and the disabled control are absent.

- [ ] **Step 3: Implement the readiness checklist**

Derive role, account, and email requirement booleans once in the view. Render the live approve form only when all three requirements pass and the lifecycle allows approval. Otherwise, for pending or rejected applications, render a warning summary, three requirement rows, and a disabled approval button associated with the summary. Keep rejection independently available.

- [ ] **Step 4: Build assets and run focused tests**

Run:

```bash
rtk npm run build:css
rtk composer test -- tests/Unit/AdminPeopleControllerTest.php
```

Expected: build succeeds and all focused tests pass.

- [ ] **Step 5: Commit the review experience**

```bash
rtk git add app/Views/admin/organizers/show.php resources/css/app.css public/assets/css/app.css tests/Unit/AdminPeopleControllerTest.php
rtk git commit -m "fix: explain organizer approval blockers"
```

### Task 4: End-to-end verification

**Files:**
- Modify only files required to correct failures introduced by Tasks 1 through 3

**Interfaces:**
- Verifies: repository projections, dashboard views, admin action forms, built assets, responsive browser behavior

- [ ] **Step 1: Run static and complete automated checks**

Run:

```bash
rtk composer validate --strict
rtk composer test
rtk npm run build:css
rtk git diff --check
```

Expected: all commands exit successfully.

- [ ] **Step 2: Browser-check the three workflow surfaces**

At desktop and mobile widths, inspect `/organizer/dashboard`, `/admin/dashboard`, and the pending `/admin/organizers/{id}` evidence page. Confirm that the status is visible, direct review links work, blocked approval cannot submit, eligible approval retains CSRF and expected-status fields, focus indicators are visible, and no content overlaps or overflows.

- [ ] **Step 3: Review the final diff and repository state**

Run:

```bash
rtk git diff --stat HEAD~3..HEAD
rtk git status --short
```

Expected: only organizer approval workflow files and pre-existing unrelated user changes are present.

- [ ] **Step 4: Commit any verification-only correction**

If Step 1 or Step 2 required a correction, commit only that correction:

```bash
rtk git add app/Controllers/DashboardController.php app/Repositories/DashboardMetricsRepository.php app/Views/admin/organizers/show.php app/Views/dashboard/admin.php app/Views/dashboard/organizer.php resources/css/app.css public/assets/css/app.css tests/Unit/AdminPeopleControllerTest.php tests/Unit/DashboardLayoutTest.php tests/Unit/DashboardMetricsRepositoryTest.php
rtk git commit -m "test: finalize organizer approval workflow"
```

If no correction was needed, do not create an empty commit.
