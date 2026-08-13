# Homepage CMS Banner Design

## Goal

Correct the public homepage presentation of active CMS banners so one or more announcements use the available width, keep a controlled height, remain readable with sparse content, and work consistently across desktop, mobile, light, and dark themes.

## Root cause

The CMS data and scheduling path are working. `PublicSiteContentProvider` supplies active home banners in the configured order, and the homepage renders them after the main hero.

The visual defect is in the view. Every announcement is rendered as a generic `dashboard-panel` inside a fixed two-column grid. A single banner therefore occupies only half of the row and leaves the other half empty. The generic card also places a wide image above a content area, so a banner with only its required title looks like an unfinished admin card. The section uses the broad marketing section spacing utility even though this is a compact announcement surface.

## Design direction

This is a preserve redesign of an existing public content block. It keeps the current OEMS brand, Manrope typography, blue accent, surface tokens, page shell, CMS fields, banner order, scheduling behavior, destination links, and URL structure.

- Design variance: 4. The announcement is distinct from event cards but stays within the existing visual system.
- Motion intensity: 2. Existing link, focus, and button feedback are enough.
- Visual density: 5. The announcement is prominent without creating another full-screen hero.

## Considered patterns

### Full-width version of the current stacked card

This removes the empty column but makes a 16:7 image very tall at the page shell width. Sparse content still reads as a separate footer attached to a large image.

### Image with overlaid copy

This creates a strong campaign treatment, but uploaded images have unpredictable light and dark regions. A fixed overlay cannot guarantee text contrast, and aggressive crops may remove important artwork.

### Full-width split announcement row

Selected. Each banner becomes one horizontal row at desktop widths. The image receives roughly two thirds of the row and the content receives the remaining third. Additional active banners stack vertically in the configured order. On smaller screens, the image sits above the content.

This pattern preserves wide artwork, gives title, subtitle, and action a stable reading area, removes the unused column, and keeps total height controlled.

## Component contract

The announcement section uses dedicated public-facing classes instead of dashboard classes:

- `home-announcements` controls compact section spacing.
- `home-announcements__list` stacks active banners with a consistent gap.
- `home-announcement` defines the bordered surface and responsive split layout.
- `home-announcement__media` owns cropping and image sizing.
- `home-announcement__body` contains the title, optional subtitle, and optional destination action.

The desktop row uses a controlled minimum height and an upper media height rather than deriving its height from the full image ratio. The image uses `object-fit: cover` and centers the artwork. The mobile layout restores a wide landscape aspect ratio so the image remains recognizable.

## Content behavior

- The required title always renders as the announcement heading.
- The optional subtitle renders only when present.
- The optional same-site destination renders as one `Learn more` action only when present.
- A title-only banner remains visually complete because the body is intentionally aligned within the split row.
- Multiple banners remain full width and stack instead of producing empty grid cells.
- The main homepage hero, category navigation, and featured events remain unchanged.

## Accessibility

- The section keeps its `Platform announcements` accessible name.
- Each banner remains an `article` with an `h2` heading.
- The uploaded image uses a concise alt value derived from the visible title so the asset is identifiable outside its visual context.
- Destination links keep visible text, a clear focus indicator, and a decorative arrow hidden from assistive technology.
- DOM order matches the visual order on desktop and mobile: image, title, summary, action.

## Responsive and theme behavior

- At desktop widths, the image and content form a two-column row.
- Below the large breakpoint, the row stacks and the image uses a wide landscape ratio.
- The component uses existing surface, line, ink, muted ink, accent, and shadow tokens, so it follows the page-wide light or dark theme without a local theme inversion.
- Long titles and subtitles wrap within the content column without horizontal overflow.

## Testing

A rendered-view regression test will supply one representative banner and assert the consumer-visible contract:

- the dedicated announcement component is rendered;
- the legacy dashboard panel and fixed two-column grid are not used for the banner;
- the banner image has a meaningful alt value;
- title, subtitle, and destination remain escaped and visible;
- the primary homepage hero remains present.

The focused test must fail before implementation. Browser verification will then inspect the active database-backed banner at desktop and mobile widths in both themes. It will measure that the announcement fills the page shell, does not overflow, and keeps the next category section in a predictable flow.

## Out of scope

- Changing CMS routes, tables, scheduling, ordering, activation, upload limits, or validation
- Replacing user-uploaded banner artwork
- Changing the main homepage hero or category navigation
- Adding a carousel or automatic motion
- Adding a new design system or dependency
