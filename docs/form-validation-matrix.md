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

## Automated guardrails

- `FormSystemTest` scans all 86 forms and fails for an unclassified form, `novalidate`, a POST form without `_token`, or a state-changing form without progress feedback.
- `tests/js/form-validation.test.mjs` verifies required/type/range/date/match/paired/file/conditional validation, destructive confirmation, accessible error summaries, correction behavior, and submit locking.
- Controller tests verify rejected input returns field errors without mutation; service tests verify the same rules cannot be bypassed by skipping the browser.
- Syntax, asset, and full PHP suites are the final release gate.
