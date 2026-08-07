# OEMS Week 2 Event Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver database-backed category, venue, event, media, moderation, public discovery, and dashboard workflows for the complete OEMS Week 2 milestone.

**Architecture:** Focused PDO repositories own prepared SQL and ownership constraints. `EventService` owns validation, normalization, slugs, uploads, and lifecycle rules; thin public, organizer, and administrator controllers adapt HTTP requests to the domain. Views extend the existing Tailwind/Manrope/Phosphor product system with accessible responsive forms, lists, filters, empty states, and detail layouts.

**Tech Stack:** PHP 8.2+, PDO MySQL with SQLite integration tests, raw OOP MVC, HTML5, Tailwind CSS v4, Vanilla JavaScript, Fileinfo/GD image validation, Composer test runner.

## Global Constraints

- Use only HTML5, CSS3, Tailwind CSS, Vanilla JavaScript, raw PHP OOP, and MySQL.
- Preserve strict typing, namespaces, dependency injection, PSR-12 structure, prepared statements, CSRF, output escaping, role middleware, and existing OEMS route/brand conventions.
- Keep registration, checkout, payment, tickets, QR, and attendance out of Week 2.
- Public event visibility is limited to non-deleted `published` records.
- Organizer writes must be scoped through the authenticated user's organizer row.
- Uploaded images must be JPEG, PNG, or WebP, no larger than 5 MB, and stored with randomized names under `public/uploads/events`.
- Keep `.env`, SMTP credentials, local presentations, `.tmp`, pnpm files, and unrelated untracked documents out of every commit.
- Add a git commit after every task.

---

### Task 1: Category and Venue Persistence

**Files:**
- Create: `app/Contracts/CategoryRepositoryInterface.php`
- Create: `app/Contracts/VenueRepositoryInterface.php`
- Create: `app/Repositories/CategoryRepository.php`
- Create: `app/Repositories/VenueRepository.php`
- Create: `tests/Unit/CategoryRepositoryTest.php`
- Create: `tests/Unit/VenueRepositoryTest.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Produces: `CategoryRepositoryInterface::active(): array`, `all(): array`, `find(int): ?array`, `slugExists(string, ?int): bool`, `create(array): int`, `update(int, array): bool`, `setActive(int, bool): bool`.
- Produces: `VenueRepositoryInterface::forOrganizerUser(int): array`, `findOwned(int, int): ?array`, `createForUser(int, array): ?int`, `updateOwned(int, int, array): bool`, `deleteOwnedIfUnused(int, int): bool`.

- [ ] **Step 1: Write failing repository integration tests**

```php
public function testActiveCategoriesExcludeDisabledRowsAndFollowSortOrder(): void
{
    $repository = new CategoryRepository($this->connection);
    $this->assertSame(['technology', 'community'], array_column($repository->active(), 'slug'));
}

public function testVenueUpdateCannotCrossOrganizerOwnership(): void
{
    $repository = new VenueRepository($this->connection);
    $updated = $repository->updateOwned(20, 91, [
        'name' => 'Changed venue',
        'address_line' => 'Road 12',
        'city' => 'Dhaka',
        'country' => 'Bangladesh',
        'postal_code' => '1209',
        'latitude' => null,
        'longitude' => null,
        'map_url' => null,
        'capacity' => 150,
    ]);
    $this->assertFalse($updated);
    $this->assertSame('Original venue', $this->venueName(91));
}
```

- [ ] **Step 2: Run tests and verify RED**

Run: `rtk php tests/run.php CategoryRepositoryTest && rtk php tests/run.php VenueRepositoryTest`

Expected: FAIL because the repository classes do not exist.

- [ ] **Step 3: Implement repository contracts and prepared PDO queries**

Use explicit column lists, organizer joins through `organizers.user_id`, and `NOT EXISTS` against live events before venue deletion. Return booleans from affected row counts and normalized arrays from fetches.

- [ ] **Step 4: Register both repositories in the container and verify GREEN**

Run: `rtk php tests/run.php CategoryRepositoryTest && rtk php tests/run.php VenueRepositoryTest && rtk composer test`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
rtk git add app/Contracts/CategoryRepositoryInterface.php app/Contracts/VenueRepositoryInterface.php app/Repositories/CategoryRepository.php app/Repositories/VenueRepository.php tests/Unit/CategoryRepositoryTest.php tests/Unit/VenueRepositoryTest.php bootstrap/app.php
rtk git commit -m "feat: add event taxonomy and venues"
```

### Task 2: Event Repository and Public Queries

**Files:**
- Create: `app/Contracts/EventRepositoryInterface.php`
- Create: `app/Repositories/EventRepository.php`
- Create: `tests/Unit/EventRepositoryTest.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Produces: `featured(int): array`, `publicSearch(array): array`, `publicCities(): array`, `findPublishedBySlug(string): ?array`, `gallery(int): array`.
- Produces: `organizerSummary(int): array`, `forOrganizerUser(int, ?string): array`, `findOwned(int, int): ?array`, `slugExists(string, ?int): bool`, `createForUser(int, array): ?int`, `updateOwned(int, int, array): bool`, `softDeleteOwned(int, int, array): bool`, `transitionOwned(int, int, array, string): bool`.
- Produces: `forAdmin(?string): array`, `findForAdmin(int): ?array`, `transitionAdmin(int, int, array, string, ?string): bool`, `replaceGallery(int, array): void`, `deleteGalleryImageOwned(int, int, int): ?string`.

- [ ] **Step 1: Write failing SQLite integration tests for public filters and ownership**

```php
public function testPublicSearchCombinesCategoryCityFreeAndSoonestFilters(): void
{
    $events = $this->repository->publicSearch([
        'search' => 'summit', 'category' => 'technology', 'city' => 'Dhaka',
        'date' => 'upcoming', 'price' => 'free', 'sort' => 'soonest',
    ]);
    $this->assertSame(['free-dhaka-summit'], array_column($events, 'slug'));
}

public function testOrganizerCannotLoadOrUpdateAnotherOrganizersEvent(): void
{
    $this->assertNull($this->repository->findOwned(20, 502));
    $this->assertFalse($this->repository->updateOwned(20, 502, $this->eventAttributes()));
}
```

- [ ] **Step 2: Run and verify RED**

Run: `rtk php tests/run.php EventRepositoryTest`

Expected: FAIL because `EventRepository` is missing.

- [ ] **Step 3: Implement prepared query builders with allow-listed sort SQL**

Build filter clauses and bound parameters separately. Use portable `CURRENT_TIMESTAMP` comparisons in repository tests, explicit public joins, JSON tag decoding after fetch, and ownership joins on every organizer mutation.

- [ ] **Step 4: Implement transactional gallery replacement and audit-backed status transitions**

Administrator transition writes update approval/publication fields and inserts an `activity_logs` row in one transaction. Gallery replacement inserts no more than six records and preserves sort order.

- [ ] **Step 5: Register the repository and verify GREEN**

Run: `rtk php tests/run.php EventRepositoryTest && rtk composer test`

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
rtk git add app/Contracts/EventRepositoryInterface.php app/Repositories/EventRepository.php tests/Unit/EventRepositoryTest.php bootstrap/app.php
rtk git commit -m "feat: add event persistence and discovery queries"
```

### Task 3: Event Validation, Lifecycle, and Secure Images

**Files:**
- Create: `app/Services/ImageUploadService.php`
- Create: `app/Services/EventService.php`
- Create: `tests/Support/FakeCategoryRepository.php`
- Create: `tests/Support/FakeVenueRepository.php`
- Create: `tests/Support/FakeEventRepository.php`
- Create: `tests/Unit/ImageUploadServiceTest.php`
- Create: `tests/Unit/EventServiceTest.php`
- Modify: `Core/Validator.php`
- Modify: `tests/Unit/ValidatorTest.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Produces: `ImageUploadService::store(?array): array{success: bool, path: ?string, error: ?string}` and `delete(?string): void`.
- Produces: `EventService::createDraft(int, array, ?array, array): array`, `update(int, int, array, ?array, array): array`, `submit(int, int): array`, `cancel(int, int): array`, `delete(int, int): array`, `moderate(int, int, string, ?string): array`.

- [ ] **Step 1: Write failing validator tests for datetime, URL, range, and relative date rules**

```php
public function testEventDatetimeAndOrderingRulesRejectImpossibleSchedule(): void
{
    $errors = Validator::validate($data, [
        'start_date' => 'required|datetime_local',
        'end_date' => 'required|datetime_local|after:start_date',
        'registration_deadline' => 'required|datetime_local|before_or_equal:start_date',
    ]);
    $this->assertArrayHasKey('end_date', $errors);
}
```

- [ ] **Step 2: Run validator tests and verify RED, then implement minimal rules and verify GREEN**

Run: `rtk php tests/run.php ValidatorTest`

Expected first run: FAIL with unsupported rules. Expected second run: PASS.

- [ ] **Step 3: Write failing upload tests using real temporary image bytes**

Assert that a valid generated JPEG stores under `/uploads/events/`, text disguised as JPEG is rejected, oversized input is rejected before move, and delete cannot escape the configured upload root.

- [ ] **Step 4: Run upload tests and verify RED, then implement Fileinfo and image-dimension validation**

Run: `rtk php tests/run.php ImageUploadServiceTest`

Expected first run: FAIL because the service is missing. Expected second run: PASS.

- [ ] **Step 5: Write failing service tests for normalization and lifecycle**

Cover unique slug suffixing, price/tag normalization, inactive category, foreign venue, venue capacity, unapproved organizer submission, rejected resubmission, invalid transition, required rejection reason, media cleanup on failure, and delete status restrictions.

- [ ] **Step 6: Implement `EventService` and verify GREEN**

Run: `rtk php tests/run.php EventServiceTest && rtk composer test`

Expected: all tests pass with no warnings.

- [ ] **Step 7: Commit**

```bash
rtk git add Core/Validator.php app/Services/EventService.php app/Services/ImageUploadService.php tests/Support/FakeCategoryRepository.php tests/Support/FakeVenueRepository.php tests/Support/FakeEventRepository.php tests/Unit/ValidatorTest.php tests/Unit/EventServiceTest.php tests/Unit/ImageUploadServiceTest.php bootstrap/app.php public/uploads/events/.gitkeep
rtk git commit -m "feat: secure event lifecycle and media"
```

### Task 4: Organizer Event and Venue Workflows

**Files:**
- Create: `app/Controllers/OrganizerEventController.php`
- Create: `app/Controllers/OrganizerVenueController.php`
- Create: `app/Views/organizer/events/index.php`
- Create: `app/Views/organizer/events/form.php`
- Create: `app/Views/organizer/events/show.php`
- Create: `app/Views/organizer/venues/index.php`
- Create: `app/Views/organizer/venues/form.php`
- Create: `tests/Unit/OrganizerEventControllerTest.php`
- Create: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `routes/web.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`

**Interfaces:**
- Consumes: repositories and `EventService` from Tasks 1-3.
- Produces: organizer routes under `/organizer/events` and `/organizer/venues`, all guarded by `role:organizer`; every POST also uses `csrf`.

- [ ] **Step 1: Write failing controller tests**

Test accessible index/create/edit/show rendering, safe old input, validation redirects, 404 for foreign records, successful create/update flashes, status action redirects, venue ownership, and blocked deletion when a venue is referenced.

- [ ] **Step 2: Run and verify RED**

Run: `rtk php tests/run.php OrganizerEventControllerTest && rtk php tests/run.php OrganizerVenueControllerTest`

Expected: FAIL because controllers and views are missing.

- [ ] **Step 3: Implement thin controllers, routes, and role-specific sidebar links**

Validate positive numeric route IDs before service calls. Preserve only scalar safe old input. Use 404 responses for missing owned records and flash messages for business-rule failures.

- [ ] **Step 4: Build responsive organizer views**

Use native labels, selects, datetime-local inputs, number inputs, file inputs with accept hints, status names, semantic tables on desktop with stacked mobile rows, visible errors, empty states, and Phosphor icons. Do not expose registration controls.

- [ ] **Step 5: Build CSS and verify GREEN**

Run: `rtk npm run build:css && rtk php tests/run.php OrganizerEventControllerTest && rtk php tests/run.php OrganizerVenueControllerTest && rtk composer test`

Expected: all tests and asset build pass.

- [ ] **Step 6: Commit**

```bash
rtk git add app/Controllers/OrganizerEventController.php app/Controllers/OrganizerVenueController.php app/Views/organizer app/Views/layouts/dashboard.php routes/web.php resources/css/app.css public/assets/css/app.css tests/Unit/OrganizerEventControllerTest.php tests/Unit/OrganizerVenueControllerTest.php
rtk git commit -m "feat: build organizer event workspace"
```

### Task 5: Administrator Categories and Event Moderation

**Files:**
- Create: `app/Controllers/AdminCategoryController.php`
- Create: `app/Controllers/AdminEventController.php`
- Create: `app/Views/admin/categories/index.php`
- Create: `app/Views/admin/categories/form.php`
- Create: `app/Views/admin/events/index.php`
- Create: `app/Views/admin/events/show.php`
- Create: `tests/Unit/AdminCategoryControllerTest.php`
- Create: `tests/Unit/AdminEventControllerTest.php`
- Modify: `routes/web.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`

**Interfaces:**
- Consumes: `CategoryRepositoryInterface`, `EventRepositoryInterface`, and `EventService`.
- Produces: administrator category and moderation routes under `/admin`, guarded by `role:super-admin` and `csrf` for every mutation.

- [ ] **Step 1: Write failing controller tests for category validation and every moderation action**

Assert duplicate category slug rejection, safe activation toggle, pending-first queue, required rejection reason, publish only from approved, complete only from published, clear redirects, and activity log side effects through the real repository fixture.

- [ ] **Step 2: Run and verify RED**

Run: `rtk php tests/run.php AdminCategoryControllerTest && rtk php tests/run.php AdminEventControllerTest`

Expected: FAIL because administrator controllers and views are missing.

- [ ] **Step 3: Implement controllers, routes, views, and administrator navigation**

Use explicit POST forms for every state change. Render event evidence before actions. Show rejection input only when relevant and keep current state visible in accessible text.

- [ ] **Step 4: Build CSS and verify GREEN**

Run: `rtk npm run build:css && rtk php tests/run.php AdminCategoryControllerTest && rtk php tests/run.php AdminEventControllerTest && rtk composer test`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
rtk git add app/Controllers/AdminCategoryController.php app/Controllers/AdminEventController.php app/Views/admin app/Views/layouts/dashboard.php routes/web.php resources/css/app.css public/assets/css/app.css tests/Unit/AdminCategoryControllerTest.php tests/Unit/AdminEventControllerTest.php
rtk git commit -m "feat: add event moderation and categories"
```

### Task 6: Public Event Discovery and SEO Details

**Files:**
- Create: `app/Controllers/PublicEventController.php`
- Create: `app/Views/events/show.php`
- Create: `tests/Unit/PublicEventControllerTest.php`
- Modify: `app/Controllers/HomeController.php`
- Modify: `app/Views/home/index.php`
- Modify: `app/Views/events/index.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `routes/web.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `tests/Unit/HomeControllerTest.php`
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: `EventRepositoryInterface` and `CategoryRepositoryInterface`.
- Produces: database-backed `/`, `/events`, and `/events/{slug}` with filter state, canonical metadata, and JSON-LD event data.

- [ ] **Step 1: Write failing public controller tests**

Assert combined filters reach the repository as normalized allow-listed values, cards link by slug, details return 404 for non-published events, semantic times and address render, canonical and JSON-LD metadata escape safely, and no active registration button appears.

- [ ] **Step 2: Run and verify RED**

Run: `rtk php tests/run.php PublicEventControllerTest && rtk php tests/run.php HomeControllerTest`

Expected: FAIL because public controllers still use hard-coded previews.

- [ ] **Step 3: Replace hard-coded previews with repository data and build details**

Format display dates and prices in PHP helpers while preserving ISO values for semantic markup. Keep filter values in their controls and provide a one-click clear state.

- [ ] **Step 4: Add canonical, description, Open Graph, and safe JSON-LD support to the public layout**

Only render optional metadata when provided. Encode JSON-LD with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES`.

- [ ] **Step 5: Build CSS and verify GREEN**

Run: `rtk npm run build:css && rtk php tests/run.php PublicEventControllerTest && rtk php tests/run.php HomeControllerTest && rtk php tests/run.php UiLayoutTest && rtk composer test`

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
rtk git add app/Controllers/HomeController.php app/Controllers/PublicEventController.php app/Views/home/index.php app/Views/events app/Views/layouts/public.php routes/web.php resources/css/app.css public/assets/css/app.css tests/Unit/HomeControllerTest.php tests/Unit/PublicEventControllerTest.php tests/Unit/UiLayoutTest.php
rtk git commit -m "feat: publish database backed event discovery"
```

### Task 7: Dashboard Integration, Demo Data, and Documentation

**Files:**
- Modify: `app/Controllers/DashboardController.php`
- Modify: `app/Views/dashboard/organizer.php`
- Modify: `app/Views/dashboard/admin.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`
- Modify: `database/demo_seed.sql`
- Modify: `README.md`

**Interfaces:**
- Consumes: event repository organizer summaries and administrator list counts.
- Produces: real organizer event metrics, recent event actions, administrator pending-review count, repeatable Week 2 demo media references, and complete setup/use documentation.

- [ ] **Step 1: Write failing dashboard behavior tests**

Assert the organizer overview renders repository counts and enabled create/manage links, and the administrator overview renders the real pending-review total and moderation link.

- [ ] **Step 2: Run and verify RED**

Run: `rtk php tests/run.php DashboardLayoutTest`

Expected: FAIL because dashboards still contain disabled Week 2 placeholders.

- [ ] **Step 3: Implement dashboard repository data and views**

Remove unavailable copy, keep registration/revenue values honest, and show recent event status and next action.

- [ ] **Step 4: Update repeatable demo data and README**

Document Week 2 routes, lifecycle, image requirements, commands, demo accounts, and explicitly state that registration and tickets begin in Week 3.

- [ ] **Step 5: Verify GREEN and commit**

Run: `rtk php tests/run.php DashboardLayoutTest && rtk composer test && rtk git diff --check`

```bash
rtk git add app/Controllers/DashboardController.php app/Views/dashboard/organizer.php app/Views/dashboard/admin.php tests/Unit/DashboardLayoutTest.php database/demo_seed.sql README.md
rtk git commit -m "docs: complete week 2 event management"
```

### Task 8: Full QA, Independent Review, and Release

**Files:**
- Modify only files required by verified QA or review findings.

**Interfaces:**
- Produces: a reviewed, tested, committed, and pushed Week 2 milestone on public `main`, with the local server responding at `127.0.0.1:8000`.

- [ ] **Step 1: Run the complete automated release matrix**

```bash
rtk composer check:syntax
rtk composer test
rtk composer validate --strict
rtk composer check-platform-reqs
rtk composer audit
rtk npm run build:css
rtk node --check public/assets/js/app.js
rtk git diff --check
```

- [ ] **Step 2: Run schema and demo seed against the configured local MySQL database**

Import `database/schema.sql`, `database/seed.sql`, and `database/demo_seed.sql`, then query counts and lifecycle records without displaying credentials.

- [ ] **Step 3: Run browser and live-route QA**

Verify public home/list/detail, every filter, organizer list/create/edit/show/venues, administrator queue/review/categories, validation states, 320/768/1440 widths, keyboard navigation, both themes, image loading, 403/404/405 behavior, and no PHP warnings.

- [ ] **Step 4: Run the frontend pre-flight audit**

Check zero em/en dashes in visible views, one accent, consistent radii, button/form contrast, no wrapping primary labels, real labels, empty/error states, semantic icons, reduced motion, dark-mode parity, responsive collapse, no unavailable registration CTA, and no external font/icon CDN.

- [ ] **Step 5: Request independent review and resolve only verified findings through TDD**

Review commits after `6460758` against the design spec and this plan, focusing on authorization, CSRF, SQL injection, cross-organizer access, upload traversal, state transitions, public visibility, responsive accessibility, secrets, and unrelated files.

- [ ] **Step 6: Commit QA fixes if any, push, and verify remote and server**

```bash
rtk git push origin main
rtk git ls-remote origin refs/heads/main
rtk curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/
```

Expected: remote and local HEAD match, the repository is public, no secret is tracked, and the server returns HTTP 200.
