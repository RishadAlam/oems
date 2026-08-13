# Global Dashboard Page Header Design

## Goal

Give every administrator, organizer, and participant page one clear, responsive page-header hierarchy. The title must remain the strongest element, supporting copy must stay readable, and page actions must align predictably without crowding or overflow.

## Root cause

The internal application currently has two names for the same header pattern:

- 38 views use the styled `.dashboard-page-heading` component;
- 19 views use `.dashboard-page-header`, which has no source or compiled CSS rule.

Tailwind Preflight resets unstyled headings to inherited typography. On the affected pages this makes the H1 render as ordinary 16-pixel text, lets section headings visually outrank it, and leaves the action button in normal document flow below the copy. The newsletter screenshot is one instance of a shared class-contract defect rather than a page-specific spacing issue.

## Design direction

This is a preserve-mode redesign of a dense event-management product. It uses the existing OEMS surface, ink, accent, spacing, radius, focus, and light/dark theme tokens with restrained enterprise hierarchy.

- Visual variance: 3/10
- Motion intensity: 2/10
- Information density: 6/10

The design should feel intentional and operational, not decorative. Public marketing heroes, public event/article headers, authentication cards, and error states keep their context-specific patterns.

## Selected approach

Make `.dashboard-page-heading` the only dashboard page-header root and migrate all 19 unstyled views to it. Preserve their semantic `<header>` elements, copy, routes, action labels, forms, and authorization conditions.

The canonical anatomy is:

1. a semantic page-header root with `.dashboard-page-heading`;
2. a text group containing `.dashboard-kicker`, exactly one H1, and a concise description;
3. an optional sibling action or action group.

A CSS compatibility alias will not be added because it would preserve the naming fork and allow future regressions.

## Typography and spacing

- Kicker: 11 pixels, bold, uppercase, tracked, muted, with a 16-pixel accent icon.
- H1: 30/36 pixels on small screens and 36/40 pixels from 640 pixels, semibold, with tight display tracking.
- Description: 14/24 pixels, muted, and constrained to 42rem for comfortable reading.
- H1 follows the kicker by 12 pixels; the description follows the H1 by 8 pixels.
- Header zones use a 24-pixel gap.
- Interactive actions keep a minimum 44-pixel target.

## Responsive behavior

### Below 640 pixels

- Text and actions stack in reading order.
- The header uses the available width without horizontal scrolling.
- Long titles wrap naturally.
- Direct actions remain intrinsic touch targets rather than stretching into oversized banners.

### From 640 pixels

- Text and actions form one row with 24 pixels between them.
- The text group may shrink and wrap; the action zone remains intact.
- Both zones align on their lower edge so the primary action sits beside the description rather than beside the kicker or below the whole header.
- Space is distributed between the content and action without creating artificial empty cards or decorative surfaces.

## Accessibility

- Every routed dashboard page keeps exactly one H1.
- The H1 remains inside the canonical page-heading container.
- Kicker icons remain decorative and hidden from assistive technology where already implemented.
- Existing link/button semantics, focus indicators, form behavior, and accessible names are preserved.
- The migration must not introduce skipped heading levels within the page-heading region.

## Scope

The migration covers the 19 affected admin, organizer, and participant views for newsletter, blog, contact, operations, trash, coupons, registrations, tickets, waitlist, and certificates. The 38 already-canonical dashboard headers serve as the visual baseline and remain compatible.

Public heroes, public listings, event-detail mastheads, blog articles, authentication headings, verification results, maintenance screens, and error pages are intentionally out of scope because they use distinct content and layout semantics.

## Regression contract

Automated checks must prove that:

- no application view retains `.dashboard-page-header`;
- every routed internal dashboard view with the shared kicker/H1/description anatomy uses `.dashboard-page-heading`;
- every canonical page header contains one H1;
- the source stylesheet keeps the shared typography and spacing tokens;
- the compiled stylesheet keeps the stacked mobile layout and the bottom-aligned row from 640 pixels;
- no obsolete compatibility selector is emitted.

Browser checks must confirm representative admin pages at 390, 768, 1280, and 2048 pixels, with no overlap or horizontal overflow and with the title visually dominant in both themes.

## Out of scope

- Changing page copy, routes, controller behavior, permissions, or action availability;
- introducing a component framework or JavaScript layout logic;
- turning internal page headers into marketing-style hero sections;
- regenerating deployment packages when no source asset changes are required.
