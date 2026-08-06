# OEMS UI/UX Overhaul Implementation Plan

> **Execution note:** Follow this plan in order with red-green-refactor discipline. Run the listed targeted test after each red and green phase, commit each completed task, stage only named project files, and never stage local environment files or unrelated presentation artifacts.

**Goal:** Deliver a branded, responsive, accessible visual system and redesign every existing OEMS surface without changing account workflows or role permissions.

**Architecture:** Keep the custom PHP MVC and server-rendered view structure. Add reusable PHP presentation components, semantic Tailwind CSS tokens and components, one locally bundled Phosphor icon family, and progressively enhanced vanilla JavaScript interactions. Existing controllers and database behavior remain unchanged unless a UI regression exposes a real behavior defect.

**Tech stack:** PHP 8.2, custom MVC views, Tailwind CSS 4, vanilla JavaScript, Manrope variable font, Phosphor Icons web package, custom PHP test runner, in-app browser QA.

**Design specification:** `docs/superpowers/specs/2026-08-06-oems-ui-ux-overhaul-design.md`

---

## Task 1: Establish executable UI contracts

**Files:**

- Create: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`
- Test: `tests/Unit/UiLayoutTest.php`
- Test: `tests/Unit/DashboardLayoutTest.php`

### Step 1: Write failing public layout and brand contract tests

Render the home view through the real public layout and assert observable output:

- The brand link contains the shared SVG mark and visible `OEMS` wordmark.
- Public `How it works` links resolve to `/#how-it-works`.
- The mobile menu trigger starts with `aria-expanded="false"` and controls `mobile-menu`.
- Theme controls expose an accurate accessible label and icon state hook.
- The layout does not render the old single-letter logo symbol.

Avoid inspecting source files. Assert rendered HTML behavior through `OEMS\Core\View`.

### Step 2: Write failing dashboard interaction contract tests

Extend the real dashboard render assertions:

- Dashboard mobile open and close controls target the same drawer.
- The open control begins collapsed.
- Primary dashboard links include icon markup without changing their accessible names.
- The admin metrics still render the supplied values independently of decorative markup.

Replace the current brittle full-string metric assertions with semantic fragments for label and value.

### Step 3: Run the targeted tests and confirm red

Run: `rtk php tests/run.php UiLayoutTest`

Expected: FAIL because the shared mark, corrected anchor, and new control hooks do not exist.

Run: `rtk php tests/run.php DashboardLayoutTest`

Expected: FAIL on the new drawer and icon contracts.

### Step 4: Commit the failing contracts

Commit: `test: define UI accessibility contracts`

---

## Task 2: Build the shared brand and interface foundation

**Files:**

- Create: `app/Views/components/brand.php`
- Modify: `app/Views/components/flash.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `resources/css/app.css`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `public/assets/css/app.css`

### Step 1: Add the icon dependency

Run: `rtk npm install --save-dev @phosphor-icons/web`

Inspect the installed package and import only the local CSS/font assets needed by the application. Do not use a CDN.

### Step 2: Implement the shared SVG brand component

Create a parameterized PHP partial that supports default, inverse, and compact display. Build the aperture mark from simple geometric SVG elements, mark it `aria-hidden="true"`, and keep the visible OEMS wordmark available by default.

Replace every duplicated placeholder brand block in public, authentication, dashboard, and footer layouts with the component.

### Step 3: Replace global tokens and core components

In `resources/css/app.css`:

- Add the light and dark token sets from the design specification.
- Set the radius hierarchy and elevation tokens.
- Rebuild buttons, icon buttons, links, badges, focus rings, fields, check rows, radio role cards, alerts, and skeleton/empty-state foundations.
- Keep form semantics native and provide clear invalid, read-only, disabled, hover, active, and focus-visible states.
- Add reduced-motion handling.
- Import the locally installed Manrope and Phosphor assets.

### Step 4: Upgrade shared feedback and layout controls

- Add semantic icons to flash messages.
- Replace text-only dismiss, theme, drawer-open, drawer-close, log-out, and account action affordances with icon-supported controls.
- Add the ARIA control attributes required by the tests.
- Correct `How it works` to `/#how-it-works` in both public navigation variants.
- Preserve all current route and session branches.

### Step 5: Build CSS and run targeted tests

Run: `rtk npm run build:css`

Run: `rtk php tests/run.php UiLayoutTest`

Run: `rtk php tests/run.php DashboardLayoutTest`

Expected: PASS.

### Step 6: Commit the foundation

Commit: `feat: build OEMS visual system`

---

## Task 3: Make navigation and controls fully operable

**Files:**

- Modify: `public/assets/js/app.js`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/auth/login.php`
- Modify: `app/Views/auth/register.php`
- Modify: `app/Views/auth/reset-password.php`
- Modify: `app/Views/auth/change-password.php`

### Step 1: Define observable interaction acceptance checks

Use the in-app browser against the running local server and record the current failing behavior:

- Public mobile menu does not close on Escape with focus restored.
- Dashboard drawer does not synchronize `aria-expanded` or restore focus.
- Password controls do not update an accurate accessible label.
- Theme initialization can throw when storage is unavailable.

These are browser behavior checks because the repository does not currently include a JavaScript test runner. Keep the server-rendered ARIA contracts covered by PHP tests.

### Step 2: Implement a reusable disclosure controller

In `public/assets/js/app.js`:

- Synchronize `hidden`, open classes, `aria-expanded`, and `aria-hidden`.
- Close on Escape, outside click, navigation click, and breakpoint transition where applicable.
- Move focus to the first logical target on open and restore it to the trigger on close.
- Lock background scroll only while the dashboard drawer is open.
- Avoid duplicate global listeners.

### Step 3: Harden theme behavior

- Wrap local storage read/write in safe helpers.
- Use system preference when no valid stored preference exists.
- Update icon visibility, accessible label, and `meta[name="theme-color"]` after every change.
- Keep initialization before paint.

### Step 4: Upgrade password visibility controls

Use Phosphor eye and eye-slash icons. Update `type`, `aria-pressed`, accessible label, and title together. Keep the controlled input relationship explicit.

### Step 5: Verify interactions

Run: `rtk php tests/run.php UiLayoutTest`

Run: `rtk php tests/run.php DashboardLayoutTest`

Use browser checks at 390px and desktop width for keyboard, pointer, focus, and theme behavior.

Expected: all disclosure and control states remain synchronized.

### Step 6: Commit interaction fixes

Commit: `fix: make UI controls fully accessible`

---

## Task 4: Redesign public discovery surfaces

**Files:**

- Modify: `app/Views/home/index.php`
- Modify: `app/Views/events/index.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `tests/Unit/UiLayoutTest.php`

### Step 1: Add failing public surface assertions

Assert rendered behavior for:

- One primary home hero action and one secondary organizer action.
- A labelled event search form.
- Event cards that expose date and location metadata.
- A `how-it-works` section reachable from the corrected navigation target.

Run: `rtk php tests/run.php UiLayoutTest`

Expected: FAIL on the new semantic metadata hooks.

### Step 2: Recompose the home page

- Build the editorial split hero with the existing real photograph.
- Make the search dock a distinct discovery control.
- Convert category links to icon chips.
- Rework featured events into an asymmetric pair with consistent metadata.
- Rebuild the process section as a connected sequence without generic equal-height feature cards.
- Tighten the organizer callout and footer navigation.
- Keep visible copy free of em dashes and en dashes.

### Step 3: Recompose the events index

- Use a compact discovery header.
- Add a labelled icon-supported search control.
- Present event metadata and routes consistently.
- Do not add controls that imply unavailable Week 2 filtering behavior.

### Step 4: Build and verify

Run: `rtk npm run build:css`

Run: `rtk php tests/run.php UiLayoutTest`

Use browser screenshots at 1440px, 1024px, 768px, and 390px in both themes. Check first-viewport composition and horizontal overflow.

### Step 5: Commit public pages

Commit: `feat: redesign public event discovery`

---

## Task 5: Redesign authentication and account forms

**Files:**

- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/auth/login.php`
- Modify: `app/Views/auth/register.php`
- Modify: `app/Views/auth/forgot-password.php`
- Modify: `app/Views/auth/reset-password.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `tests/Unit/UiLayoutTest.php`

### Step 1: Add failing authentication render assertions

Render login and registration through the real auth layout and assert:

- Theme control is available with an accessible label.
- Password reveal controls start unpressed and identify their target.
- Role options preserve native radio semantics and expose clear visible names.
- Email and password fields retain explicit labels and autocomplete attributes.

Run: `rtk php tests/run.php UiLayoutTest`

Expected: FAIL on missing theme and password control states.

### Step 2: Implement the authentication redesign

- Preserve the split image composition and existing form order.
- Apply the inverse brand and a small trust statement to the visual panel.
- Add compact mobile brand, theme, and back actions.
- Add leading field icons only where they improve recognition.
- Rework role selection cards with participant and organizer icons.
- Tighten desktop registration while preserving mobile readability.
- Keep every validation message and `aria-describedby` relationship.

### Step 3: Build and verify all account states

Run: `rtk npm run build:css`

Run: `rtk php tests/run.php UiLayoutTest`

Run: `rtk composer test`

Use browser checks for login, registration, forgot password, invalid submission, and password visibility at 390px and desktop widths.

### Step 4: Commit authentication pages

Commit: `feat: redesign account forms`

---

## Task 6: Redesign dashboards, profile, and security

**Files:**

- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/dashboard/participant.php`
- Modify: `app/Views/dashboard/organizer.php`
- Modify: `app/Views/dashboard/admin.php`
- Modify: `app/Views/profile/edit.php`
- Modify: `app/Views/auth/change-password.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `tests/Unit/DashboardLayoutTest.php`
- Modify: `tests/Unit/ProfileControllerTest.php`

### Step 1: Add failing product UI assertions

Assert rendered behavior for:

- Supplied admin totals remain visible beside semantic metric labels.
- Participant and organizer primary actions retain their existing route or disabled semantics.
- Profile form field names and order remain unchanged.
- Profile and security sections expose descriptive headings.
- Active navigation still works with trailing-slash URLs.

Run: `rtk php tests/run.php DashboardLayoutTest`

Run: `rtk php tests/run.php ProfileControllerTest`

Expected: FAIL on new heading and component contracts only.

### Step 2: Rebuild the dashboard shell and content hierarchy

- Add icon navigation and a compact account identity block.
- Convert metrics to clear product stat surfaces with semantic icons.
- Rebuild participant and organizer empty states with one useful action.
- Keep Week 2 create-event functionality disabled and labelled.
- Keep the admin overview concise and data driven.
- Ensure desktop content remains in the second grid column and mobile content never sits beneath the drawer.

### Step 3: Rebuild profile and security surfaces

- Add an identity summary using the user's initials.
- Give each form section a clear icon, heading, and supporting copy.
- Preserve field names, order, values, helpers, validation, and CSRF.
- Use a readable form width and a stable action region.
- Apply the same shell to password settings and communicate the password minimum clearly.

### Step 4: Build and verify

Run: `rtk npm run build:css`

Run: `rtk php tests/run.php DashboardLayoutTest`

Run: `rtk php tests/run.php ProfileControllerTest`

Run: `rtk composer test`

Use browser checks for participant dashboard, organizer dashboard where available, admin dashboard where available, profile, and security at desktop and 390px widths in both themes.

### Step 5: Commit product pages

Commit: `feat: redesign account dashboards`

---

## Task 7: Complete responsive, accessibility, and regression QA

**Files:**

- Modify as defects require: `resources/css/app.css`
- Modify as defects require: `public/assets/js/app.js`
- Modify as defects require: `app/Views/**/*.php`
- Modify as defects require: `tests/Unit/*Test.php`
- Modify: `public/assets/css/app.css`

### Step 1: Run complete automated verification

Run: `rtk composer check:syntax`

Run: `rtk composer test`

Run: `rtk npm run build:css`

Run: `rtk git diff --check`

Expected: all commands exit 0.

### Step 2: Run a route and state matrix in the browser

Check:

- `/`, `/events`, `/login`, `/register`, `/forgot-password`
- `/dashboard`, `/profile`, `/settings/password` while authenticated
- Light and dark modes
- 1440px desktop, 1024px, 768px, 390px, and 320px
- Keyboard tab order, focus visibility, menu Escape behavior, focus restoration, password reveal state, flash dismissal, and reduced motion
- No horizontal overflow and no hidden content after reveal handling

### Step 3: Fix root causes with regression coverage

For every defect, first add the smallest reproducible render test when the behavior can be tested in PHP. For browser-only interaction or layout defects, record the failing state, fix the root cause, and repeat the same browser check.

### Step 4: Audit final visible copy and assets

- Confirm no em dashes, en dashes, emoji icons, placeholder logo, broken links, or unsupported controls remain.
- Confirm all fonts and icons load from local project assets.
- Confirm no secret or local environment file is tracked.
- Confirm generated CSS is current.

### Step 5: Commit QA fixes

Commit: `fix: complete responsive UI QA`

---

## Task 8: Independent review, final proof, and public push

**Files:**

- Modify only files required by validated review findings

### Step 1: Request an independent code review

Provide the design specification, implementation plan, base commit, and current head. Ask the reviewer to prioritize accessibility, server-rendered behavior preservation, responsive layout, design-system consistency, and secrets or unrelated file inclusion.

### Step 2: Triage review findings

Verify each finding against the actual code and browser behavior. Add a failing test before fixing any reproducible functional issue. Make no speculative changes.

### Step 3: Run final verification from a clean index

Run: `rtk composer check:syntax`

Run: `rtk composer test`

Run: `rtk npm run build:css`

Run: `rtk git diff --check`

Run: `rtk git status --short`

Run: `rtk git diff --cached --name-only`

Confirm test totals from fresh output, confirm only intended project files are committed, and confirm `.env` and unrelated artifacts are absent.

### Step 4: Commit validated review fixes if needed

Commit: `fix: address UI review findings`

Skip this commit if review produces no code changes.

### Step 5: Push the completed main branch

Run: `rtk git push origin main`

Verify the remote branch and public repository URL with `gh` without exposing credentials.

