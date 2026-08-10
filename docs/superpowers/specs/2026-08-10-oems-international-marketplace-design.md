# OEMS International Marketplace Design

**Status:** Accepted replacement for the Bangladesh-specific completion direction

**Date:** 2026-08-10

**Target:** A self-hosted, international event-management product suitable for commercial distribution on CodeCanyon and other software marketplaces

## 1. Product Direction

OEMS must be region-neutral by default and configurable for a buyer's country, language, timezone, currency, map provider, payment providers, messaging providers, branding, and legal content. Bangladesh remains supported through configuration and optional adapters, but no Bangladesh-specific value is allowed to define the core product.

The commercial product is a self-hosted PHP application, not a hosted SaaS. A buyer installs one licensed instance, chooses platform defaults, configures their own third-party credentials, and owns the resulting operational data.

## 2. Current Commercial Blocker

The current GitHub repository is public and MIT licensed. Existing recipients already have the broad rights granted by that license, and those grants cannot be retroactively erased by changing a file later. Envato also warns that an item already available free elsewhere needs substantial original value beyond the free version.

The recommended product structure is therefore:

- freeze the current public repository as the **OEMS Community Edition** baseline;
- develop the international installer, provider adapters, localization framework, marketplace documentation, premium theme polish, and commercial packaging as a materially enhanced **OEMS International** edition;
- keep commercial-only implementation in a private repository or private release pipeline;
- retain all required third-party license notices and transparently identify the MIT community baseline;
- obtain a licensing review before submission; do not claim that an MIT grant already received by third parties has been revoked;
- distribute OEMS International non-exclusively so it may be sold on CodeCanyon and other marketplaces under their applicable buyer licenses.

This is a product and release decision, not a request to rewrite Git history or silently remove attribution.

## 3. International Defaults

| Concern | Marketplace default | Buyer configurable | Invariant |
|---|---|---|---|
| Language | English (`en`) | Enabled locale packs and default locale | No visible core string is hardcoded in a view |
| Direction | LTR | Per-locale LTR/RTL metadata | Arabic acceptance proves the layout works in RTL |
| Timezone | UTC | Platform and per-user IANA timezone | Database timestamps remain UTC |
| Currency | USD | ISO 4217 allowlist and per-event currency | Mixed currencies are never summed |
| Country | None selected | ISO 3166-1 alpha-2 | No country or city is prefilled in buyer forms |
| Phone | Empty | E.164 international numbers | No `+880` assumption |
| Map | World view | Tile, geocoder, directions, center, zoom | No Dhaka coordinates in defaults |
| Payment | Free + manual disabled | Stripe, PayPal, manual, optional regional adapters | Provider callbacks are signed and idempotent |
| SMS | Disabled | Transport adapter selected by buyer | Consent and unsubscribe are mandatory |
| Date/number display | Locale-aware | Per-user locale and timezone | Stored machine values stay canonical |
| Tax | Disabled | Buyer-defined rates and labels | OEMS is not a tax engine and performs no tax advice |

## 4. Locale Architecture

Use versioned PHP translation catalogs, for example `resources/lang/en/*.php`, addressed through stable keys. English is the canonical fallback. A locale registry contains the BCP 47 tag, native label, direction, fallback, enabled state, and translation completeness metadata.

The commercial package includes reviewed English and acceptance packs that prove the architecture across Latin and RTL layouts. Additional languages are installable without editing controllers or views. Locale packs translate application strings; buyer-authored event, CMS, FAQ, email, and legal content remains buyer content unless a future content-translation module is installed.

Required formatting boundaries:

- `IntlDateFormatter` for local dates and times;
- `NumberFormatter` or an exact safe fallback for currency display;
- IANA timezones, with conversion to UTC before persistence;
- `Locale`, `Currency`, `Country`, and `Timezone` catalogs validated server-side;
- `dir="rtl"` and logical CSS properties for RTL locales;
- localized validation, mail, notification, ticket, PDF, CSV-heading, installer, and error text;
- Unicode-safe search and PDF font coverage for bundled locales.

Do not translate identifiers, URLs, enum values, audit facts, payment references, or QR tokens.

## 5. Country, Address, and Phone Model

Profiles and venues store a normalized `country_code` while retaining bounded human address fields. Address line, locality/city, administrative area, postal code, and country must not assume one national ordering. Forms render country-aware labels but do not make postal code, state, or phone universally required.

Phones are normalized to E.164 when used for delivery. Raw display formatting is separate from the normalized delivery value. SMS opt-in is explicit and channel-specific. The product does not infer country from IP address.

## 6. Time and Calendar Model

All persisted operational timestamps are UTC. Each event records the timezone used to interpret its local schedule. Organizer forms accept local wall time plus an IANA timezone, validate daylight-saving gaps/ambiguities, and store UTC values. Public pages render in the viewer's timezone with the event timezone clearly available. ICS and Google Calendar exports include correct timezone semantics.

Historical events keep their original timezone even when platform defaults change.

## 7. Money, Currency, Fees, and Tax

Every event has exactly one ISO 4217 currency. Registrations, coupons, payments, refunds, analytics, invoices/receipts, and exports preserve it. Monetary calculations use integer minor units or exact decimal strings; floats are prohibited.

The platform may enable multiple currencies but cannot convert between them without an explicit future exchange-rate module. Dashboards group totals by currency. Zero-decimal and three-decimal currencies are supported through currency metadata rather than a universal two-decimal assumption.

Optional tax configuration supports buyer-defined inclusive or exclusive rates, labels, registration snapshots, and exact totals. It does not determine legal tax obligations, file returns, or silently infer tax from IP geolocation.

## 8. Payment Provider Architecture

The core exposes a `PaymentGatewayInterface` and keeps free registration plus optional manual review. Marketplace adapters are independent modules:

- Stripe Checkout for broad card and supported local-method coverage;
- PayPal Checkout for a second international provider;
- SSLCOMMERZ as an optional Bangladesh adapter, not a core dependency;
- future adapters registered without changing registration lifecycle code.

Each adapter declares supported currencies and capabilities. The locked registration amount, currency, event, and user remain authoritative. Create-session calls use idempotency keys. Return URLs are presentation only; signed server callbacks determine settlement. Duplicate, delayed, out-of-order, malformed, and conflicting callbacks are tested. Refunds are provider-capability-aware and never falsely labeled successful.

No marketplace package contains live credentials. Buyers provide their own accounts and remain responsible for provider availability, onboarding, fees, and regional restrictions.

## 9. Communication Provider Architecture

Email, SMS, browser push, and calendar integrations sit behind contracts and durable outboxes:

- SMTP remains the required universal email path;
- SMS uses `SmsTransportInterface`, with Twilio as one optional adapter rather than a hardcoded dependency;
- browser push uses VAPID keys created during installation;
- Google Calendar OAuth is optional and revocable;
- ICS download remains the provider-free fallback;
- notification preferences, consent timestamp/source, unsubscribe, retries, and safe logs apply consistently.

Communication failure never rolls back completed domain work. Provider credentials are environment-owned or encrypted using an installation-specific application key.

## 10. Global Maps and Location Privacy

Leaflet remains the default map UI, with buyer-configurable HTTPS tiles, attribution, geocoder, directions allowlist, center, and zoom. The default center is a neutral world view. OpenStreetMap services are not promised as unlimited production infrastructure; documentation requires buyers to select providers appropriate to their traffic and terms.

Existing public versus confirmed-participant location privacy remains authoritative in every consumer, API, structured-data response, email, ticket, and calendar artifact.

## 11. Buyer Installation and Upgrade Experience

The release includes both a browser installer and a CLI path. Installation is available only before a lock file exists and performs:

1. PHP extension, MySQL, filesystem, HTTPS/proxy, and cron/worker checks;
2. database connection verification using buyer-supplied least-privilege credentials;
3. application URL, timezone, default locale, country, currency, and mail configuration;
4. first administrator creation with a generated or buyer-entered strong password;
5. migrations and seed of non-demo defaults;
6. application-key and VAPID generation;
7. atomic `.env` creation with restrictive permissions where supported;
8. installation lock creation and permanent installer-route disablement.

Upgrades use a migration journal, preflight checks, maintenance mode, backup reminder, resumable migrations, cache invalidation, and a documented rollback boundary. Existing databases are never told to re-import `schema.sql`.

## 12. Marketplace Customization

Buyers can change product name, logo, favicon, contact details, default locale/currency/timezone, footer, email identity, legal pages, homepage CMS content, and a bounded color-token theme from administrator settings. Custom CSS or executable PHP is not accepted through the browser.

Demo content uses fictional events across several continents and no real brands, famous people, financial accounts, or unlicensed media. Demo import is optional and repeatable. Production seed is region-neutral.

## 13. Distribution and Support Package

The generated marketplace archive contains only release files:

- application source and production dependencies permitted for redistribution;
- installer and migration runner;
- `.env.example`, requirements checker, cron/worker examples, and web-server examples;
- public English HTML or PDF documentation written for non-developers;
- administration, organizer, participant, provider, security, backup, upgrade, troubleshooting, and uninstall/data-retention guides;
- changelog, semantic version, third-party notices, source/asset credits, and buyer-license notice;
- demo credentials only in demo documentation, never in production seed;
- no `.git`, `.env`, test artifacts, local uploads, logs, cache, database dump, secrets, internal reports, or unrelated files.

A deterministic package manifest and checksum prove the ZIP contents. A clean-container install from only the ZIP is a release gate.

## 14. Quality and Acceptance

Release acceptance covers:

- PHP 8.2 and supported MySQL 8 versions;
- Apache and Nginx/PHP-FPM documentation plus the built-in server only for local development;
- Chromium, Firefox, and WebKit-compatible browser behavior;
- 320, 768, and 1440 pixel layouts in light/dark and LTR/RTL;
- at least two currencies with different minor-unit rules;
- at least four IANA timezones spanning DST and non-DST regions;
- provider fakes plus sandbox acceptance for enabled payment adapters;
- fresh installation, upgrade from the public baseline, retry, rollback boundary, and uninstall/data-retention documentation;
- OWASP-oriented security, dependency, secret, license, accessibility, privacy, and package audits;
- no unresolved Critical or Important review findings.

## 15. Deliberate Non-Goals

- automatic currency conversion;
- automatic legal, VAT, GST, or sales-tax determination;
- IP-based country or locale tracking;
- mandatory paid third-party services;
- a marketplace purchase-code lock that prevents legitimate offline installation;
- shipping live API credentials;
- claiming every language is professionally translated;
- rewriting Git history or pretending the existing MIT release was never public.

## 16. Success Definition

OEMS International succeeds when a buyer in any supported country can install it without editing source code, select their locale/timezone/country/currency, configure at least one suitable payment and communication path, run the full event lifecycle, update safely, and understand every operational requirement from the included documentation. Bangladesh works as one configuration among many, not as the product's default identity.

## 17. Marketplace Sources Checked

- Envato Code Item Preparation & Technical Requirements: <https://help.author.envato.com/hc/en-us/articles/360000471583-Code-Item-Preparation-Technical-Requirements>
- Envato Common Rejection Factors for Code Items: <https://help.author.envato.com/hc/en-us/articles/360000536823-Common-Rejection-Factors-for-Code-Items>
- Envato asset and redistribution rules: <https://help.author.envato.com/hc/en-us/articles/360000424643-What-Assets-Can-I-Use-In-My-Items>
- Envato Market Author Terms, revised 2026-07-01: <https://help.author.envato.com/hc/en-us/articles/41371538488473-Envato-Market-Author-Terms>
- Envato author-intake status: <https://help.author.envato.com/hc/en-us/articles/53981083605913-Author-applications-are-temporarily-closed>
