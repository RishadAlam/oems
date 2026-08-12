# OEMS Composer Build and Database Commands Design

**Date:** 2026-08-13

**Scope:** Production build, cPanel ZIP artifact, database migration, rollback, refresh, and local fake-data seed commands

## Goal

Provide memorable Composer commands that prepare OEMS for deployment and manage its MySQL schema without introducing a framework or exposing database credentials.

## Command contract

| Command | Behavior |
| --- | --- |
| `composer build` | Validate Composer metadata, install optimized production PHP dependencies, install locked frontend dependencies, build public assets, run syntax checks, and run PHP and JavaScript tests. |
| `composer package:cpanel` | Run the production build, then create `dist/oems-cpanel.zip` with compiled assets and production PHP dependencies. |
| `composer db:migrate` | Initialize a fresh database from the canonical schema or apply pending migrations to an existing database. |
| `composer db:rollback` | Roll back the latest reversible migration batch. In production it also requires `--force`. |
| `composer db:refresh -- --force` | Destructively rebuild the configured database from the canonical schema, base seed, and fake-data seed. |
| `composer db:seed` | Load the base seed when required and apply the repeatable fake-data seed. This command refuses to run when `APP_ENV=production`. |

Every command exits nonzero on failure and prints a concise message without credentials, SQL bodies, or exception traces.

## Architecture

Composer aliases call small CLI entry points under `scripts/`. Database behavior lives in a testable service that receives a PDO connection and explicit schema, seed, and migration paths. Packaging behavior lives in a separate service that writes an allowlisted project tree to a ZIP archive.

The application remains a custom PHP MVC project. No Laravel command layer, third-party migration package, or deployment framework is added.

## Database lifecycle

`database/schema.sql` remains the canonical current schema. `database/migrations/manifest.php` defines the exact historical upgrade order, which differs from filename sorting for the two 2026-08-09 files.

On an empty database, migrate imports `schema.sql`, creates the `oems_migrations` history table, and records migrations as baseline batch `0` because their effects are already present in the canonical schema.

On a populated database without history, migrate applies the existing idempotent forward migrations in manifest order and records them as baseline batch `0`. Pending reversible migrations use the next positive batch number.

Existing historical `.sql` migrations stay forward-only. The project must not claim they can safely reconstruct discarded data. Future reversible migrations use paired files:

```text
database/migrations/2026-08-14-example.up.sql
database/migrations/2026-08-14-example.down.sql
```

Rollback selects the highest positive batch, executes its down files in reverse order, and removes their history rows. If there is no reversible batch, it exits successfully with an explicit no-op message.

Applied migration checksums are verified before new work. A changed applied migration fails closed so deployed history cannot silently drift.

## Refresh and seed safety

Refresh deletes application data by executing the canonical schema and therefore requires the exact `--force` flag in every environment. It then records the current migration baseline, loads `database/seed.sql`, and loads `database/demo_seed.sql`.

The fake-data seed checks `APP_ENV`. It refuses production even when other flags are supplied. On an empty seeded schema it loads the base seed first; on an already initialized schema it applies only the repeatable demo seed.

Database credentials come only from `.env` and `config/database.php`. PDO receives the password directly, never through a shell command or command-line argument.

## cPanel package

The ZIP contains one top-level `oems/` directory with the runtime application, including:

- `app/`, `Core/`, `bootstrap/`, `config/`, `database/`, `public/`, `routes/`, `scripts/`, and `storage/`
- optimized `vendor/`
- compiled `public/assets/`
- root and public Apache rules
- `composer.json`, `composer.lock`, `.env.example`, and `README.md`

The ZIP excludes:

- `.env`, `.git/`, tests, docs, Node dependencies, source frontend tooling, and existing `dist/`
- logs, backups, caches, private tickets and certificates
- uploaded event, blog, and ticket media other than required protection and placeholder files
- editor files, operating-system metadata, presentation artifacts, and local inspection output

The packaging service uses an explicit top-level allowlist plus runtime exclusions. It never follows symbolic links. The archive is replaced atomically only after a new temporary ZIP is complete and readable.

The cPanel deployment keeps the full application outside the public web root where possible and points the domain document root to `oems/public`. The uploaded `.env.example` is copied to `.env` and populated on the server; `.env` is never packaged.

## Testing

Database lifecycle tests use a real temporary SQLite database with small compatible schema and migration fixtures. They verify fresh migration, ordered existing upgrades, checksum drift rejection, reversible batch rollback, forced refresh, and production seed refusal without mocking PDO.

Packaging tests build and inspect a real temporary ZIP. They verify required runtime files are present and secrets, runtime data, uploads, tests, and symbolic links are absent.

Command registration is verified through Composer’s command listing. Final verification runs the focused tests, production asset build, full PHP suite, full JavaScript suite, syntax checks, Composer validation, ZIP inspection, and whitespace checks.
