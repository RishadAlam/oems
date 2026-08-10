# OEMS Week 3 Operations and Production Readiness Design

## Goal

Complete the remaining operational milestone so organizers can run promotions, participants receive reliable scheduled communication and calendar artifacts, visitors can contact or subscribe to the platform safely, administrators can operate those workflows, and the application has explicit production health, maintenance, backup, and deployment boundaries.

The accepted scope is derived from the original OEMS specification, the completed Week 1 account work, and the completed Week 2 event and transaction work. The user's instruction to complete Week 3 without further questions is the approval for this design.

## Design Read

This is a preservation release for a trust-first event platform. It retains the existing cobalt and cool-neutral token system, Manrope typography, Phosphor icons, Tailwind v4 build, dual light and dark themes, 12/18/24 pixel surface radii, restrained motion, and dense role workspaces. Public pages remain calm and image-led. Operational pages prioritize evidence, status, and explicit actions.

Design dials:

- `DESIGN_VARIANCE: 4` for stable public and workspace composition.
- `MOTION_INTENSITY: 2` for state feedback only.
- `VISUAL_DENSITY: 7` for operational tables, queues, and reporting.

No second component system, external icon family, CDN asset, or client framework will be introduced.

## Scope

### 1. Organizer Coupons and Participant Redemption

Organizers manage event-scoped coupon codes through owned routes. A coupon has a normalized uppercase code, fixed or percentage discount, optional start and expiry, optional usage limit, and explicit active state. It may only target an event owned by the organizer and cannot be moved across organizers.

Checkout validates the code server-side inside the registration transaction. The repository locks the event and coupon in a stable order, verifies publication, registration timing, ownership, schedule, limits, and one-use-per-participant rules, then records the exact decimal discount in `coupon_usage`. The amount stored on the registration and payment is the final database-derived amount. Client-submitted prices or discounts are never trusted.

Cancellation or payment rejection does not reuse a redeemed coupon automatically after a successful reservation. This avoids race-prone counter decrements and makes the audit history truthful. A failed transaction rolls back coupon usage and `used_count` with the registration.

### 2. Public Contact and Newsletter Workflows

The existing Contact CMS page gains a real contact form. Public submission requires CSRF, scalar input, bounded validation, a honeypot field, and rate limits by normalized email and trusted request IP. It persists only name, email, subject, and message. The public response is generic and never exposes storage failures.

Administrators receive a bounded contact queue with allow-listed status filters, safe search, pagination, detail, mark-read, archive, and reply actions. A reply is queued for email delivery and only marks the message replied after the outbox job is accepted. Repeated identical actions are idempotent.

Newsletter subscription uses double opt-in. The public form accepts an email, stores only a token hash, and queues a confirmation email. Confirmation activates the subscription. Unsubscribe uses a separate hashed one-time token and remains available without authentication. Public responses do not reveal whether an address already exists. Administrators may view aggregate subscriber counts and create a bounded plain-text campaign. Recipient fan-out is queued, not sent during the web request.

### 3. Durable Email Outbox and Event Reminders

All new fan-out email uses a database outbox. Each job contains an allow-listed template name, normalized recipient, bounded JSON payload, attempt count, next-attempt time, stable idempotency key, and delivery state. It never stores SMTP credentials, raw reset tokens in logs, or arbitrary PHP templates.

`scripts/process-mail-outbox.php` claims jobs in small batches with row locking, sends through the existing mail transport, writes the existing email audit log, and applies bounded exponential retry. Failed jobs become terminal after the configured maximum and retain a sanitized error only.

`scripts/queue-event-reminders.php` queues 24-hour reminders for confirmed, active, verified, nondeleted participants on nondeleted published events. A unique event/registration/reminder key prevents duplicate sends across repeated cron runs. Cancellation messages and organizer announcements may enqueue through the same service without making their domain transaction depend on SMTP availability.

### 4. Calendar Delivery

Published and completed public events expose a generated RFC 5545 `.ics` download with escaped text, UTC timestamps, stable UID, and safe filename. Restricted venues expose only coarse public location data to guests; confirmed participants receive exact location through their owned registration calendar route.

Participant registration and ticket screens receive an Add to calendar action. The calendar response is private for restricted confirmed-registration data and public only for already-public event data. Google Calendar is an external link generated from the same safe normalized event payload; there is no account linking or OAuth token storage.

### 5. Accessible Analytics Charts

Administrator and organizer analytics gain locally hosted Chart.js visualizations for monthly events, registrations, verified revenue, attendance, and category distribution. Every chart has an adjacent semantic data table or text summary, so the canvas is never the only representation. Chart data is emitted through hex-safe JSON, contains aggregate non-PII values only, and uses exact decimal strings before display conversion.

Charts respect both theme token sets, reduced motion, responsive resizing, and page lifecycle cleanup. If JavaScript or canvas is unavailable, the existing reports remain fully usable.

### 6. Production Operations

The application provides two minimal JSON health endpoints:

- `/health/live` proves the PHP process can dispatch a request without touching the database.
- `/health/ready` checks database connectivity, required migrations, writable runtime directories, and private ticket storage. It returns only component names and boolean state, never paths, credentials, versions, SQL, or stack traces.

Maintenance mode is read from the private settings catalog with a short file-backed cache. When active, public and non-administrator application routes return an accessible `503` page with `Retry-After`. Health endpoints, static assets, login, and super-administrator access remain available. Only a super administrator can change maintenance state through a CSRF-protected confirmation action.

A backup script writes a timestamped compressed SQL dump beneath `storage/backups`, uses `MYSQL_PWD` rather than a password command argument, sets restrictive permissions, verifies a non-empty output, and applies an allow-listed retention count. It never accepts an arbitrary output directory or database identifier from a web request. Restore remains an explicit operator procedure.

Deployment assets document and provide example Nginx, PHP-FPM, and systemd timer/service configuration for the HTTP application, outbox worker, reminder scheduler, and backups. Secrets remain environment-only. Production deployment requires HTTPS, secure cookies, trusted proxies, edge HSTS, writable runtime directories, migrated database, and a post-deploy readiness probe.

## Architecture

Each subsystem uses the established contract, repository, service, controller, view pattern:

- Repositories own prepared SQL, transactions, locking, and hydration.
- Services own validation, authorization-independent business rules, idempotency, and safe error mapping.
- Controllers obtain the authenticated actor, apply scalar request boundaries, render escaped views, and choose HTTP status or redirect behavior.
- Route middleware owns authentication, role, method, CSRF, and rate-limit boundaries.
- CLI scripts resolve the same container services and never duplicate domain rules.

The outbox is the only new asynchronous boundary. Domain transactions enqueue a durable job and commit. SMTP delivery occurs later, so an unavailable provider cannot roll back registration, cancellation, contact persistence, newsletter confirmation, or announcement persistence.

## Data Changes

The Week 3 migration is repeatable and upgrades both a populated current database and a fresh schema.

- `coupons`: add deterministic event/code and active-window indexes plus constraints for percentage range, positive fixed values, date order, and used-count bounds.
- `coupon_usage`: add one-use-per-coupon/user uniqueness and lookup indexes.
- `newsletter`: add confirmation and unsubscribe token hashes, confirmation timestamp, and token/active indexes.
- `newsletter_campaigns`: bounded subject/message, status, creator, recipient counts, request key, scheduling and audit timestamps.
- `mail_outbox`: allow-listed template, recipient, payload JSON, idempotency key, status, attempts, availability/lock/sent timestamps, and sanitized error.
- `contact_messages`: retain the existing lifecycle and add deterministic queue/search indexes.
- `settings`: add private maintenance, outbox retry, reminder lead-time, and backup-retention defaults without exposing them through the public settings provider.

Every forward migration uses `information_schema` guards where MySQL lacks `IF NOT EXISTS`, is safe to run repeatedly, and preserves existing rows.

## Security and Privacy

- Prepared statements and native MySQL prepares remain mandatory.
- All state-changing web routes are POST and CSRF-protected.
- Organizer resources are scoped through organizer ownership, not request identifiers alone.
- Public write endpoints use account or email and trusted-IP rate-limit buckets.
- Coupon calculations use the existing exact money helper and database values only.
- Newsletter and calendar tokens are random, stored only as SHA-256 hashes, bounded by expiry where applicable, and never logged.
- Contact and newsletter exports are excluded. Administrators see only the operational fields required for the workflow.
- Outbox payloads use template-specific allowlists and exclude credentials, payment gateway responses, exact participant location, and raw authentication tokens.
- Backup and worker scripts are CLI-only and fail closed outside an initialized environment.
- Logs contain event, job, or message identifiers and sanitized error classes, not message bodies, email tokens, SMTP responses, or participant PII.

## UI and Accessibility

- Existing workspace navigation gains Coupons, Contact, Newsletter, and Operations links only for the roles that own them.
- Forms use labels above fields, stable helper/error associations, explicit consequences, scalar old-input allowlists, and 44 pixel mobile targets.
- Coupon and contact tables use semantic tables that become data-labeled mobile cards.
- Empty, success, validation, conflict, rate-limit, and persistence-failure states remain explicit.
- Destructive or broad actions use evidence-first confirmation, not browser alerts.
- Visible copy uses plain functional language, one accent color, the existing radius rules, and no em or en dash characters.
- Charts always have accessible non-canvas equivalents.

## Error Handling and Idempotency

- Duplicate coupon application, confirmation links, outbox jobs, reminders, campaigns, contact status actions, and maintenance actions return the already-achieved state without duplicate audit or delivery.
- Stale opposite-state actions return `409`.
- Invalid scalar/filter/date input returns inline validation or `422` without widening to an unfiltered query.
- Foreign or hidden resources return `404` without revealing existence.
- Provider errors remain queued for bounded retry and never surface raw diagnostics to the browser.
- CLI scripts exit nonzero with sanitized operational output and do not leave a job permanently locked.

## Testing and Release Gates

Every behavior is delivered through observed RED, minimal GREEN, refactor, focused verification, independent requirements review, and one scoped commit.

The final release requires:

- Full PHP suite, PHP syntax, strict Composer validation, platform requirements, install dry-run, and advisory audit.
- npm advisory audit, deterministic asset/CSS build, JavaScript syntax, and behavioral harnesses.
- Fresh schema/seed/demo imports twice in a disposable MySQL database.
- Populated-current-database Week 3 migration twice with preserved counts and native-prepare coupon/outbox/contact/newsletter checks.
- CLI worker, reminder, and backup behavior against disposable state.
- Live HTTP journeys for coupons, contact, newsletter confirmation, calendar delivery, health, maintenance, and role/CSRF/IDOR/method/rate-limit boundaries.
- Browser checks at 320, 768, and 1440 pixels in light and dark themes with zero horizontal overflow, sampled WCAG contrast failures, console errors, focus traps, missing labels, or inaccessible chart-only information.
- Package, secret, private-artifact, migration-order, and public-project-file audits.

## Explicit Non-Goals

- Real card, bank, or mobile-wallet gateway settlement remains outside the project because no provider contract or credentials exist.
- Browser push notifications, SMS, OAuth calendar account linking, multi-language translation, and multi-node distributed queue infrastructure remain separate integrations.
- The built-in worker and limiter target the documented single-node deployment. A future multi-node deployment must replace their locking storage with shared infrastructure.
- Production restore is documented and tested on disposable databases, but the application never exposes restore or arbitrary shell execution through HTTP.

## Completion Definition

Week 3 is complete when organizers can create and operate event coupons, participants can redeem them through exact transactional checkout, visitors can use contact and double-opt-in newsletter workflows, email jobs and reminders survive provider outages, event calendars preserve location privacy, analytics have accessible charts, operations can observe health and enable maintenance, backups and deployment units are documented and testable, all Critical and Important review findings are closed, every required gate passes, each slice has a Git commit, and the verified project files are pushed to public `main`.
