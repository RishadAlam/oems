# Homepage CMS Banner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the half-width generic CMS banner card on the public homepage with a full-width, responsive, accessible announcement row.

**Architecture:** Keep the existing CMS repository, scheduling provider, and homepage order unchanged. Render each active banner through dedicated public announcement markup, style the component as a stacked mobile block and controlled split desktop row, then rebuild the Tailwind artifact and advance the stylesheet cache key across every layout and PWA policy consumer.

**Tech Stack:** PHP 8.2 custom MVC views, Tailwind CSS 4 component layer, custom PHP test runner, Node.js PWA asset tests, in-app browser visual verification.

## Global Constraints

- Preserve the existing OEMS brand, CMS fields, ordering, schedules, routes, page hierarchy, and public hero.
- Use the existing Manrope typography, blue accent, theme tokens, page shell, and radius system.
- Use design variance 4, motion intensity 2, and visual density 5.
- Do not add a carousel, automatic motion, dependency, image replacement, route, schema field, or validation rule.
- Keep one page-wide light or dark theme and verify both.
- Use no em dash or en dash characters in visible page copy.
- Keep image, title, subtitle, and action DOM order aligned with the visual reading order.
- Use a failing regression test before production changes.
- Commit the completed test and implementation steps separately.

---

### Task 1: Capture the public announcement regression

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: `View::render('home/index', array $data, 'public'): string`
- Produces: `UiLayoutTest::testHomeBannersRenderAsFullWidthPublicAnnouncements(): void` and a `renderHome(array $overrides = []): string` fixture helper

- [x] **Step 1: Extend the home rendering fixture**

Change `renderHome()` to accept an override array and merge it over the normal page data so one test can supply representative `homeBanners` without constructing database infrastructure:

```php
private function renderHome(array $overrides = []): string
{
    $view = new View(base_path('app/Views'));
    $data = [
        'app' => ['name' => 'OEMS'],
        'currentUser' => null,
        'flash' => [],
        'pageTitle' => 'Events worth showing up for',
        'featuredEvents' => [[
            'title' => 'Designing for public life',
            'slug' => 'designing-for-public-life',
            'category' => 'Creative workshop',
            'date' => 'August 22',
            'datetime' => '2026-08-22T10:00:00+06:00',
            'time' => '10:00 AM',
            'venue' => 'Dhanmondi, Dhaka',
            'price' => 'Free',
            'image' => '/assets/images/event-creative.webp',
            'alt' => 'A collaborative design workshop around a studio table',
        ]],
    ];

    return $view->render('home/index', array_replace($data, $overrides), 'public');
}
```

- [x] **Step 2: Write the failing rendered-view test**

Add a test that supplies a complete banner fixture and asserts the public contract independently from the implementation:

```php
public function testHomeBannersRenderAsFullWidthPublicAnnouncements(): void
{
    $html = $this->renderHome([
        'homeBanners' => [[
            'id' => 7,
            'title' => 'August community series',
            'subtitle' => 'Three practical sessions for local organizers.',
            'image_path' => '/uploads/banners/community-series.webp',
            'link_url' => '/events?category=community',
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 10,
        ]],
    ]);

    $this->assertTrue(str_contains($html, 'class="home-announcements"'));
    $this->assertTrue(str_contains($html, 'class="home-announcement"'));
    $this->assertTrue(str_contains($html, 'class="home-announcement__media"'));
    $this->assertTrue(str_contains($html, 'class="home-announcement__body"'));
    $this->assertTrue(str_contains($html, 'alt="Promotion: August community series"'));
    $this->assertTrue(str_contains($html, '<h2>August community series</h2>'));
    $this->assertTrue(str_contains($html, 'Three practical sessions for local organizers.'));
    $this->assertTrue(str_contains($html, 'href="/events?category=community"'));
    $this->assertTrue(str_contains($html, 'Find your next standout event.'));
    $this->assertFalse(str_contains($html, 'class="dashboard-panel overflow-hidden p-0"'));
    $this->assertFalse(str_contains($html, 'lg:grid-cols-2'));
}
```

This test catches a return to a half-width generic card, missing announcement structure, missing accessible image identity, lost CMS content, or accidental replacement of the primary hero.

- [x] **Step 3: Run the focused test and verify the expected failure**

Run:

```bash
php tests/run.php tests/Unit/UiLayoutTest.php
```

Expected: FAIL in `testHomeBannersRenderAsFullWidthPublicAnnouncements` because `home-announcements` and `home-announcement` do not exist in the current output.

- [x] **Step 4: Commit the failing regression test**

```bash
git add tests/Unit/UiLayoutTest.php
git commit -m "test: capture homepage banner layout regression"
```

### Task 2: Implement the responsive announcement component

**Files:**
- Modify: `app/Views/home/index.php:39-41`
- Modify: `resources/css/app.css:340-365`
- Modify: `public/assets/css/app.css`
- Modify: `app/Views/layouts/public.php:35`
- Modify: `app/Views/layouts/auth.php:11`
- Modify: `app/Views/layouts/dashboard.php:13`
- Modify: `app/Views/layouts/maintenance.php:9`
- Modify: `public/service-worker.js:3-8`
- Modify: `tests/Unit/PwaStaticPolicyTest.php:62`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php:184`
- Modify: `tests/js/pwa.test.mjs:90-121`

**Interfaces:**
- Consumes: each `$homeBanners` row with `title`, `subtitle`, `image_path`, and `link_url`
- Produces: semantic `.home-announcements` and `.home-announcement` public markup plus compiled responsive CSS served as `20260813-home-banner-v1`

- [x] **Step 1: Replace the generic banner markup**

Render the collection as a dedicated stacked list. Use escaped values for every CMS field and retain the optional content conditions:

```php
<?php if (($homeBanners ?? []) !== []): ?>
<section class="home-announcements" aria-label="Platform announcements">
    <div class="page-shell home-announcements__list">
        <?php foreach ($homeBanners as $banner): ?>
            <article class="home-announcement">
                <div class="home-announcement__media">
                    <img src="<?= e($banner['image_path']) ?>" alt="Promotion: <?= e($banner['title']) ?>" width="1200" height="525" loading="lazy">
                </div>
                <div class="home-announcement__body">
                    <h2><?= e($banner['title']) ?></h2>
                    <?php if (!empty($banner['subtitle'])): ?><p><?= e($banner['subtitle']) ?></p><?php endif; ?>
                    <?php if (!empty($banner['link_url'])): ?><a class="text-link" href="<?= e($banner['link_url']) ?>"><span>Learn more</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
```

- [x] **Step 2: Add the dedicated component styles**

Add these rules near the other homepage section styles:

```css
.home-announcements {
    @apply py-8 sm:py-10 lg:py-12;
}

.home-announcements__list {
    @apply grid gap-5;
}

.home-announcement {
    @apply grid min-w-0 overflow-hidden rounded-[24px] border border-[var(--line)] bg-[var(--surface-raised)] shadow-[var(--shadow-soft)] lg:min-h-[300px] lg:grid-cols-[minmax(0,1.9fr)_minmax(18rem,1fr)];
}

.home-announcement__media {
    @apply min-h-0 overflow-hidden bg-[var(--surface-soft)];
}

.home-announcement__media img {
    @apply aspect-[16/7] h-full w-full object-cover object-center lg:min-h-[300px] lg:aspect-auto;
}

.home-announcement__body {
    @apply flex min-w-0 flex-col justify-center p-6 sm:p-8 lg:p-10;
}

.home-announcement__body h2 {
    @apply text-2xl font-semibold leading-tight tracking-[-0.035em] sm:text-3xl;
}

.home-announcement__body p {
    @apply mt-3 text-sm leading-6 text-[var(--ink-muted)] sm:text-base sm:leading-7;
}

.home-announcement__body .text-link {
    @apply mt-6 self-start;
}
```

- [x] **Step 3: Rebuild the stylesheet**

Run:

```bash
npm run build:css
```

Expected: exit 0 and `public/assets/css/app.css` contains the new component rules and the large-breakpoint split grid.

- [x] **Step 4: Advance the stylesheet cache key everywhere**

Replace `20260813-form-divider-v2` with `20260813-home-banner-v1` in all four layouts, `public/service-worker.js`, `tests/Unit/PwaStaticPolicyTest.php`, `tests/Unit/OrganizerVenueControllerTest.php`, and `tests/js/pwa.test.mjs`. Do not change JavaScript cache keys.

- [x] **Step 5: Run focused PHP and PWA tests**

Run:

```bash
php tests/run.php tests/Unit/UiLayoutTest.php
php tests/run.php tests/Unit/PwaStaticPolicyTest.php
php tests/run.php tests/Unit/OrganizerVenueControllerTest.php
node --test tests/js/pwa.test.mjs tests/js/assets.test.mjs
```

Expected: all commands exit 0. The new home banner test passes, the new stylesheet URL is present in rendered layouts, and the service worker precaches and updates the same URL.

- [x] **Step 6: Commit the implementation**

```bash
git add app/Views/home/index.php resources/css/app.css public/assets/css/app.css app/Views/layouts/public.php app/Views/layouts/auth.php app/Views/layouts/dashboard.php app/Views/layouts/maintenance.php public/service-worker.js tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php tests/js/pwa.test.mjs
git commit -m "fix: redesign homepage cms banners"
```

### Task 3: Verify the completed homepage experience

**Files:**
- Modify: `docs/superpowers/plans/2026-08-13-homepage-cms-banner.md`

**Interfaces:**
- Consumes: the database-backed active banner at `http://127.0.0.1:8088/`
- Produces: recorded automated and visual verification evidence in this plan

- [x] **Step 1: Verify desktop light theme**

At 1440x900, reload the homepage and confirm:

- the main hero remains the first-screen focus;
- the announcement article spans the page shell instead of half the shell;
- image and copy form one row;
- the announcement has no horizontal overflow;
- the category rail follows the announcement without a large dead area.

- [x] **Step 2: Verify desktop dark theme**

Switch the same page to dark theme and confirm the surface, border, title, muted copy, link, and focus treatment use the existing theme tokens with no local light panel.

- [x] **Step 3: Verify mobile light and dark themes**

At 390x844, reload in both themes and confirm:

- the image stacks above the content;
- the image keeps a wide landscape crop;
- title, subtitle, and action wrap without clipping;
- the page has no horizontal overflow;
- all tap targets and focus states remain usable.

- [x] **Step 4: Run the full verification suite**

Run:

```bash
composer test
node --test tests/js/*.test.mjs
composer check:syntax
composer validate --strict
git diff --check
```

Expected: every command exits 0.

- [x] **Step 5: Record exact evidence and commit plan completion**

Replace the unchecked boxes in this plan with completed boxes, add the exact PHP test count, assertion count, JavaScript test count, and browser viewport results, then commit only this plan:

```bash
git add docs/superpowers/plans/2026-08-13-homepage-cms-banner.md
git commit -m "docs: complete homepage banner implementation plan"
```

## Completion evidence

- Red test: `UiLayoutTest` produced 19 tests, 84 assertions, and the one expected homepage announcement failure before production changes.
- Focused green tests: `UiLayoutTest` produced 19 tests and 94 assertions; `PwaStaticPolicyTest` produced 4 tests and 51 assertions; `OrganizerVenueControllerTest` produced 15 tests and 98 assertions; focused PWA and asset tests produced 2 passes. All had zero failures.
- Production asset build: `npm run build:css` completed with Tailwind CSS 4.3.3 and rebuilt local fonts, Leaflet assets, Chart.js, PWA icons, and the minified stylesheet.
- Full PHP suite: 824 tests, 6097 assertions, 0 failures.
- Full JavaScript suite: 48 tests, 48 passes, 0 failures.
- PHP syntax scan: every application, view, script, and test PHP file reported no syntax errors.
- Composer validation: `composer.json` is valid under `--strict`.
- Repository whitespace check: `git diff --check` exited 0.
- Desktop light verification at 1440x900: the live announcement measured 1160px wide and 302px high inside the 1240px shell, with a 759px media region, a 399px content region, and no horizontal overflow.
- Desktop dark verification at 1440x900: the announcement used the dark surface `rgb(19, 29, 44)`, border `rgb(42, 56, 77)`, heading `rgb(242, 245, 250)`, and no horizontal overflow.
- Mobile light and dark verification at 390x844: the announcement measured 350px wide and 232px high, the 348px by 152px image stacked above the content, both themes remained readable, and the document had no horizontal overflow.
