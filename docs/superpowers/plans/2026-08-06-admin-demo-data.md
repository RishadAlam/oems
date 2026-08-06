# Admin Dashboard Demo Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove both admin placeholder panels, show live platform totals, and load a repeatable realistic development dataset.

**Architecture:** A small `DashboardMetricsRepository` receives a PDO connection and owns the aggregate SQL. `DashboardController` injects it and passes a three-key metrics array to the existing PHP view. A separate transactional `database/demo_seed.sql` resolves relationships by stable emails and slugs so it can be imported repeatedly without duplicating demo records.

**Tech Stack:** PHP 8.2, custom MVC container, PDO MySQL, MySQL 8 SQL, dependency-free PHP tests, Tailwind CSS 4.

## Global Constraints

- Preserve `admin@oems.local` and the required records in `database/seed.sql`.
- Keep demo data optional and local-development only in `database/demo_seed.sql`.
- Count non-deleted users, all organizer profiles, and non-deleted events.
- Preserve dashboard navigation, design tokens, responsive behavior, dark theme, and accessibility behavior.
- Use one Git commit for each implementation task and push only project files.
- Prefix every shell command with `rtk`.

---

### Task 1: Remove Admin Placeholder Panels

**Files:**
- Modify: `tests/Unit/DashboardLayoutTest.php`
- Modify: `app/Views/dashboard/admin.php`
- Modify: `public/assets/css/app.css` through the existing Tailwind build

**Interfaces:**
- Consumes: `View::render('dashboard/admin', array $data, 'dashboard'): string`
- Produces: an admin overview whose content ends after `.dashboard-metric-grid`

- [ ] **Step 1: Write the failing rendered-view test**

Add a test that renders the real admin view and asserts both removed headings are absent:

```php
public function testAdminDashboardOmitsDeliveryPlaceholderPanels(): void
{
    $html = $this->renderAdminDashboard();

    $this->assertFalse(str_contains($html, 'Foundation readiness'));
    $this->assertFalse(str_contains($html, 'Next delivery'));
}
```

Extract the existing render setup into `private function renderAdminDashboard(array $overrides = []): string` so both layout tests use the real view without duplicating fixture data.

- [ ] **Step 2: Run the focused test and confirm red**

Run: `rtk php tests/run.php DashboardLayoutTest`

Expected: the new test fails because both headings still render.

- [ ] **Step 3: Remove both panels**

Delete the complete `<div class="mt-8 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">` block from `app/Views/dashboard/admin.php`. Do not replace it with another card or placeholder.

- [ ] **Step 4: Rebuild and verify**

Run: `rtk php tests/run.php DashboardLayoutTest`

Expected: all dashboard layout tests pass.

Run: `rtk npm run build:css`

Expected: Tailwind completes successfully and writes `public/assets/css/app.css`.

- [ ] **Step 5: Commit**

```bash
rtk git add -- tests/Unit/DashboardLayoutTest.php app/Views/dashboard/admin.php public/assets/css/app.css
rtk git commit -m "refactor: remove admin placeholder panels"
```

### Task 2: Add Live Admin Metrics

**Files:**
- Create: `app/Repositories/DashboardMetricsRepository.php`
- Create: `tests/Unit/DashboardMetricsRepositoryTest.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Controllers/DashboardController.php`
- Modify: `app/Views/dashboard/admin.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`

**Interfaces:**
- Produces: `DashboardMetricsRepository::totals(): array{users:int, organizers:int, events:int}`
- Consumes: `metrics` view data with the same three integer keys

- [ ] **Step 1: Write the failing repository test**

Create an in-memory SQLite PDO connection with minimal `users`, `organizers`, and `events` tables. Insert two visible users plus one deleted user, two organizer rows, and three visible events plus one deleted event. Assert:

```php
$this->assertSame([
    'users' => 2,
    'organizers' => 2,
    'events' => 3,
], $repository->totals());
```

- [ ] **Step 2: Run the repository test and confirm red**

Run: `rtk php tests/run.php DashboardMetricsRepositoryTest`

Expected: failure because `DashboardMetricsRepository` does not exist.

- [ ] **Step 3: Implement the repository**

Use one portable aggregate query and cast every count explicitly:

```php
$row = $this->connection->query(
    'SELECT
        (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS users,
        (SELECT COUNT(*) FROM organizers) AS organizers,
        (SELECT COUNT(*) FROM events WHERE deleted_at IS NULL) AS events'
)->fetch();

return [
    'users' => (int) $row['users'],
    'organizers' => (int) $row['organizers'],
    'events' => (int) $row['events'],
];
```

- [ ] **Step 4: Prove repository green and view red**

Run: `rtk php tests/run.php DashboardMetricsRepositoryTest`

Expected: pass.

Extend `DashboardLayoutTest` to render `metrics => ['users' => 12, 'organizers' => 3, 'events' => 6]` and assert each supplied value appears in the corresponding metric article. Run `rtk php tests/run.php DashboardLayoutTest` and confirm it fails while the view remains hardcoded.

- [ ] **Step 5: Wire metrics through the application**

Bind `DashboardMetricsRepository` in `bootstrap/app.php` with the shared `Database` connection. Add an explicit `DashboardController` constructor that accepts `View $view`, `Session $session`, `Security $security`, `Auth $auth`, `Config $config`, and `DashboardMetricsRepository $dashboardMetrics`; call `parent::__construct($view, $session, $security, $auth, $config)` and store the repository. In `admin()`, pass:

```php
[
    'pageTitle' => 'Platform overview',
    'metrics' => $this->dashboardMetrics->totals(),
]
```

Render escaped integer metrics in the view and use the captions `Registered accounts`, `Organizer profiles`, and `Event records`.

- [ ] **Step 6: Verify and commit**

Run: `rtk composer test`

Expected: zero failures.

Run: `rtk composer check:syntax`

Expected: no syntax errors.

```bash
rtk git add -- app/Repositories/DashboardMetricsRepository.php tests/Unit/DashboardMetricsRepositoryTest.php bootstrap/app.php app/Controllers/DashboardController.php app/Views/dashboard/admin.php tests/Unit/DashboardLayoutTest.php
rtk git commit -m "feat: show live admin metrics"
```

### Task 3: Add and Load Repeatable Demo Data

**Files:**
- Create: `database/demo_seed.sql`
- Modify: `README.md`

**Interfaces:**
- Consumes: roles, categories, payment methods, and administrator created by `database/seed.sql`
- Produces: 3 organizers, 8 participants, 3 venues, 6 events, 12 registrations, 8 payments, 8 tickets, 8 favorites, 6 notifications, 4 reviews, and event schedules

- [ ] **Step 1: Confirm the missing-seed failure**

Run an import against a verified-empty disposable MySQL database after loading `schema.sql` and `seed.sql`.

Expected: importing `database/demo_seed.sql` fails because the file does not exist.

- [ ] **Step 2: Create the transactional demo seed**

Use stable keys and guarded inserts. Each related lookup follows this pattern:

```sql
START TRANSACTION;

INSERT INTO users (role_id, name, email, password, status, email_verified_at)
SELECT id, 'Ayesha Rahman', 'ayesha.organizer@oems.local', '$2y$10$jgpoan2Mw3QGbb/ADEz5UebGZI9U7rGifg/ulZ98qHkt/aQWJqCIS', 'active', NOW()
FROM roles WHERE slug = 'organizer'
ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status);

SET @ayesha_user_id = (
    SELECT id FROM users WHERE email = 'ayesha.organizer@oems.local'
);

COMMIT;
```

Resolve every foreign key from a stable email, slug, registration number, transaction reference, or ticket number. Guard venue, schedule, notification, favorite, and review inserts with their natural identifying fields because those tables do not all have suitable unique keys.

- [ ] **Step 3: Verify repeatability and integrity**

Import `schema.sql`, `seed.sql`, then `demo_seed.sql` twice into the disposable database. Query exact demo totals and assert there are no foreign-key violations with `CHECK TABLE` for all populated tables. Drop only the uniquely named disposable database after verification.

Expected demo totals: 12 total users including the administrator, 3 organizers, 3 venues, 6 events, 12 registrations, 8 payments, 8 tickets, 8 favorites, 6 notifications, and 4 reviews after both imports.

- [ ] **Step 4: Document and load local data**

Add the optional command to `README.md`:

```bash
mysql -u root -p oems < database/demo_seed.sql
```

Document `DemoPass!2026` as the local-only password for every demo account and list one organizer and one participant email. Import `database/demo_seed.sql` into the configured local `oems` database, then query the exact totals above.

- [ ] **Step 5: Run final verification and commit**

Run:

```bash
rtk composer test
rtk composer check:syntax
rtk composer validate --strict
rtk npm run build:css
rtk git diff --check
```

Inspect the authenticated admin dashboard at 1440 by 1000 and 390 by 844. Verify the live values are 12, 3, and 6; both removed headings are absent; the mobile drawer opens and closes; and browser console logs are empty.

```bash
rtk git add -- database/demo_seed.sql README.md
rtk git commit -m "feat: add repeatable demo dataset"
rtk git push origin main
```
