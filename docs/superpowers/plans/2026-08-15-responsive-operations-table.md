# Responsive Operations Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the three unstyled legacy table wrappers with the existing responsive operational-table component.

**Architecture:** Keep the current semantic tables, controllers, actions, and data. Adopt `organizer-table-wrap`, `organizer-table`, and `organizer-table__action` in the three affected views so one existing component owns desktop spacing, tablet containment, and mobile cards.

**Tech Stack:** PHP 8 view templates, Tailwind CSS v4 compiled assets, PHPUnit-style OEMS test runner, DOMDocument/XPath, in-app browser QA.

## Global Constraints

- Do not change URL routes, controller logic, repository behavior, form actions, CSRF fields, confirmation copy, or status helpers.
- Do not create a second table component or add new CSS when the existing component provides the required behavior.
- Preserve semantic table markup and add one descriptive screen-reader caption per table.
- Preserve the current CSS-variable theme system and verify both light and dark modes.
- Keep interactive actions at least 44 CSS pixels high and on one line.
- Do not stage or modify unrelated working-tree files.

---

### Task 1: Capture the shared responsive table contract

**Files:**
- Modify: `tests/Unit/UiLayoutTest.php`

**Interfaces:**
- Consumes: the three production view source files.
- Produces: `UiLayoutTest::testOperationalListsUseTheSharedResponsiveTableContract()`.

- [ ] **Step 1: Write the failing test**

Add a source-DOM contract test that checks all affected lists:

```php
public function testOperationalListsUseTheSharedResponsiveTableContract(): void
{
    $views = [
        'app/Views/admin/newsletter/index.php' => ['Newsletter campaigns', 'Action'],
        'app/Views/admin/contact/index.php' => ['Contact messages', 'Action'],
        'app/Views/organizer/coupons/index.php' => ['Organizer coupons', 'Actions'],
    ];

    foreach ($views as $view => [$caption, $actionLabel]) {
        $source = (string) file_get_contents(base_path($view));
        $markup = preg_replace('/<\?(?:php|=).*?\?>/s', '', $source) ?? '';
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($markup);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $this->assertTrue($loaded, $view . ' must contain parseable table markup.');
        $xpath = new \DOMXPath($document);
        $tables = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " organizer-table-wrap ")]'
            . '/table[contains(concat(" ", normalize-space(@class), " "), " operations-table ")]'
            . '[contains(concat(" ", normalize-space(@class), " "), " organizer-table ")]',
        );

        $this->assertSame(1, $tables?->length, $view . ' must adopt the shared responsive table.');
        $table = $tables?->item(0);
        $this->assertSame(1, $xpath->query('./caption[@class="sr-only" and normalize-space(.)="' . $caption . '"]', $table)?->length);
        $this->assertSame(1, $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " organizer-table__action ") and @data-label="' . $actionLabel . '"]', $table)?->length);
        $this->assertFalse(str_contains($source, 'table-shell'));
    }
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run: `rtk php tests/run.php UiLayoutTest`

Expected: FAIL because the three views still use `table-shell`, omit `organizer-table`, and omit accessible captions.

- [ ] **Step 3: Commit the RED contract**

```bash
rtk git add tests/Unit/UiLayoutTest.php
rtk git commit -m "test: capture responsive operations tables"
```

---

### Task 2: Adopt the existing responsive table component

**Files:**
- Modify: `app/Views/admin/newsletter/index.php`
- Modify: `app/Views/admin/contact/index.php`
- Modify: `app/Views/organizer/coupons/index.php`

**Interfaces:**
- Consumes: `.organizer-table-wrap`, `.operations-table`, `.organizer-table`, and `.organizer-table__action` from `resources/css/app.css`.
- Produces: three semantic responsive operational lists with screen-reader captions.

- [ ] **Step 1: Update Newsletter campaigns**

Replace the legacy wrapper and table classes with:

```php
<div class="organizer-table-wrap mt-6">
    <table class="operations-table organizer-table">
        <caption class="sr-only">Newsletter campaigns</caption>
```

Add `organizer-table__action` to the `Action` cell. Add `break-words` to the subject and message blocks so long unbroken content does not control column width.

- [ ] **Step 2: Update Contact inbox**

Use `organizer-table-wrap`, `operations-table organizer-table`, caption `Contact messages`, and `organizer-table__action` on the `Action` cell. Preserve all filters and review links.

- [ ] **Step 3: Update Coupons**

Use `organizer-table-wrap`, `operations-table organizer-table`, caption `Organizer coupons`, and `organizer-table__action` on the `Actions` cell. Preserve status actions and confirmation behavior.

- [ ] **Step 4: Run focused GREEN verification**

Run:

```bash
rtk php tests/run.php UiLayoutTest
rtk php tests/run.php NewsletterControllerTest
rtk php tests/run.php StatusUiTest
rtk php tests/run.php TransactionUiTest
rtk composer check:syntax
rtk npm run test:forms
```

Expected: every command exits 0 with no failures.

- [ ] **Step 5: Commit the implementation**

```bash
rtk git add app/Views/admin/newsletter/index.php app/Views/admin/contact/index.php app/Views/organizer/coupons/index.php
rtk git commit -m "fix: standardize responsive operations tables"
```

---

### Task 3: Verify the delivered layout end to end

**Files:**
- Verify only: `app/Views/admin/newsletter/index.php`
- Verify only: `app/Views/admin/contact/index.php`
- Verify only: `app/Views/organizer/coupons/index.php`

**Interfaces:**
- Consumes: the committed responsive table markup and existing compiled CSS.
- Produces: browser and full-suite evidence for release readiness.

- [ ] **Step 1: Run responsive browser QA**

Inspect all three routes at 390px, 768px, 1280px, and 2048px in light and dark themes. Confirm no document overflow, correct labeled mobile cards, contained tablet overflow, separated desktop headings, one-line actions, and visible keyboard focus.

- [ ] **Step 2: Run the full verification suite**

Run:

```bash
rtk composer test
rtk npm run test:assets
rtk node tests/js/pwa.test.mjs
rtk git diff --check
```

Expected: all tests pass, asset integrity passes, and the committed diff has no whitespace errors.

- [ ] **Step 3: Record final commit hashes and preserve unrelated files**

Run `rtk git log -5 --oneline` and `rtk git status --short`. Report the specification, RED-test, and implementation commits. Confirm unrelated existing modifications and untracked files remain untouched.
