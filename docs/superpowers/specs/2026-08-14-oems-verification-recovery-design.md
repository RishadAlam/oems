# OEMS Verification Recovery Design

## Goal

Give users a secure, obvious way to request a replacement email-verification link wherever verification can block them, while preserving account privacy, invalidating superseded links, and reusing the project's existing mail, CSRF, theme, and form systems.

## Existing behavior and root cause

Registration creates one 64-character verification token, stores only its SHA-256 digest, and sends one email. The application can consume that token, but it has no repository operation for rotating it and no route, form, or controller action for requesting another link. The sign-in error says verification is required without offering recovery, and an invalid verification link redirects to the same dead end.

Password reset already provides request-new-link recovery. Newsletter confirmation already rotates and queues a fresh token when the address is submitted through the newsletter form again. Certificates, tickets, organizer approval, and payment review are not email-ownership links and must not be presented as resendable email verification.

## Chosen design

Use one account-verification recovery flow shared by guests and authenticated users.

- `GET /verify-email/resend` renders a focused recovery page in the auth layout.
- `POST /verify-email/resend` validates the email, applies CSRF protection, rate-limits both normalized email and source IP, and always returns privacy-neutral copy.
- Eligible accounts are active, undeleted, and not already verified.
- A successful eligible request generates a cryptographically random 32-byte token, atomically replaces the stored token digest, and sends the existing OEMS verification message.
- Replacing the digest invalidates every older verification link.
- Missing, inactive, deleted, already-verified, and throttled accounts produce the same browser response and do not send mail to the submitted address.
- Unknown/ineligible public requests use the configured privacy sink, matching the existing password-reset anti-enumeration pattern. Throttled requests send nothing.
- No raw token is logged, flashed, placed in a query string, or stored in plaintext.

## User experience

The dedicated page explains that the newest link replaces older links, includes one email field, and provides a 48-pixel primary action. The submitted address is retained after validation failure or success without being placed in the URL.

Recovery entry points are placed where users need them:

- the sign-in page always includes “Resend verification email” near account recovery links;
- successful registration redirects to the verification recovery page with clear inbox guidance;
- an invalid or already-used verification link redirects to the recovery page rather than a dead-end sign-in error;
- every authenticated dashboard page shows one reusable warning panel when `email_verified_at` is empty, with the account email and a resend action;
- newsletter confirmation failure links directly to the existing newsletter form, whose current service rotates the confirmation token.

The dashboard banner is rendered once by the shared dashboard layout so profile, organizer, participant, and administrator pages cannot drift. It is omitted as soon as the current user is verified.

## Backend boundaries

### Repository

`UserRepositoryInterface::replaceEmailVerificationToken(int $userId, string $tokenHash): bool` conditionally updates only an active, undeleted, unverified user. The database implementation performs a bounded update; the fake repository mirrors the same eligibility rules.

### Service

`AuthService::requestEmailVerification(string $email, string $ipAddress): array` owns normalization, dual rate limits, eligibility checks, token creation, and digest rotation. It returns dispatch metadata only to the controller and otherwise returns a generic successful result.

### Controller and mailer

`AuthController` owns form validation, mail dispatch, flash copy, and redirects. `AccountMailer` gains a verification privacy-probe operation built from the same verification-message template so submitted unknown addresses never receive mail and observable work remains comparable.

## Security and failure behavior

- CSRF is mandatory for every resend submission, including guest submissions.
- Email and IP rate-limit keys are distinct and never contain the raw email address.
- Browser responses do not reveal whether an account exists or is already verified.
- Mail delivery failure is recorded by the existing mail audit/logger path; the browser remains privacy-neutral.
- A repository failure must not expose internal details and must not place a token in the response.
- Verification links remain single-use because successful verification clears the digest.
- Signed-in users may consume a verification link; successful verification returns them to `/profile`, while guests return to `/login`.

## Accessibility and visual contract

- Forms use native labels, email autocomplete, existing server/client validation, form-error summaries, and specific submit-progress copy.
- Informational and warning panels use OEMS semantic tokens in both themes; no raw palette or `dark:` utilities are introduced.
- The global banner has a heading, explanatory copy, visible email, and a standard button with an icon that is decorative.
- Status copy does not rely on color alone.

## Test and verification contract

Tests must prove:

- eligible requests rotate the digest and invalidate the previous token;
- missing, verified, inactive, throttled, and malformed requests do not dispatch to the submitted address;
- controller responses and mail behavior are privacy-neutral;
- the resend GET/POST routes have the intended middleware;
- sign-in, registration, invalid-link, dashboard, and newsletter recovery surfaces expose the correct actions without duplication;
- the shared dashboard banner appears only for unverified users;
- both source and compiled CSS contain the recovery component contract;
- PHP, JavaScript, form, syntax, asset, PWA, and responsive browser checks remain green.

