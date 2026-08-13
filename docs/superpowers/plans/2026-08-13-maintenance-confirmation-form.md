# Maintenance Confirmation Form Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the admin maintenance confirmation field to the shared form layout and make its client-side and server-side error presentation concise and accessible.

**Architecture:** Keep `AdminOperationsController` as the server-side authority for the exact maintenance phrase. Update only the operations view to use the existing `form-stack`, `field-group`, `field-help`, and `field-error` contracts, and give the client validator a concise field label through `data-form-label`. Prove the rendered behavior through the real controller and view rather than source-text-only tests.

**Tech Stack:** PHP 8.2, custom OEMS MVC and view layer, Tailwind CSS v4 shared component classes, Node client-side form enhancer, custom PHP and Node test runners.

## Global Constraints

- Preserve the existing dashboard theme, semantic danger color, typography, spacing scale, button, and error-summary component.
- Keep `ENABLE MAINTENANCE` and `DISABLE MAINTENANCE` as the exact server-authoritative phrases.
- Keep server rejection at HTTP 422 and do not change maintenance authorization, routes, or endpoint availability.
- Use the existing shared form classes; do not add page-specific CSS or a component dependency.
- Keep a persistent label above the input and associate help and error content with `aria-describedby`.
- Do not modify or stage unrelated working-tree files.

---

### Task 1: Maintenance confirmation form contract

**Files:**
- Modify: `tests/Unit/AdminOperationsControllerTest.php`
- Modify: `app/Controllers/AdminOperationsController.php`
- Modify: `app/Views/admin/operations/index.php`

**Interfaces:**
- Consumes: `AdminOperationsController::updateMaintenance(Request): Response`, shared `form-stack`, `field-group`, `field-help`, and `field-error` CSS contracts, and `data-form-label` consumed by `OEMSForms.messageFor`.
- Produces: a rendered confirmation control with `id="maintenance-confirmation"`, a concise `Confirmation phrase` label, phrase-specific helper copy, and linked invalid feedback.

- [ ] **Step 1: Write the failing rendered-form test**

Extend `testOperationsPageAndConfirmationBoundToggleAreTruthful()` immediately after the invalid response assertions:

```php
$invalidBody = $invalid->body();
$this->assertTrue(str_contains($invalidBody, '<form class="form-stack mt-6"'));
$this->assertTrue(str_contains($invalidBody, '<div class="field-group">'));
$this->assertTrue(str_contains($invalidBody, '<label for="maintenance-confirmation">Confirmation phrase</label>'));
$this->assertTrue(str_contains($invalidBody, 'data-form-label="Confirmation phrase"'));
$this->assertTrue(str_contains($invalidBody, 'Type <strong>ENABLE MAINTENANCE</strong> exactly as shown.'));
$this->assertTrue(str_contains($invalidBody, 'aria-invalid="true"'));
$this->assertTrue(str_contains($invalidBody, 'maintenance-confirmation-error'));
$this->assertTrue(str_contains($invalidBody, 'Enter the exact confirmation phrase.'));
$this->assertFalse(str_contains($invalidBody, 'class="form-label"'));
$this->assertFalse(str_contains($invalidBody, 'class="form-input"'));
$this->assertFalse(str_contains($invalidBody, 'class="form-help"'));
$this->assertFalse(str_contains($invalidBody, 'class="form-error"'));
```

This test catches a regression back to loose legacy controls, verbose client field naming, missing accessibility state, or an imprecise server message.

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
rtk composer test -- AdminOperationsControllerTest
```

Expected: FAIL because the form still renders `class="mt-6"`, legacy field classes, the instruction as the visible label, and the previous server validation message.

- [ ] **Step 3: Commit the failing regression test**

```bash
rtk git add tests/Unit/AdminOperationsControllerTest.php
rtk git commit -m "test: capture maintenance form layout regression"
```

- [ ] **Step 4: Implement the shared form hierarchy**

In `AdminOperationsController::updateMaintenance()`, return this concise validation message for a missing or non-exact phrase:

```php
return $this->page(['confirmation' => ['Enter the exact confirmation phrase.']], 422);
```

In `app/Views/admin/operations/index.php`:

1. Change the form class to `form-stack mt-6`.
2. Change the summary label for `confirmation` to `Confirmation phrase`.
3. Replace the loose legacy elements with this shared field group:

```php
<div class="field-group">
    <label for="maintenance-confirmation">Confirmation phrase</label>
    <input
        id="maintenance-confirmation"
        name="confirmation"
        type="text"
        autocomplete="off"
        autocapitalize="characters"
        spellcheck="false"
        data-form-label="Confirmation phrase"
        required
        aria-describedby="maintenance-confirmation-instruction maintenance-help<?= !empty($errors['confirmation']) ? ' maintenance-confirmation-error' : '' ?>"
        <?= !empty($errors['confirmation']) ? 'aria-invalid="true"' : '' ?>
    >
    <p id="maintenance-confirmation-instruction" class="field-help">Type <strong><?= e($phrase) ?></strong> exactly as shown.</p>
    <?php if (!empty($errors['confirmation'])): ?>
        <p id="maintenance-confirmation-error" class="field-error" role="alert"><?= e($errors['confirmation'][0]) ?></p>
    <?php endif; ?>
    <p id="maintenance-help" class="field-help">Health endpoints, the login page, static assets, and signed-in super administrators remain available.</p>
</div>
```

4. Remove the button's `mt-5` and add `justify-self-start` so `form-stack` controls vertical rhythm without stretching the destructive action across the panel.

- [ ] **Step 5: Run focused PHP and JavaScript form tests and verify GREEN**

Run:

```bash
rtk composer test -- AdminOperationsControllerTest
rtk node --test tests/js/form-validation.test.mjs
```

Expected: both commands PASS. The controller test proves the server-rendered invalid state, while the existing JavaScript test proves concise `data-form-label` values drive linked client summaries.

- [ ] **Step 6: Commit the implementation**

```bash
rtk git add app/Controllers/AdminOperationsController.php app/Views/admin/operations/index.php
rtk git commit -m "fix: align maintenance confirmation form"
```

- [ ] **Step 7: Verify the rendered error state in the browser**

Start the local app and open `/admin/operations` as a super administrator. Submit the empty confirmation field to generate the non-mutating client error state. Verify at 1440px and 390px widths in light and dark themes:

- the summary, label, input, helper, error, context note, and button form one vertical flow;
- the input uses the full available field width and does not overlap text;
- summary copy identifies `Confirmation phrase` instead of repeating the full instruction;
- the focus ring and error colors remain readable;
- the page has no horizontal overflow;
- maintenance stays inactive.

- [ ] **Step 8: Run the full verification suite**

Run:

```bash
rtk composer test
rtk node --test tests/js/*.test.mjs
rtk composer check:syntax
rtk composer validate --strict
rtk git diff --check
```

Expected: all PHP and JavaScript tests pass, all PHP files parse, Composer validation succeeds, and Git reports no whitespace errors.

- [ ] **Step 9: Record completion and commit final plan state if changed**

Mark completed plan checkboxes and record the exact verification results. If the plan file changes, commit only that file:

```bash
rtk git add docs/superpowers/plans/2026-08-13-maintenance-confirmation-form.md
rtk git commit -m "docs: complete maintenance form implementation plan"
```
