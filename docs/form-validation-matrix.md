# OEMS form validation matrix

Audited 2026-08-12. The application contains 86 forms across public, account, participant, organizer, and administrator views. Every form now declares one interaction pattern, every POST form includes a CSRF token, and every state-changing form provides submission feedback (the location form uses its dedicated live-status flow).

## Shared interaction contract

| Form kind | Browser behavior | Server behavior |
| --- | --- | --- |
| `entry` | Progressive validation after blur, an accessible error summary on submit, specific field messages, and submit locking with contextual progress copy | Revalidates every accepted field, normalizes scalar input, rejects unknown/unsafe values, and returns field-keyed errors |
| `action` | Confirmation for destructive operations and single-submission locking | CSRF, authorization, current-state/ownership checks, and idempotent or conflict-safe transitions |
| `filter` | Native field semantics without blocking optional searches | Allowlists sort/status/date/query values and bounds result ranges |
| `special` | Purpose-built feedback for geolocation and check-in workflows | CSRF plus strict coordinate/token validation and authorization |

All entry controls preserve label/help/error relationships with `aria-describedby`; invalid controls receive `aria-invalid`; the first invalid field is focused from the summary. Required fields are identified in text as well as native markup. File inputs validate type, size, and count before upload while the server remains authoritative.

## End-to-end field families

| Area | Forms and required browser rules | Authoritative server boundary | Coverage |
| --- | --- | --- | --- |
| Authentication | Sign in: email, password. Registration: name 2–100, email ≤190, password 8–128 with confirmation, role, terms. Recovery/change use the same email/password contract. | `AuthController`, `AuthService`; account status, verified identity, password hashing, token expiry, and credential checks remain server-only. | `FormSystemTest`, `AuthControllerTest`, `AuthServiceTest` |
| Profile | Name 2–100; bounded phone, bio, address, city, country, postal code; safe URL; non-future birth date; allowlisted locale/timezone. | `ProfileController`; avatar MIME/size/storage checks and current-account ownership are server-only. | `FormSystemTest`, `ProfileControllerTest` |
| Public contact/newsletter | Contact name 2–100, valid email ≤190, subject 3–180, message 10–4000. Newsletter requires valid email ≤190. | `ContactService`, `NewsletterService`; honeypot handling, double opt-in, enumeration safety, and queue integrity are server-only. | `FormSystemTest`, `ContactServiceTest`, `NewsletterServiceTest` |
| Event discovery/location | Optional bounded search, category/date/radius filters; coordinates are submitted only after successful browser geolocation. | Public event/location services allowlist filters, clamp radii, validate latitude/longitude, and store reduced-precision coordinates. | `PublicEventControllerTest`, `LocationServiceTest`, JavaScript location tests |
| Participant registration | Required attendee/payment choices are exposed only for the event’s current registration state; inputs are bounded and labeled. | `RegistrationService`; account eligibility, capacity, duplicate registration, price, coupon, payment state, and concurrent inventory checks are server-only. | `ParticipantRegistrationControllerTest`, `RegistrationServiceTest` |
| Participant payment/cancellation | Payment reference/receipt policy and cancellation reason/confirmation are mirrored in the UI; progress is explicit. | `RegistrationService`, payment upload/storage policy; ownership, payment transition, refund/cancellation eligibility, and stale-state checks remain server-only. | Registration/payment controller and service tests |
| Waitlist/favorites/notifications | Required waitlist payment data where applicable; action forms confirm intent and lock once submitted. | Waitlist/favorite/notification services enforce account ownership, event state, uniqueness, promotion order, and idempotency. | `WaitlistServiceTest`, participant controller tests |
| Reviews and replies | Participant rating/comment and organizer reply lengths are bounded; review moderation actions expose clear state feedback. | `ReviewService`; attendance eligibility, ownership, one-review policy, publish/hide transitions, and stale-state checks are server-only. | `ReviewServiceTest`, `ReviewControllerTest` |
| Organizer events | Category, title 5–180, description 30–20000, schedule ordering, registration deadline, capacity 1–100000, nonnegative bounded price, visibility, and waitlist choice; image type/size/count policies. | `EventService`; organizer approval, ownership, venue eligibility, lifecycle transitions, registrations, image storage, and concurrency remain server-only. | `FormSystemTest`, `EventServiceTest`, organizer event controller tests |
| Organizer venues | Name ≤160, address ≤190, city/country ≤100, coordinate pair and ranges, optional safe URL, capacity 1–100000. | `VenueService`; ownership, persisted coordinate pairing, deletion eligibility, and geocoding response validation are server-only. | `VenueServiceTest`, organizer venue controller tests |
| Organizer coupons | Required code/type/value and validity bounds; percentage/amount limits change with discount type; schedule ordering and usage limits. | `CouponService`; ownership, uniqueness, active-event applicability, redemption totals, stale status, and concurrent usage remain server-only. | `CouponServiceTest`, coupon controller tests |
| Announcements/check-in | Required bounded announcement content; check-in token/identifier requirements and dedicated scanner feedback. | Announcement/check-in services enforce event ownership, recipient eligibility, ticket validity, duplicate scans, and audit records. | Announcement and check-in controller/service tests |
| Admin CMS/blog/settings | Required bounded plain text, safe same-site links, ordered schedules, and image ≤5 MB; blog title 3–180/body 40–50000; settings match catalog bounds and formats. | `CmsService`, `BlogService`, `PlatformSettingsService`; HTML/control-character rejection, immutable page slugs, file storage cleanup, publishing state, and transactional writes are server-only. | `FormSystemTest`, `CmsServiceTest`, `BlogServiceTest`, `PlatformSettingsServiceTest` |
| Admin campaigns/contact | Campaign subject 3–180 and message 10–4000; contact reply 2–4000; queue/status actions disclose progress and destructive scope. | `NewsletterService`, `ContactService`; confirmed-recipient fanout, deduplication, outbox rollback, message state, and audit history are server-only. | `FormSystemTest`, `NewsletterServiceTest`, `ContactServiceTest` |
| Admin moderation/operations | Rejection reasons and notes are bounded; categories/settings/maintenance editors expose required fields; filters and status actions use allowlisted choices. | Admin event/people/payment/category/report services enforce role, self/super-admin protection, lifecycle eligibility, compare-and-swap state, transactionality, and audit logging. | Admin controller/service suites plus `FormSystemTest` |

## Field-level parity ledger

Every field error below is rendered next to its control as `#<control>-error` and linked from the form summary. Cross-field or state errors link to the nearest section heading. “Optional” means the browser accepts an empty value; any supplied value still has to pass the listed rule on both sides.

### Public, authentication, and account forms

| Form | Editable fields and browser constraint | Matching server rule | Server-only boundary |
| --- | --- | --- | --- |
| Sign in | `email`: required, email, ≤190; `password`: required, ≤1024; `remember`: optional checkbox | `AuthController` normalizes email and delegates credential validation to `AuthService` | Active account, verified credential hash, throttling, and session rotation |
| Create account | `role`: required `participant`/`organizer`; `name`: required 2–100; `email`: required email ≤190; `password`: required 8–128; `password_confirmation`: required and equal; `terms`: required checkbox | `AuthController`/`AuthService` repeat every length, format, allowlist, match, and acceptance rule | Unique email, role availability, password hashing, and verification token lifecycle |
| Forgot password | `email`: required email ≤190 | `AuthController`/`AuthService` normalize and validate the same email boundary | Enumeration-safe response, account state, token invalidation, and rate limiting |
| Reset password | `password`: required 8–128; `password_confirmation`: required and equal | `AuthController`/`AuthService` repeat bounds and equality | Reset-token ownership, expiry, single use, and session invalidation |
| Change password | `current_password`: required ≤1024; `password`: required 8–128; `password_confirmation`: required and equal | `AuthController`/`AuthService` repeat all three rules | Current-hash verification and session rotation |
| Profile | `name`: required 2–100; `phone`: optional ≤30; `bio`: optional ≤2000; `date_of_birth`: optional valid non-future date; `gender`: optional allowlist; `address_line`: optional ≤190; `city`, `country`: optional ≤100; `postal_code`: optional ≤30; `website`: optional HTTP(S) URL ≤255; `locale`: required `en`/`bn`; `timezone`: required `Asia/Dhaka`/`UTC` | `ProfileController` repeats normalization, bounds, URL/date rules, and choice allowlists | Current-account ownership; email and role are read-only and ignored from the form |
| Contact | `name`: required 2–100; `email`: required email ≤190; `subject`: required 3–180; `message`: required 10–4000 | `ContactService` repeats all bounds and email validation | Honeypot, control-character rejection, queue/audit integrity, and throttling |
| Newsletter subscribe | `newsletter_email`: required email ≤190 | `NewsletterService` normalizes and validates email | Honeypot, enumeration-safe result, double opt-in, deduplication, and throttling |

### Participant forms

| Form | Editable fields and browser constraint | Matching server rule | Server-only boundary |
| --- | --- | --- | --- |
| Event registration | `coupon_code`: optional ≤80; `channel`: optional allowlist; `transaction_reference`: optional 6–190, with payment fields required when a balance remains | `RegistrationService` repeats bounds, channel allowlist, and conditional payment requirement | Eligibility, duplicate registration, live capacity, event state, authoritative price/coupon, and transactional inventory |
| Promoted waitlist payment | `channel`: required `bank_transfer`/`mobile_banking`/`cash_deposit`; `transaction_reference`: required 6–190 | `RegistrationService` repeats allowlist and bounds | Ownership, active claim window, amount, duplicate reference, and payment transition |
| Cancel registration | `reason`: required ≤500 | `RegistrationService` rejects blank/oversized reasons | Ownership, cancellable state, ticket/payment effects, and seat release transaction |
| Leave waitlist | `reason`: required ≤500 | Waitlist controller/service repeats reason rules | Ownership, queue state, promotion race, and idempotent removal |
| Event review | `rating`: required integer 1–5; `review`: required 10–2000 | `ReviewService` repeats range and length | Attendance eligibility, one-review ownership, moderation reset, and event completion |

### Organizer forms

| Form | Editable fields and browser constraint | Matching server rule | Server-only boundary |
| --- | --- | --- | --- |
| Event create/edit — details | `title`: required 5–180; `category_id`: required available option; `venue_id`: optional option; `description`: required 30–20000; `speaker`: optional ≤190; `tags`: optional ≤500 and up to 12 comma-separated tags | `EventService` repeats bounds, IDs, normalization, and tag count | Organizer approval/ownership, category status, and venue ownership |
| Event create/edit — access and schedule | `location_visibility`: required `public`/`registered`; `arrival_notes`: optional ≤500; `start_date`, `end_date`, `registration_deadline`: required datetimes with end after start and deadline ≤ start; `capacity`: required integer 1–100000; `ticket_price`: required 0–9999999.99; `waitlist_enabled`: boolean; `map_url`: optional trusted HTTPS map URL ≤500 | `EventService` repeats choice, length, numeric, URL, and date-order rules | Lifecycle/edit eligibility, registered-seat floor, currency, trusted map host, and concurrent updates |
| Event media | `banner`: optional JPEG/PNG/WebP ≤5 MB; `gallery[]`: optional JPEG/PNG/WebP, ≤5 MB each, maximum 6 | `EventService`/upload policy repeat MIME, byte, count, and decoded-pixel rules | Image decoding, secure generated names, atomic replacement, and storage cleanup |
| Venue create/edit | `name`: required ≤160; `address_line`: required ≤190; `city`, `country`: required ≤100; `postal_code`: optional ≤30; `latitude`: optional −90…90; `longitude`: optional −180…180 and paired with latitude; `map_url`: optional HTTPS trusted map URL ≤500; `capacity`: optional integer 1–100000 | `VenueService` repeats every bound, coordinate pairing/range, and URL rule | Organizer ownership, approved map host, geocoding response validation, and deletion eligibility |
| Coupon create/edit | `code`: required 3–80 and `[A-Za-z0-9][A-Za-z0-9_-]*`; `event_id`: optional owned event; `discount_type`: required `fixed`/`percentage`; `discount_value`: required 0.01–9999999999.99 and ≤100 for percentage; `usage_limit`: optional integer 1–1000000; `starts_at`, `expires_at`: optional ordered datetimes | `CouponService` repeats format, allowlists, numeric limits, and schedule order | Organizer/event ownership, uppercase uniqueness, redemption count, active state, and concurrent usage |
| Announcement compose | `subject`: required ≤180; `message`: required ≤1000 | Announcement service repeats plain-text and length rules | Event ownership/state, eligible audience recalculation, request-key replay protection, and delivery fanout |
| Ticket check-in | `code`: required 9–300 | Check-in service repeats token bounds and accepted ticket format | Event ownership, ticket authenticity, registration state, duplicate scan, and audit record |
| Organizer review reply | `reply`: required 2–1000 | `ReviewService` repeats length and plain-text validation | Review/event ownership, published-review state, and one current reply |

### Administrator forms

| Form | Editable fields and browser constraint | Matching server rule | Server-only boundary |
| --- | --- | --- | --- |
| Category create/edit | `name`: required ≤100; `slug`: required ≤120 and lowercase hyphen pattern; `parent_id`: optional available option; `sort_order`: required integer 0–1000000; `icon`: optional ≤100; `description`: optional ≤500 | Category service repeats bounds, pattern, parent, and integer rules | Slug uniqueness, circular-parent prevention, protected/category-in-use transitions |
| Blog create/edit | `title`: required 3–180; `slug`: optional lowercase hyphen pattern ≤200; `category`: optional ≤100; `meta_title`: optional ≤190; `meta_description`: optional ≤300; `excerpt`: required 20–500; `body`: required 40–50000; `cover_image`: optional JPEG/PNG/WebP ≤5 MB | `BlogService` repeats text, slug, SEO, and upload rules | Slug uniqueness, optimistic `updated_at`, publication state, decoded pixels, and storage cleanup |
| CMS page | `title`: required ≤180; `content`: required ≤20000; `meta_title`: optional ≤190; `meta_description`: optional ≤320 | `CmsService` repeats every bound and plain-text policy | Immutable route slug, control-character rejection, and transactional update |
| CMS FAQ | `question`: required ≤255; `answer`: required ≤5000; `category`: optional ≤100; `sort_order`: required integer 0–1000000 | `CmsService` repeats bounds and integer rule | Status transition, content sanitization, and transactional ordering |
| CMS banner | `title`: required ≤180; `subtitle`: optional ≤255; `image`: required on create, optional on edit, JPEG/PNG/WebP ≤5 MB; `link_url`: optional same-site path ≤500; `starts_at`, `ends_at`: optional with end after start; `sort_order`: required integer 0–1000000 | `CmsService` repeats text, upload, path, schedule, and integer rules | Same-origin enforcement, decoded pixels, storage cleanup, and active state |
| Newsletter campaign | `subject`: required 3–180; `message`: required 10–4000 | `NewsletterService` repeats both bounds | Confirmed-recipient audience, fanout deduplication, queue transaction, and immutable sent content |
| Contact reply | `reply`: required 2–4000 | `ContactService` repeats length and plain-text rules | Message state, recipient identity, outbox transaction, and audit history |
| Event/organizer rejection | `reason`: required ≤500 | Admin moderation services repeat blank/length checks | Role, target state, stale transition, organizer self-protection, and audit log |
| Payment verify/reject review | `note`: optional ≤500 | Payment service repeats note boundary | Payment/current registration state, stale-state token, financial transition, and audit log |
| Platform settings | All required: `site_name` ≤80; `contact_email` valid email ≤190; `support_phone` ≤40; `site_tagline` ≤160; `footer_blurb` ≤240; `footer_location` ≤120; `home_hero_kicker` ≤80; `home_hero_title` ≤100; `home_hero_copy` ≤240; `default_seo_description` ≤320 | `PlatformSettingsService` repeats required, format, and length catalog | Super-admin authorization, catalog key allowlist, cache invalidation, and transactional write |
| Maintenance mode | `confirmation`: required exact phrase shown in the form | Operations controller compares the exact normalized phrase | Super-admin authorization, requested target state, self/session safety, and audit log |

### Filter and action forms

All remaining 54 forms are read-only filters or single-purpose actions rather than data-entry editors. Filter controls (`q`, `status`, category/event/date/radius/sort/page-size selectors) are optional and bounded or allowlisted on the server; invalid choices fall back safely instead of mutating data. Every action form carries only a CSRF token plus explicit identifiers/current-state tokens. The server rechecks authorization, ownership, target state, optimistic/replay tokens, and transition eligibility; destructive actions require clear confirmation and all actions use the form-level single-submit lock.

## Automated guardrails

- `FormSystemTest` scans all 86 forms and fails for an unclassified form, `novalidate`, a POST form without `_token`, or a state-changing form without progress feedback.
- `tests/js/form-validation.test.mjs` verifies required/type/range/date/match/paired/file/conditional validation, destructive confirmation, accessible error summaries, correction behavior, and submit locking.
- Controller tests verify rejected input returns field errors without mutation; service tests verify the same rules cannot be bypassed by skipping the browser.
- Syntax, asset, and full PHP suites are the final release gate.
