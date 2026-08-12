# OEMS Composer Build and Database Commands Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add safe Composer commands for production builds, cPanel ZIP artifacts, schema migration, reversible rollback, destructive refresh, and local fake-data seeding.

**Architecture:** Keep Composer as the public command surface. Implement database and archive behavior in small PHP services with real SQLite and ZIP tests, then expose them through CLI scripts. Preserve the canonical MySQL schema and historical forward-only migrations while supporting paired up/down files for future reversible batches.

**Tech Stack:** PHP 8.2, Composer 2, PDO MySQL and SQLite, ZipArchive, MySQL SQL files, Tailwind CSS 4, Node.js 20, custom PHP test runner.

## Global Constraints

- Do not add Laravel, Symfony Console, or a third-party migration framework.
- Never pass a database password in a shell argument or print it in output.
- Preserve `database/schema.sql` as the canonical fresh-install schema.
- Preserve the documented historical migration order: participant transactions, live location, specification completion, Week 3 operations, Week 4 growth experience.
- Treat existing historical migrations as forward-only baseline batch `0`.
- Require the exact `--force` argument for destructive refresh.
- Refuse fake-data seeding when `APP_ENV=production`.
- Exclude `.env`, user uploads, logs, backups, cache data, private artifacts, tests, docs, and frontend source tooling from the cPanel ZIP.
- Preserve unrelated tracked and untracked user files.
- Commit every completed test and implementation step separately.

---

### Task 1: Define the database lifecycle behavior with real database tests

**Files:**
- Create: `tests/Unit/DatabaseLifecycleServiceTest.php`
- Test: `app/Services/DatabaseLifecycleService.php`
- Test: `database/migrations/manifest.php`

**Interfaces:**
- Consumes: PDO connection, driver name, SQL paths, migration definitions, and application environment
- Produces: expected public methods `migrate(): array`, `rollback(bool $force = false): array`, `refresh(bool $force): array`, and `seedDemo(): array`

- [ ] **Step 1: Write a fresh-schema migration test**

Create temporary SQLite SQL fixtures and assert `migrate()` creates application tables, records every supplied migration with batch `0`, and does not replay migration SQL already represented by the canonical schema.

- [ ] **Step 2: Write existing-database ordering and checksum tests**

Create a populated SQLite baseline, supply two forward migration files in an explicit order that differs from alphabetical order, and assert their effects and history follow the supplied order. Change an applied file and assert the next `migrate()` fails with a safe checksum-drift error.

- [ ] **Step 3: Write rollback, refresh, and seed safety tests**

Use a paired up/down migration to assert positive-batch rollback executes down SQL in reverse order. Assert refresh fails without `--force`, refresh with force rebuilds and applies both seeds, local seed initializes the base seed only once, and production seed refuses without changing data.

- [ ] **Step 4: Run the focused test and observe the missing-class failure**

Run: `rtk php tests/run.php DatabaseLifecycleServiceTest`

Expected: failure because `OEMS\App\Services\DatabaseLifecycleService` does not exist.

- [ ] **Step 5: Commit the red database contract**

```bash
rtk git add tests/Unit/DatabaseLifecycleServiceTest.php
rtk git commit -m "test: define database lifecycle commands"
```

### Task 2: Implement database lifecycle service and CLI

**Files:**
- Create: `app/Services/DatabaseLifecycleService.php`
- Create: `database/migrations/manifest.php`
- Create: `scripts/database.php`
- Modify: `composer.json`
- Test: `tests/Unit/DatabaseLifecycleServiceTest.php`

**Interfaces:**
- Consumes: constructor `__construct(PDO $pdo, string $driver, string $schemaPath, string $baseSeedPath, string $demoSeedPath, array $migrations, string $environment)`
- Produces: Composer commands `db:migrate`, `db:rollback`, `db:refresh`, and `db:seed`

- [ ] **Step 1: Implement portable migration history and SQL execution**

Create `oems_migrations` with `migration`, `batch`, `checksum`, `reversible`, and `applied_at` columns. Execute each complete SQL file through PDO, reject unreadable or empty files, verify stored SHA-256 checksums, and use driver-specific table discovery for MySQL and SQLite.

- [ ] **Step 2: Implement migrate and rollback**

For an empty database, execute the canonical schema and record all supplied migrations as batch `0`. For an existing untracked database, execute forward-only entries in manifest order and record them as batch `0`; apply reversible pending entries in the next positive batch. Rollback the highest positive batch in reverse manifest order and remove only successfully reversed history rows.

- [ ] **Step 3: Implement refresh and fake-data seed**

Refresh must throw unless `$force === true`, execute the canonical schema, rebuild history, record the migration baseline, and run base plus demo seeds. `seedDemo()` must reject production, verify application tables exist, run the base seed only when `roles` is empty, then run the repeatable demo seed.

- [ ] **Step 4: Add the ordered migration manifest**

Return five entries with exact paths in documented order. Give each historical entry `down => null` so rollback never claims those migrations are reversible.

- [ ] **Step 5: Add the safe CLI entry point and Composer aliases**

`scripts/database.php` accepts exactly `migrate`, `rollback`, `refresh`, or `seed`; accepts only `--force`; loads `.env`, creates a PDO connection with multi-statements enabled for MySQL, invokes the service, prints the result message, and converts all failures to a generic nonzero CLI response. Register:

```json
"db:migrate": "@php scripts/database.php migrate",
"db:rollback": "@php scripts/database.php rollback",
"db:refresh": "@php scripts/database.php refresh",
"db:seed": "@php scripts/database.php seed"
```

- [ ] **Step 6: Run focused tests and command discovery**

Run: `rtk php tests/run.php DatabaseLifecycleServiceTest` and `rtk composer run-script --list`.

Expected: all database lifecycle tests pass and all four commands appear.

- [ ] **Step 7: Commit the database implementation**

```bash
rtk git add app/Services/DatabaseLifecycleService.php database/migrations/manifest.php scripts/database.php composer.json tests/Unit/DatabaseLifecycleServiceTest.php
rtk git commit -m "feat: add composer database lifecycle commands"
```

### Task 3: Define and implement the cPanel ZIP contract

**Files:**
- Create: `tests/Unit/CpanelPackageServiceTest.php`
- Create: `app/Services/CpanelPackageService.php`
- Create: `scripts/package-cpanel.php`
- Modify: `composer.json`

**Interfaces:**
- Consumes: `CpanelPackageService::__construct(string $projectRoot)` and `package(string $destination): string`
- Produces: `composer package:cpanel` and `dist/oems-cpanel.zip`

- [ ] **Step 1: Write a real ZIP regression test**

Build a temporary project fixture containing allowed runtime files, `.env`, tests, docs, cache data, backup data, uploaded media, and a symbolic link. Package it, open the real ZIP with `ZipArchive`, and assert every entry starts with `oems/`; allowlisted runtime files and placeholders exist; secrets, runtime data, source-only directories, and the symlink do not exist.

- [ ] **Step 2: Run the focused test and observe the missing-class failure**

Run: `rtk php tests/run.php CpanelPackageServiceTest`

Expected: failure because `OEMS\App\Services\CpanelPackageService` does not exist.

- [ ] **Step 3: Commit the red package contract**

```bash
rtk git add tests/Unit/CpanelPackageServiceTest.php
rtk git commit -m "test: define cpanel package contract"
```

- [ ] **Step 4: Implement an allowlisted atomic archive builder**

Allow only `.htaccess`, `.env.example`, `README.md`, `composer.json`, `composer.lock`, `app`, `Core`, `bootstrap`, `config`, `database`, `deploy`, `public`, `routes`, `scripts`, `storage`, and `vendor`. Walk without following links, apply explicit runtime exclusions, write to a same-directory temporary ZIP, verify it reopens and contains `oems/public/index.php` plus `oems/vendor/autoload.php`, then atomically rename it to the destination.

- [ ] **Step 5: Add the package CLI and Composer alias**

The CLI accepts no arguments, invokes the service for `dist/oems-cpanel.zip`, and prints the relative path and byte size. Register `package:cpanel` to run `@build` followed by `@php scripts/package-cpanel.php`.

- [ ] **Step 6: Run focused tests and commit the package implementation**

Run: `rtk php tests/run.php CpanelPackageServiceTest`.

Stage the package service, script, Composer changes, and test. Commit: `feat: add cpanel zip packaging`.

### Task 4: Add the production build command and operator documentation

**Files:**
- Modify: `composer.json`
- Modify: `README.md`
- Test: `tests/Unit/ComposerCommandTest.php`

**Interfaces:**
- Consumes: existing Composer, npm, asset, syntax, and test commands
- Produces: `composer build` and documented deployment/database workflows

- [ ] **Step 1: Write a Composer command registration test**

Run `composer run-script --list` from PHP and assert it exits successfully and exposes `build`, `package:cpanel`, `db:migrate`, `db:rollback`, `db:refresh`, and `db:seed`.

- [ ] **Step 2: Run the test and observe `build` is missing**

Run: `rtk php tests/run.php ComposerCommandTest`

Expected: failure because `build` has not been registered.

- [ ] **Step 3: Commit the red Composer command contract**

```bash
rtk git add tests/Unit/ComposerCommandTest.php
rtk git commit -m "test: define composer operator commands"
```

- [ ] **Step 4: Register the production build pipeline**

Define `build` as an ordered Composer script array that disables process timeout, validates Composer metadata, installs optimized no-dev PHP dependencies, runs `npm ci --no-audit --no-fund`, builds CSS and static assets, runs PHP syntax checks, runs the PHP suite, and runs all JavaScript tests.

- [ ] **Step 5: Document local, cPanel, and database usage**

Replace manual fresh-database steps with the Composer equivalents while retaining raw SQL upgrade guidance for legacy operators. Add cPanel upload steps, document the `public` document root, writable directories, `.env` creation, the fake-data production block, refresh force requirement, rollback limits, and exact command examples.

- [ ] **Step 6: Verify registration and commit the build/docs step**

Run: `rtk php tests/run.php ComposerCommandTest` and `rtk composer validate --strict`.

Stage `composer.json`, `README.md`, and the test. Commit: `build: add production composer workflow`.

### Task 5: Build, inspect, and verify the release artifact

**Files:**
- Generate: `dist/oems-cpanel.zip`
- Modify only if verification exposes a tested defect

**Interfaces:**
- Consumes: all new Composer commands
- Produces: verified cPanel upload artifact and complete test evidence

- [ ] **Step 1: Run database and package focused suites**

Run: `rtk php tests/run.php DatabaseLifecycleServiceTest`, `rtk php tests/run.php CpanelPackageServiceTest`, and `rtk php tests/run.php ComposerCommandTest`.

- [ ] **Step 2: Run the production package workflow**

Run: `rtk composer package:cpanel`.

Expected: build succeeds and `dist/oems-cpanel.zip` is created.

- [ ] **Step 3: Inspect the generated archive**

Run: `rtk unzip -t dist/oems-cpanel.zip` and list its entries. Confirm `oems/public/index.php`, `oems/vendor/autoload.php`, `.env.example`, schema, manifest, database CLI, compiled CSS, and Apache rules are present. Confirm `.env`, tests, docs, uploads, logs, backups, cache data, Node dependencies, and `dist/` are absent.

- [ ] **Step 4: Run the complete quality gate**

Run: `rtk composer test`, `rtk node --test tests/js/*.test.mjs`, `rtk composer check:syntax`, `rtk composer validate --strict`, and `rtk git diff --check`.

Expected: all commands pass without test, syntax, metadata, or whitespace failures.

- [ ] **Step 5: Commit any deterministic generated source changes**

If the build changed tracked public assets, stage only those assets and commit `build: refresh deployment assets`. Keep the generated ZIP untracked and report its absolute path and size.
