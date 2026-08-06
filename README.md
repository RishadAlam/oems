# OEMS

OEMS is a custom PHP MVC foundation for an online event management platform. Week 1 delivers the application shell, complete relational schema, secure account flows, role-based dashboards, and the initial responsive interface.

## Requirements

- PHP 8.2 or newer with PDO MySQL
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

3. Create a MySQL database, then import the schema and development seed data.

   ```bash
   mysql -u root -p -e "CREATE DATABASE oems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p oems < database/schema.sql
   mysql -u root -p oems < database/seed.sql
   ```

4. Build the Tailwind stylesheet.

   ```bash
   npm run build:css
   ```

5. Start the local server and visit `http://localhost:8000`.

   ```bash
   php -S 127.0.0.1:8000 -t public public/router.php
   ```

With `APP_DEBUG=true`, registration and password-reset screens expose local-only verification links. Production delivery will require an SMTP mail transport.

## Development administrator

- Email: `admin@oems.local`
- Password: `ChangeMe!2026`

Change this password immediately outside isolated local development.

## Week 1 deliverables

- Dependency-injected custom MVC core with routing, middleware, sessions, validation, views, configuration, database transactions, logging, and error responses
- MySQL schema for accounts, permissions, organizers, events, schedules, registrations, payments, tickets, attendance, notifications, reviews, CMS, reporting support, and audit data
- Participant and organizer registration, email verification, login, logout, remember-me sessions, password reset, and password change
- CSRF protection, prepared database statements, escaped views, session rotation, password hashing, token hashing, and file-backed login throttling
- Role guards and separate participant, organizer, and super-admin dashboard shells
- Responsive public, authentication, events, and dashboard interfaces with light and dark themes
- Self-hosted Manrope font and original generated event imagery

Event creation, registration checkout, payments, QR tickets, attendance, reviews, notifications, admin CRUD, reports, and CMS workflows are scheduled for Weeks 2 through 4.

## Quality checks

```bash
composer test
composer check:syntax
composer validate --strict
npm run build:css
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
tests/                  Dependency-free unit test suite
```

The Week 1 design and implementation records are in `docs/superpowers/`.
