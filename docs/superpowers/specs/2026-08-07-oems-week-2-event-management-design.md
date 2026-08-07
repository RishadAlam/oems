# OEMS Week 2 Event Management Design

## Goal

Complete the Week 2 milestone so organizers can manage venues and event drafts, submit events for review, administrators can moderate and publish them, and visitors can discover real database-backed events through filters and SEO-friendly detail pages.

## Milestone Definition

Week 2 covers the event-management phase of the OEMS development specification:

- Categories
- Venues
- Organizer event creation, editing, soft deletion, and submission
- Banner and gallery image uploads
- Administrator approval, rejection, publication, completion, and cancellation
- Public event listing, search, filters, sorting, and event details
- Organizer and administrator dashboard integration

Registration, checkout, payments, tickets, QR generation, and attendance remain Week 3 work.

## Existing System Audit

The application already has a custom dependency-injected PHP MVC core, role middleware, CSRF protection, output escaping, authenticated profiles, MySQL schema tables for the full event domain, demo categories, venues, events, and a consistent Tailwind/Manrope/Phosphor interface. Public events are currently hard-coded previews. Organizer event creation is disabled, and the administrator has no moderation workflow.

The implementation must preserve:

- The existing cobalt accent, cool neutral palette, Manrope typography, OEMS mark, and 12/18/24 pixel radius rules
- Light and dark theme parity
- Existing route slugs and primary navigation labels
- Role middleware, CSRF middleware, prepared statements, escaped output, and flash-message conventions
- Existing database table names and event status values
- Untracked local presentation artifacts and environment secrets

## Design Read

This is a trust-first event-management product for organizers, administrators, and attendees. The public experience should feel editorial and clear, while workspace screens should be information-dense without becoming a spreadsheet.

- `DESIGN_VARIANCE: 5` for offset but predictable product layouts
- `MOTION_INTENSITY: 3` for tactile states and restrained reveal behavior
- `VISUAL_DENSITY: 6` for useful event operations without cramped controls
- Design system: the existing custom Tailwind v4 OEMS product system
- Redesign mode: targeted evolution, preserving brand, IA, routes, and accessibility

## Approaches Considered

### Single event controller and repository

This would be quick initially, but category, venue, upload, lifecycle, public query, and moderation behavior would become coupled. It would make ownership rules and tests difficult to isolate.

### Generic CRUD engine

A metadata-driven admin engine could cover categories, venues, and events, but it adds abstraction before the workflows are stable. Event lifecycle and file handling are too domain-specific for a useful generic layer in Week 2.

### Focused domain services and repositories

This is the selected approach. Category, venue, event persistence, upload validation, and lifecycle decisions receive clear boundaries. Controllers stay thin, repositories own prepared SQL, and services own validation, normalization, authorization-sensitive business rules, and transitions.

## Architecture

### Domain boundaries

- `CategoryRepository` manages active public categories and administrator category maintenance.
- `VenueRepository` manages venues owned by the authenticated organizer.
- `EventRepository` manages public discovery queries, organizer-owned event persistence, administrator queues, and event status updates.
- `EventService` validates event input, normalizes tags and prices, creates collision-safe slugs, enforces ownership, and controls allowed lifecycle transitions.
- `ImageUploadService` validates upload errors, size, MIME type, actual image dimensions, and stores randomized files only under `public/uploads/events`.
- Public, organizer, and administrator controllers adapt HTTP requests to these services and render role-appropriate views.

### Dependency flow

Controllers depend on services and read repositories. Services depend on repository interfaces and the upload service. Repositories depend on PDO. Views receive presentation-ready arrays and never perform queries.

## Data and Ownership Rules

- Categories use the existing `categories` table. Public queries include active categories only. Administrators can create, edit, and activate or deactivate categories.
- Venues use the existing `venues` table. Organizer routes resolve the organizer from the authenticated user and may only read or mutate that organizer's venues.
- Events use the existing `events` table and always enforce organizer ownership for organizer routes.
- Gallery images use the existing `event_gallery` table and inherit event ownership.
- Event deletion is a soft delete through `deleted_at`. Organizer deletion is limited to draft, rejected, or cancelled events.
- Published public queries exclude soft-deleted records and only return `published` events.
- Existing registrations are never modified during Week 2 event operations.

## Event Lifecycle

Allowed organizer transitions:

- `draft` to `pending`
- `rejected` to `pending` after edits
- `approved` or `published` to `cancelled`

Allowed administrator transitions:

- `pending` to `approved`
- `pending` to `rejected`, with a required reason
- `approved` to `published`
- `published` to `completed`
- `approved` or `published` to `cancelled`

Only approved organizer accounts may submit events. Pending organizer accounts may create and edit drafts. Published events are visible publicly. Rejected events expose their rejection reason only to the owning organizer and administrators.

## Validation

Event validation requires:

- Title: 5-180 characters
- Description: 30-20000 characters
- Active category and organizer-owned venue
- Start and end values in `Y-m-d\TH:i` format, with end after start
- Registration deadline before the start
- Capacity from 1-100000 and no greater than the selected venue capacity when one is set
- Ticket price from 0-9999999.99 in BDT
- Speaker up to 190 characters
- Tags normalized from comma-separated input into at most 12 unique tags, each no longer than 40 characters
- Optional map URL up to 500 characters

Category names, venue values, moderation reasons, and uploaded images receive equivalent bounded validation. Invalid input redirects back with field-level errors and safe old input.

## Upload Security

- Accept JPEG, PNG, and WebP only.
- Limit each image to 5 MB.
- Verify the uploaded file error, `is_uploaded_file` in HTTP operation, Fileinfo MIME, and `getimagesize` output.
- Generate filenames from cryptographically random bytes and an allow-listed extension.
- Store paths relative to the public web root.
- Never trust the original filename or client MIME type.
- Delete replaced files only after the database mutation succeeds, and only when the resolved path remains inside `public/uploads/events`.
- Limit each event gallery to six images.

## Public Discovery

The hard-coded preview source is replaced with database queries. `/events` supports:

- Text search across title, description, speaker, organizer, category, venue, and city
- Category
- City
- Date: upcoming, today, this week, this month
- Price: free or paid
- Sort: soonest, latest, price low to high, price high to low

All filters use prepared parameters and allow-listed sort clauses. Filter state remains visible in the form. Cards link to `/events/{slug}`. Empty states explain how to broaden the search.

`/events/{slug}` includes banner, category, schedule facts, venue, organizer, price, remaining capacity, tags, gallery, structured event metadata, canonical URL, and a clear Week 3 registration placeholder that does not imply checkout is active.

The home page shows up to two database-backed featured published events, then upcoming published events as fallback.

## Organizer Experience

The organizer sidebar gains Events and Venues links. The overview shows real counts and recent events. The event index uses a compact status-aware list with filters and explicit actions. Create and edit forms use named sections:

- Event basics
- Schedule and registration
- Place and capacity
- Pricing and discovery
- Media

Every field has a visible label, helper text when needed, an inline error location, and keyboard-visible focus. The forms collapse to one column on mobile. The event detail workspace shows lifecycle actions separately from editing to prevent accidental status changes.

## Administrator Experience

The administrator sidebar gains Event review and Categories links. The review index shows pending first and supports status filtering. Event review displays ownership, schedule, capacity, pricing, media, and the current lifecycle. Rejection uses a dedicated reason field. Category management provides create, edit, and activate/deactivate controls without deleting referenced categories.

## Security and Error Handling

- Organizer and administrator mutation routes use role and CSRF middleware.
- Ownership is checked in SQL and again in service outcomes.
- Route identifiers are validated as positive integers; public slugs use a restricted pattern.
- Invalid ownership returns 404 to avoid disclosing another organizer's records.
- Invalid transitions return a clear flash error and leave state unchanged.
- Database writes that combine events and gallery rows use transactions.
- Upload and database failures are logged without exposing filesystem paths or SQL details.

## Testing Strategy

- SQLite repository integration tests cover prepared filters, ownership constraints, create/update, soft delete, dashboard counts, categories, venues, public detail, and moderation queries.
- Service tests cover normalization, validation, slug collision handling, organizer approval, lifecycle transitions, gallery limits, and upload rejection.
- Controller tests cover rendered states, redirect targets, safe old input, missing records, and flash outcomes.
- Route security tests cover guest, participant, organizer, cross-organizer, CSRF, and administrator access.
- View contract tests cover accessible labels, semantic dates and addresses, status names, empty states, responsive structure, and no unavailable registration action.
- Full PHP syntax, Composer validation/audit, JavaScript syntax, Tailwind build, live route probes, light/dark responsive browser QA, and `git diff --check` complete the milestone.

## Completion Criteria

Week 2 is complete when:

- Categories and organizer-owned venues are maintainable.
- Organizers can create, edit, preview, submit, cancel, and safely delete eligible events.
- Banner and gallery uploads are validated and stored securely.
- Administrators can approve, reject, publish, complete, and cancel events.
- Published events come from MySQL on the home page, listing, filters, and SEO detail route.
- Organizer and administrator dashboards expose real event workflows and metrics.
- Role, CSRF, ownership, validation, escaping, upload, and transition tests pass.
- The public repository contains only project files and no environment secrets.

