# Global result summary design

## Goal

Replace the visually orphaned result-count sentence used by dashboard filters with one compact, professional result-summary pattern. The component must make the count easy to scan, keep the label clear, announce one coherent phrase to assistive technology, and remain truthful after filtering.

## Design read

This is a preserve-mode enterprise admin refinement for operators. The visual language is restrained and operational, drawing on Fluent and Carbon information hierarchy while keeping the existing OEMS tokens, Manrope typography, radius system, and responsive toolbar architecture.

Design dials:

- Design variance: 3 of 10
- Motion intensity: 1 of 10
- Visual density: 6 of 10

No new dependency, animation, icon, shadow, or nested card is needed.

## Scope

The shared result-summary primitive applies to these seven filtered-result surfaces:

1. Admin users
2. Admin organizers
3. Admin event moderation
4. Admin review moderation
5. Organizer events
6. Admin payments
7. Organizer participants

Public event-discovery counts, calendar counts, dashboard KPI cards, coupon totals, pagination, payment amounts, and capacity values are outside this component. They communicate different concepts and retain their existing patterns.

## Root cause

The five toolbar summaries are currently 14px muted sentences with an 18px number. On wide screens, the fixed filter form sits at the right edge of a bordered toolbar while the small sentence floats alone at the left. The result has no grouping, no clear label hierarchy, and no deliberate relationship to the 48px controls.

Payments and Participants express the same filtered-result concept as helper copy in panel headings, so they should use the same visual and accessibility primitive rather than preserve another one-off treatment.

Organizer events has a related correctness defect: the page displays the unfiltered organizer total while rendering a status-filtered list. Its result summary must use the filtered list count.

## Chosen component

Each result summary is a compact mini-metric:

- A minimum 44px accent-soft count tile uses bold, tabular numerals.
- A two-line text group provides context and subject, such as `Matching` and `Users`.
- The component uses existing semantic color tokens and is neutral. A result count is not a success, warning, or error state.
- The component has no extra border or shadow because the toolbar or panel already provides containment.
- The tile grows horizontally for large counts and never clips the number.

Visible copy by surface:

| Surface | Context | Subject | Accessible phrase |
| --- | --- | --- | --- |
| Admin users | Matching | Users | `N matching user/users` |
| Admin organizers | Matching | Organizers | `N matching organizer/organizers` |
| Admin events | In queue | Events | `N event/events in this queue` |
| Admin reviews | In queue | Reviews | `N review/reviews in this queue` |
| Organizer events | Matching | Events | `N matching event/events` |
| Admin payments | Matching | Payments | `N matching payment/payments` |
| Organizer participants | Matching | Registrations | `N matching registration/registrations` |

## Markup contract

The shared primitive uses `.result-summary` and its child classes. Toolbar placement adds `.filter-toolbar__summary` as a layout adapter.

```html
<p class="result-summary filter-toolbar__summary"
   role="status"
   aria-live="polite"
   aria-atomic="true">
    <strong class="result-summary__count" aria-hidden="true">15</strong>
    <span class="result-summary__copy" aria-hidden="true">
        <span class="result-summary__context">Matching</span>
        <span class="result-summary__subject">Users</span>
    </span>
    <span class="sr-only">15 matching users</span>
</p>
```

The visible fragments are hidden from the accessibility tree so the atomic live region announces the complete phrase once. The visually hidden phrase preserves correct singular and plural grammar. The summary is noninteractive and does not enter the keyboard tab order.

## Placement

### Filter toolbars

The summary remains the first direct child and the filter form remains its sibling. From 640px, the toolbar aligns complete summary and form units to the bottom. The count tile therefore aligns with the 48px control row, while labels stay above controls. Existing flex wrapping remains the safe fallback.

### Result panels

Payments and Participants place the summary in the panel heading as a named right-side summary. The existing heading, icon, and explanatory text remain intact. On narrow screens it moves below the heading content; on wider screens it sits at the right without changing the filter form or table.

## Responsive behavior

- Under 640px: summary is full width, appears before controls or records, and keeps the count and label together.
- 640px to 1279px: complex filter forms may wrap below the summary; compact forms may share a row.
- 1280px and wider: the summary and form share one intentional row when space permits.
- The component has no fixed width and supports zero, singular, multi-digit, and large counts.
- No horizontal scrolling or absolute positioning is introduced.

## Theme and accessibility

- Count tile: `--accent-soft` background and `--accent` text.
- Primary label: `--ink`.
- Context label: `--ink-muted`.
- Existing token contrast already passes WCAG AA in both themes.
- `role="status"`, `aria-live="polite"`, and `aria-atomic="true"` expose a real live-region contract.
- The number is not communicated through color alone.
- The component remains legible at 200 percent zoom.

## Data correctness

Organizer events must derive the displayed filtered count from `count($events)`. The list is unpaginated, so this is the exact result count for the active status filter. Other surfaces continue using their filtered pagination totals or filtered queue arrays.

## Testing

Automated tests will enforce:

- All seven surfaces use exactly one semantic `.result-summary`.
- The five toolbar summaries remain direct children of their toolbars.
- Count, copy, context, subject, and one complete accessible phrase are present.
- Zero, singular, and plural phrases are grammatically correct.
- Organizer event filtering reports the filtered count.
- Source and compiled CSS contain the shared visual contract.
- The stylesheet cache version is bumped everywhere it is referenced.

Browser checks will cover representative complex and compact toolbars plus a panel summary at 390, 768, 1280, and 2048px in light and dark themes. Checks include layout grouping, overflow, 200 percent zoom, accessible status text, keyboard order, and truthful filtered counts.

## Non-goals

- No redesign of filter controls, tables, pagination, public event counters, or dashboard KPI cards.
- No new design-system dependency.
- No changes to routes, query names, form behavior, or visible domain terminology beyond making result labels consistent and grammatical.
