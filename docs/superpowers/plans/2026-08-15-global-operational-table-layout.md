# Global Operational Table Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every OEMS operational table structurally consistent, responsive, accessible, overflow-safe, and professionally aligned, including truthful banner delivery states.

**Architecture:** Preserve the existing PHP view and Tailwind-based OEMS design system. Enforce one static markup contract across all 25 operational tables, keep flex behavior inside action groups instead of table cells, and strengthen the shared desktop/mobile CSS. Derive banner delivery presentation through a pure strict presenter fed one configured-timezone clock by the CMS controller, without changing persisted state or action endpoints.

**Tech Stack:** PHP 8.2+, custom OEMS MVC/views, Tailwind CSS 4 source utilities, compiled CSS in `public/assets/css/app.css`, PHPUnit-style OEMS test runner, Node-based PWA assertions, service worker static cache.

## Global Constraints

- Preserve every URL, controller endpoint, HTTP method, form field, CSRF input, confirmation, and status mutation.
- Use only existing OEMS tokens, components, Phosphor icons, and light/dark theme strategy.
- Add no dependency, shadow, gradient, animation, or page-specific layout override.
- Keep native tables at 768px and wider; use labelled cards below 768px.
- Never apply flex or grid display directly to a desktop `<td>`.
- Keep every interactive target at least 44px high through the existing button contract.
- Do not change, delete, or silently repair stored banner dates.
- Rebuild CSS and publish one matching asset version through all layouts, the service worker, and pinned tests.
- Preserve unrelated worktree changes and untracked files.

---

### Task 1: Capture the project-wide table contract in RED tests

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/Unit/StatusUiTest.php`
- Modify: `tests/Unit/CmsControllerTest.php`

**Interfaces:**
- Consumes: all view sources under `app/Views` and both source/compiled CSS files.
- Produces: `testEveryOperationalTableUsesTheGlobalResponsiveContract()`, `testOperationalTableCssPublishesDesktopAndMobileLayoutContract()`, and banner delivery-state render assertions.

- [ ] **Step 1: Extend the source-DOM inventory test**

Add a manifest for all 21 table-bearing view files and assert that exactly 25 tables satisfy:

```php
$this->assertStringContainsString('operations-table organizer-table', $table->getAttribute('class'));
$this->assertSame(1, $xpath->query('./caption[normalize-space(.) != ""]', $table)?->length);
$this->assertSame(0, $xpath->query('.//th[not(@scope="col")]', $table)?->length);
$this->assertSame(0, $xpath->query('.//td[not(@data-label) or normalize-space(@data-label)=""]', $table)?->length);
```

Also scan source markup and fail on either legacy action shape:

```php
$this->assertDoesNotMatchRegularExpression('/<td[^>]*\badmin-table-actions\b/', $source);
$this->assertDoesNotMatchRegularExpression('/class="[^"]*\btable-actions\b[^"]*"/', $source);
```

Require visible `Action` or `Actions` header text for every `organizer-table__action` column and require a nested `.admin-table-actions` when the cell contains more than one interactive action.

- [ ] **Step 2: Add CSS source/compiled parity assertions**

Assert both CSS files publish:

```css
.operations-table { min-width: 760px; }
.organizer-table td { overflow-wrap: anywhere; }
.organizer-table__action { width: 1%; white-space: nowrap; }
.admin-table-actions { flex-wrap: nowrap; min-width: max-content; }
```

Within `@media (max-width: 767px)`, require:

```css
.organizer-table td::before { grid-column: 1; grid-row: 1; }
.organizer-table td > * { grid-column: 2; min-width: 0; }
.organizer-table td.organizer-table__action { width: 100%; white-space: normal; }
.organizer-table td.organizer-table__action .admin-table-actions { min-width: 0; flex-wrap: wrap; }
```

Explicitly reject `td.admin-table-actions::before` so mobile action labels cannot be hidden.

- [ ] **Step 3: Add banner delivery-state fixtures**

Render `admin/cms/index` with four stable records:

```php
'banners' => [
    ['id' => 1, 'title' => 'Live', 'is_active' => 1, 'starts_at' => null, 'ends_at' => null],
    ['id' => 2, 'title' => 'Scheduled', 'is_active' => 1, 'starts_at' => '2099-01-01 10:00:00', 'ends_at' => null],
    ['id' => 3, 'title' => 'Ended', 'is_active' => 1, 'starts_at' => '2000-01-01 10:00:00', 'ends_at' => '2001-01-01 10:00:00'],
    ['id' => 4, 'title' => 'Disabled', 'is_active' => 0, 'starts_at' => null, 'ends_at' => null],
],
```

Assert one textual `Live`, `Scheduled`, `Ended`, and `Disabled` state with success, warning, neutral, and neutral status modifiers. Require `Starts`, `Ends`, `<time datetime="2099-01-01T10:00:00">`, `Immediately`, and `No end date`; reject the old raw `Active banner` contract.

- [ ] **Step 4: Run focused tests and confirm intentional RED**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php StatusUiTest
rtk php tests/run.php CmsControllerTest
```

Expected: failures only for missing shared markup, missing CSS rules, and old banner status/schedule output. No fixture, parser, or syntax failures.

- [ ] **Step 5: Commit the RED contract**

```bash
rtk git add tests/Unit/UiLayoutTest.php tests/Unit/StatusUiTest.php tests/Unit/CmsControllerTest.php
rtk git commit -m "test: capture global operational table layout"
```

---

### Task 2: Normalize all operational table markup

**Files:**
- Modify: `app/Views/components/analytics-charts.php`
- Modify: `app/Views/dashboard/organizer.php`
- Modify: `app/Views/admin/analytics/index.php`
- Modify: `app/Views/admin/blog/index.php`
- Modify: `app/Views/admin/categories/index.php`
- Modify: `app/Views/admin/cms/index.php`
- Modify: `app/Views/admin/contact/index.php`
- Modify: `app/Views/admin/events/index.php`
- Modify: `app/Views/admin/events/trash.php`
- Modify: `app/Views/admin/newsletter/index.php`
- Modify: `app/Views/admin/organizers/index.php`
- Modify: `app/Views/admin/payments/index.php`
- Modify: `app/Views/admin/reports/index.php`
- Modify: `app/Views/admin/users/index.php`
- Modify: `app/Views/organizer/analytics/index.php`
- Modify: `app/Views/organizer/announcements/index.php`
- Modify: `app/Views/organizer/coupons/index.php`
- Modify: `app/Views/organizer/events/index.php`
- Modify: `app/Views/organizer/events/trash.php`
- Modify: `app/Views/organizer/participants/index.php`
- Modify: `app/Views/organizer/venues/index.php`
- Test: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: markup invariants introduced in Task 1.
- Produces: 25 operational tables with one stable desktop/mobile DOM contract.

- [ ] **Step 1: Apply the shared table classes and semantics**

For every table, use:

```html
<table class="operations-table organizer-table">
```

Add a non-empty `caption.sr-only` where absent, especially `Organizer event participants`. Add `scope="col"` to every literal and dynamic `<th>`.

- [ ] **Step 2: Match every cell to its responsive label**

Give every `<td>` a non-empty `data-label` matching its corresponding visible header. Replace screen-reader-only action headers with visible `Action` for one operation and `Actions` for multiple operations.

Use this single-action shape:

```html
<th scope="col">Action</th>
<td class="organizer-table__action" data-label="Action"><a class="text-link" href="/admin/users/1">Review</a></td>
```

- [ ] **Step 3: Repair every multi-action cell**

Change CMS pages, CMS FAQs, CMS banners, Categories, and Coupons to:

```html
<th scope="col">Actions</th>
<td class="organizer-table__action" data-label="Actions">
    <div class="admin-table-actions">
        <a class="button button--quiet button--compact" href="/admin/categories/<?= e($category['id']) ?>/edit"><span>Edit</span></a>
        <form action="/admin/categories/<?= e($category['id']) ?>/status" method="post" data-form-kind="action"<?= $isActive ? ' data-confirm="Deactivate this category? It will no longer be available for new event drafts."' : '' ?>>
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>">
            <button class="button button--compact <?= $isActive ? 'button--danger' : 'button--quiet' ?>" type="submit"><span><?= $isActive ? 'Deactivate' : 'Activate' ?></span></button>
        </form>
    </div>
</td>
```

Do not change action URLs, HTTP methods, hidden inputs, confirmation text, or button states.

- [ ] **Step 4: Group the high-risk CMS/category values**

Wrap CMS Page, FAQ Question, Banner, and Category primary copy in:

```html
<div class="organizer-table__primary organizer-table__value">
    <strong><?= e($record['title']) ?></strong>
    <small><?= e($record['summary'] ?? 'No summary') ?></small>
</div>
```

Apply `.organizer-table__value` to route, slug, code, email, and other explicit identifier cells touched by the migration.

- [ ] **Step 5: Run structural tests and PHP syntax checks**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php StatusUiTest
rtk composer check:syntax
```

Expected: markup assertions pass; CSS and banner presentation assertions may remain RED until Task 3.

- [ ] **Step 6: Commit the markup migration**

```bash
rtk git add app/Views tests/Unit/UiLayoutTest.php tests/Unit/StatusUiTest.php
rtk git commit -m "fix: standardize operational table markup"
```

---

### Task 3: Publish the responsive CSS and truthful banner presentation

**Files:**
- Create: `app/Support/CmsBannerPresenter.php`
- Create: `tests/Unit/CmsBannerPresenterTest.php`
- Modify: `app/Controllers/AdminCmsController.php`
- Modify: `resources/css/app.css`
- Modify: `app/Views/admin/cms/index.php`
- Modify: `public/assets/css/app.css` (generated)
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/js/pwa.test.mjs`
- Test: `tests/Unit/UiLayoutTest.php`
- Test: `tests/Unit/StatusUiTest.php`
- Test: `tests/Unit/CmsControllerTest.php`

**Interfaces:**
- Consumes: standardized table markup from Task 2.
- Produces: `CmsBannerPresenter::present(array $banner, DateTimeImmutable $now, DateTimeZone $timezone): array`, source/compiled responsive layout parity, and accurate CMS banner delivery presentation.

- [ ] **Step 1: Add strict presenter and controller-boundary RED tests**

Create `CmsBannerPresenterTest` with a frozen `2026-08-15 12:00:00 Asia/Dhaka` instant. Hand-written fixtures must prove:

```php
$this->assertSame('Live', $presenter->present($live, $now, $timezone)['delivery']['label']);
$this->assertSame('Scheduled', $presenter->present($future, $now, $timezone)['delivery']['label']);
$this->assertSame('Ended', $presenter->present($past, $now, $timezone)['delivery']['label']);
$this->assertSame('Disabled', $presenter->present($disabled, $now, $timezone)['delivery']['label']);
```

Also require exact-start and exact-end equality to be Live, null bounds to produce `Immediately` and `No end date`, malformed/non-scalar/reversed active schedules to return neutral `Unknown` plus `Schedule unavailable`, and valid ISO values to contain `+06:00`. Update the existing controller render expectation from `2099-01-01T10:00:00` to `2099-01-01T10:00:00+06:00`, then extend `CmsControllerTest` so rendered malformed schedule rows contain no `<time>` and do not throw.

Run:

```bash
rtk php tests/run.php CmsBannerPresenterTest
rtk php tests/run.php CmsControllerTest
```

Expected: presenter test fails because the class does not exist; controller test fails because raw rows are still passed directly to the view.

- [ ] **Step 2: Implement the pure banner presenter and controller mapping**

Create this public API:

```php
final class CmsBannerPresenter
{
    public function present(array $banner, DateTimeImmutable $now, DateTimeZone $timezone): array;
}
```

Strictly parse scalar strings with `DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone)` and reject parse warnings/errors or a reformatted value that differs from the input. Disabled takes precedence over schedule validity. For enabled rows, return Unknown when either supplied bound is invalid or when `end <= start`; otherwise use strict public-window comparisons (`start > now` Scheduled, `end < now` Ended, equality Live). Format valid machine values as `Y-m-d\TH:i:sP` and display values as `M j, Y, g:i A`.

Inject `CmsBannerPresenter` into `AdminCmsController`. In `index()`, construct one `DateTimeZone` from `$this->config->get('timezone', 'UTC')`, one `DateTimeImmutable('now', $timezone)`, and map every raw banner through the presenter before rendering.

- [ ] **Step 3: Strengthen the shared desktop CSS**

Update the existing component rules:

```css
.organizer-table td {
    @apply border-b border-[var(--line)] px-4 py-4 align-middle last:border-b-0;
    min-width: 0;
    overflow-wrap: anywhere;
}

.organizer-table__action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.admin-table-actions {
    @apply flex flex-nowrap items-center justify-end gap-2;
    min-width: max-content;
}
```

- [ ] **Step 4: Fix mobile grid placement and actions**

Inside the existing max-width 767px media block add:

```css
.organizer-table td::before {
    grid-column: 1;
    grid-row: 1;
}

.organizer-table td > * {
    grid-column: 2;
    min-width: 0;
}

.organizer-table td.organizer-table__action {
    width: 100%;
    white-space: normal;
}

.organizer-table td.organizer-table__action .admin-table-actions {
    width: 100%;
    min-width: 0;
    flex-wrap: wrap;
}
```

Delete the legacy `td.admin-table-actions::before` rule.

- [ ] **Step 5: Render the presented banner schedule and delivery state**

Read only the presenter's `schedule` and `delivery` structures in `admin/cms/index.php`. A valid bound renders a labelled semantic time:

```php
<span class="organizer-table__meta-label">Starts</span>
<time datetime="<?= e($banner['schedule']['starts']['iso']) ?>"><?= e($banner['schedule']['starts']['display']) ?></time>
```

Missing bounds render `Immediately` or `No end date`. Invalid or reversed legacy schedules render `Schedule unavailable` without a `<time>`. Render the presenter's semantic tone directly:

```php
<span class="status-chip status-chip--<?= e($banner['delivery']['tone']) ?>"><?= e($banner['delivery']['label']) ?></span>
```

- [ ] **Step 6: Build CSS and publish one cache version**

Run:

```bash
rtk npm run build:css
```

Replace `20260815-operations-table-v1` with `20260815-table-layout-v2` in all four layouts, the service-worker cache name/asset URL, and the three pinned test files.

- [ ] **Step 7: Run focused GREEN gates**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php StatusUiTest
rtk php tests/run.php CmsBannerPresenterTest
rtk php tests/run.php CmsControllerTest
rtk php tests/run.php PwaStaticPolicyTest
rtk php tests/run.php OrganizerVenueControllerTest
rtk node tests/js/pwa.test.mjs
rtk composer check:syntax
rtk npm run test:forms
rtk npm run test:assets
```

Expected: all pass with source/compiled CSS and asset-version parity.

- [ ] **Step 8: Commit the published implementation**

```bash
rtk git add app/Support/CmsBannerPresenter.php app/Controllers/AdminCmsController.php resources/css/app.css public/assets/css/app.css app/Views/admin/cms/index.php app/Views/layouts public/service-worker.js tests
rtk git commit -m "fix: publish resilient operational table layout"
```

---

### Task 4: Browser verification, independent review, and completion audit

**Files:**
- Create: `.superpowers/sdd/2026-08-15-global-operational-table-layout/verification-report.md`
- Modify only when browser or review evidence proves a defect: files from Tasks 1-3.

**Interfaces:**
- Consumes: completed markup, CSS, compiled assets, and banner presentation.
- Produces: browser evidence, full-suite evidence, an independent review, and a final corrective commit only if needed.

- [ ] **Step 1: Run the responsive browser matrix**

Use authenticated real routes:

- `/admin/cms`
- `/admin/categories`
- `/organizer/coupons`
- the Participants link discovered from the authenticated `/organizer/events` page

At 390, 768, 1280, and 2048 CSS pixels in light and dark themes verify:

- no document-level horizontal overflow;
- mobile cards have visible labels and values in the correct columns;
- desktop table cells remain table cells;
- action buttons are grouped, aligned, and unwrapped on desktop;
- action groups wrap without clipping on mobile;
- long titles, subtitles, slugs, email addresses, and codes wrap safely;
- CMS dates are readable and status reflects Disabled, Scheduled, Ended, or Live;
- keyboard order follows row content then actions;
- empty states remain intact.

- [ ] **Step 2: Run the complete automated suite**

Run:

```bash
rtk composer test
rtk node tests/js/pwa.test.mjs
rtk npm run test:assets
rtk npm run test:forms
rtk composer check:syntax
rtk git diff --check
```

If localhost socket tests are sandbox-blocked, rerun the same command with the approved escalation instead of weakening tests.

- [ ] **Step 3: Request an independent code review**

Review the complete range from the pre-task base through HEAD for:

- table semantics and header/label parity;
- route/form/CSRF preservation;
- CSS source/compiled parity;
- mobile and desktop geometry risks;
- banner state truthfulness and date parsing;
- status tone correctness;
- cache/version consistency;
- unrelated-file contamination.

- [ ] **Step 4: Apply only evidence-backed corrections**

For each Critical or Important finding, add a failing regression test, make the smallest fix, rerun focused and full gates, and commit:

```bash
rtk git add app/Views resources/css/app.css public/assets/css/app.css public/service-worker.js tests
rtk git commit -m "fix: close operational table review gaps"
```

- [ ] **Step 5: Write the verification report**

Record exact commands, test counts, browser route/viewport/theme results, any fixture limitations, commit hashes, and final worktree status in:

```text
.superpowers/sdd/2026-08-15-global-operational-table-layout/verification-report.md
```

Do not stage unrelated pre-existing modifications or artifacts.
