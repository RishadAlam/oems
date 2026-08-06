# OEMS Week 1 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the custom PHP MVC, database, authentication, role access, and responsive UI foundation required by Week 1.

**Architecture:** A single public front controller dispatches named routes through middleware to thin controllers. Controllers call focused services, repositories isolate PDO persistence, and PHP views use shared semantic design tokens compiled through Tailwind CSS.

**Tech Stack:** PHP 8.2+, MySQL 8+, Composer PSR-4 autoloading, HTML5, Tailwind CSS v4, vanilla JavaScript.

## Global Constraints

- Use raw PHP OOP only; do not use Laravel, CodeIgniter, Symfony, WordPress, or another PHP framework.
- Follow PSR-12, strict typing, namespaces, SOLID, DRY, and KISS.
- Keep controllers thin and business logic in services.
- Use `password_hash()` and `password_verify()` for passwords.
- Validate every request, escape every rendered value, and use prepared statements.
- Use transactions for multi-record writes.
- Public UI must be responsive, keyboard accessible, and support light and dark themes.

---

### Task 1: Runtime and test harness

**Files:**

- Create: `composer.json`, `.env.example`, `.gitignore`, `bootstrap/app.php`
- Create: `Core/Container.php`, `Core/Request.php`, `Core/Response.php`, `Core/Router.php`
- Create: `tests/bootstrap.php`, `tests/Support/TestCase.php`, `tests/run.php`, `tests/Unit/RouterTest.php`

**Interfaces:**

- Produces: `Router::get()`, `Router::post()`, `Router::dispatch(Request): Response`, `Request::create()`, and `Response::redirect()`.

- [ ] Write router tests for static routes, path parameters, missing routes, and method mismatch.
- [ ] Run `php tests/run.php` and confirm the router tests fail because the runtime classes do not exist.
- [ ] Implement the minimal request, response, container, and router behavior.
- [ ] Run `php tests/run.php` and confirm the router tests pass.

### Task 2: Validation, sessions, and security

**Files:**

- Create: `Core/Validator.php`, `Core/Session.php`, `Core/Security.php`, `Core/Logger.php`
- Create: `app/Helpers/helpers.php`
- Test: `tests/Unit/ValidatorTest.php`, `tests/Unit/SecurityTest.php`

**Interfaces:**

- Produces: `Validator::validate(array $data, array $rules): array`, `Security::csrfToken(): string`, `Security::verifyCsrf(?string $token): bool`, `Session::flash()`, and escaped `e()` output.

- [ ] Write tests proving invalid required/email/min/confirmed/in rules return field errors and valid input does not.
- [ ] Write tests proving a generated CSRF token validates and a different token fails.
- [ ] Run the tests and confirm they fail because validation and security behavior are missing.
- [ ] Implement the minimal behavior and rerun the suite to green.

### Task 3: Database schema and persistence

**Files:**

- Create: `config/app.php`, `config/database.php`, `Core/Database.php`, `Core/Model.php`
- Create: `database/schema.sql`, `database/seed.sql`
- Create: `app/Contracts/UserRepositoryInterface.php`, `app/Repositories/UserRepository.php`, `app/Models/User.php`

**Interfaces:**

- Produces: `Database::connection(): PDO`; repository create/find/update methods consumed by `AuthService`.

- [ ] Define the full OEMS schema with foreign keys, uniqueness constraints, lifecycle status checks, and query indexes.
- [ ] Seed the three roles, granular permissions, mappings, initial categories, default settings, and a documented Super Admin credential.
- [ ] Implement PDO creation with native prepared statements and injectable configuration.
- [ ] Implement user persistence with parameterized statements only.
- [ ] Validate SQL structure locally and run a MySQL smoke import when credentials are available.

### Task 4: Authentication service and role middleware

**Files:**

- Create: `app/Services/AuthService.php`, `Core/Auth.php`, `Core/Middleware.php`
- Create: `app/Middleware/AuthMiddleware.php`, `app/Middleware/GuestMiddleware.php`, `app/Middleware/RoleMiddleware.php`, `app/Middleware/CsrfMiddleware.php`
- Test: `tests/Unit/AuthServiceTest.php`, `tests/Unit/RoleMiddlewareTest.php`

**Interfaces:**

- Produces: `AuthService::register()`, `attempt()`, `verifyEmail()`, `requestPasswordReset()`, `resetPassword()`, `changePassword()`, and `logout()`.

- [ ] Write tests for password hashing, duplicate registration, verified login, invalid credentials, inactive accounts, and permitted registration roles.
- [ ] Write middleware tests for guest redirects, authenticated continuation, accepted roles, and rejected roles.
- [ ] Run the tests and confirm expected failures.
- [ ] Implement the minimal authentication and authorization behavior, then rerun all tests.

### Task 5: Controllers, routes, and views

**Files:**

- Create: `Core/Controller.php`, `Core/View.php`
- Create: `app/Controllers/HomeController.php`, `app/Controllers/AuthController.php`, `app/Controllers/DashboardController.php`
- Create: `routes/web.php`, `public/index.php`, `public/.htaccess`, `.htaccess`
- Create: `app/Views/layouts/public.php`, `app/Views/layouts/auth.php`, `app/Views/layouts/dashboard.php`
- Create: `app/Views/home/index.php`, `app/Views/auth/*.php`, `app/Views/dashboard/*.php`, `app/Views/errors/*.php`

**Interfaces:**

- Consumes: router, service, session, security, auth, and middleware contracts from Tasks 1-4.
- Produces: public, guest, authenticated, and role-specific HTTP flows.

- [ ] Register public, guest, authenticated, and role-protected routes.
- [ ] Implement thin controllers that validate input and delegate to `AuthService`.
- [ ] Implement accessible forms with labels, helper text, field errors, CSRF values, and password visibility controls.
- [ ] Implement role-specific dashboard shells and safe error pages.
- [ ] Run route and service tests after integration.

### Task 6: Frontend system and generated event imagery

**Files:**

- Create: `package.json`, `resources/css/app.css`, `public/assets/js/app.js`
- Generate: `public/assets/images/hero-events.webp`, `public/assets/images/event-creative.webp`, `public/assets/images/event-community.webp`
- Build: `public/assets/css/app.css`

**Interfaces:**

- Produces: semantic light/dark tokens, responsive navigation, theme persistence, mobile menu, flash dismissal, and password visibility behavior.

- [ ] Generate three project-bound event images with no embedded text, logos, or watermarks.
- [ ] Define the Tailwind input, semantic design tokens, responsive components, focus states, reduced-motion fallbacks, and print-safe defaults.
- [ ] Implement vanilla JavaScript enhancements without making core navigation or forms JavaScript-dependent.
- [ ] Install frontend dependencies and run `npm run build:css`.

### Task 7: Verification and handoff

**Files:**

- Create: `README.md`
- Modify: implementation files only when verification exposes defects.

**Interfaces:**

- Produces: reproducible setup, database, test, asset-build, and local-server commands.

- [ ] Run `composer validate --strict`.
- [ ] Run syntax checks for every PHP file.
- [ ] Run `php tests/run.php` and confirm zero failures.
- [ ] Run the production CSS build.
- [ ] Start PHP's development server and verify key pages at desktop and mobile widths in both themes.
- [ ] Run the frontend pre-flight checklist, correct every failure, and record any environment-only limitations.

