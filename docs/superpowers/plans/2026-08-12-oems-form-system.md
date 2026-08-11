# OEMS Professional Form System Implementation Plan

> **Execution rule:** Complete these tasks in order with red-green-refactor discipline. After each green task, stage only the named project files and create the listed commit. Preserve all unrelated workspace files.

**Goal:** Redesign every OEMS form around one accessible visual and interaction system, establish client/server validation parity for all user-editable fields, and verify the workflows end to end.

**Architecture:** Preserve server-rendered PHP and the custom MVC structure. Add shared PHP form-presentation helpers, semantic CSS components, and a progressively enhanced vanilla JavaScript controller. Keep browser constraints visible in HTML and enforce the same data rules independently in controllers/services through `Core\Validator` and domain validation.

**Tech stack:** PHP 8.2, custom MVC, Tailwind CSS 4, vanilla JavaScript, Node test runner, custom PHP test runner, in-app browser QA.

**Design specification:** `docs/superpowers/specs/2026-08-12-oems-form-system-design.md`

---

## Task 1: Establish the executable form inventory and contracts

**Files:**

- Create: `tests/Unit/FormSystemTest.php`
- Create: `tests/js/form-validation.test.mjs`
- Modify: `package.json`

### Steps

1. Add failing render/source contracts that inventory all 86 current form instances and classify expectations for entry, filter, action, and specialized forms.
2. Assert that user-editable forms expose a shared enhancement hook, preserve native constraints, have associated labels, and render field-addressable server errors.
3. Assert that every POST form includes a CSRF token unless explicitly documented as a test fixture or non-state-changing special case.
4. Add JavaScript behavior tests for blur timing, submit validation, error summary focus, error recovery, confirmation matching, cross-field dates, paired coordinates, file rules, and duplicate-submit locking.
5. Add `test:forms` and include it in the frontend test workflow.
6. Run the targeted PHP and JavaScript tests and confirm the new behavioral expectations fail before production implementation.
7. Commit: `test: define global form contracts`

## Task 2: Build the shared form foundation

**Files:**

- Modify: `app/Helpers/helpers.php`
- Create: `app/Views/components/form-errors.php`
- Create: `public/assets/js/form-validation.js`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `tests/Unit/FormSystemTest.php`
- Modify: `tests/js/form-validation.test.mjs`

### Steps

1. Add PHP helpers for stable control/error/help IDs, combined `aria-describedby`, invalid attributes, safe old values, and normalized error-summary entries.
2. Add a reusable error-summary partial with focus target, semantic heading, field links, and a generic fallback for non-field errors.
3. Implement the progressive validation controller:
   - enhance forms with the shared hook;
   - validate on blur only after interaction;
   - validate all fields on submit;
   - revalidate invalid fields during correction;
   - synchronize inline client errors and ARIA state;
   - enforce confirmation, date ordering, paired values, and file policies from data attributes;
   - focus the summary after invalid submission;
   - lock only a valid state-changing submission.
4. Load the controller from every layout with a cache-busted asset URL.
5. Add professional field, hint, error, summary, filter-bar, action-bar, file-picker, busy, and responsive styles. Ensure control internals reserve icon/action space so text never overlaps adornments.
6. Build CSS and run the targeted form tests until green.
7. Commit: `feat: build shared form system`

## Task 3: Migrate public and authentication forms

**Files:**

- Modify: `app/Views/home/index.php`
- Modify: `app/Views/events/index.php`
- Modify: `app/Views/events/show.php`
- Modify: `app/Views/pages/contact.php`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/auth/login.php`
- Modify: `app/Views/auth/register.php`
- Modify: `app/Views/auth/forgot-password.php`
- Modify: `app/Views/auth/reset-password.php`
- Modify: `app/Views/auth/change-password.php`
- Modify: `app/Controllers/AuthController.php`
- Modify: `app/Services/ContactService.php`
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/Unit/AuthControllerMailTest.php`
- Modify: `tests/Unit/ContactControllerTest.php`

### Steps

1. Add failing tests for HTML/server parity of name, email, password, confirmation, role, terms, contact subject/message, newsletter email, and event search/location inputs.
2. Migrate entry forms to the shared hook, summary, required note, helpers, autocomplete/inputmode attributes, and action copy.
3. Add password confirmation client contract without persisting password values.
4. Convert discovery forms to the compact filter/search pattern and state-change forms to the action pattern.
5. Verify backend rules reject blank, malformed, overlong, unknown-option, and tampered values.
6. Run targeted tests and browser-check public/auth flows in light/dark and mobile/desktop.
7. Commit: `feat: improve public and account forms`

## Task 4: Migrate profile and participant forms

**Files:**

- Modify: `app/Views/profile/edit.php`
- Modify: `app/Views/participant/reviews/form.php`
- Modify: `app/Views/participant/registrations/register.php`
- Modify: `app/Views/participant/registrations/show.php`
- Modify: `app/Views/participant/waitlist/index.php`
- Modify: `app/Views/participant/notifications/index.php`
- Modify: `app/Views/participant/favorites/index.php`
- Modify: `app/Controllers/ProfileController.php`
- Modify: `app/Services/RegistrationService.php`
- Modify: `app/Services/ReviewService.php`
- Modify: `app/Services/WaitlistService.php`
- Modify: `tests/Unit/ProfileControllerTest.php`
- Modify: `tests/Unit/ParticipantRegistrationControllerTest.php`
- Modify: `tests/Unit/ReviewServiceTest.php`
- Modify: `tests/Unit/WaitlistServiceTest.php`
- Modify: `tests/Unit/FormSystemTest.php`

### Steps

1. Add failing parity and boundary tests for profile identity/address/preferences, review rating/comment, registration/payment, cancellation reason, waitlist notes/quantity, and notification actions.
2. Migrate profile sections with concise hints, required/optional state, correct autocomplete tokens, and a stable save area.
3. Migrate registration/payment forms with conditional field requirements and safe handling of sensitive values.
4. Migrate review and waitlist forms with clear ranges, counters/limits, and field-level errors.
5. Apply duplicate-submit protection and precise progress language to participant actions.
6. Run targeted PHP/JS tests and role-based browser scenarios.
7. Commit: `feat: improve participant forms`

## Task 5: Migrate organizer forms

**Files:**

- Modify: `app/Views/organizer/events/form.php`
- Modify: `app/Views/organizer/events/index.php`
- Modify: `app/Views/organizer/events/show.php`
- Modify: `app/Views/organizer/events/trash.php`
- Modify: `app/Views/organizer/venues/form.php`
- Modify: `app/Views/organizer/venues/index.php`
- Modify: `app/Views/organizer/coupons/form.php`
- Modify: `app/Views/organizer/coupons/index.php`
- Modify: `app/Views/organizer/announcements/create.php`
- Modify: `app/Views/organizer/announcements/index.php`
- Modify: `app/Views/organizer/reviews/index.php`
- Modify: `app/Views/organizer/check-in/index.php`
- Modify: `app/Views/organizer/participants/index.php`
- Modify: `app/Views/organizer/analytics/index.php`
- Modify: `app/Services/EventService.php`
- Modify: `app/Services/VenueService.php`
- Modify: `app/Services/CouponService.php`
- Modify: `app/Services/AnnouncementService.php`
- Modify: `tests/Unit/EventServiceTest.php`
- Modify: `tests/Unit/VenueServiceTest.php`
- Modify: `tests/Unit/CouponServiceTest.php`
- Modify: `tests/Unit/AnnouncementServiceTest.php`
- Modify: `tests/Unit/OrganizerEventControllerTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/Unit/FormSystemTest.php`

### Steps

1. Add failing contracts for every organizer editable field and business boundary.
2. Migrate event creation/editing with section hierarchy, schedule-order validation, capacity/price constraints, visibility choices, professional file pickers, and a responsive action area.
3. Migrate venue editing with paired coordinates, secure map URL, capacity rules, geocode/map status, and permission recovery guidance.
4. Migrate coupon and announcement forms with conditional limits, confirmation safeguards, audience clarity, and irreversible-send messaging.
5. Migrate reviews, check-in, list filters, analytics filters, publish/cancel/delete/restore actions, and participant search.
6. Verify MIME/size/count upload rules, option tampering, ownership, state transitions, and no-mutation-on-failure server behavior.
7. Run targeted tests and complete organizer browser scenarios from event draft through submission.
8. Commit: `feat: improve organizer forms`

## Task 6: Migrate administrator forms

**Files:**

- Modify: `app/Views/admin/categories/form.php`
- Modify: `app/Views/admin/categories/index.php`
- Modify: `app/Views/admin/blog/form.php`
- Modify: `app/Views/admin/blog/index.php`
- Modify: `app/Views/admin/cms/faq-form.php`
- Modify: `app/Views/admin/cms/page-form.php`
- Modify: `app/Views/admin/cms/banner-form.php`
- Modify: `app/Views/admin/cms/index.php`
- Modify: `app/Views/admin/settings/edit.php`
- Modify: `app/Views/admin/newsletter/campaign-form.php`
- Modify: `app/Views/admin/newsletter/index.php`
- Modify: `app/Views/admin/events/index.php`
- Modify: `app/Views/admin/events/show.php`
- Modify: `app/Views/admin/events/trash.php`
- Modify: `app/Views/admin/users/index.php`
- Modify: `app/Views/admin/users/show.php`
- Modify: `app/Views/admin/organizers/index.php`
- Modify: `app/Views/admin/organizers/show.php`
- Modify: `app/Views/admin/payments/index.php`
- Modify: `app/Views/admin/payments/show.php`
- Modify: `app/Views/admin/contact/index.php`
- Modify: `app/Views/admin/contact/show.php`
- Modify: `app/Views/admin/reviews/index.php`
- Modify: `app/Views/admin/reports/index.php`
- Modify: `app/Views/admin/analytics/index.php`
- Modify: `app/Views/admin/operations/index.php`
- Modify relevant admin controllers/services and their existing unit tests
- Modify: `tests/Unit/FormSystemTest.php`

### Steps

1. Add failing contracts for CMS, category, blog, settings, campaign, moderation, payment, contact, report, analytics, and maintenance inputs.
2. Migrate content forms with consistent required state, slug/status guidance, character limits, file policies, and publication-action separation.
3. Migrate platform settings with section navigation, clear save scope, and server parity for every required setting.
4. Migrate moderation and operational actions with reason requirements, expected-state protection, consequences, and confirmation treatment.
5. Normalize all admin search/filter bars and add clear/reset affordances where useful.
6. Verify server rejection of malformed, missing, overlong, stale, unauthorized, and unknown-option submissions.
7. Run targeted tests and admin browser scenarios.
8. Commit: `feat: improve administrator forms`

## Task 7: Close backend validation and parity gaps

**Files:**

- Modify: `Core/Validator.php`
- Modify applicable controllers/services discovered by the parity audit
- Modify: `tests/Unit/ValidatorTest.php`
- Modify applicable controller/service tests
- Create: `docs/form-validation-matrix.md`

### Steps

1. Produce the final matrix of each editable field, client constraint, server validator, error target, and server-only rules.
2. Add failing tests for every discovered gap before changing implementation.
3. Add only reusable primitive rules to `Core\Validator`; keep business/state/authorization validation in services.
4. Ensure safe old-input handling, field-addressable errors, 422 API behavior, and no mutation on invalid input.
5. Run the validator and affected workflow suites until green.
6. Commit: `fix: enforce form validation parity`

## Task 8: Full automated and browser verification

**Files:**

- Modify only files needed for regressions found during verification
- Modify: `docs/form-validation-matrix.md` if evidence changes

### Steps

1. Run `rtk composer check:syntax`.
2. Run `rtk php tests/run.php`.
3. Run `rtk npm run test:forms` and every existing `tests/js/*.test.mjs` test.
4. Run `rtk npm run build:css` and `rtk npm run test:assets`.
5. Start the local app and browser-test representative create/edit/invalid/valid flows for public, participant, organizer, and administrator roles.
6. Verify light/dark themes, 320px mobile, desktop, keyboard-only operation, focus/error behavior, file selection, duplicate submit prevention, CSRF continuity, and no horizontal overflow.
7. Fix each coherent regression test-first and commit it separately with a scoped `fix:` message.
8. Confirm `rtk git diff --check` and a clean tracked worktree while leaving pre-existing unrelated untracked files untouched.

## Definition of Done

- Every form instance is classified, styled, and behaviorally audited.
- Every editable required field has matching client and server enforcement.
- Every validation failure is actionable and accessible.
- Specialized and state-changing forms use appropriate safeguards.
- All automated and browser workflows pass without reintroducing session/CSRF failures.
- Every completed task is represented by a focused commit.
