# OEMS

OEMS is a custom PHP MVC online event management platform. Week 2 adds database-backed event discovery, organizer-owned venues and events, secure event media, administrator moderation, and real event metrics on the role dashboards.

## Requirements

- PHP 8.2 or newer with PDO MySQL, mbstring, and OpenSSL
- MySQL 8.0 or newer
- Composer 2
- Node.js 20 or newer

## Local setup

1. Install the PHP and frontend dependencies.

   ```bash
   composer install
   npm install
   ```

2. Create the local environment file and update its database credentials.

   ```bash
   cp .env.example .env
   ```

3. Configure SMTP in `.env`. Mailtrap Sandbox is suitable for local development because it captures messages instead of delivering them to real inboxes.

   ```dotenv
   MAIL_HOST=sandbox.smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=your-mailtrap-username
   MAIL_PASSWORD=your-mailtrap-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=no-reply@oems.local
   MAIL_FROM_NAME=OEMS
   MAIL_PRIVACY_SINK_ADDRESS=security@example.com
   ```

   Keep SMTP credentials in `.env` only. Never commit them to the repository. The privacy sink receives reset-shaped probe messages for unknown addresses, keeping the public response behavior uniform without emailing the submitted unknown address.

4. Create a MySQL database, then import the schema and development seed data.

   ```bash
   mysql -u root -p -e "CREATE DATABASE oems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p oems < database/schema.sql
   mysql -u root -p oems < database/seed.sql
   ```

   To add the optional local demo dataset, import the repeatable demo seed after the base seed. The demo seed uses keyed upserts and guarded inserts, so the same command can be run again without duplicating its accounts, events, schedules, or gallery references.

   ```bash
   mysql -u root -p oems < database/demo_seed.sql
   ```

5. Build the Tailwind stylesheet.

   ```bash
   npm run build:css
   ```

6. Start the local server and visit `http://localhost:8000`.

   ```bash
   php -S 127.0.0.1:8000 -t public public/router.php
   ```

Registration and password-reset messages are sent through the configured SMTP transport. With `APP_DEBUG=true`, the screens also expose local development links so account flows can be tested without opening the mail sandbox.

In production, set `APP_URL` to the externally reachable HTTPS origin so account email links open the correct secure site.

The application does not require `pnpm dev`. Run `npm run watch:css` only while editing Tailwind styles; the PHP server handles application requests.

## Development administrator

- Email: `admin@oems.local`
- Password: `ChangeMe!2026`

Change this password immediately outside isolated local development.

## Demo accounts

The optional `database/demo_seed.sql` file adds realistic local-only organizers, participants, venues, lifecycle events, schedules, and event media references. It also contains full-schema reference rows for later milestones; ticket rows intentionally keep their nullable QR and PDF paths empty until Week 3 generates real files. The current product does not expose registration, payment, revenue, or ticket workflows until Week 3. Every non-administrator demo account uses the password `DemoPass!2026`.

- Super administrator: `admin@oems.local` / `ChangeMe!2026`
- Approved organizer: `ayesha.organizer@oems.local` / `DemoPass!2026`
- Approved organizer: `farhan.organizer@oems.local` / `DemoPass!2026`
- Pending organizer: `nusrat.organizer@oems.local` / `DemoPass!2026`
- Participant: `tahmid.participant@oems.local` / `DemoPass!2026`

Never import the demo dataset into a production database.

## Week 2 workflows

Public discovery is available without authentication:

- `GET /` shows repository-backed featured events.
- `GET /events` supports text, category, city, date, price, and allow-listed sort filters.
- `GET /events/{slug}` shows a published event with canonical, Open Graph, and JSON-LD metadata.

An organizer uses these authenticated routes:

- `/organizer/dashboard` shows real event totals and recent next actions.
- `/organizer/events` lists owned events; `/organizer/events/create` creates a draft.
- `/organizer/events/{id}` shows an owned event; `/organizer/events/{id}/edit` updates an editable event.
- The event detail actions submit, cancel, or soft-delete an eligible owned event through CSRF-protected POST requests.
- `/organizer/venues` manages owned venues. A venue referenced by a live event cannot be deleted.

A super administrator uses these authenticated routes:

- `/admin/dashboard` shows platform totals and the real pending-review count.
- `/admin/events` opens the moderation queue; `/admin/events/{id}` shows the evidence and lifecycle actions.
- `/admin/categories` creates, edits, activates, and deactivates event categories.

Every organizer mutation is scoped to the authenticated organizer. Every organizer and administrator POST action requires a valid CSRF token.

### Event lifecycle

Organizer actions:

- `draft` or `rejected` → `pending` by submitting for review. Only an approved organizer account may submit.
- `approved` or `published` → `cancelled`.
- `draft`, `rejected`, or `cancelled` → soft-deleted.

Administrator actions:

- `pending` → `approved` or `rejected`; rejection requires a reason.
- `approved` → `published` or `cancelled`.
- `published` → `completed` or `cancelled`.

Only non-deleted `published` events appear in public discovery.

### Event images

- Banner and gallery uploads accept JPEG, PNG, or WebP images only.
- Each uploaded image must be no larger than 5 MB; a gallery accepts at most six images.
- Files are validated from their actual bytes and dimensions, renamed randomly, and stored under `public/uploads/events`.
- The repeatable demo seed references the committed `/assets/images/hero-events.webp`, `/assets/images/event-creative.webp`, and `/assets/images/event-community.webp` files; it does not depend on untracked local uploads.

## Week 2 deliverables

- Prepared, ownership-scoped repositories for categories, venues, events, public filters, moderation, galleries, and dashboard summaries
- Organizer venue and event create, edit, preview, submit, cancel, and eligible soft-delete workflows
- Administrator category management and event approve, reject, publish, complete, and cancel workflows
- Secure banner/gallery validation and storage with bounded uploads and safe cleanup
- Database-backed home, event search, event details, canonical metadata, Open Graph data, and JSON-LD
- Repository-backed organizer and administrator dashboards with enabled workflow actions
- Role guards, CSRF protection, escaped views, prepared queries, lifecycle validation, and automated regression coverage

Registration, checkout, payments, revenue reporting, QR tickets, and attendance begin in Week 3. Week 2 screens intentionally do not calculate registration or revenue totals and do not expose active registration controls.

## Week 1 deliverables

- Dependency-injected custom MVC core with routing, middleware, sessions, validation, views, configuration, database transactions, logging, and error responses
- MySQL schema for accounts, permissions, organizers, events, schedules, registrations, payments, tickets, attendance, notifications, reviews, CMS, reporting support, and audit data
- Participant and organizer registration, SMTP email verification, login, logout, remember-me sessions, password reset, and password change
- Authenticated profile management for contact details, personal details, address, locale, and timezone
- CSRF protection, prepared database statements, escaped views, session rotation, password hashing, token hashing, and file-backed login and password-reset throttling
- Role guards and separate participant, organizer, and super-admin dashboard shells
- Responsive public, authentication, events, and dashboard interfaces with light and dark themes
- Self-hosted Manrope font and original generated event imagery

The Week 1 deliverables remain the foundation for the Week 2 workflows above.

## Quality checks

```bash
php tests/run.php DashboardLayoutTest
composer test
composer check:syntax
composer validate --strict
composer audit
npm run build:css
git diff --check
```

## Structure

```text
Core/                   Framework primitives
app/                    Controllers, services, repositories, middleware, and views
bootstrap/app.php       Container and application bootstrap
config/                 Application and database configuration
database/               Schema and seed SQL
public/                 Web root and compiled assets
resources/              Tailwind source stylesheet
routes/web.php          Route definitions
storage/                Runtime cache and log files
tests/                  Custom PHP unit test suite
```

The Week 1 and Week 2 design and implementation records are in `docs/superpowers/`.

## License

OEMS is open-source software licensed under the [MIT License](LICENSE).
