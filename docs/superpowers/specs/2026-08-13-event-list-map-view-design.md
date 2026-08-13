# Event List and Map View Design

## Objective

Redesign the public event discovery List/Map control so each selected view uses the available space, communicates its state clearly, and remains usable across phone, tablet, and desktop layouts. Preserve the existing event filters, location privacy rules, routes, card content, and Leaflet data contract.

## Problem

The current desktop discovery wrapper always reserves a map column. In List mode the map is hidden but its column remains, constraining results to two cards and leaving a large empty area. Map also has three different meanings: a replacement view on phones, a map appended below every card on tablets, and a split list/map workspace on desktops. The toolbar keeps map announcements in the location status area, the map panel repeats guidance, and a marker can focus a hidden card on a narrow screen.

## Design direction

Use a calm, utility-first discovery pattern within the existing OEMS token system. The location preference and result-view selector remain one compact toolbar, but they have separate status semantics. The switch is a clearly labelled two-option segmented control with one accent-soft active state, a visible focus state, and no nested double-border effect.

The result view is explicit on the discovery wrapper:

- **List** is the canonical default. The map is hidden and results use the full content width: one column on phones, two on tablets, and three on wide desktops.
- **Map** is a focused map on viewports below 1024px. Results remain available through List and stay visible until a usable map has loaded, so a map failure never removes the canonical list.
- **Map** becomes a split workspace at 1024px and above: two result columns on the left and one sticky map panel on the right.

This adaptive contract preserves useful event context on larger screens while making the selector honest and immediately useful on smaller screens.

## Structure and copy

The control bar contains:

1. Location preference controls and their own live status.
2. A labelled `View` control group containing List and Map.
3. A separate screen-reader live region for view and map state announcements.

The discovery wrapper starts with `data-event-discovery-view="list"`. JavaScript changes that value together with the pressed state and panel visibility. Desktop split CSS applies only when the value is `map`; List never reserves an empty map track.

The map panel uses one concise header:

- Title: `Event map`
- Context: a mapped-event count and a privacy-safe explanation that only public event locations appear.

Remove the repeated instruction to use List. The selected control and responsive behavior already communicate that action.

## Interaction contract

- Activating List updates both pressed states, hides the map panel, restores results, sets wrapper state to `list`, and announces the number of visible results.
- Activating Map updates both pressed states, reveals and initializes the map, sets wrapper state to `map`, and announces whether the map is shown alone or alongside results.
- Below 1024px, results hide only after Leaflet reports a usable tile layer. Invalid payloads, no public markers, absent Leaflet, and tile failures keep results visible and expose recovery guidance.
- Crossing the 1024px breakpoint while Map is selected reconciles result visibility without changing the selected control.
- Activating a marker while results are hidden switches to List before focusing its matching event card. Focus never moves into hidden content.
- BFCache cleanup and restoration retain the selected view without duplicate listeners.
- Reduced-motion behavior remains unchanged.

## Visual system

- Toolbar: raised surface, one subtle border, 18px radius, compact 16px padding, balanced horizontal alignment at tablet and desktop widths.
- View group: small `View` label, 44px minimum controls, muted inactive state, accent-soft active state, no extra ring that reads as a second border.
- Map panel: one raised surface with 18px radius and restrained padding; map canvas uses a smaller internal radius so hierarchy reads as panel then content.
- Spacing: 20px from filters to toolbar and 32px from toolbar/search preview to results; 24px gaps inside discovery layouts.
- Motion: only existing short state transitions; respect `prefers-reduced-motion`.

## Accessibility

- Use `role="group"` with `aria-labelledby` for the segmented view selector.
- Keep `aria-pressed` on the two mutually exclusive buttons.
- Maintain at least 44px targets and the global 3px focus-visible outline.
- Separate geolocation messages from view/map announcements.
- Preserve the labelled map region and inline fallback.
- Keep the complete semantic event list as the canonical fallback.

## Data and privacy

No backend filtering changes are permitted. The map payload continues to include only published, non-deleted events whose exact location visibility is public and whose coordinates are valid. Restricted addresses, coordinates, directions, and arrival notes remain excluded.

## Verification

Automated coverage must prove:

- initial List wrapper state and grouped control markup;
- List restores full results and Map sets explicit wrapper state;
- Map hides results below 1024px only after successful load;
- Map retains results at 1024px and above;
- map failures preserve the list;
- marker activation never focuses a hidden card;
- desktop split selectors are scoped only to Map state;
- source and compiled CSS/JS asset versions stay synchronized.

Browser QA must cover 390, 768, 1280, and 2048px in light and dark themes, both List and Map states, with no horizontal overflow or empty reserved column.
