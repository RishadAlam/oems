# Homepage Featured Event Card Design

## Design read

OEMS is a consumer event-discovery homepage for participants. The featured section should feel like a polished marketplace: calm, current, visually rich, and fast to scan. The existing brand tokens, Manrope typography, Phosphor icons, light/dark themes, routes, copy, and save behavior remain unchanged.

Design dials: variance 6, motion 3, density 5.

## Problem

The current two-card layout assigns one card a special wide treatment and uses unequal columns. This makes the first event dominate without a product reason, creates inconsistent image and content proportions, leaves large empty card bodies, and makes the second event look secondary even though both are equally featured. Long venue text further disrupts footer alignment.

## Reference synthesis

- Luma presents popular events as a consistent collection with cover image and concise event identity.
- Meetup emphasizes chronological scanning and surfaces date/time, organizer context, and participation signals without making one arbitrary result oversized.
- Ticketmaster prioritizes date, title, and venue in a predictable order for rapid comparison.

OEMS will borrow the information hierarchy, not the visual branding, from these products.

## Selected direction

Render the two featured events as equal editorial cards in a responsive grid.

- One column below 768px and two equal columns from 768px.
- Both images use the same 16:9 crop and reserved aspect ratio.
- The body begins with category, then a two-line title.
- Date/time and location use compact icon-led rows with no redundant `Date` or `Place` mini-labels.
- Venue text may wrap naturally but must never force horizontal overflow.
- The footer remains anchored to the bottom with price on the left and save/view actions on the right.
- Card links and controls retain 44px touch targets and visible focus states.
- Hover motion is limited to a subtle lift/image scale and is disabled by the existing reduced-motion policy.
- Empty state remains unchanged.

## Accessibility and resilience

- Keep semantic `article`, `time`, and `address` elements.
- Keep descriptive image alt text and accessible save labels.
- Keep keyboard order: image/title, save control, event link.
- Support long titles, long venues, free and paid prices, guest and participant controls.
- Preserve dark/light token contrast and avoid overlay text on photography.

## Acceptance criteria

1. The homepage contains no wide featured-card modifier.
2. Both featured cards have equal grid tracks and the same media ratio.
3. Titles are clamped to two lines with a reserved minimum height.
4. Metadata uses one compact shared row per date and location.
5. Footer alignment is stable for different title and venue lengths.
6. Layout has no horizontal overflow at 390, 768, 1280, or 2048 CSS pixels.
7. Existing event links, favorite behavior, semantic metadata, and empty state pass unchanged.
8. Source and compiled CSS stay synchronized and asset cache versions are bumped.

