# OEMS Verification Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add secure, throttled, privacy-safe verification-email recovery and expose it consistently across authentication, dashboard, invalid-link, and newsletter confirmation surfaces.

**Architecture:** Extend the existing user repository with conditional verification-token rotation, keep eligibility and throttling in `AuthService`, and keep delivery/redirect behavior in `AuthController`. Render one dedicated guest recovery page and one shared authenticated dashboard notice; reuse the existing newsletter subscription service for newsletter confirmation recovery.

**Tech Stack:** PHP 8 custom MVC, PDO, OEMS `RateLimiter`, PHPMailer transport abstraction, Tailwind CSS 4, vanilla JavaScript form system, custom PHP/JavaScript test runners.

## Global Constraints

- Store only SHA-256 token digests; raw tokens may exist only in request-scoped memory and the outgoing message.
- Require CSRF on resend POST requests and apply both normalized-email and source-IP rate limits.
- Never reveal whether a submitted address exists, is active, or is already verified.
- A replacement token invalidates every older email-verification link.
- Use existing OEMS semantic tokens and form primitives; introduce no dependency, raw palette utility, or `dark:` utility.
- Preserve the existing newsletter subscribe behavior as the newsletter confirmation resend mechanism.
- Preserve unrelated dirty and untracked worktree files.

---

### Task 1: Capture the missing verification recovery contract

**Files:**
- Modify: `tests/Unit/AuthServiceTest.php`
- Modify: `tests/Unit/UserRepositoryTest.php`
- Modify: `tests/Unit/AuthControllerMailTest.php`
- Modify: `tests/Unit/DashboardLayoutTest.php`
- Modify: `tests/Unit/NewsletterControllerTest.php`

**Interfaces:**
- Consumes: current `AuthService`, `AuthController`, `UserRepositoryInterface`, auth/dashboard views, and newsletter confirmation page.
- Produces: failing behavioral tests for the exact repository, service, route, mail, privacy, and UI contracts implemented by Tasks 2 and 3.

- [ ] **Step 1: Add repository RED tests**

Add tests proving `replaceEmailVerificationToken()` updates only an active, undeleted, unverified user, stores the supplied digest, and causes the prior raw token to fail verification.

- [ ] **Step 2: Add service RED tests**

Add tests for eligible rotation metadata, normalization, verified/inactive/missing privacy results, and dual email/IP throttling. Assert returned raw tokens match `^[a-f0-9]{64}$` only for eligible dispatches.

- [ ] **Step 3: Add controller and mail RED tests**

Add tests proving the resend page renders, POST sends to eligible users, unknown requests use the privacy sink, throttled requests dispatch nothing, registration redirects to recovery, and invalid verification links recover there.

- [ ] **Step 4: Add UI RED tests**

Require the login recovery link, conditional shared dashboard notice, correct CSRF form, and newsletter failure action. Require one notice per dashboard render and none for verified users.

- [ ] **Step 5: Run focused tests and confirm intended failures**

Run:

```bash
rtk php tests/run.php AuthServiceTest UserRepositoryTest AuthControllerMailTest DashboardLayoutTest NewsletterControllerTest
```

Expected: failures identify missing `replaceEmailVerificationToken`, resend controller actions/routes/views, shared notice, and newsletter recovery action; no parser or fixture errors.

- [ ] **Step 6: Commit the RED contract**

```bash
rtk git add tests/Unit/AuthServiceTest.php tests/Unit/UserRepositoryTest.php tests/Unit/AuthControllerMailTest.php tests/Unit/DashboardLayoutTest.php tests/Unit/NewsletterControllerTest.php
rtk git commit -m "test: capture verification recovery flow"
```

---

### Task 2: Implement secure resend behavior

**Files:**
- Modify: `app/Contracts/UserRepositoryInterface.php`
- Modify: `app/Repositories/UserRepository.php`
- Modify: `tests/Support/FakeUserRepository.php`
- Modify: `app/Services/AuthService.php`
- Modify: `app/Services/AccountMailer.php`
- Modify: `app/Controllers/AuthController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `UserRepositoryInterface::replaceEmailVerificationToken(int $userId, string $tokenHash): bool` and `AuthService::requestEmailVerification(string $email, string $ipAddress): array`.
- Returns from the service: `success`, nullable `verification_token`, nullable `user_id`, nullable `name`, nullable `email`, and `mail_dispatch` with `verification`, `probe`, or `none`.

- [ ] **Step 1: Implement bounded repository rotation**

Use one conditional `UPDATE users SET email_verification_token_hash = :token_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id AND status = 'active' AND email_verified_at IS NULL AND deleted_at IS NULL`. Return `rowCount() === 1`; mirror it in `FakeUserRepository`.

- [ ] **Step 2: Implement service eligibility and throttling**

Normalize the email, consume `verification-resend:ip:` plus a SHA-256 IP digest and `verification-resend:email:` plus a SHA-256 email digest, return `mail_dispatch=none` when either limit fails, and otherwise return eligible dispatch metadata only after the digest replacement succeeds. Missing, inactive, and verified users return `mail_dispatch=probe`.

- [ ] **Step 3: Implement privacy-probe mail**

Extract verification message construction inside `AccountMailer`; add `sendVerificationPrivacyProbe()` using `mail.privacy_sink_address` or the configured sender fallback and an unpersisted random token.

- [ ] **Step 4: Implement controller actions and routes**

Add `showResendVerification()` and `resendVerification()`. Validate `required|email|max:190`, send eligible or probe messages based on service metadata, retain the submitted email in flash old data, and return the same browser success copy for all valid requests. Register GET and CSRF-protected POST `/verify-email/resend` without guest-only middleware; allow signed-in users to consume `/verify-email/{token}`.

- [ ] **Step 5: Improve registration and invalid-link recovery**

Redirect successful registration to `/verify-email/resend` after flashing its email and inbox guidance. Redirect failed verification consumption to the same recovery page. Redirect successful signed-in verification to `/profile`; guests still return to `/login`.

- [ ] **Step 6: Run focused backend tests**

Run the five Task 1 suites and `AccountMailerTest`. Expected: all pass.

- [ ] **Step 7: Commit backend recovery**

```bash
rtk git add app/Contracts/UserRepositoryInterface.php app/Repositories/UserRepository.php tests/Support/FakeUserRepository.php app/Services/AuthService.php app/Services/AccountMailer.php app/Controllers/AuthController.php routes/web.php tests/Unit/AuthServiceTest.php tests/Unit/UserRepositoryTest.php tests/Unit/AuthControllerMailTest.php
rtk git commit -m "feat: add secure verification resend"
```

---

### Task 3: Publish recovery UI across the project

**Files:**
- Create: `app/Views/auth/resend-verification.php`
- Create: `app/Views/components/email-verification-notice.php`
- Modify: `app/Views/auth/login.php`
- Modify: `app/Controllers/PublicNewsletterController.php`
- Modify: `app/Views/pages/newsletter-result.php`
- Modify: `resources/css/app.css`
- Modify: `public/assets/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/UiLayoutTest.php`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/js/pwa.test.mjs`

**Interfaces:**
- Consumes: `/verify-email/resend`, common `currentUser`, `csrfToken`, `errors`, `old`, and flash data.
- Produces: reusable `.verification-recovery` and `.verification-notice` visual contracts.

- [ ] **Step 1: Create the recovery form**

Render a labeled email control with `autocomplete="email"`, help/error associations, the shared form-error summary, and a full-width primary button whose progress label is “Sending verification email…”. Include a sign-in return link.

- [ ] **Step 2: Create the shared dashboard notice**

Render only when `currentUser.email_verified_at` is empty. Include the account email, concise effect/recovery guidance, CSRF token, hidden email value, and “Resend verification email” action. Include it once in the dashboard layout after global flash messages.

- [ ] **Step 3: Add recovery entry points**

Add the login link and supply the newsletter result page with a context-specific action URL/label so failed confirmation directs to `/#newsletter` while success continues to `/events`.

- [ ] **Step 4: Add responsive semantic styling**

Use existing warning/info/surface/line variables, a compact icon, readable copy, responsive flex/grid action placement, `min-width:0`, and 44–48 pixel controls. Add source-and-compiled CSS tests that reject fixed widths, raw colors, and `dark:` utilities.

- [ ] **Step 5: Build and update the static cache graph**

Run `rtk npm run build:css`, bump the CSS/service-worker cache token once, and update every exact layout/PWA assertion that references it.

- [ ] **Step 6: Run focused UI and PWA tests**

Run `DashboardLayoutTest`, `NewsletterControllerTest`, `UiLayoutTest`, `PwaStaticPolicyTest`, `OrganizerVenueControllerTest`, `tests/js/pwa.test.mjs`, and form tests. Expected: all pass.

- [ ] **Step 7: Commit the global UI**

```bash
rtk git add app/Views/auth/resend-verification.php app/Views/components/email-verification-notice.php app/Views/auth/login.php app/Views/layouts/public.php app/Views/layouts/dashboard.php app/Views/layouts/auth.php app/Views/layouts/maintenance.php app/Controllers/PublicNewsletterController.php app/Views/pages/newsletter-result.php resources/css/app.css public/assets/css/app.css public/service-worker.js tests/Unit/DashboardLayoutTest.php tests/Unit/NewsletterControllerTest.php tests/Unit/UiLayoutTest.php tests/Unit/PwaStaticPolicyTest.php tests/Unit/OrganizerVenueControllerTest.php tests/js/pwa.test.mjs
rtk git commit -m "style: publish verification recovery UI"
```

---

### Task 4: Verify end to end

**Files:**
- Modify only if fresh evidence reveals an in-scope defect.

- [ ] **Step 1: Run the complete automated gates**

Run the full PHP suite outside the socket-restricted sandbox if necessary, all JavaScript tests, form tests, PHP syntax checks, asset checks, and `git diff --check`.

- [ ] **Step 2: Browser-check real routes**

Verify `/verify-email/resend` and representative dashboard routes at 390, 768, 1280, and 2048 pixels in light and dark themes. Check no overflow, one notice, form labels/errors, keyboard focus order, submit feedback, and console output.

- [ ] **Step 3: Verify lifecycle behavior**

Using a reversible unverified fixture, prove resend delivery, old-link rejection, new-link success, post-verification notice removal, and newsletter failure recovery. Restore fixture data exactly.

- [ ] **Step 4: Audit repository state and commit any evidence-backed correction**

Confirm no scoped files remain uncommitted, unrelated changes remain untouched, and create one correction commit only if verification required a product change.
