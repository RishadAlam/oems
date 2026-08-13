# Project-wide Form Divider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure every sectioned form renders one separator between sections and exactly one separator before its action footer.

**Architecture:** Keep the two established form families and change their shared CSS contract from section bottom borders to later-sibling top borders. Keep the profile action footer in normal form flow so it cannot overlay scrolling dividers. Test the compiled stylesheet that browsers consume, then publish it under a fresh cache key in all layouts and the service worker.

**Tech Stack:** PHP 8.2 custom test runner, Tailwind CSS 4, Node.js test runner, service worker static cache

## Global Constraints

- Content sections have no bottom border.
- Every content section after the first receives one top border and matching top padding.
- The action footer retains one top border.
- The profile action footer stays in normal flow and never overlays scrolling content.
- The correction must cover `profile-form-section` and `organizer-form__section`, including all nine templates that use them.
- Existing unrelated working-tree changes must remain untouched.
- Every shell command starts with `rtk`.

---

### Task 1: Replace the false-positive divider regression test

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`
- Test: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: compiled CSS at `public/assets/css/app.css`
- Produces: a regression contract for base section rules, sibling section rules, and action footer rules

- [x] **Step 1: Write the failing test**

Replace `testSectionedFormsRemoveTheContentDividerBeforeTheActionDivider()` with a compiled-CSS behavior test. For each base section selector, capture its exact rule and assert that it contains no `border-bottom-width`. Assert that the compiled stylesheet contains a later-sibling rule for both section families with `border-top-width:1px`. Keep the assertion that each action footer has `border-top-width:1px`.

```php
public function testSectionedFormsRenderOnlyOneDividerBeforeActions(): void
{
    $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

    foreach (['profile-form-section', 'organizer-form__section'] as $sectionClass) {
        $matched = preg_match('/\.' . preg_quote($sectionClass, '/') . '\{([^}]*)\}/', $css, $baseRule);
        $this->assertSame(1, $matched);
        $this->assertFalse(str_contains($baseRule[1], 'border-bottom-width'));
    }

    $this->assertTrue(
        preg_match(
            '/\.profile-form-section~\.profile-form-section,.organizer-form__section~\.organizer-form__section\{(?=[^}]*border-top-width:1px)(?=[^}]*padding-top:calc\(var\(--spacing\) \* 8\))[^}]*\}/',
            $css,
        ) === 1,
    );

    foreach (['profile-form-actions', 'organizer-form__actions'] as $actionClass) {
        $this->assertTrue(
            preg_match('/\.' . preg_quote($actionClass, '/') . '\{[^}]*border-top-width:1px[^}]*\}/', $css) === 1,
        );
    }

    preg_match_all('/\.profile-form-actions\{([^}]*)\}/', $css, $profileActionRules);
    $this->assertFalse(str_contains(implode('', $profileActionRules[1]), 'position:sticky'));
}
```

- [x] **Step 2: Run the focused test to verify it fails for the current browser CSS**

Run: `rtk composer test -- UiLayoutTest`

Expected: FAIL because the base section rules still contain `border-bottom-width:1px` and the sibling separator rule does not exist.

- [x] **Step 3: Commit the failing regression test**

```bash
rtk git add tests/Unit/UiLayoutTest.php
rtk git commit -m "test: capture global form divider regression"
```

### Task 2: Require a fresh stylesheet cache key

**Files:**
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/js/pwa.test.mjs`

**Interfaces:**
- Consumes: stylesheet link URLs and service worker cache metadata
- Produces: the final cache-key contract `20260813-form-divider-v2`

- [x] **Step 1: Change cache delivery expectations first**

Replace every expected CSS/cache version `20260813-admin-filter-alignment-v1` in the three test files with the final `20260813-form-divider-v2` key. The key advanced from `v1` to `v2` after rendered verification exposed the sticky-footer overlay.

- [x] **Step 2: Run delivery tests to verify they fail against production files**

Run: `rtk composer test -- PwaStaticPolicyTest`

Expected: FAIL because layouts still link the previous CSS version.

Run: `rtk composer test -- OrganizerVenueControllerTest`

Expected: FAIL because the dashboard layout still links the previous CSS version.

Run: `rtk node --test tests/js/pwa.test.mjs`

Expected: FAIL because the service worker still uses the previous cache name and stylesheet URL.

- [x] **Step 3: Commit the failing delivery contract**

```bash
rtk git add tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php tests/js/pwa.test.mjs
rtk git commit -m "test: require fresh form divider stylesheet"
```

### Task 3: Implement and publish the shared divider contract

**Files:**
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css` through the build command
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`

**Interfaces:**
- Consumes: the two shared section and action class families
- Produces: compiled CSS with later-sibling separators and one action-footer separator

- [x] **Step 1: Replace bottom-border section rules with later-sibling top-border rules**

Use this shared CSS contract:

```css
.profile-form-section,
.organizer-form__section {
    @apply grid gap-5;
}

.profile-form-section ~ .profile-form-section,
.organizer-form__section ~ .organizer-form__section {
    @apply border-t border-[var(--line)] pt-8;
}
```

Remove the obsolete `:last-of-type` rules. Leave `profile-form-actions` and `organizer-form__actions` with one top border. Remove sticky positioning, translucent overlay treatment, and backdrop blur from `profile-form-actions` so it remains in normal form flow.

- [x] **Step 2: Publish the new cache key everywhere**

Replace `20260813-admin-filter-alignment-v1` with `20260813-form-divider-v2` in all four layouts and `public/service-worker.js`.

- [x] **Step 3: Build production CSS**

Run: `rtk npm run build:css`

Expected: exit 0 and an updated `public/assets/css/app.css`.

- [x] **Step 4: Run focused tests to verify green**

Run: `rtk composer test -- UiLayoutTest`

Expected: PASS.

Run: `rtk composer test -- PwaStaticPolicyTest`

Expected: PASS.

Run: `rtk composer test -- OrganizerVenueControllerTest`

Expected: PASS.

Run: `rtk node --test tests/js/pwa.test.mjs`

Expected: PASS.

- [x] **Step 5: Commit the implementation**

```bash
rtk git add resources/css/app.css public/assets/css/app.css app/Views/layouts/public.php app/Views/layouts/auth.php app/Views/layouts/dashboard.php app/Views/layouts/maintenance.php public/service-worker.js
rtk git commit -m "fix: remove double dividers from sectioned forms"
```

### Task 4: Verify the project and complete the plan

**Files:**
- Modify: `docs/superpowers/plans/2026-08-13-project-wide-form-divider.md`

**Interfaces:**
- Consumes: completed implementation and test suite
- Produces: final verification evidence and a checked plan

- [x] **Step 1: Inspect representative rendered forms**

Check the profile form and at least one organizer/admin `organizer-form` page at desktop and mobile widths. Confirm one separator between sections and one before actions in both light and dark themes. If the local application is unavailable, record that limitation and rely on compiled-CSS assertions plus rendered-view coverage.

- [x] **Step 2: Run the full verification suite**

```bash
rtk composer test
rtk node --test tests/js/*.test.mjs
rtk composer check:syntax
rtk composer validate --strict
rtk git diff --check
```

Expected: all commands exit 0 with no test failures or syntax errors.

- [x] **Step 3: Mark every completed plan checkbox and record the test totals**

Update this document with the actual verification result and no placeholders.

- [x] **Step 4: Commit plan completion**

```bash
rtk git add docs/superpowers/plans/2026-08-13-project-wide-form-divider.md
rtk git commit -m "docs: complete form divider implementation plan"
```

## Verification results

- Production CSS build: passed with Tailwind CSS 4.3.3.
- Focused divider, PWA, and venue controller tests: passed.
- Browser verification: passed on profile and admin settings forms at 1280x720 and 390x844, in light and dark themes, with no horizontal overflow.
- Computed CSS: every base section has a `0px` bottom border; later sections have one `1px` top border; action footers have one `1px` top border; the profile action footer is `position: static`.
- PHP suite: 823 tests, 6,072 assertions, 0 failures.
- JavaScript suite: 48 tests, 0 failures.
- PHP syntax scan: passed.
- Composer strict validation: passed.
- Git whitespace check: passed.
