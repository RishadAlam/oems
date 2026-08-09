# OEMS

OEMS is a custom PHP MVC online event management platform for public discovery, participant registration, manual payment review, QR ticketing, attendance, reviews, notifications, and organizer and administrator operations.

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

## Database upgrades

`database/schema.sql` is the canonical schema for a fresh installation. For a new database, import `schema.sql`, then `seed.sql`, and finally the optional `demo_seed.sql` as shown above. Do not replay historical migrations on a fresh schema.

For an existing populated database created from baseline `5857358`, use this exact order:

1. Back up the database and stop or drain application processes that can write to it.
2. Make the new release files available, but do not start the new PHP code yet.
3. Run the forward migration before deploying the code that reads the new payment-review columns.

   ```bash
   mysql -u root -p oems < database/migrations/2026-08-09-participant-transactions.sql
   ```

4. Deploy the new PHP code and restart the application processes.
5. Run the health and acceptance checks. Import `demo_seed.sql` only for an isolated local environment; never replace or re-import `schema.sql` over a populated database.

The migration adds nullable `payments.reviewed_by`, `payments.reviewed_at`, and `payments.review_note`, the reviewer foreign key, and the review moderation index. Its `information_schema` guards make a second deployment and recovery after a partially applied MySQL DDL run safe; existing rows and values are preserved.

## Development administrator

- Email: `admin@oems.local`
- Password: `ChangeMe!2026`

Change this password immediately outside isolated local development.

## Demo accounts

The optional `database/demo_seed.sql` file adds internally consistent local-only organizers, participants, venues, lifecycle events, registrations, manual payments, tickets, notifications, and eligible reviews. It can be imported repeatedly. Existing rows are updated by stable keys and guarded inserts prevent duplicate schedules, galleries, and notifications. Every non-administrator demo account uses the password `DemoPass!2026`.

The demo activates the manual payment method with explicitly fictional instructions. Seeded future tickets keep `qr_path` and `pdf_path` as `NULL` because no media file has been generated for those reference rows. The application generates real QR and PDF assets only when its ticket issuance workflow runs.

- Super administrator: `admin@oems.local` / `ChangeMe!2026`
- Approved organizer: `ayesha.organizer@oems.local` / `DemoPass!2026`
- Approved organizer: `farhan.organizer@oems.local` / `DemoPass!2026`
- Pending organizer: `nusrat.organizer@oems.local` / `DemoPass!2026`
- Participant: `tahmid.participant@oems.local` / `DemoPass!2026`
- Participant with a completed-event review: `jannat.participant@oems.local` / `DemoPass!2026`

Never import the demo dataset into a production database.

## Application workflows

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

### Manual payment workflow and limitations

Paid registration uses a manual review flow:

1. The participant selects a supported payment channel and submits a provider reference.
2. OEMS reserves one seat and records the registration and payment as pending.
3. A super administrator reviews the reference in `/admin/payments`.
4. Verification confirms the registration and generates its QR and PDF ticket. Rejection cancels the registration and releases the seat.
5. OEMS records in-app notifications and attempts the configured transactional email.

The included instructions and demo references are fictional. Do not send money or enter real banking, card, wallet, or account credentials while using the demo. OEMS does not connect to a payment gateway, bank, or mobile wallet, and it cannot independently prove that funds moved. An administrator must verify references using an approved process outside this application before marking a payment paid.

### Event lifecycle

The normal release path is organizer draft → organizer submission → administrator approval → organizer publication. After publication, a participant can save the event as a favorite and register.

Organizer actions:

- `rejected` → `draft` by saving edits that address the administrator's review note.
- `draft` → `pending` by submitting for review. Only an approved organizer account may submit.
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

### Ticket storage

- Issued QR images and PDF tickets are stored under `public/uploads/tickets` with random filenames.
- The database stores relative `qr_path` and `pdf_path` values. It stores only the QR payload hash, never the raw one-time token.
- Participant download routes enforce registration ownership before returning an asset.
- Ticket cancellation invalidates entry. A successful check-in records attendance and moves a valid ticket to checked in.
- Ensure the PHP process can create and write `public/uploads/tickets` in local and production environments. Do not commit generated ticket files.
- A decoder round-trip test is intentionally deferred: the installed `endroid/qr-code` API is an encoder, and the project has no installed QR decoder or `zbar` binary. Adding ZXing, ZBar, or another decoder would introduce a new runtime dependency solely for this test. The current suite verifies the generated PNG signature and MIME type; add round-trip decoding when a maintained lightweight decoder becomes an application dependency.

### Full acceptance journey

Use an isolated local database with both seeds imported.

1. Sign in as the approved organizer `farhan.organizer@oems.local`, create a future paid event draft, save it, and submit it for review.
2. Sign in as `admin@oems.local`, open `/admin/events`, inspect the submitted event, and approve it. Do not publish it from the administrator screen for this journey.
3. Sign back in as Farhan, open the approved event, and publish it. Confirm it now appears in public discovery.
4. Sign in as `tahmid.participant@oems.local`, save the newly published event as a favorite, open it from `/participant/favorites`, and register with a fictional reference such as `OEMS-DEMO-ACCEPTANCE-001`.
5. Confirm the participant registration page shows payment review pending and no ticket yet.
6. Sign in as the administrator, open `/admin/payments?status=pending`, inspect the submitted evidence, and verify it.
7. Sign back in as Tahmid. Confirm the registration is confirmed, the ticket opens, QR and PDF downloads work, and the notification center reports the update.
8. Sign in as Farhan, open the event's participant operations page, and confirm the participant, payment, ticket, and attendance states agree.
9. Use that event's check-in screen to scan the QR or enter the printed ticket number. Confirm a repeated scan is reported as already checked in rather than creating duplicate attendance.
10. For the review lifecycle, sign in as `jannat.participant@oems.local`, update the completed Product Leaders Meetup review, then sign in as the administrator to publish it and as `ayesha.organizer@oems.local` to add a public organizer reply.
11. Check both light and dark themes at mobile and desktop widths on the participant registration, ticket, notification, administrator payment queue, and organizer operations screens.

## Current platform capabilities

- Prepared, ownership-scoped repositories for categories, venues, events, public filters, moderation, galleries, and dashboard summaries
- Organizer venue and event create, edit, preview, submit, cancel, and eligible soft-delete workflows
- Administrator category management and event approve, reject, publish, complete, and cancel workflows
- Secure banner/gallery validation and storage with bounded uploads and safe cleanup
- Database-backed home, event search, event details, canonical metadata, Open Graph data, and JSON-LD
- Repository-backed organizer and administrator dashboards with enabled workflow actions
- Role guards, CSRF protection, escaped views, prepared queries, lifecycle validation, and automated regression coverage

- Participant registration, manual payment review, seat reconciliation, QR and PDF ticket issuance, attendance, favorites, notifications, and reviews
- Owner-scoped organizer participant operations, CSV export, camera-enhanced check-in, revenue metrics, and review replies
- Administrator payment, event, category, and participant-review moderation queues
- Responsive transaction tables that become explicitly labeled mobile cards, accessible forms, and light and dark token themes
- Dependency-injected custom MVC foundations, role guards, CSRF protection, prepared queries, escaped views, and automated regression coverage

## Quality checks

```bash
php tests/run.php DashboardLayoutTest
php tests/run.php DemoSeedIntegrityTest
php tests/run.php TransactionUiTest
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
