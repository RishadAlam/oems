# Homepage Journey Pattern Redesign

## Purpose

Replace the oversized, table-like "How OEMS works" block with a compact journey pattern that helps participants and organizers scan their next three actions without changing routes, meaning, or page order.

## Experience direction

- Keep the existing section anchor, heading, audience paths, copy, and calls to action.
- Use the page's semantic color tokens instead of forcing a dark panel inside the light theme.
- Stack the section label, title, and supporting copy into one readable introduction.
- Present each audience path as its own responsive journey card with clear visual separation.
- Replace generic numeric markers with meaningful icons for discovery, registration, arrival, creation, submission, and check-in operations.
- Use a subtle vertical connector to communicate sequence without making the steps look like form rows or a comparison table.
- Keep both paths visible at the same time; do not hide content behind tabs or interaction.

## Responsive behavior

- Use two columns on large screens and one column on small screens.
- Keep cards equal-height when side by side and allow their calls to action to align naturally at the bottom.
- Preserve at least 44px interactive targets and avoid horizontal overflow at 320px.
- Maintain clear focus states and semantic ordered-list structure.

## Visual constraints

- One blue accent family, applied through existing `--accent` and `--accent-soft` tokens.
- Existing `--surface-*`, `--ink*`, `--line`, and shadow tokens must control both themes.
- No oversized empty intro area, split-panel divider, inverted section theme, floating pill badge, or decorative gradients.
- Motion remains limited to the existing restrained hover/focus treatment.

## Verification

- Add contract coverage for semantic headings, audience labels, meaningful step icons, token-based styling, and responsive card layout.
- Build production CSS and run the focused PHP test.
- Run the complete PHP and JavaScript suites.
- Verify the section in desktop and mobile widths in both light and dark themes.

