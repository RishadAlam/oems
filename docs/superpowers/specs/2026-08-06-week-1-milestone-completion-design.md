# Week 1 Milestone Completion Design

## Goal

Complete the Week 1 account milestone so participants and organizers can register, receive and use an email-verification link, log in, manage their profile, and reach the dashboard allowed by their role.

## Acceptance Criteria

- Participant and organizer registration creates the correct user, profile, and organizer records.
- Registration sends a verification email through configured SMTP.
- Verification links remain single-use and store only their SHA-256 hash.
- Verified active users can log in and are redirected to their role dashboard.
- Authenticated users can view and update their own profile.
- Password-reset requests send a reset email without revealing whether an account exists.
- Development mode continues to expose local verification and reset links.
- SMTP credentials remain only in the gitignored `.env` file.

## Profile Management

Add authenticated `GET /profile` and `POST /profile` routes and a dashboard-styled profile form.

Editable user fields:

- Name
- Phone

Editable profile fields:

- Bio
- Date of birth
- Gender
- Address line
- City
- Country
- Postal code
- Website
- Locale
- Timezone

Email address and role are visible but read-only. Avatar upload is excluded because file uploads belong to a later module.

A focused profile repository will load the joined user/profile record and update both tables in one database transaction. The controller will validate input, preserve submitted values after validation errors, and redirect with a success message after an update. Dashboard navigation will include a Profile link for every role.

### Profile UI Direction

This is a preserve-mode extension of the current OEMS dashboard rather than a redesign. It will reuse the existing Tailwind color tokens, typography, spacing scale, radii, navigation shell, dark theme, buttons, alerts, and focus states. No second design system or frontend dependency will be introduced.

The page will use one main content surface with clearly titled groups for account details, personal details, address, and regional preferences. This avoids a collection of equal cards and keeps the form easy to scan. Labels sit above controls, optional fields are identified in helper text, and validation messages sit directly below their related control.

Related short fields may share two columns on desktop. Every group collapses to one column below the existing mobile breakpoint, with full-width controls and action buttons. Email and role use read-only controls with explanatory text so their status is clear without relying on color.

The active Profile navigation state, keyboard focus indicators, input contrast, error states, and success alert will follow the dashboard's established patterns. Motion is limited to existing hover and focus feedback. The page must be checked at desktop and mobile widths, in light and dark modes, with keyboard navigation, and with visible validation errors.

## Email Delivery

Install PHPMailer and place it behind a small mail transport contract. An account-mail service will create the verification and password-reset subjects, HTML bodies, plain-text alternatives, and absolute application URLs.

SMTP configuration keys:

- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

The local environment will use the supplied Mailtrap sandbox with port 2525 and STARTTLS. The committed `.env.example` will contain non-secret placeholders only. Mailtrap captures development messages; production delivery requires production SMTP credentials.

Every delivery attempt will create or update an `email_logs` record with the recipient, template, subject, sent or failed status, provider message identifier when available, sanitized error text, and sent timestamp.

## Application Flow

### Registration

1. Validate registration fields.
2. Create the user and related profile records.
3. Build an absolute verification URL from `APP_URL` and the raw one-time token.
4. Send the verification message.
5. Show a generic registration result and retain the development link when debug mode is active.

### Password Reset

1. Accept the email address and always return the same user-facing response.
2. Generate and persist a reset token only for an active account.
3. Send the reset message when a token was generated.
4. Retain the development link when debug mode is active.

### Profile Update

1. Require authentication and CSRF validation.
2. Validate and normalize submitted fields.
3. Update the current user and profile rows transactionally.
4. Redirect to `/profile` with a success message.

## Failure and Security Behavior

- SMTP exceptions are caught and logged without exposing server details to the browser.
- Registration and reset tokens never appear in application logs or database email logs.
- Mail failure does not roll back an already-created account or password-reset token.
- Debug links remain available only when `APP_DEBUG=true`.
- Profile updates always use the authenticated user identifier and cannot accept a user ID from form input.
- All profile output is escaped and every write requires CSRF protection.

## Testing and Verification

- Test profile reads and transactional updates against an in-memory database.
- Test profile validation and rendering with literal fixtures.
- Test verification and reset email composition through a fake transport.
- Test mail success and failure logging without connecting to SMTP.
- Test route authentication and role-dashboard redirects.
- Run all unit tests, PHP syntax checks, Composer validation, and the Tailwind production build.
- Exercise registration, verification, login, profile update, logout, and role dashboard access in the browser.
- Confirm the Mailtrap SMTP connection with a dedicated development message before declaring email delivery complete.

## Commit Boundaries

1. Commit this approved design specification.
2. Commit the detailed implementation plan.
3. Commit profile persistence, routes, UI, and tests.
4. Commit PHPMailer transport, account messages, configuration, email logging, and tests.
5. Commit documentation and any final verified integration adjustments.
