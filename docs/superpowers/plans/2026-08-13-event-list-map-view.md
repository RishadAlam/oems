# Event List and Map View Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the public event List/Map selector visually clear and responsive, with full-width list results, an intentional desktop split map, and failure-safe accessible behavior.

**Architecture:** Add an explicit `data-event-discovery-view` state to the existing discovery wrapper and keep `[hidden]` as the progressive-enhancement visibility mechanism. JavaScript owns pressed state, announcements, narrow-screen result visibility, map lifecycle, and marker/card focus synchronization; CSS owns full-width List geometry and Map-only desktop split geometry. The backend map payload and privacy filtering remain unchanged.

**Tech Stack:** PHP 8 view templates and unit tests, vanilla JavaScript with Node's test runner/VM harness, Tailwind CSS v4 source utilities, compiled CSS, Leaflet 1.9.4, service worker cache policy.

## Global Constraints

- Preserve event filters, location forms, routes, card content, Leaflet payload shape, and all published/public/valid-coordinate privacy rules.
- List is the canonical default and must never reserve an empty map column.
- Below 1024px, Map hides results only after a usable map loads; at 1024px and above, Map shows results beside a sticky map.
- Map failure, missing Leaflet, malformed data, or zero public markers must leave the canonical list visible.
- Use the existing OEMS surface, line, ink, accent, radius, spacing, and focus tokens; add no dependency.
- Preserve at least 44px pointer targets, the global 3px focus indicator, BFCache behavior, and reduced-motion behavior.
- Do not modify unrelated dirty or untracked files.

---

### Task 1: Capture the responsive view contract in failing tests

**Files:**
- Modify: `tests/js/location.test.mjs`
- Modify: `tests/Unit/PublicEventControllerTest.php`
- Modify: `tests/Unit/LocationAccessibilityStylesTest.php`

**Interfaces:**
- Consumes: existing `createHarness()` DOM/Leaflet stubs and rendered `/events` response fixture.
- Produces: the required `data-event-discovery-view`, grouped selector, separated view status, 1024px breakpoint, and map-scoped CSS contract that production code must satisfy.

- [ ] **Step 1: Extend the JavaScript harness and write failing state tests**

Add an `ElementStub` for `[data-event-discovery]` with `dataset.eventDiscoveryView = 'list'`, a separate `[data-event-view-status]`, and a viewport matcher whose narrow query is `(max-width: 1023px)`. Return both elements from `createHarness()`.

Add tests asserting:

```js
test('list and map controls synchronize explicit discovery state on narrow screens', () => {
    const harness = createHarness({ mobile: true });
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'list');

    harness.mapToggle.click();
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.panel.hidden, false);
    assert.equal(harness.list.hidden, true);
    assert.match(harness.viewStatus.textContent, /map view/i);

    harness.listToggle.click();
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'list');
    assert.equal(harness.panel.hidden, true);
    assert.equal(harness.list.hidden, false);
    assert.match(harness.viewStatus.textContent, /list view/i);
});

test('map keeps results beside it at the desktop split breakpoint', () => {
    const harness = createHarness({ mobile: false });
    harness.mapToggle.click();
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'map');
    assert.equal(harness.list.hidden, false);
    assert.match(harness.viewStatus.textContent, /alongside/i);
});

test('marker activation reveals a hidden result before focusing its card', () => {
    const harness = createHarness({ mobile: true });
    harness.mapToggle.click();
    harness.leaflet.markers[0].emit('click');
    assert.equal(harness.discovery.dataset.eventDiscoveryView, 'list');
    assert.equal(harness.list.hidden, false);
    assert.equal(harness.card.focusCalls, 1);
});
```

Update the existing breakpoint test to transition across 1024px and verify Map hides results below it but retains results at and above it. Update failure tests to assert `data-event-discovery-view="map"`, a visible list, and recovery copy in the dedicated view status.

- [ ] **Step 2: Add rendered markup assertions**

In `testIndexMapPayloadContainsOnlyPublishedPublicEventsWithValidCoordinates()`, assert the body contains:

```php
$this->assertTrue(str_contains($body, 'data-event-discovery data-event-discovery-view="list"'));
$this->assertTrue(str_contains($body, 'role="group" aria-labelledby="event-view-label"'));
$this->assertTrue(str_contains($body, 'id="event-view-label"'));
$this->assertTrue(str_contains($body, 'data-event-view-status'));
$this->assertTrue(str_contains($body, '1 public event location'));
$this->assertFalse(str_contains($body, 'Use the List view for complete event details.'));
```

Keep the existing escaped payload and restricted-location assertions intact.

- [ ] **Step 3: Add source-CSS contract assertions**

Add a test to `LocationAccessibilityStylesTest` that reads `resources/css/app.css` and asserts:

```php
$this->assertTrue(str_contains($stylesheet, '.event-discovery-layout[data-event-discovery-view="map"]'));
$this->assertFalse(str_contains($stylesheet, '.event-discovery-layout:not(.event-discovery-layout--empty) {'));
$this->assertTrue(str_contains($stylesheet, '@media (min-width: 1024px)'));
$this->assertTrue(str_contains($stylesheet, '.event-view-control'));
```

Also preserve the existing focus-indicator test.

- [ ] **Step 4: Run focused tests and confirm intentional failures**

Run:

```bash
node --test tests/js/location.test.mjs
php tests/run.php PublicEventControllerTest LocationAccessibilityStylesTest
```

Expected: failures only for the new discovery state, view-status, grouped markup, copy, and scoped CSS assertions.

- [ ] **Step 5: Commit the RED contract**

```bash
git add tests/js/location.test.mjs tests/Unit/PublicEventControllerTest.php tests/Unit/LocationAccessibilityStylesTest.php
git commit -m "test: capture responsive event view contract"
```

---

### Task 2: Implement accessible markup and reliable view behavior

**Files:**
- Modify: `app/Views/events/index.php`
- Modify: `public/assets/js/location.js`
- Modify: `app/Views/layouts/public.php`
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: Task 1's `[data-event-discovery]`, `[data-event-view-status]`, `role="group"`, and 1024px behavior assertions.
- Produces: `setView(view)`, explicit wrapper state, separated announcements, safe narrow-marker focus, and cache-busted `location.js?v=20260813-event-view-v1`.

- [ ] **Step 1: Update the event view markup**

In `app/Views/events/index.php`:

- wrap the segmented selector in `.event-view-control`;
- add `<span class="event-view-control__label" id="event-view-label">View</span>`;
- change the switch to `role="group" aria-labelledby="event-view-label"`;
- add `<p class="sr-only" data-event-view-status role="status" aria-live="polite"></p>`;
- add `data-event-discovery data-event-discovery-view="list"` to the discovery wrapper;
- replace the two map-heading paragraphs with a single pluralized line such as `3 public event locations. Only exact locations shared publicly appear here.`

- [ ] **Step 2: Synchronize state and announcements in JavaScript**

In `public/assets/js/location.js`:

- query `[data-event-discovery]` and `[data-event-view-status]`;
- replace the 767px query with `(max-width: 1023px)` and name it `compactViewQuery`;
- add `setViewStatus(message)` and keep geolocation messages in `setStatus(message)`;
- set `discovery.dataset.eventDiscoveryView = view` at the start of `setView()`;
- announce `List view shown. N events available.`, `Map view shown. N events remain available in List view.` on compact screens, and `Map shown alongside N events.` on desktop;
- route payload, marker, tile, and Leaflet failure copy to the view status while preserving the inline fallback;
- when a marker is activated while results are hidden, call `setView('list')` before focusing and scrolling the matching card;
- keep viewport reconciliation, BFCache cleanup, and reduced-motion options intact.

- [ ] **Step 3: Version the changed JavaScript asset**

Change the public layout URL and matching `UiLayoutTest` expectation from `location.js?v=20260811-geolocation-secure` to `location.js?v=20260813-event-view-v1`. Do not change the independently versioned venue map asset.

- [ ] **Step 4: Run focused behavior and render tests**

Run:

```bash
node --test tests/js/location.test.mjs
php tests/run.php PublicEventControllerTest UiLayoutTest
```

Expected: JavaScript and markup tests pass; only Task 1's not-yet-implemented CSS contract may remain red.

- [ ] **Step 5: Commit markup and interaction behavior**

```bash
git add app/Views/events/index.php public/assets/js/location.js app/Views/layouts/public.php tests/Unit/UiLayoutTest.php
git commit -m "fix: clarify event list and map behavior"
```

---

### Task 3: Implement the responsive visual system and publish assets

**Files:**
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css` (generated)
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/js/pwa.test.mjs`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`

**Interfaces:**
- Consumes: `data-event-discovery-view="list|map"`, `.event-view-control`, `.event-view-switch`, `.event-map-panel`.
- Produces: full-width List grid, Map-only desktop split geometry, compact segmented control styling, and synchronized CSS cache version `20260813-event-view-v1`.

- [ ] **Step 1: Refine the toolbar and segmented control**

Update source CSS so `.event-view-control` aligns a small `View` label with the switch, the switch uses one subtle surface border, and the active button uses `var(--accent-soft)` plus `var(--accent)` without a nested ring. Preserve 44px minimum controls and the global focus outline. Keep the location status hidden when empty and full-width only inside its own preference area.

- [ ] **Step 2: Scope responsive discovery geometry to Map state**

Replace the unconditional desktop rules with:

```css
@media (min-width: 1024px) {
    .event-discovery-layout[data-event-discovery-view="map"]:not(.event-discovery-layout--empty) {
        grid-template-columns: minmax(0, 1.15fr) minmax(22rem, 0.85fr);
        align-items: start;
    }

    .event-discovery-layout[data-event-discovery-view="map"]:not(.event-discovery-layout--empty) > .event-results-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .event-discovery-layout[data-event-discovery-view="map"]:not(.event-discovery-layout--empty) > .event-map-panel {
        position: sticky;
        top: 6rem;
    }
}
```

List mode then inherits the existing one/two/three-column result grid. Reduce the map canvas internal radius to 12px and make the narrow desktop map heading a single compact block.

- [ ] **Step 3: Publish CSS and cache version**

Replace the CSS version `20260813-dashboard-header-v1` with `20260813-event-view-v1` in the four layouts, service worker, and exact tests. Run:

```bash
npm run build:css
```

Do not hand-edit generated `public/assets/css/app.css`.

- [ ] **Step 4: Run asset and focused regression gates**

Run:

```bash
php tests/run.php LocationAccessibilityStylesTest PwaStaticPolicyTest OrganizerVenueControllerTest UiLayoutTest PublicEventControllerTest
node --test tests/js/location.test.mjs
node tests/js/pwa.test.mjs
npm run test:assets
```

Expected: all focused PHP, JavaScript, PWA, and asset checks pass.

- [ ] **Step 5: Commit responsive styling and generated assets**

```bash
git add resources/css/app.css public/assets/css/app.css app/Views/layouts/public.php app/Views/layouts/auth.php app/Views/layouts/dashboard.php app/Views/layouts/maintenance.php public/service-worker.js tests/js/pwa.test.mjs tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php
git commit -m "fix: redesign responsive event discovery views"
```

---

### Task 4: Browser verification and final quality gate

**Files:**
- Modify only if browser evidence reveals an in-scope defect.

**Interfaces:**
- Consumes: completed List/Map view contract and built production assets.
- Produces: viewport/theme evidence and a clean verified commit chain.

- [ ] **Step 1: Verify real `/events` geometry and state**

Using the local app in the in-app browser, check 390, 768, 1280, and 2048px in light and dark themes:

- List has no map panel or reserved map column;
- List uses one, two, and three result columns at the intended breakpoints;
- Map is focused below 1024px after successful load;
- Map is split and sticky at 1024px and above;
- the switch has one clear active state, visible keyboard focus, 44px targets, and no overflow;
- failure fallback keeps the list visible;
- marker activation does not focus hidden content;
- long event titles and addresses do not create horizontal scrolling.

- [ ] **Step 2: Run the complete automated suite**

Run:

```bash
php tests/run.php
node --test tests/js/*.test.mjs
```

Expected: zero failures.

- [ ] **Step 3: Review the final diff and repository state**

Run:

```bash
git diff --check
git status --short
git log -6 --oneline
```

Verify only intentional event-view files were committed and all pre-existing unrelated dirty/untracked files remain untouched.

- [ ] **Step 4: Commit only evidence-driven corrections**

If browser QA required a correction, stage only those exact files and commit:

```bash
git commit -m "fix: polish event discovery view layout"
```

If no correction was required, create no empty commit.
