# Admin Filter Toolbar Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bottom-align labeled filter controls and unlabeled action buttons in shared admin toolbars on responsive desktop layouts.

**Architecture:** Preserve the shared toolbar markup and existing mobile stack. Protect the compiled production CSS contract with a focused test, then replace the nested form's responsive center alignment with end alignment and rebuild the stylesheet.

**Tech Stack:** PHP 8.2 custom test runner, Tailwind CSS 4, server-rendered PHP views, browser-computed layout verification.

## Global Constraints

- Keep all current routes, form field names, labels, values, and submission behavior unchanged.
- Apply the correction through the shared `.organizer-toolbar form` rule so Users, Organizers, Events, and Reviews stay consistent.
- Keep the mobile layout as a single-column stack.
- Preserve unrelated tracked and untracked files.
- Commit the completed tested fix separately.

---

### Task 1: Correct shared filter action alignment

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`

**Interfaces:**
- Consumes: `.organizer-toolbar form` responsive Tailwind component rule
- Produces: compiled responsive CSS with `flex-direction:row` and `align-items:flex-end`

- [x] **Step 1: Write the failing compiled-layout regression test**

Add this test to `UiLayoutTest`:

```php
public function testResponsiveToolbarBottomAlignsLabeledControlsAndUnlabeledActions(): void
{
    $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

    $this->assertTrue(
        preg_match(
            '/\\.organizer-toolbar form\\{(?=[^}]*flex-direction:row)(?=[^}]*align-items:flex-end)[^}]*\\}/',
            $css,
        ) === 1,
        'Responsive toolbar controls and actions must share one lower edge.',
    );
}
```

- [x] **Step 2: Run the focused test and verify RED**

Run: `rtk php tests/run.php UiLayoutTest`

Expected: FAIL because the compiled responsive rule currently contains `align-items:center`.

- [x] **Step 3: Implement the minimal shared source correction**

Change:

```css
.organizer-toolbar form {
    @apply flex flex-col gap-2 sm:flex-row sm:items-center;
}
```

to:

```css
.organizer-toolbar form {
    @apply flex flex-col gap-2 sm:flex-row sm:items-end;
}
```

- [x] **Step 4: Rebuild the compiled stylesheet**

Run: `rtk npm run build:css`

Expected: the responsive compiled rule contains `align-items:flex-end`.

- [x] **Step 5: Run focused and complete verification**

Run: `rtk php tests/run.php UiLayoutTest`, `rtk composer test`, `rtk npm run test:assets`, `rtk npm run test:forms`, `rtk composer check:syntax`, and `rtk git diff --check`.

Expected: all tests and checks pass without warnings or failures.

- [x] **Step 6: Verify responsive behavior in the local browser**

At 2048 x 432 on `/admin/users`, confirm the Search input, Role select, Status select, Apply filters button, and result count have equal bottom coordinates. At 390 x 844, confirm the count and every form control remain a full-width vertical stack without horizontal overflow.

- [ ] **Step 7: Commit the fix**

```bash
rtk git add tests/Unit/UiLayoutTest.php resources/css/app.css public/assets/css/app.css docs/superpowers/plans/2026-08-13-admin-filter-toolbar-alignment.md
rtk git commit -m "fix: align admin filter toolbar controls"
```
