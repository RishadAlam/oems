# OEMS

OEMS is a custom PHP MVC online event management platform for public discovery, participant registration, manual payment review, QR ticketing, attendance, reviews, notifications, and organizer and administrator operations.

## Requirements

- PHP 8.2 or newer with PDO MySQL, GD, mbstring, and OpenSSL
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

5. Build the Tailwind stylesheet and copy self-hosted frontend assets.

   ```bash
   npm run build:css
   npm run build:assets
   ```

6. Start the local server and visit `http://localhost:8000`.

   ```bash
   php -S 127.0.0.1:8000 -t public public/router.php
   ```

Registration and password-reset messages are sent through the configured SMTP transport. With `APP_DEBUG=true`, the screens also expose local development links so account flows can be tested without opening the mail sandbox.

In production, set `APP_URL` to the externally reachable HTTPS origin so account email links open the correct secure site. Browser location access also requires HTTPS outside `localhost`; it will not work on an ordinary remote HTTP origin.

When TLS terminates at a reverse proxy, set `COOKIE_SECURE=true`. Configure `TRUSTED_PROXIES` with only the proxy IPs or CIDRs controlled by the deployment; OEMS ignores `Forwarded` and `X-Forwarded-For` from every other peer. The proxy must replace, rather than append to client-supplied forwarding headers, overwrite `Host` with the expected public hostname, and reject unexpected hostnames. Enable HSTS at the HTTPS edge only after the hostname and certificate are production-ready. The built-in rate limiter uses files under `storage/cache/rate-limits` and is intended for a single application node; multi-node deployments need a shared atomic limiter before serving production traffic.

The application does not require `pnpm dev`. Run `npm run watch:css` only while editing Tailwind styles; the PHP server handles application requests.

The PHP process must be able to create and write `storage/cache` (rate limits and cache locks), `storage/logs` (application logs), `storage/tickets` (private QR/PDF artifacts), and `public/uploads/events` (public event images). Keep all other application and source paths read-only in production. Do not commit generated runtime files.

## Database upgrades

`database/schema.sql` is the canonical schema for a fresh installation. For a new database, import `schema.sql`, then `seed.sql`, and finally the optional `demo_seed.sql` as shown above. Do not replay historical migrations on a fresh schema.

For an existing populated database created from baseline `5857358`, use this exact order:

1. Back up the database and stop or drain application processes that can write to it.
2. Make the new release files available, but do not start the new PHP code yet.
3. Run the forward migrations in order before deploying code that reads the new columns.

   ```bash
   mysql -u root -p oems < database/migrations/2026-08-09-participant-transactions.sql
   mysql -u root -p oems < database/migrations/2026-08-09-live-location.sql
   mysql -u root -p oems < database/migrations/2026-08-10-spec-completion.sql
   mysql -u root -p oems < database/migrations/2026-08-10-week-3-operations.sql
   mysql -u root -p oems < database/migrations/2026-08-10-week-4-growth-experience.sql
   ```

4. Deploy the new PHP code while application traffic remains stopped or drained.
5. Migrate ticket files created by older releases out of the public document root. The command is repeatable and never overwrites a conflicting private file.

   ```bash
   php scripts/migrate-ticket-artifacts.php
   ```

6. Restart the application processes, then run the health and acceptance checks before restoring traffic. Import `demo_seed.sql` only for an isolated local environment; never replace or re-import `schema.sql` over a populated database.

The transaction migration adds payment-review fields and indexes. The live-location migration then adds event location visibility and arrival notes, venue coordinate integrity and indexing, and the geocoding cache. The specification-completion migration adds persisted organizer announcements. The Week 3 operations migration adds durable mail, coupons, newsletter delivery, contact queue indexes, and private operational defaults. The Week 4 migration adds event waitlist controls and queue timestamps, private attendance-certificate records, and Blog publication records. All migrations are repeatable after a partially applied MySQL DDL deployment without replacing existing rows.

If the populated database already includes the participant-transaction release represented by baseline `90cb666`, run the live-location, specification-completion, Week 3, and Week 4 migrations. If it already includes the live-location release, run the specification-completion, Week 3, and Week 4 migrations. A current Week 3 deployment needs only the Week 4 migration. Never import `schema.sql` or either seed over a populated production database.

## Production operations

OEMS is designed as a production-ready single-node PHP/MySQL deployment. Copy the examples under `deploy/` into the host configuration only after replacing every `__PLACEHOLDER__`; the files intentionally contain no deployable host paths, users, certificates, or secrets.

- `GET /health/live` is a process-only liveness probe and does not query the database.
- `GET /health/ready` checks the database, required Week 3 schema, and private writable runtime directories. It returns component booleans only—never paths, versions, credentials, SQL, or exception text.
- `/admin/operations` gives super administrators a readiness summary and a confirmation-bound maintenance control. Maintenance returns `503` with `Retry-After` for public and non-admin application routes while keeping health probes, login, static assets, and signed-in super administrators available.
- `php scripts/process-mail-outbox.php --limit=50` delivers durable queued mail.
- `php scripts/queue-event-reminders.php --limit=100` queues due event reminders idempotently.
- `php scripts/process-waitlists.php --limit=100` releases expired unpaid claims and promotes the oldest eligible entries into newly available seats. Run it every minute with the supplied timer.
- `php scripts/backup-database.php` creates a portable gzip-compressed SQL archive only beneath `storage/backups`, excludes server-specific GTID state, passes the database password through `MYSQL_PWD`, enforces private permissions, verifies non-empty output, and retains 1–30 archives according to the private `backup_retention` setting.

Recommended release sequence:

1. Drain writes or enable maintenance, then run and verify a database backup.
2. Deploy dependencies with `composer install --no-dev --classmap-authoritative` and `npm ci`; build assets with `npm run build:css` and `npm run build:assets` in the release artifact.
3. Run the required forward migrations in the documented order and run `php scripts/migrate-ticket-artifacts.php` for pre-private-storage installations.
4. Make only `storage/cache`, `storage/logs`, `storage/tickets`, `storage/backups`, and the documented public upload directories writable by the application user. Keep source, configuration, and vendor files read-only.
5. Enable the PHP-FPM pool, Nginx site, and the four systemd timers. Confirm `systemctl list-timers 'oems-*'` reports future runs and inspect each oneshot service result.
6. Probe `/health/live` and `/health/ready`, run the role/CSRF/download acceptance journey, then disable maintenance and restore traffic.

Restore is deliberately operator-only; there is no HTTP restore route. Restore into a new database first, run `gzip -cd storage/backups/<archive>.sql.gz | mysql ...`, verify table counts and integrity, point a maintenance deployment at the restored database, run readiness and acceptance checks, and only then switch traffic. Never restore over a live writable database.

For rollback, keep the previous immutable release artifact. Enable maintenance, stop workers, restore the matching verified database backup if the release performed incompatible data changes, switch the release symlink, restart PHP-FPM/workers, pass readiness and acceptance checks, then reopen traffic. Rotate logs externally and alert on readiness failures, repeated outbox failures, backup failures, disk pressure, and timer failures.

## Maps and nearby discovery

The map integration uses locally built Leaflet assets and provider-neutral configuration:

- `MAP_TILE_URL` is the HTTPS tile template. The local default is `https://tile.openstreetmap.org/{z}/{x}/{y}.png`.
- `MAP_TILE_ATTRIBUTION` is the visible provider attribution. Keep the attribution required by the selected provider.
- `MAP_DEFAULT_LAT`, `MAP_DEFAULT_LNG`, and `MAP_DEFAULT_ZOOM` define the fallback map view before a pin or result is selected.
- `MAP_GEOCODER_URL` is the HTTPS server-side forward-geocoding endpoint.
- `MAP_PROVIDER_NAME` names the configured geocoder in safe operational messages.
- `MAP_USER_AGENT` identifies this installation to the geocoding provider.
- `MAP_CONTACT_EMAIL` supplies provider contact information when required. It may be blank locally.
- `MAP_DIRECTIONS_HOSTS` is the comma-separated allowlist for organizer-supplied HTTPS directions URLs. Invalid or untrusted custom URLs are ignored at display time; saved coordinates still produce a safe Google Maps directions link.
- `LOCATION_SESSION_TTL` is the nearby-location session lifetime in seconds. It is hard-capped at `1209600` (14 days), which is also the default.

The public OpenStreetMap tile service and Nominatim defaults are for low-volume, human-driven local development only. OEMS displays map attribution, sends only explicit organizer address searches to the server-side geocoder, caches bounded results, and limits provider requests to at most one per second. Before production use, configure tile and geocoding services that permit the installation's expected traffic. Switching providers requires environment changes, not template or JavaScript changes. Do not commit provider credentials.

Nearby discovery begins only after a visitor selects **Use my location**. The browser coordinates are rounded to three decimal places, stored only in the session, expire after 14 days by default, and can be removed with **Clear location**. OEMS does not continuously track attendees, call `watchPosition`, infer location from an IP address, broadcast participant position, or write device coordinates to application tables or analytics.

Organizers can enter the written venue address without JavaScript. **Find address** performs one explicit search; selecting a result, clicking or dragging the map pin, or choosing **Use current position** fills the coordinate pair. **Clear pin** removes both coordinates. Moving a pin never silently rewrites the written address. Each event can expose its exact location publicly or only to confirmed participants, and may include arrival notes. Public exact locations show a map and directions. A registered-only event shows guests and pending participants only its city and country; confirmed participants, the owning organizer, and administrators can see its exact address, map, directions, and arrival notes.

## Development administrator

- Email: `admin@oems.local`
- Password: `ChangeMe!2026`

Change this password immediately outside isolated local development.

## Demo accounts

The optional `database/demo_seed.sql` file adds internally consistent local-only organizers, participants, venues, lifecycle events, registrations, manual payments, tickets, notifications, and eligible reviews. It can be imported repeatedly. Existing rows are updated by stable keys and guarded inserts prevent duplicate schedules, galleries, and notifications. Every non-administrator demo account uses the password `DemoPass!2026`.

The demo activates the manual payment method with explicitly fictional instructions. Seeded future tickets keep `qr_path` and `pdf_path` as `NULL` because no media file has been generated for those reference rows. The application generates real QR and PDF assets only when its ticket issuance workflow runs.

The demo also provides repeatable map journeys:

- `dhaka-tech-summit-2026` is a future published event with a public exact location, map, directions, and arrival notes.
- `startup-growth-forum-2026` is a future published event whose exact location and arrival notes are restricted. Guests cannot see them; `sohana.participant@oems.local` and `arif.participant@oems.local` have confirmed registrations and can verify the authorized state.
- `community-arts-night-2026` is another future published coordinate-backed event for nearby radius and map-result testing.

The demo seed refreshes only the three owned venue pins and does not seed or clear the geocoding cache. Reimporting it keeps stable event, registration, payment, and ticket identifiers.

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

- Issued QR images and PDF tickets are stored outside the document root under `storage/tickets` with random filenames.
- The database retains relative `uploads/tickets/...` values as private artifact locators for compatibility. These values are never public URLs. It stores only the QR payload hash, never the raw one-time token.
- Participant download routes enforce registration ownership and return private, no-store responses. The built-in router and Apache rules never serve `/uploads/tickets/...` as static files.
- Ticket cancellation invalidates entry. A successful check-in records attendance and moves a valid ticket to checked in.
- For upgrades that have artifacts under `public/uploads/tickets`, run `php scripts/migrate-ticket-artifacts.php` before accepting traffic and confirm the legacy directory contains no ticket files afterward.
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
- FIFO event waitlists with participant-owned queue controls, automatic seat promotion, bounded payment claims, expiry reconciliation, and native MySQL concurrency verification
- Owner-scoped organizer participant operations, CSV export, camera-enhanced check-in, revenue metrics, and review replies
- Administrator payment, event, category, and participant-review moderation queues
- Responsive transaction tables that become explicitly labeled mobile cards, accessible forms, and light and dark token themes
- Dependency-injected custom MVC foundations, role guards, CSRF protection, prepared queries, escaped views, and automated regression coverage

- Durable SMTP outbox delivery with bounded retries, stale-lock recovery, idempotency keys, and concurrent MySQL worker claims
- Organizer-owned coupons with percentage/fixed discounts, usage limits, date windows, exact-money calculations, and concurrency-safe redemption
- Public contact intake, double-opt-in newsletter subscriptions, administrator inbox/campaign operations, and private operational evidence
- Participant and organizer calendar exports, event reminders, accessible administrator/organizer analytics charts, and semantic table fallbacks
- Process-only liveness, dependency readiness, maintenance mode, portable private database backups, and Nginx/PHP-FPM/systemd deployment examples

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
npm run test:assets
npm run build:assets
node --check public/assets/js/location.js
node --check public/assets/js/venue-map.js
node tests/js/location.test.mjs
node tests/js/venue-map.test.mjs
OEMS_OUTBOX_TEST_MYSQL=1 tests/verify-outbox-concurrency-mysql.sh
OEMS_BACKUP_RESTORE_MYSQL=1 OEMS_BACKUP_ARCHIVE=storage/backups/<archive>.sql.gz tests/verify-backup-restore-mysql.sh
OEMS_WEEK4_TEST_MYSQL=1 tests/verify-week-4-migration-mysql.sh
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
