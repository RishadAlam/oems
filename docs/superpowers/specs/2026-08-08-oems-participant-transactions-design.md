# OEMS Participant Transactions Design

## Goal

Complete the remaining end-to-end event milestone so an organizer can publish an approved event, an administrator can approve and oversee it, and participants can discover, register for, pay for, favorite, attend, and review events.

## Milestone Definition

This milestone connects the event-management foundation to the existing registration, payment, ticket, attendance, notification, favorite, and review tables. It includes:

- Organizer publication of approved events
- Participant registration and checkout
- Free and manually verified paid registrations
- Secure ticket issuance, QR check-in, and PDF downloads
- Favorites and participant event lists
- Reviews, organizer replies, and administrator moderation
- Registration, payment, ticket, notification, and attendance dashboards
- Organizer participant export and check-in operations
- Administrator payment and review oversight

Unrelated later product areas such as CMS pages, backup automation, PWA installation, and third-party gateway settlement are outside this milestone.

## Existing System Audit

OEMS already provides a custom PHP MVC core, role and CSRF middleware, verified accounts, profile management, organizer approval, event and venue management, administrator event moderation, public event discovery, MySQL tables for the full transaction domain, SMTP delivery, a responsive Tailwind interface, and demo data. The transaction tables are currently seeded as future examples but are not connected to HTTP workflows.

The implementation must preserve:

- Raw PHP MVC, PDO repositories, thin controllers, service-owned business rules, and dependency injection
- The existing cobalt, cool-neutral, Manrope, and Phosphor design language
- Light and dark theme parity, keyboard focus, reduced-motion behavior, and responsive layouts
- Existing event slugs, role boundaries, flash messages, prepared SQL, output escaping, and soft-delete rules
- Local environment secrets and unrelated untracked workspace artifacts

## Design Read

This is the trust and fulfillment phase of the product. A participant needs to understand the total price, payment state, ticket state, and next action at a glance. Organizers and administrators need dense operational views without ambiguous status changes.

- `DESIGN_VARIANCE: 5` for a product-specific but predictable layout
- `MOTION_INTENSITY: 3` for restrained feedback and state transitions
- `VISUAL_DENSITY: 6` for operational lists with comfortable touch targets
- Design system: the existing OEMS Tailwind v4 product system
- Redesign mode: extend the current system instead of introducing a second visual language

## Approaches Considered

### External gateway first

Stripe or another provider would give hosted card collection, but a real integration requires an account, credentials, webhook registration, and a deployment URL. Shipping a gateway-shaped form without those pieces would falsely imply money was processed.

### Instant demo payment

Marking every paid registration as successful would be fast, but it would violate payment integrity and make administrator oversight meaningless.

### Free plus manually verified settlement

This is the selected approach. Free registrations confirm and issue tickets immediately. Paid registrations collect a bounded payment reference and enter a pending verification queue. An administrator verifies or rejects the payment. Verification confirms the registration and issues the ticket atomically. This produces a complete, honest payment workflow now and leaves a clean provider boundary for a later hosted gateway.

## Architecture

### Domain boundaries

- `RegistrationRepository` owns registration records, capacity-safe reservation, status changes, participant lists, and dashboard aggregates.
- `PaymentRepository` owns payment attempts, transaction-reference uniqueness, verification queues, and settlement state.
- `TicketRepository` owns issued tickets, participant ticket lookup, QR-token resolution, and check-in state.
- `FavoriteRepository` owns participant-event favorite state and favorite discovery lists.
- `ReviewRepository` owns eligible participant reviews, public review aggregates, organizer replies, and moderation queues.
- `NotificationRepository` owns in-app notification creation, unread counts, lists, and read state.
- `RegistrationService` coordinates eligibility, locking, seat reservation, payment creation, confirmation, cancellation, and ticket issuance.
- `TicketService` generates opaque check-in tokens, QR assets, PDF tickets, secure downloads, and duplicate-safe check-in.
- `ReviewService` enforces attendance or confirmed-registration eligibility, event completion, rating bounds, ownership, and moderation transitions.
- `ParticipantController`, organizer operations controllers, and administrator transaction controllers expose the workflows through role-scoped routes.

Controllers adapt HTTP input. Services own validation and transactions. Repositories own prepared SQL. Views never query the database.

## Event Publication

The administrator keeps approval and rejection authority. Once an event is approved, its owning approved organizer can publish it. Publication uses a compare-and-set update so only `approved` can become `published`. The administrator retains emergency publish, cancel, complete, and moderation operations.

Publishing requires a valid future schedule, registration deadline, active category, and approved organizer at the moment of transition. Public discovery continues to show only published, non-deleted events in active categories.

## Registration and Capacity Rules

- Only authenticated participants can register.
- The event must be published, not deleted, in an active category, before its registration deadline and start time.
- The server determines ticket price, currency, and event identity. Client-submitted totals are ignored.
- A registration reserves one seat while payment is pending or confirmed.
- Seat reservation occurs in a transaction with a locked event and an atomic capacity check.
- The unique event-user relationship prevents duplicate active registrations.
- A cancelled or failed registration can be safely reactivated through the existing row rather than creating a duplicate.
- Cancellation and payment rejection release a seat exactly once.
- A participant cannot cancel after event start or after check-in.
- Organizer and administrator counts exclude cancelled and failed registrations where appropriate.

## Payment Workflow

### Free registration

1. Validate participant and event eligibility.
2. Reserve a seat.
3. Create or reactivate a confirmed registration.
4. Record a zero-value successful payment using the active free-registration method.
5. Issue a ticket.
6. Create an in-app notification and send a confirmation email.

### Paid registration

1. Present the server-calculated amount and active manual-payment instructions.
2. Collect a payment channel and a unique, bounded transaction reference. No card secrets are collected or stored.
3. Reserve a seat and create or reactivate a pending registration.
4. Create a pending payment record.
5. Show the participant a pending-verification state.
6. An administrator verifies or rejects the payment with an optional internal note.
7. Verification atomically marks the payment paid, confirms the registration, and issues a ticket.
8. Rejection marks the payment failed, cancels the registration, and releases the seat.

Every transition is idempotent and uses an eligible-status compare-and-set guard. Payment settlement retains the reviewing administrator in `reviewed_by`, its timestamp in `reviewed_at`, and an optional bounded `review_note` for audit-friendly oversight. Payment metadata stores bounded JSON without credentials or sensitive account data.

## Ticket and Attendance Security

- Ticket numbers use an unpredictable unique suffix and a readable OEMS prefix.
- Each QR contains an opaque random bearer token, never a sequential database identifier or user secret.
- Only the SHA-256 token digest is stored in `tickets.qr_payload_hash`.
- QR images and PDF tickets are generated locally and stored under `public/uploads/tickets` with randomized filenames.
- File paths are normalized and confined to the ticket upload directory before download or deletion.
- Participant download routes resolve ownership in SQL and stream the expected MIME type with safe headers.
- Organizer check-in is limited to tickets for events owned by the authenticated organizer.
- A valid scan records attendance once. Repeat scans show the original check-in time without duplicating attendance.
- Cancelled, refunded, failed, or void tickets cannot be checked in.
- Manual ticket-code entry remains available when a camera is unavailable.

QR and PDF generation use PHP 8.2-compatible Composer packages. Assets are generated only after the database state is eligible, and partial files are cleaned up when a transaction fails.

## Favorites

- Participant-only, CSRF-protected POST actions add or remove a published event favorite.
- Favorite state is derived from the authenticated user, not a request-supplied user identifier.
- Event cards and details expose one accessible favorite control with clear saved and unsaved states.
- The participant workspace includes a paginated favorite-event list and an honest empty state.
- Unavailable events remain identifiable in a participant's history but cannot be newly favorited.

## Reviews and Replies

- A participant may create one review per event after the event is completed or its end time has passed.
- Eligibility requires a confirmed registration. Checked-in attendance is displayed as a verified-attendee signal when present.
- Ratings are integers from 1 through 5. Comment text is trimmed, bounded, and escaped.
- New and edited reviews enter `pending` moderation.
- Administrators can publish or hide reviews with compare-and-set transitions.
- Public event details show only published reviews, their aggregate rating, and escaped organizer replies.
- Only the owning organizer can reply to a review for their event. A reply creates a participant notification.
- Editing a published review returns it to pending moderation.

## Notifications and Email

In-app notifications are created for registration submission, payment verification or rejection, ticket issuance, participant cancellation, event cancellation, review moderation, and organizer replies. Participants can list notifications, mark one or all as read, and see a real unread count.

Existing SMTP delivery sends participant-facing registration, payment, and ticket messages. Mail failures are logged with sanitized context and do not corrupt the committed transaction. No SMTP credentials or raw gateway metadata appear in logs or responses.

## Participant Experience

The public event detail replaces the Week 3 placeholder with a context-aware action:

- `Register free` for eligible free events
- `Register and pay` for eligible paid events
- `View registration` when already registered
- `Registration closed`, `Sold out`, or `Event ended` when unavailable

The checkout is a short summary-first form: event, schedule, one ticket, amount, payment method, reference, and final action. The participant dashboard shows upcoming registrations, payment state, available tickets, saved events, reviews awaiting action, and unread notifications. Dedicated registration and ticket pages provide cancellation, QR display, and PDF download without hiding status explanations.

All controls retain visible labels, helper and error associations, 44-pixel touch targets, keyboard focus, mobile stacking, and light/dark contrast. Loading, empty, success, pending, rejected, cancelled, and error states receive distinct copy and icon treatment.

## Organizer Operations

The organizer workspace adds:

- Publish action for approved events
- Participant counts by registration and payment status
- Participant list with search and status filters
- CSV export with safe spreadsheet values
- Ticket lookup, manual code entry, QR scan support, and attendance history
- Registration, gross-paid, ticket-issued, and checked-in dashboard metrics
- Review list with ownership-scoped reply forms

Organizer views never expose another organizer's participants, transaction metadata, ticket tokens, or reviews.

## Administrator Operations

The administrator workspace adds:

- Pending payment verification queue and full payment history
- Verify and reject actions with CSRF, status guards, and audit-friendly notes
- Review moderation queue with publish and hide actions
- Registration, paid amount, refund, ticket, attendance, and review dashboard metrics
- Scoped detail views that connect participant, event, organizer, registration, and payment state

Administrators never see or collect card data because manual settlement stores only a payment channel and external reference.

## Routes

Representative additions:

- `POST /organizer/events/{id}/publish`
- `GET|POST /events/{slug}/register`
- `GET /participant/registrations`
- `GET /participant/registrations/{id}`
- `POST /participant/registrations/{id}/cancel`
- `GET /participant/tickets`
- `GET /participant/tickets/{id}`
- `GET /participant/tickets/{id}/qr`
- `GET /participant/tickets/{id}/pdf`
- `POST /events/{id}/favorite`
- `DELETE /events/{id}/favorite`
- `GET|POST /participant/reviews/{eventId}`
- `GET /participant/notifications`
- `POST /participant/notifications/{id}/read`
- `POST /participant/notifications/read-all`
- `GET /organizer/events/{id}/participants`
- `GET /organizer/events/{id}/participants.csv`
- `GET|POST /organizer/check-in`
- `POST /organizer/reviews/{id}/reply`
- `GET /admin/payments`
- `POST /admin/payments/{id}/verify`
- `POST /admin/payments/{id}/reject`
- `GET /admin/reviews`
- `POST /admin/reviews/{id}/publish`
- `POST /admin/reviews/{id}/hide`

Route identifiers and slugs are validated before use. State-changing routes use role and CSRF middleware. Unsupported methods return 405.

## Error Handling and Security

- All authorization-sensitive reads include participant, organizer, or administrator scope in SQL.
- Ownership failures return 404 where disclosure would create an IDOR signal.
- Eligibility and state-transition conflicts return a clear flash message without partial mutation.
- Registration, payment verification, ticket issuance, cancellation, and check-in use database transactions.
- SQL values are parameterized and sort clauses are allow-listed.
- Monetary values use decimal strings and integer-safe comparison, never client floats.
- Uploaded and generated paths are randomized, normalized, and directory-confined.
- CSV exports prefix formula-leading cells and set safe download headers.
- Logs use event identifiers and exception classes, not secrets, payment references, tokens, SQL, or filesystem details.
- Rate limiting protects registration, payment submission, review submission, and check-in attempts.
- Output, metadata, JSON-LD, email HTML, and PDF text are escaped for their target context.

## Demo Data and Documentation

Demo data keeps deterministic participants, registrations, payments, tickets, attendance, favorites, notifications, and reviews. Generated ticket assets are not committed; repeatable seed rows keep nullable asset paths until an application workflow generates them. The manual payment method is active in demo mode with clearly fictional instructions and references.

The README documents setup, demo accounts, SMTP behavior, payment limitations, participant and operations routes, ticket storage, and the exact acceptance workflow. No environment values or generated private ticket assets are committed.

## Testing Strategy

- Repository integration tests cover capacity, uniqueness, ownership, state changes, queues, aggregates, favorites, reviews, notifications, and check-in.
- Service tests cover eligibility, server-calculated totals, free and paid workflows, idempotency, rollback, ticket generation, cancellation, payment verification, moderation, and sanitized failures.
- Controller and route tests cover guest, participant, organizer, administrator, CSRF, IDOR, invalid input, status conflicts, downloads, CSV, and unsupported methods.
- View tests cover checkout summaries, status language, favorite controls, review forms, QR and PDF links, notifications, queues, accessible associations, escaping, responsive structure, and all empty/error states.
- Native MySQL rollback tests prove locking and affected-row behavior under realistic PDO settings.
- Acceptance QA covers the complete organizer-to-admin-to-participant-to-check-in-to-review journey.
- Full syntax, Composer strict validation, platform requirements, dependency audit, Tailwind build, JavaScript syntax, `git diff --check`, light/dark responsive browser checks, keyboard operation, contrast, console diagnostics, downloads, and SMTP-safe behavior complete the release gate.

## Completion Criteria

The milestone is complete when:

- An approved organizer can publish an approved event.
- An administrator can approve the event and oversee its payments and reviews.
- A participant can find and favorite a published event.
- A participant can register for a free or paid event without overselling capacity.
- Paid registration remains pending until administrator verification; free registration confirms immediately.
- Confirmed participants receive a secure ticket with QR and PDF access.
- The owning organizer can export participants and check in a valid ticket once.
- An eligible participant can submit a review, an administrator can publish it, and the organizer can reply.
- Participant, organizer, and administrator dashboards use real transaction data.
- Notifications, email attempts, demo data, README instructions, automated tests, live MySQL checks, and responsive browser QA all pass.
- Each implementation slice is committed separately, only project files are pushed, and the public repository contains no secrets.
