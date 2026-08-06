# OEMS Week 1 Foundation Design

## Goal

Build the production-shaped foundation for the Online Event Management System: a custom PHP MVC runtime, MySQL schema, secure authentication, role-based access, and a polished responsive public interface.

## Scope

Week 1 includes:

- Custom routing, requests, responses, controllers, views, database access, validation, sessions, CSRF protection, logging, and middleware.
- Registration for participants and organizers, login, logout, email verification, password recovery, remember-me sessions, and password changes.
- Super Admin, Organizer, and Participant role boundaries.
- A complete MySQL schema for the planned OEMS modules so later weeks can add domain behavior without destructive schema rewrites.
- Public home, login, registration, recovery, verification, and role-dashboard shells.
- Automated tests for routing, validation, security tokens, authentication rules, and authorization.

Event creation, registration, payment, ticketing, QR attendance, reporting, and CMS behavior remain assigned to Weeks 2-4. The database tables and UI navigation affordances for those modules may exist, but their domain workflows do not.

## Design Read

Reading this as a greenfield event platform for participants and organizers, with a confident editorial-product language, leaning toward Tailwind CSS, custom semantic tokens, and restrained native JavaScript motion.

- `DESIGN_VARIANCE: 7`: asymmetric public layouts, strict single-column mobile collapse.
- `MOTION_INTENSITY: 4`: short CSS entry and interaction transitions that respect reduced-motion preferences.
- `VISUAL_DENSITY: 5`: comfortable public pages and compact dashboard shells.

The public interface uses cool off-white and charcoal surfaces with coral as the single brand accent. Cards use a consistent 16px radius, controls use 10px, and buttons use full-pill geometry. Dark mode is implemented with semantic CSS variables and a persisted user preference.

## Architecture

`public/index.php` is the only web entry point. It loads Composer autoloading, configuration, the dependency container, and routes. The router converts the current request into a controller action and applies route middleware before the controller runs.

Controllers remain thin. `AuthService` owns authentication policy, `UserRepository` owns user persistence, and core classes own framework concerns. Views receive escaped data through a shared renderer and use reusable public and dashboard layouts.

Dependencies flow inward through constructor injection:

`Request -> Router -> Middleware -> Controller -> Service -> Repository -> Database`

## Authentication Flow

Registration validates name, email, password confirmation, and account type. The service rejects duplicate emails, hashes passwords with `password_hash()`, creates a participant or organizer account, and stores a one-time email-verification token.

Login checks rate limits, account status, verification state, and `password_verify()`. Successful login regenerates the session ID and stores only the authenticated user ID and role slug. Remember-me uses a selector plus a hashed validator stored in the `sessions` table; the raw validator exists only in the secure cookie.

Password recovery stores a hashed one-time token with a 60-minute expiry. Password changes invalidate active remembered sessions. Development mode exposes verification and reset links through flash messages because the PHPMailer transport belongs to Week 3.

## Authorization

Routes can declare `auth`, `guest`, or `role:<slug>` middleware. Authorization reads the canonical role from the authenticated database record, not from user-controlled request data. Unauthorized visitors receive a redirect to login; authenticated users with the wrong role receive HTTP 403.

## Error Handling and Security

- PDO uses prepared statements, exception mode, and native prepares.
- Every state-changing form requires a CSRF token.
- View output uses the shared `e()` helper.
- Sessions use HttpOnly, SameSite=Lax cookies and Secure cookies under HTTPS.
- Login failures are rate limited by normalized email plus client IP.
- Security-sensitive events are written to the activity log and filesystem logger without passwords or raw tokens.
- Production errors render a safe page; development errors include diagnostic context.

## Testing

The project uses a dependency-free PHP test runner for the framework foundation. Tests are written before production behavior and cover observable contracts:

- Static, parameterized, missing, and method-mismatch routes.
- Required, email, length, confirmation, and allowed-value validation rules.
- CSRF token generation and verification.
- Password hashing, duplicate registration, verified login, invalid credentials, and role selection.
- Guest, authenticated, and role middleware outcomes.

Database schema checks run against MySQL when test credentials are available; all other tests run without external services.

## Acceptance Criteria

- `composer validate`, PHP syntax checks, the test suite, and the Tailwind production build pass.
- A participant and organizer can register, verify, login, logout, request a reset, reset the password, and change the password.
- The seeded Super Admin can access the admin dashboard and cannot enter organizer-only routes as an organizer.
- Public and authentication pages are responsive, keyboard accessible, dual-theme, and visually checked at desktop and mobile sizes.
- Later modules have schema tables, foreign keys, indexes, and clear service boundaries ready for implementation.

