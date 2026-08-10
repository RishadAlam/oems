# OEMS International Marketplace Completion Plan

> **Execution rule:** Implement in order with test-driven development. Every task starts with observed RED behavior, ends with focused and full verification, receives an independent Critical/Important review, creates the stated Git commit, and never stages unrelated local artifacts.

**Goal:** Convert OEMS from a Bangladesh-default open-source project into a region-neutral, international, commercially distributable self-hosted event-management product while completing all previously identified technical gaps.

**Design source:** [OEMS International Marketplace Design](../specs/2026-08-10-oems-international-marketplace-design.md)

**Architecture:** Preserve the working raw-PHP MVC application and lifecycle. Add catalogs and formatter boundaries for country, locale, timezone, currency, and tax; register third-party integrations behind provider contracts; add an install/upgrade system; and generate a deterministic marketplace package. Bangladesh-specific values become optional configuration or an optional provider adapter.

**Primary stack:** PHP 8.2+, MySQL 8 with native PDO prepares, Tailwind CSS 4, vanilla JavaScript, Leaflet, Composer, npm, PHPUnit-style custom test runner, Stripe Checkout, PayPal Checkout, optional SSLCOMMERZ, provider-neutral SMS, Web Push, Google Calendar OAuth, Redis through a contract, GD WebP, PHP_CodeSniffer, and a test-only QR decoder.

## Non-negotiable constraints

- Store timestamps in UTC; interpret event input in an explicit IANA timezone.
- Use BCP 47 locale tags, ISO 3166-1 alpha-2 country codes, ISO 4217 currencies, and E.164 delivery phones.
- Use exact decimal strings or integer minor units for money. Never use floating-point arithmetic and never add different currencies together.
- English is the canonical fallback. A missing translation falls back safely and is reported by tooling; it never exposes a raw key to a buyer.
- No country, currency, city, timezone, phone prefix, payment provider, or directions provider is hardcoded in controllers or views.
- Free registration and manual payment remain provider-free fallbacks. Hosted providers are optional, capability-declared adapters.
- Every callback is signed/validated, replay-safe, idempotent, and reconciled against locked database state.
- Third-party failure cannot undo already committed domain work unless it is inside the documented atomic settlement boundary.
- Buyer credentials are never shipped, logged, returned to HTML, exported, or included in support bundles.
- All state-changing browser routes are POST, CSRF-protected, role/permission-scoped, scalar-bounded, and rate-limited where abuse is practical.
- All new UI passes keyboard, screen-reader naming, 44-pixel target, 3-pixel focus, reduced-motion, 320/768/1440, light/dark, LTR/RTL, and no-overflow checks.
- Existing public URLs and data remain upgrade-compatible. Forward migrations are repeatable and preserve historical values.
- The commercial release must be built from a private release source or pipeline. Do not publish commercial-only modules back into the public Community Edition by accident.
- Do not alter or erase the historical MIT grant. Keep attribution and get a licensing review before commercial submission.

---

### Task 1: Establish the commercial edition boundary and release metadata

**Files:**
- Create: `COMMERCIAL_RELEASE.md`
- Create: `VERSION`
- Create: `THIRD_PARTY_NOTICES.md`
- Create: `config/edition.php`
- Create: `tests/Unit/CommercialReleaseBoundaryTest.php`
- Modify: `README.md`
- Modify: `.gitignore`
- Modify: `composer.json`

**Deliverables:**
- Identify the current public/MIT code as the Community Edition baseline.
- Define OEMS International as the enhanced marketplace edition without claiming prior MIT grants are revoked.
- Add semantic version, release channel, support URL placeholder, update-feed placeholder, and attribution manifest.
- Document that Envato is non-exclusive as of its 2026-07-01 Author Terms, while the same free public item may not be resold without substantial value.
- Record that new Envato Market author intake is currently paused, so the package must also work for other marketplaces and direct distribution.

- [ ] Write RED tests that fail when version, edition, notices, or public/commercial boundary documentation is absent.
- [ ] Add machine-readable edition metadata without exposing license keys or purchase codes at runtime.
- [ ] Audit every Composer/npm/font/icon/map dependency for redistribution and attribution.
- [ ] Decide commercial source custody before Task 2: private repository or private build pipeline based on the frozen public baseline.
- [ ] Run `composer validate --strict`, notice/license tests, secret scan, and `git diff --check`.
- [ ] Commit: `docs: establish international commercial edition boundary`

**Important:** This task does not make the current public repository private, rewrite history, or replace the historical MIT license. Those are explicit owner/legal operations outside an implementation commit.

### Task 2: Add international catalogs, neutral defaults, and upgrade-safe schema

**Files:**
- Create: `app/Support/CountryCatalog.php`
- Create: `app/Support/CurrencyCatalog.php`
- Create: `app/Support/LocaleCatalog.php`
- Create: `app/Support/TimezoneCatalog.php`
- Create: `database/migrations/2026-08-11-international-core.sql`
- Create: `tests/Unit/InternationalCatalogTest.php`
- Create: `tests/Unit/InternationalSchemaTest.php`
- Create: `tests/verify-international-migration-mysql.php`
- Modify: `database/schema.sql`
- Modify: `database/seed.sql`
- Modify: `database/demo_seed.sql`
- Modify: `.env.example`
- Modify: `config/app.php`
- Modify: `app/Services/PlatformSettingsService.php`
- Modify: `app/Views/admin/settings/edit.php`

**Schema/config changes:**
- change fresh-install defaults from `Asia/Dhaka`, `BDT`, and `Bangladesh` to `UTC`, `USD`, and no preselected country;
- add `profiles.country_code CHAR(2)`, widen locale for BCP 47, retain IANA timezone, and normalize delivery phone separately;
- add `venues.country_code CHAR(2)` without deleting historical country display text during upgrade;
- add `events.timezone VARCHAR(64)` and retain event currency;
- add configurable enabled locales, countries, currencies, platform timezone, tax mode, map center, and map zoom;
- add payment/integration tables needed by later tasks with guarded indexes and foreign keys;
- preserve historical BDT/Dhaka rows exactly while using neutral defaults only for new records.

- [ ] RED: fresh schema contains no Bangladesh defaults; populated current schema upgrades twice; partial values reconcile safely; invalid codes fail closed.
- [ ] GREEN: add catalog validation and a repeatable `information_schema`-guarded migration.
- [ ] Replace production seed copy, support phone, footer location, map center, and administrator profile with neutral values.
- [ ] Replace demo seed with fictional Toronto, London, Singapore, Nairobi, Sao Paulo, and Dhaka examples using several currencies/timezones; keep all identities and accounts fictional.
- [ ] Prove demo import is repeatable and does not seed geocoder caches or live credentials.
- [ ] Native MySQL gate: current populated database -> migration twice -> data/count/constraint integrity.
- [ ] Commit: `feat: add international platform foundations`

### Task 3: Implement complete localization, RTL, and locale-aware formatting

**Files:**
- Create: `app/Support/Translator.php`
- Create: `app/Support/LocalizedFormatter.php`
- Create: `resources/lang/en/`
- Create: `resources/lang/es/`
- Create: `resources/lang/ar/`
- Create: `resources/lang/bn/`
- Create: `scripts/check-translations.php`
- Create: `tests/Unit/TranslatorTest.php`
- Create: `tests/Unit/LocalizedFormatterTest.php`
- Create: `tests/Unit/InternationalUiContractTest.php`
- Modify: `Core/View.php`
- Modify: `bootstrap/app.php`
- Modify: all application views, mail templates, notification copy, ticket/PDF copy, validation messages, installer copy, and visible controller messages
- Modify: `resources/css/app.css`

**Behavior:**
- English is complete and canonical; Spanish and Arabic prove Latin/RTL behavior; Bangla remains an optional supported pack rather than the product identity.
- Locale selection follows user preference, then platform default, then English.
- User preference changes only after validation against enabled locales.
- Localize dates, times, currency display, counts, plural forms, statuses, errors, email, notifications, tickets, and accessibility labels.
- Keep buyer-authored CMS/event content as authored.

- [ ] Generate RED from hardcoded visible strings and incomplete catalog parity.
- [ ] Add `t()`, `trans_choice()`, and formatting boundaries; no global mutable locale state leaks across tests/requests.
- [ ] Replace physical left/right CSS with logical properties where direction matters.
- [ ] Add RTL sidebar, tables, forms, maps, dialog, ticket, email, and PDF regression tests.
- [ ] Check UTF-8 PDF font embedding and no missing glyphs for bundled packs.
- [ ] Browser gate: 320/768/1440, light/dark, English/Spanish/Arabic/Bangla, keyboard and screen reader semantics.
- [ ] Commit: `feat: add international localization and rtl support`

### Task 4: Make timezone, country, address, and phone handling globally correct

**Files:**
- Create: `app/Support/LocalDateTime.php`
- Create: `app/Support/PhoneNumber.php`
- Modify: profile service/controller/view/repository files
- Modify: organizer event and venue service/controller/view/repository files
- Modify: calendar, ICS, API, mail, notification, ticket, reports, and structured-data presenters
- Create: `tests/Unit/InternationalDateTimeTest.php`
- Create: `tests/Unit/InternationalAddressPhoneTest.php`

**Behavior:**
- Organizer enters local wall time plus event timezone; persistence is UTC and event timezone is retained.
- Reject nonexistent DST wall times and require an explicit interpretation for ambiguous times.
- Viewer output uses their validated timezone while displaying the event timezone context.
- Address forms do not universally require state/postcode and never assume a field order from one country.
- Delivery phones normalize to E.164; optional display formatting remains separate.
- Calendar/ICS/JSON-LD/API output uses correct offsets and timezone identifiers.

- [ ] RED across New York DST gap/fold, London DST, Kolkata/Dhaka non-DST, Pacific date boundary, and UTC.
- [ ] GREEN with immutable conversion helpers and no `date_default_timezone_set()` request leakage.
- [ ] Replace every `Asia/Dhaka`, `Bangladesh`, `+880`, and Dhaka map fallback outside optional/demo data.
- [ ] Run full route/view/source scan proving zero forbidden production defaults.
- [ ] Commit: `feat: make event time and location international`

### Task 5: Support exact multi-currency pricing and configurable tax snapshots

**Files:**
- Extend: `app/Support/Money.php`
- Create: `app/Services/PricingService.php`
- Create: `app/Contracts/TaxPolicyInterface.php`
- Create: `app/Services/ConfiguredTaxPolicy.php`
- Create: `database/migrations/2026-08-11-international-pricing.sql`
- Modify: event, coupon, registration, payment, refund, analytics, report, CSV, ticket, and admin setting boundaries
- Create: `tests/Unit/InternationalMoneyTest.php`
- Create: `tests/Unit/PricingServiceTest.php`
- Create: `tests/verify-international-pricing-mysql.php`

**Behavior:**
- Support currency-specific minor units, including zero-decimal and three-decimal currencies.
- Snapshot base, discount, tax, and total on registration; later setting changes do not rewrite history.
- Tax is optional, buyer-configured, labeled as inclusive/exclusive, and never inferred from IP.
- Event currency cannot change after financial activity exists.
- Reports group by currency and explicitly reject mixed-currency totals.

- [ ] RED with USD, EUR, JPY, BHD, very large exact values, coupon edges, tax rounding, and concurrent last-seat settlement.
- [ ] GREEN using decimal strings/integer minor units only.
- [ ] Add localized formatting while preserving canonical CSV/API numeric fields and currency codes.
- [ ] Native MySQL concurrency and DECIMAL round-trip verification.
- [ ] Commit: `feat: add exact international pricing`

### Task 6: Add provider-neutral hosted payments, webhooks, and refunds

**Files:**
- Create: `app/Contracts/PaymentGatewayInterface.php`
- Create: `app/Services/PaymentGatewayRegistry.php`
- Create: `app/Payments/StripeGateway.php`
- Create: `app/Payments/PayPalGateway.php`
- Create: `app/Payments/ManualGateway.php`
- Create: `app/Payments/SslCommerzGateway.php` as an optional adapter
- Create: hosted checkout, return, webhook, refund controllers and services
- Create: provider migrations, routes, views, tests, and provider fakes
- Modify: registration/payment services, bootstrap, settings, dashboards, mail, notifications, docs

**Behavior:**
- Stripe and PayPal are the international adapters; manual/free paths remain available; SSLCOMMERZ is optional and disabled unless configured.
- Each adapter declares supported currencies, refund capability, webhook algorithm, and required settings.
- Registration creates an exact pending attempt using a random request key and provider idempotency key.
- Webhook is authoritative; browser return cannot mark payment paid.
- Duplicate/out-of-order callbacks and refund events reconcile truthfully under row locks.
- Provider secrets are environment-owned or encrypted, never sent back to the browser after entry.

- [ ] RED unit/contract tests shared by every adapter.
- [ ] RED real-controller tests for CSRF, role, IDOR, open redirect, amount/currency tampering, replay, and signature failure.
- [ ] GREEN Stripe/PayPal sandbox adapters and fake-server acceptance; optional SSLCOMMERZ contract without core coupling.
- [ ] Add capability-aware UI so unavailable methods never appear.
- [ ] Add refund state machine: requested, processing, succeeded, failed; never invent external success.
- [ ] Commit: `feat: add international hosted payments`

### Task 7: Add provider-neutral communications and calendar sync

**Files:**
- Create: `app/Contracts/SmsTransportInterface.php`
- Create: `app/Services/SmsTransportRegistry.php`
- Create: `app/Sms/TwilioTransport.php`
- Create: durable SMS/push outbox repositories and workers
- Create: notification-preference and push-subscription controllers/views/routes
- Create: Google Calendar OAuth/sync services and callbacks
- Modify: mail outbox, SMTP settings encryption, registration/review/announcement lifecycle hooks
- Modify: `.env.example`, cron/worker docs, privacy/consent docs

**Behavior:**
- SMTP is universal; welcome mail is queued after verified registration workflow.
- Twilio is one disabled-by-default SMS adapter; future adapters register through the same contract.
- Browser push requires explicit permission and server-side opt-in; unsubscribe removes delivery material.
- Google Calendar is optional, state/PKCE-protected, encrypted at rest, revocable, and falls back to ICS.
- Preferences are purpose/channel-specific and do not retroactively imply consent.

- [ ] RED consent, opt-out, inactive/deleted user, provider failure, retry, duplicate delivery, and secret-redaction tests.
- [ ] GREEN durable outbox claim/lease/retry/dead-letter behavior with database fallback.
- [ ] Add localized templates and provider-neutral admin health diagnostics that expose no secrets.
- [ ] Commit: `feat: add international communication providers`

### Task 8: Add buyer-safe installer, upgrader, and system diagnostics

**Files:**
- Create: `install/` application and templates
- Create: `bin/oems`
- Create: `app/Services/InstallerService.php`
- Create: `app/Services/MigrationRunner.php`
- Create: `app/Services/SystemCheckService.php`
- Create: migration journal schema
- Create: Apache/Nginx examples and cron/worker examples
- Create: clean-install and upgrade test harnesses
- Modify: public entrypoint, bootstrap, `.env.example`, README

**Behavior:**
- Installer runs only when explicitly enabled and no install lock exists.
- Checks PHP/extensions, MySQL version/mode/native prepares, writable private paths, webroot boundary, HTTPS/proxy, cron/worker, mail, and optional providers.
- Creates application key, VAPID keys, admin account, neutral settings, schema, and install lock.
- Prevents default/admin/demo credentials in production.
- Upgrader journals migrations, supports retry, enters maintenance mode, and never asks buyers to re-import schema.
- Diagnostics redact credentials and generate an opt-in support bundle containing no PII/secrets.

- [ ] RED traversal, repeat install, forged lock, weak password, writable-webroot, partial migration, retry, and rollback-boundary tests.
- [ ] GREEN CLI plus progressive browser installer.
- [ ] Clean VM/container gate installs from only the release ZIP.
- [ ] Upgrade gate starts from current public baseline with realistic data and applies every migration twice safely.
- [ ] Commit: `feat: add secure installation and upgrades`

### Task 9: Complete discovery, media, permissions, Redis, QR, and code-quality gaps

**Files:** Existing files named by the superseded remaining-capabilities plan, adjusted to provider-neutral names.

**Deliverables:**
- deterministic `popular` event sort and MySQL FULLTEXT relevance with privacy-safe fallback;
- automatic bounded, metadata-free WebP uploads with alpha/orientation handling;
- authoritative runtime database role permissions with revisioned cache invalidation;
- optional Redis adapter for rate limits, cache, queues, and locks while preserving truthful database/file fallback;
- QR PNG round-trip decode verification and private artifact tests;
- PSR-12 lint in Composer and CI;
- remove remaining inline script/style requirements so CSP can drop `unsafe-inline` where compatible.

- [ ] Port the existing RED/GREEN definitions from Tasks 2, 3, 4, 10, and 11 of the superseded plan.
- [ ] Rename every provider-specific config/table/class assumption to a capability-neutral boundary.
- [ ] Run native MySQL, multiprocess Redis/fallback, upload corruption, permission revocation, QR decode, CSP, and full regression gates.
- [ ] Commit: `feat: complete marketplace quality capabilities`

### Task 10: Add marketplace branding, global demo, and buyer documentation

**Files:**
- Create: `docs/marketplace/` public documentation site
- Create: `docs/marketplace/assets/`
- Create: `resources/branding/`
- Create: `app/Services/BrandingService.php`
- Create: theme-token settings and validation
- Modify: layouts, mail, PDF, ticket, PWA manifest, icons, CMS defaults, demo seed

**Documentation chapters:**
1. requirements and choosing hosting;
2. installation by browser and CLI;
3. upgrading, backups, rollback boundaries, and migration order;
4. first administrator setup;
5. countries, locales, RTL, timezones, currencies, and tax configuration;
6. Stripe, PayPal, manual, optional SSLCOMMERZ, webhook, and refund setup;
7. SMTP, SMS adapters, Web Push, calendar, maps, Redis, cron, and workers;
8. roles, permissions, event lifecycle, privacy, exports, and private ticket storage;
9. branding, CMS, demo import/removal, and translation-pack authoring;
10. security hardening, HTTPS/proxy/HSTS, filesystem permissions, secret rotation, and incident response;
11. troubleshooting, support boundaries, update policy, and uninstall/data retention;
12. asset credits, third-party licenses, changelog, and known limitations.

- [ ] RED package/documentation contract for every chapter, screenshot alt text, links, and asset credit.
- [ ] Add bounded logo/favicon/color customization; no custom executable code input.
- [ ] Create fictional global demo data and redistributable self-created/vector assets only.
- [ ] Render and visually inspect English documentation; keep it publicly accessible without a purchase gate as required by CodeCanyon.
- [ ] Commit: `docs: add international buyer documentation`

### Task 11: Build a deterministic marketplace package and release automation

**Files:**
- Create: `scripts/build-marketplace-package.sh`
- Create: `scripts/verify-marketplace-package.sh`
- Create: `build/marketplace-manifest.txt` at build time only
- Create: CI release workflow
- Modify: `.gitattributes`, `.gitignore`, Composer scripts, npm scripts

**Archive layout:**

```text
OEMS-International-vX.Y.Z/
  application/
  documentation/
  changelog.txt
  license-and-attribution.txt
  readme-first.html
```

**Package exclusions:** `.git`, `.env`, tests not promised to buyers, node_modules, local uploads, ticket artifacts, cache, sessions, logs, screenshots not included in purchase, database dumps, internal plans/reports, IDE files, `.DS_Store`, presentation files, and all secrets.

- [ ] RED manifest tests for forbidden files, missing production files, symlinks, executable surprises, line endings, permissions, and secret patterns.
- [ ] Build production CSS/assets and install Composer production dependencies reproducibly.
- [ ] Include source code, production dependency licenses, Leaflet license, font/icon notices, and all required attribution.
- [ ] Generate SHA-256 checksums and a software bill of materials.
- [ ] Extract into an empty directory and run installer, lifecycle smoke, upgrade, asset, and health tests only from archive contents.
- [ ] Produce marketplace thumbnail/preview specification and truthful feature/requirement/limitation copy; preview-only assets must not leak into the main ZIP.
- [ ] Commit: `build: add marketplace release packaging`

### Task 12: Run international commercial release acceptance

**Files:**
- Create: `docs/release/OEMS-INTERNATIONAL-RELEASE-CHECKLIST.md`
- Create: `docs/release/OEMS-INTERNATIONAL-TEST-MATRIX.md`
- Create: `CHANGELOG.md`
- Modify: `README.md`
- Modify: `VERSION`

**Automated gates:**

```bash
composer test
composer check:syntax
composer lint
composer validate --strict
composer check-platform-reqs
composer audit
npm audit --audit-level=moderate
npm run build:assets
npm run test
git diff --check
```

**Acceptance matrix:**
- fresh install and upgrade from current public baseline on supported PHP/MySQL versions;
- English LTR, Spanish LTR, Arabic RTL, and Bangla optional pack;
- UTC, America/New_York, Europe/London, Asia/Kolkata, Asia/Dhaka, and Pacific/Auckland boundaries;
- USD, EUR, JPY, BHD, and historical BDT exact-money paths;
- free, manual, Stripe sandbox, PayPal sandbox, refund, callback replay/out-of-order/failure paths;
- SMTP, SMS fake/Twilio sandbox where available, Web Push, ICS, Google Calendar fake/sandbox;
- global map, restricted location, geocoder failure, no-map progressive fallback;
- guest/participant/organizer/admin, CSRF, role, permission, IDOR, method, rate limit, concurrency, stale writer, privacy, and private artifact boundaries;
- 320/768/1440, light/dark, LTR/RTL, keyboard, screen reader, reduced motion, contrast, print/PDF, CSV, and no-console-error checks;
- package extraction, documentation links, secret scan, SBOM, license/asset audit, and clean ZIP install.

- [ ] Obtain an independent whole-release Critical/Important review and fix every valid finding test-first.
- [ ] Confirm no Bangladesh production default remains; Bangladesh appears only in optional locale/demo/provider data.
- [ ] Confirm no marketplace name or purchase link is hardcoded inside the application package.
- [ ] Confirm buyer documentation is publicly accessible and written in English for non-developers.
- [ ] Confirm the exact public/free baseline and commercial additions are transparently documented for marketplace review.
- [ ] Tag the private commercial release `vX.Y.Z`; generate ZIP and checksums; do not deploy a buyer's production instance.
- [ ] Commit: `release: prepare oems international marketplace edition`

---

## Completion definition

The plan is complete only when all conditions are true:

- A clean buyer can install and upgrade OEMS without editing PHP or SQL.
- No country, city, timezone, currency, phone prefix, provider, or language is a hidden product assumption.
- Event scheduling is timezone-correct and DST-tested.
- Pricing, discounts, tax snapshots, settlement, refunds, analytics, and exports are exact and currency-safe.
- Stripe and PayPal work as optional international adapters; free/manual work without them; SSLCOMMERZ is optional.
- Email, SMS, push, and calendar integrations are consent-aware, durable, optional, and provider-neutral.
- English is complete; translation packs are replaceable; both LTR and RTL are release-tested.
- The installer, upgrader, diagnostics, backups guidance, and worker/cron instructions are buyer-ready.
- The package contains only licensed release files, complete English documentation, attribution, version/changelog, and no secrets or internal artifacts.
- The commercial edition provides substantial original value beyond the frozen public MIT baseline and is built from a private commercial source/pipeline.
- All automated, native MySQL, provider sandbox/fake, HTTP, browser, accessibility, privacy, security, package, license, and documentation gates pass with no unresolved Critical or Important findings.

## Marketplace reality as of 2026-08-10

- Envato Market author terms are non-exclusive, so an accepted author may sell the item on other marketplaces too.
- Envato currently has new author intake paused, and its July 2026 notice says inbound Code category applications are not planned to reopen at this stage. This plan therefore targets a marketplace-neutral ZIP and does not make CodeCanyon availability a release dependency.
- CodeCanyon requires organized editable source, correctly licensed/credited assets, English public documentation, and a single prepared ZIP. The release gates above intentionally exceed that minimum.
- The exact public MIT version should not be uploaded as a paid item with little added value. OEMS International must be materially differentiated and transparently documented.
