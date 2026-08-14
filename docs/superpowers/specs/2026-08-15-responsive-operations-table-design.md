# Responsive Operations Table Design

## Scope

Fix the broken operational table layout on:

- `/admin/newsletter`
- `/admin/contact`
- `/organizer/coupons`

The routes, data, copy, status mapping, CSRF protection, confirmation behavior, and actions remain unchanged.

## Root cause

The three views use `table-shell` and `operations-table`. `table-shell` has no source or compiled CSS, while `operations-table` only establishes a minimum width. The browser therefore controls cell sizing, alignment, and spacing. Long content consumes the available width and visually joins neighboring headings such as `Status` and `Recipients`.

The project already has a complete responsive table system through `organizer-table-wrap`, `organizer-table`, and `organizer-table__action`. It supplies desktop gutters and dividers, contained horizontal overflow, and a labeled mobile card layout.

## Design direction

Use the existing operational table system instead of introducing a campaign-specific card or another table component.

- Design variance: 3, predictable and structured.
- Motion intensity: 1, no automatic motion.
- Visual density: 7, compact but readable operational data.
- Theme strategy: retain the existing semantic CSS variables in light and dark modes.
- Shape system: retain the existing 14px mobile record radius and dashboard panel radius.

## Shared markup contract

Every affected non-empty list uses:

```html
<div class="organizer-table-wrap mt-6">
    <table class="operations-table organizer-table">
        <caption class="sr-only">Descriptive table name</caption>
        <!-- column headings and records -->
    </table>
</div>
```

Each action cell uses `organizer-table__action` and keeps its existing `data-label`. Existing `data-label` values remain the mobile field names.

Campaign subjects and messages must wrap safely when they contain a long URL or another unbroken value. The action remains one line and at least 44 CSS pixels high.

## Responsive behavior

- Desktop: full-width table, left-aligned column headings, consistent cell padding, row dividers, and a right-aligned action column.
- Tablet: the table may scroll inside its wrapper when necessary, but the document must not scroll horizontally.
- Mobile below 768px: the heading row is visually hidden and each record becomes a bordered card with visible field labels derived from `data-label`.
- Long content: subject, message, date, status, counts, and actions cannot overlap or force document overflow.

## Accessibility

- Add a concise screen-reader caption to each table.
- Preserve semantic `table`, `thead`, `tbody`, `th`, and `td` elements.
- Preserve all form labels, CSRF tokens, confirmation prompts, and focus behavior.
- Keep buttons and links at least 44 CSS pixels high.
- Use only existing theme tokens and status components.

## Testing

1. Add a failing rendered-view test covering all three affected views.
2. Assert the shared wrapper, shared table classes, caption, mobile `data-label` attributes, and action-cell class.
3. Render long campaign content to guard wrapping and column separation.
4. Run focused layout/controller tests, asset and syntax checks, then the full PHP test suite.
5. Verify 390px, 768px, 1280px, and 2048px layouts in light and dark themes, including keyboard focus and document overflow.

## Acceptance criteria

1. `Status` and `Recipients` no longer appear joined.
2. Desktop columns have visible spacing and stable alignment.
3. Tablet overflow stays inside the table wrapper.
4. Mobile records render as labeled cards without a visible table header.
5. Long content wraps without clipping or page overflow.
6. Queue, review, edit, and status actions retain their original behavior.
7. All automated and browser checks pass.
