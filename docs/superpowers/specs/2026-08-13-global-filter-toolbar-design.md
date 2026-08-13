# Global Filter Toolbar Design

## Goal

Replace the inconsistent top-level filter bars used by administrator and organizer list pages with one responsive, accessible toolbar pattern. The toolbar must keep each label above its control, align controls and actions on a shared lower edge, center the result summary independently, and avoid overflow at narrow widths.

## Root cause

The existing `organizer-toolbar` accepts two incompatible DOM shapes:

- people-directory pages wrap each label and control in `.field-group`;
- moderation and organizer-event pages render a label and select as direct form siblings.

The shared form is a flex row at larger widths. A direct label therefore becomes a separate horizontal flex item instead of the heading for the select. The parent toolbar also bottom-aligns its result summary with the tallest form content, which makes the left and right zones look unrelated. A previous lower-edge alignment rule improved the button baseline but could not correct the invalid visual grouping.

## Selected approach

Create a semantic `filter-toolbar` component and migrate all five true top-level toolbars:

- administrator event moderation;
- administrator review moderation;
- administrator users;
- administrator organizers;
- organizer events.

The component has four explicit parts:

1. `.filter-toolbar` — the bordered surface and responsive layout container;
2. `.filter-toolbar__summary` — a live result count with a tabular, prominent number;
3. `.filter-toolbar__form` — the control layout and search landmark;
4. `.filter-toolbar__field` and `.filter-toolbar__actions` — vertical label/control groups and action grouping.

This is preferable to styling every GET form globally because event discovery, homepage search, analytics range forms, payment filters, and participant filters are purposeful panel layouts rather than top-level toolbars. Their existing grid semantics should remain intact.

## Layout behavior

### Narrow and medium screens

- Summary and form stack vertically.
- The form fills the available width.
- Controls use one column on phones and two columns when space permits.
- Search fields may span both columns.
- The action group spans the available row and its button remains a full-width touch target.
- No horizontal scrolling is introduced.

### Large screens

- Summary sits on the left and is vertically centered within the toolbar.
- The form sits on the right as one atomic layout unit; its field and action must never split onto separate toolbar rows.
- At extra-large widths the form is max-content, non-shrinking, and non-wrapping. If a wide directory form cannot share the first line with the summary, the entire form moves to the next line and remains right-aligned.
- One-field moderation forms use a compact two-column field/action grid from medium widths so they remain on the summary line without waiting for the extra-large layout.
- Every field keeps its label directly above its control.
- All controls and the action button share the same lower edge.
- Search fields use a bounded 16rem desktop width and status selectors use 10rem, preventing both excess empty space and uncontrolled compression.

## Visual and interaction rules

- Reuse the existing surface, border, ink, accent, radius, and focus tokens in both themes.
- Keep controls at least 44 pixels high and buttons at least 44 pixels high.
- Use restrained spacing and no new shadows or decorative gradients.
- Preserve visible labels; do not replace them with placeholders.
- Give each filter form an accessible search label.
- Mark the summary as polite live content so refreshed result totals are announced without interrupting users.
- Preserve all current query names, values, methods, endpoints, auto-submit behavior, and button labels.
- A one-field toolbar should be approximately 104 pixels tall at desktop widths: 72 pixels of labeled-control content plus 32 pixels of vertical padding.

## Regression contract

Automated checks must prove that:

- all five toolbar views adopt the shared component classes;
- no view retains the legacy `organizer-toolbar` class;
- every toolbar label/control is wrapped by `.filter-toolbar__field`;
- compiled CSS vertically centers the summary zone;
- compiled CSS bottom-aligns fields and actions at large widths;
- desktop forms use max-content, `flex: none`, and `nowrap` so an action can never wrap below its field;
- the one-field compact modifier keeps its field and action in two columns from medium widths;
- narrow layouts are full-width and actions do not overflow;
- the CSS asset version and public cache key are refreshed after the style change.

## Out of scope

- Changing filtering behavior or controller query handling;
- redesigning filter panels embedded inside analytics, reports, payments, participants, blog, contact, event discovery, or homepage content;
- adding JavaScript or a new component framework.
