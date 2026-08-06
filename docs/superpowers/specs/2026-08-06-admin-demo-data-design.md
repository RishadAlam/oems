# Admin Dashboard Demo Data Design

## Goal

Replace the Week 1 placeholder panels with a cleaner admin overview backed by realistic, repeatable local-development data.

## Dashboard UI

- Remove the entire `Foundation readiness` section from the super-admin dashboard.
- Remove the entire `Next delivery` section from the super-admin dashboard.
- End the admin overview after the live metric cards, without adding another placeholder panel.
- Preserve the current dashboard navigation, visual tokens, responsive behavior, dark theme, and accessibility behavior.
- Replace the hardcoded Users, Organizers, and Events values with database totals.
- Count non-deleted users, all organizer profiles, and non-deleted events.

## Application Architecture

- Add a focused dashboard repository responsible only for retrieving the three platform totals.
- Inject that repository into `DashboardController` through the existing dependency container.
- Pass a named `metrics` array to the admin view with integer `users`, `organizers`, and `events` values.
- Keep SQL and database access out of the view.

## Demo Dataset

Add `database/demo_seed.sql` as an optional development-only seed that runs after `database/seed.sql`.

The dataset will contain:

- 3 organizer accounts and matching organizer profiles.
- 8 participant accounts and profiles.
- 3 venues in Dhaka.
- 6 events distributed across published, pending, draft, and completed states.
- Event schedules plus representative registrations, payments, tickets, favorites, notifications, and published reviews.

The existing `admin@oems.local` administrator remains unchanged. Demo accounts use documented local-only credentials.

## Repeatability and Safety

- Use stable unique emails, event slugs, registration numbers, transaction references, and ticket numbers.
- Use upserts or guarded inserts so rerunning the file updates or preserves existing demo records instead of duplicating them.
- Resolve foreign keys by stable slugs and emails rather than assuming auto-incremented identifiers.
- Wrap the seed in a transaction so a failed import does not leave a partially loaded dataset.
- Keep demo records out of the base `database/seed.sql` so required platform data remains suitable for a clean installation.

## Documentation

Update the local setup guide with the optional demo seed command, demo login details, and a warning that the records are for local development only.

## Testing and Verification

- Add a repository test that proves the three totals are returned with integer values.
- Update the rendered-dashboard test to prove supplied values appear in the metrics and that both removed sections are absent.
- Verify the demo seed imports twice without duplicate rows.
- Verify expected database counts and foreign-key integrity after import.
- Run all PHP tests, PHP syntax checks, Composer validation, and the Tailwind production build.
- Inspect the authenticated admin dashboard at desktop and mobile widths, including mobile navigation and a clean browser console.

## Commit Boundaries

1. Commit the approved design specification.
2. Commit the dashboard placeholder removal.
3. Commit the live dashboard metrics, repeatable demo seed, and setup documentation.
