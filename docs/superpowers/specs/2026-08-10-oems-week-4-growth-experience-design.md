# OEMS Week 4 Growth and Experience Completion Design

Date: 2026-08-10

## Goal

Complete the fourth and final product milestone by adding the remaining attendee-growth, content-discovery, recovery, export, and installability capabilities named in the original OEMS specification.

Weeks 1 through 3 already provide accounts and roles, event lifecycle management, participant registration and payment, QR tickets and attendance, reviews and favorites, communications, analytics, reports, CMS pages, production operations, backups, and deployment controls. Week 4 extends those verified systems without weakening their transaction, privacy, or accessibility boundaries.

The user's instruction to start and continue Week 4 without further questions is the approval for this design.

## Product and Visual Direction

This is a preservation release. The current trust-first event platform remains recognizable:

- Existing cobalt and cool-neutral tokens, Manrope typography, Phosphor icons, light and dark themes, 12/18/24 pixel surface radii, and visible 3 pixel focus rings remain the design system.
- Public discovery remains image-led and calm. Calendar, Blog, and certificate verification use the same page shell, cards, metadata rows, and empty/error states.
- Operational workspaces remain dense, evidence-first, and server rendered.
- No client framework, second icon set, decorative animation system, external CDN, or automatic carousel is introduced.
- All primary actions remain available without JavaScript. JavaScript is progressive enhancement for install prompts and offline state only.

Design dials are `DESIGN_VARIANCE: 4`, `MOTION_INTENSITY: 2`, and `VISUAL_DENSITY: 7`.

## Module 1: Week 4 Foundation

The fresh schema and a resumable forward migration add only the data needed by the new workflows:

- `events.waitlist_enabled` defaults to enabled and can be changed only before an event has started.
- `registrations.waitlisted_at` and `registrations.promoted_at` record queue order and promotion without creating a second source of participant/event uniqueness.
- A deterministic `(event_id, status, waitlisted_at, id)` index supports queue locking.
- `event_certificates` stores one certificate per registration, a random public certificate number, a SHA-256 verification-token hash, private PDF path, issue/revocation audit fields, and timestamps.
- `blog_posts` stores author, bounded title/slug/excerpt/plain-text body/category, optional protected image path, draft/published status, SEO fields, publication timestamps, and soft deletion.

The existing `registrations.status = waitlisted` value is used instead of a competing waitlist table. Waitlisted registrations do not consume seats and have no payment, coupon usage, ticket, or attendance row.

Migration verification upgrades a populated Week 3 database twice, preserves all counts and artifacts, and proves the new indexes and constraints with native MySQL prepares.

## Module 2: Event Waitlists

### Joining and leaving

An active, verified participant may join a waitlist when:

- The event is published, nondeleted, future-dated, and still accepts registrations.
- Waitlisting is enabled.
- No seats remain.
- The participant has no pending or confirmed registration for the event.

The repository derives price and currency from the locked event. Joining creates or truthfully reactivates a waitlisted registration with a stable registration number, queue timestamp, database-derived amount, and no coupon. It never creates a payment or ticket and never decrements capacity.

Participants can leave their own waitlist entry before promotion. Leaving changes the entry to cancelled and records a bounded reason without affecting capacity. Repeated join and leave actions are truthful and idempotent.

### Promotion

When participant cancellation or administrator payment rejection restores a seat, a post-commit promotion service attempts to promote the oldest eligible entry. A CLI reconciliation command can safely retry available events so an interrupted notification or web request cannot strand a seat.

Promotion locks in this order: event, oldest waitlisted registration, payment, ticket. It rechecks publication, deadline, schedule, account eligibility, queue state, and available seats before consuming one seat.

- A free event promotes to confirmed and issues its ticket atomically through the existing ticket service.
- A paid event promotes to pending and creates the normal pending manual-payment record.
- A stale or concurrent loser rereads the achieved state and does not double-consume a seat.

Notification and queued-email delivery happen after the domain transaction. Delivery failure never rolls back a successful promotion. Organizer participant views expose queue position and status without adding participant PII to public output.

## Module 3: Calendar Discovery and Read-only Public API

### Calendar view

`GET /events/calendar` renders a real month view and a canonical chronological list. The month is a strict `YYYY-MM` value within a bounded range. Previous, current, and next-month controls are ordinary links and work without JavaScript.

Only published or completed, nondeleted events from active categories and organizers appear. Restricted-location events expose only their already-approved coarse public location. Each date cell has an accessible label and the list remains the canonical mobile and screen-reader representation.

### REST API

Versioned read-only endpoints provide the same public discovery data:

- `GET /api/v1/events`
- `GET /api/v1/events/{slug}`
- `GET /api/v1/events/calendar`

The API accepts only scalar allow-listed search, category, date, price, location, sort, month, page, and limit inputs. Limits are bounded, unknown input fails closed with JSON `422`, hidden records return `404`, and wrong methods return `405`.

Responses use explicit stable fields, ISO 8601 dates, exact decimal price strings, pagination metadata, `application/json`, `nosniff`, CORS disabled by default, and short public cache validators. Restricted exact venue identity, coordinates, arrival notes, participant state, private artifacts, internal IDs not needed by clients, and moderation data are excluded.

The calendar page and API share a repository query so privacy and lifecycle rules cannot drift.

## Module 4: Attendance Certificates

A confirmed participant with a recorded `present` attendance row may request a certificate only after the event is completed. The service locks and rechecks the participant, registration, event, ticket, and attendance facts, then creates one idempotent certificate record and one private PDF artifact.

The PDF contains the participant name, event title, completion date, certificate number, issue date, and a verification URL. It does not contain email, phone, payment reference, QR check-in token, exact restricted location, or internal database identifiers.

Routes:

- `GET /participant/certificates`
- `POST /participant/registrations/{id}/certificate`
- `GET /participant/certificates/{id}/pdf`
- `GET /certificates/verify/{token}`

Participant routes enforce ownership and private no-store downloads. The public verification token has at least 128 bits of entropy and is stored only as a hash. Verification exposes only the deliberate certificate facts: validity, participant name, event title, completion date, and issue date. Revoked, malformed, unknown, or deleted-user certificates return a generic unavailable result.

Artifact creation is path-confined outside the public document root, uses the existing FPDF dependency, removes partial files after failure, and never logs verification tokens.

## Module 5: Public Blog and Administrator Publishing

The missing Blog surface from the original public frontend is added as a fixed CMS content type.

Public routes:

- `GET /blog`
- `GET /blog/{slug}`

Administrator routes provide a bounded list, create, edit, preview, publish/unpublish, and soft-delete actions. All writes are POST plus CSRF and require `role:super-admin`.

Posts use plain text with paragraph rendering, not arbitrary HTML. Slugs are normalized and unique. Publication uses compare-and-swap timestamps; identical repeats are truthful. Only published, nondeleted posts appear publicly. Images reuse upload MIME, byte, pixel, random-name, and cleanup protections and are rendered with explicit dimensions and lazy loading below the first viewport.

List and detail pages include canonical URLs, meta title/description, Open Graph data, visible publication dates, category labels, reading-time estimates, empty states, and escaped content. Draft preview stays administrator-only and is marked noindex/private.

## Module 6: Recovery, Report Formats, and Installable PWA

### Event trash recovery

Organizer and administrator trash pages list only soft-deleted events within their scope. A restore is allowed only for events that were eligible for deletion: no registration history and an editable or terminal lifecycle state. It is compare-and-swap protected and audited. Restoring cannot publish an event, restore related deleted files, or bypass organizer approval; it returns the event to its retained lifecycle state.

There is no web-accessible permanent purge. Database backup/restore remains operator-owned.

### Report format completion

Existing report families gain PDF and Excel-compatible SpreadsheetML exports in addition to CSV. They reuse the same role scope, filters, safe columns, exact decimal values, historical aggregate rules, paging, and privacy exclusions as CSV.

- PDF is generated with the installed FPDF dependency and is bounded to a safe row count with explicit continuation guidance.
- Excel output uses standards-based SpreadsheetML XML, treats every cell as data rather than a formula, strips control characters, and is streamed with the correct private download headers.

No export contains participant names/emails, tokens, gateway responses, ticket paths, exact location, or soft-deleted user PII.

### Progressive Web App

The public application gains a same-origin web manifest, brand icons, an offline page, and a small service worker. The implementation follows the current W3C Web Application Manifest and Service Workers specifications:

- https://www.w3.org/TR/appmanifest/
- https://www.w3.org/TR/service-workers/

The service worker precaches only versioned same-origin static assets and the static offline shell. It never stores HTML responses, API responses, authenticated routes, redirects, health responses, ticket/certificate downloads, map tiles, or any request with authorization/cookie-dependent private content. All non-GET requests and navigations remain network-first; a failed navigation receives only the generic offline page.

Old cache versions are deleted on activation. Registration is external JavaScript compatible with the existing CSP, and lack of Service Worker support leaves the application fully usable. The manifest uses an explicit `/` scope and stable start URL with no tracking parameter.

## Security, Privacy, and Failure Rules

- Every state-changing web route uses POST, CSRF, role middleware, positive identifiers, and scalar input boundaries.
- Domain repositories own transactions, locks, native prepared SQL, and compare-and-swap behavior.
- Public calendar, API, Blog, certificate verification, and PWA outputs never reveal restricted exact location or participant transaction secrets.
- Public JSON and verification endpoints use bounded rate limits where enumeration or expensive filters are possible.
- Files are random-named, path-confined, and never directly served from a writable private directory.
- Invalid filters return `422`; hidden/foreign resources return `404`; stale opposite-state writes return `409`; CSRF returns `419`.
- Persistence failures are logged with sanitized identifiers only. Notification and mail failures are isolated after commit.
- Empty, offline, stale, concurrent, invalid, unsupported, and unavailable states have explicit accessible output.

## Accessibility and Responsive Behavior

- Calendar days, Blog cards, waitlist position, certificate status, trash actions, and export controls use semantic headings, labels, tables/lists, and live status copy.
- Mobile controls meet the existing 44 pixel target contract and tables use existing `data-label` card transformations.
- The month grid never becomes the only calendar representation.
- Install/offline enhancements do not hide server-rendered content.
- All new pages are verified at 320, 768, and 1440 pixels in light and dark themes for overflow, contrast, focus, labels, error associations, empty states, and console errors.

## Release Verification

Every module follows observed RED, minimal GREEN, refactor, focused verification, and a scoped Git commit.

The final Week 4 gate includes:

1. Full PHP tests, PHP syntax, Composer strict validation/platform/install/audit.
2. npm audit, deterministic CSS/assets build, Node syntax, and all JavaScript harnesses.
3. Fresh schema/seed/demo import twice and populated Week 3 migration twice in unique disposable MySQL databases.
4. Native MySQL concurrency for waitlist promotion, certificate idempotency, Blog publication, event restoration, and calendar/API privacy.
5. Full HTTP journeys for waitlist join/promotion/payment/ticket, certificate issue/download/verification, calendar/API, Blog moderation, trash restore, report formats, and PWA assets.
6. Guest, role, CSRF, IDOR, method, malformed, rate-limit, stale, replay, and failure-injection boundaries.
7. In-app browser checks for calendar, Blog, waitlist, certificates, trash, reports, install metadata, and offline fallback.
8. Secret, private-artifact, public-package, migration-order, license, and generated-asset audits.
9. Independent final review with every Critical and Important finding fixed before push.

## Explicit Non-goals

- Live gateway settlement/refund APIs remain blocked on a real provider contract and credentials; verified manual payment remains supported.
- Browser push, SMS, OAuth calendar account linking, and full English/Bangla translation remain separate integrations requiring content/provider decisions.
- The public API is read-only. OAuth/API-token write access is not introduced.
- The PWA does not promise offline transactions or cache private user content.
- Permanent event purge and database restore remain operator-only.

## Completion Definition

Week 4 is complete when a participant can safely waitlist and be promoted, browse a month calendar or public API, receive and verify an attendance certificate, visitors can read administrator-published Blog posts, authorized users can recover eligible deleted events, reports support CSV/PDF/Excel-compatible downloads, the public shell is installable without caching private data, all release gates pass, each slice has a Git commit, and verified project-only commits are pushed to public `main`.
