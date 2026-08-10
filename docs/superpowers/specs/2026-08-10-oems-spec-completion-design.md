# OEMS Original Specification Completion Design

Date: 2026-08-10

## Objective

Complete the remaining capabilities named in the original OEMS specification without replacing the working event, registration, payment, review, notification, or live-location flows.

The completed scope adds:

- Super-admin user and organizer management.
- Safe super-admin event deletion.
- Organizer announcements to eligible participants.
- Organizer and super-admin analytics and reports.
- Database-backed public settings and CMS pages.
- A repair for the ticket artifact migration guard regression.
- Deployment readiness checks that can run without production credentials.

Manual payment verification remains the supported payment workflow. A live payment gateway is not added because it needs a selected provider, merchant account, webhook secret, settlement rules, and production credentials. The existing verified manual-payment workflow fulfills the specification's payment-management requirement.

## Product and Design Direction

This is a preservation redesign of a trust-first event platform. Existing URLs, brand, role workspaces, public discovery, light and dark themes, semantic form patterns, and accessibility behavior remain stable.

The workspace design profile is:

- Design variance: 4. Clear hierarchy with limited asymmetry.
- Motion intensity: 2. State feedback only, with no decorative motion.
- Visual density: 7. Compact operations pages with readable tables and grouped controls.
- System: existing Tailwind CSS, Manrope, Phosphor icons, cobalt accent, cool-neutral surfaces, 12/18/24 radius system.
- Breakpoints: explicit single-column behavior below 768px and table labels on narrow screens.

No new frontend component framework is introduced. The existing system already provides the correct visual and accessibility foundation. New pages reuse its buttons, fields, focus ring, status badges, empty states, sidebar behavior, and dark-mode tokens.

## Module 1: Release Foundation and Migration Repair

### Ticket migration repair

`TicketArtifactService::migrateLegacyArtifacts()` must migrate generated PNG and PDF artifacts only. It must preserve `.htaccess` and `.gitkeep` control files in the legacy public directory. Unsupported hidden/control files are ignored only through an explicit allowlist; unsafe symlinks and unknown ordinary files continue to fail closed.

The repair is proven by running the migration twice and asserting that:

- Generated artifacts move to private storage once.
- `.htaccess` remains in public storage.
- A second run reports zero migrated artifacts.
- The legacy URL remains denied.

### Database additions

Add an `announcements` table with:

- Organizer-owned event reference.
- Author user reference.
- Bounded subject and plain-text message.
- Audience fixed to confirmed participants for this release.
- Recipient and delivery counters.
- Immutable sent timestamp and created timestamp.
- Indexes for organizer event history.

Existing `settings`, `pages`, `faqs`, `banners`, and `activity_logs` tables are reused. No duplicate CMS schema is added.

The forward migration is resumable and repeatable. It upgrades a populated database and preserves existing event and transaction data.

## Module 2: Admin People and Event Management

### Users

Super admins can list, search, and filter users by role and status. The result set is bounded, paginated, and deterministic.

Allowed actions:

- Set active, inactive, or suspended status.
- Restore a soft-deleted participant or organizer account.
- Soft-delete an eligible participant or organizer account.

Rules:

- A super admin cannot change or delete their own account.
- Super-admin accounts cannot be modified through this workspace.
- Soft deletion removes public identity exposure and revokes remember sessions and password resets.
- Historical transaction rows remain intact.
- Status and deletion updates use compare-and-swap conditions and an audit record.
- Search is bounded and never falls back to an unfiltered query when invalid.

### Organizers

Super admins can list and filter organizer applications, inspect organization details, approve an eligible pending/rejected application, or reject a pending/approved application with a bounded reason.

Rules:

- Approval requires an active, verified, non-deleted organizer user.
- Rejection blocks new event submission and publication through existing service guards.
- Repeated identical actions are truthful and idempotent.
- Approval and rejection write reviewer, timestamp, reason, audit log, and a post-commit notification.

### Admin event deletion

Deletion is a soft-delete operation. It is permitted only for draft, rejected, cancelled, or completed events. Published, approved, and pending events must first use their lifecycle transition so participants receive the correct settlement and cancellation behavior.

The operation is compare-and-swap protected, audited, hidden from public discovery, and preserves registrations, payments, tickets, reviews, and attendance for reporting and participant history.

## Module 3: Organizer Announcements

An approved organizer can send an announcement from an owned event workspace.

Eligibility:

- Event belongs to the organizer and is not deleted.
- Event status is approved, published, completed, or cancelled.
- Recipients are active, verified, non-deleted participants with confirmed, non-cancelled registrations for the event.

Behavior:

- Subject is required and limited to 180 characters.
- Message is required plain text and limited to 2,000 characters.
- A single-use confirmation intent prevents accidental duplicate sends and forged confirmation.
- Recipient resolution and announcement persistence are transactional.
- Notifications and email delivery run after commit so transport failure never rolls back the announcement.
- A stable delivery key prevents duplicate in-app notifications for the same announcement and participant.
- Delivery counters record attempted and successful notification/email outcomes without storing secrets.
- History shows subject, sent time, author, audience, and counts. Message output is escaped.

## Module 4: Analytics and Reports

### Organizer analytics

The organizer analytics page is ownership-scoped and supports an allowlisted date range. It shows:

- Event counts by lifecycle state.
- Confirmed, pending, cancelled, and attended participant counts.
- Gross verified revenue, refunded amount, and net revenue using exact decimal money handling.
- Capacity utilization and attendance rate with zero-safe calculations.
- A per-event breakdown and CSV export.

### Admin analytics and reports

The super-admin analytics page supports the same bounded date range and shows:

- Active users and approved organizers.
- Events by lifecycle state.
- Registration and attendance totals.
- Verified gross revenue, refunds, and net revenue.
- Pending moderation and payment queues.
- Top categories and events based on real stored data.

Reports expose allowlisted report types only: events, registrations, payments, attendance, and organizers. CSV exports neutralize spreadsheet formulas, strip control characters, use UTF-8 BOM, set private no-store headers, and never include password hashes, tokens, gateway responses, QR tokens, private ticket paths, or soft-deleted user PII.

Analytics are server rendered. Small visual summaries use semantic HTML and CSS, with a readable table as the canonical representation. No chart dependency is required.

## Module 5: Platform Settings and CMS

### Settings

The settings repository uses an explicit catalog. Only catalog keys can be read or updated through the admin workspace.

Initial public settings:

- Site name.
- Public support email.
- Public support phone.
- Footer summary.
- Home hero title.
- Home hero summary.
- Default SEO description.

Operational or secret values remain environment-owned. SMTP credentials, application secrets, database credentials, trusted proxies, cookie security, and map-provider policy are not editable from the browser.

Values have type-specific normalization, bounds, safe defaults, and transactional updates. Public pages receive only `is_public = true` catalog values. Invalid database values fall back safely.

### CMS pages

Super admins can list, create, edit, publish, and unpublish pages. Content is stored and rendered as plain text paragraphs for this release. Arbitrary HTML is not accepted, which keeps CSP and sanitization boundaries simple.

Rules:

- Title, slug, content, meta title, and meta description are bounded.
- Slugs are normalized and unique.
- Reserved application route prefixes are rejected.
- Publishing sets `published_at`; unpublishing clears it.
- Public access is `/pages/{slug}` and returns only published pages.
- Admin output and public content are escaped.
- Meta title and description are integrated without changing existing event SEO.

FAQs and banners remain schema-ready but are not exposed as incomplete editors. Pages plus the allowlisted home/footer settings satisfy the original CMS requirement without creating unsafe or unused interfaces.

## Routing and Authorization

All new write routes use POST plus CSRF middleware. All admin routes require `role:super-admin`; organizer announcement and analytics routes require `role:organizer`.

Expected route groups:

- `/admin/users`
- `/admin/organizers`
- `/admin/analytics`
- `/admin/reports`
- `/admin/settings`
- `/admin/pages`
- `/organizer/analytics`
- `/organizer/events/{id}/announcements`
- `/pages/{slug}`

Positive integer identifiers are validated at the controller boundary. Cross-owner organizer resources return 404. Unauthorized role access returns 403. Wrong methods return 405. CSRF failure returns 419.

## Failure Handling

- Domain writes use repository transactions and compare-and-swap conditions.
- Persistence exceptions are logged with sanitized identifiers and return safe user messages.
- Email and notification failures are isolated after commit.
- CSV and public outputs are escaped or neutralized at their boundary.
- Empty, invalid, stale, repeated, and concurrent actions have explicit outcomes.
- New pages include loading-independent server-rendered content, empty states, inline errors, and keyboard-visible focus.

## Verification Strategy

Each module follows RED, GREEN, refactor:

1. Add focused repository/service/controller/UI tests and observe the intended failures.
2. Implement the smallest behavior that satisfies them.
3. Run focused tests, full PHP suite, syntax checks, CSS and JS builds, and diff checks.
4. Commit that module independently.

Final release verification includes:

- Forward migration twice on a populated disposable MySQL database.
- Native MySQL checks for compare-and-swap and aggregate queries.
- Full HTTP role, CSRF, IDOR, method, validation, CSV, and public CMS checks.
- Browser checks at 320, 768, and 1440 pixels in light and dark themes.
- Keyboard, focus, overflow, console, contrast, empty, and error-state checks.
- Composer and npm audit, package drift, secret scan, and tracked-project-file audit.
- Independent code review with all Critical and Important findings fixed before push.

## Out of Scope Without External Production Authority

The codebase can validate and document these boundaries, but cannot create real production infrastructure or third-party accounts:

- TLS certificate and HSTS edge deployment.
- Production SMTP reputation and DNS records.
- Payment-provider merchant onboarding and webhook credentials.
- Provider-specific live settlement and refund APIs.

The release will include actionable environment and health checks for these items without embedding secrets or pretending local credentials are production-ready.
