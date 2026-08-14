# Global Operational Table Layout Design

## Status

Approved through the user's standing instruction to proceed without follow-up questions and deliver the strongest professional result.

## Design read

OEMS is an enterprise event-operations product used by administrators and organizers. Its list screens should feel restrained, information-dense, and predictable, borrowing the scanning discipline of Carbon and Fluent while retaining the existing OEMS tokens, typography, Phosphor icons, and light/dark themes.

- Design variance: 3/10
- Motion intensity: 2/10
- Visual density: 7/10
- Redesign mode: preserve and standardize

## Problem

The project has one intended responsive table system but several incompatible markup contracts. The most visible defect appears on Content management > Home banners:

- `.admin-table-actions` is applied directly to `<td>`, changing a native table cell into a flex container.
- The action header is visually hidden, leaving an unexplained blank column.
- The action cell has no `data-label`, so mobile card mode cannot identify it.
- Controls wrap vertically even when horizontal space is available.
- long CMS text does not use the shared overflow-safe value treatment.
- schedule values are raw SQL timestamps with no labels or readable date formatting.
- an enabled but expired banner can be labelled Active even though it is not delivered publicly.

The same structural action defect exists in Categories. Coupons uses a similarly shaped but undefined action wrapper. One participant table lacks a caption. Several operational tables omit the desktop containment class, and the mobile grid does not keep multiple value children in the value column.

## Options considered

### A. Strengthen the shared operational-table contract (selected)

Keep native table semantics on wider screens, use the existing responsive card transformation below 768px, standardize actions and value grouping, and migrate every violating operational table.

Benefits:

- fixes the root cause once;
- preserves dense desktop scanning;
- provides deliberate mobile cards;
- avoids route, controller, and form behavior changes;
- makes future violations testable.

### B. Patch only the CMS banner table

This is smaller but leaves the same defect in CMS pages, FAQs, Categories, and Coupons. It would also preserve the mobile auto-placement defect.

### C. Replace tables with bespoke cards at every viewport

This would reduce information density, duplicate established table behavior, and create a larger accessibility and maintenance surface. It is not appropriate for operational directories.

## Shared contract

Every operational data table must use this hierarchy:

```html
<div class="organizer-table-wrap">
  <table class="operations-table organizer-table">
    <caption class="sr-only">Descriptive table purpose</caption>
    <thead>
      <tr>
        <th scope="col">Record</th>
        <th scope="col">Status</th>
        <th scope="col">Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td data-label="Record">
          <div class="organizer-table__primary organizer-table__value"><strong>Record title</strong><small>Record details</small></div>
        </td>
        <td data-label="Status"><span class="status-chip status-chip--neutral">Draft</span></td>
        <td class="organizer-table__action" data-label="Actions">
          <div class="admin-table-actions"><a class="button button--quiet button--compact" href="/admin/categories/1/edit">Edit</a></div>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

Rules:

1. The `<td>` remains a table cell. Flex layout belongs only on its inner action group.
2. Action headers are visible and named `Action` or `Actions` to match the row label.
3. Every `<th>` uses `scope="col"`.
4. Every table has a descriptive screen-reader caption.
5. Every row cell has a non-empty `data-label` matching its header for mobile cards.
6. Free text, identifiers, URLs, email addresses, and other unbounded values use `.organizer-table__value`.
7. Multi-line primary content is grouped in `.organizer-table__primary` so it stays together beside its mobile label.
8. Multi-action groups use `.admin-table-actions`; the undefined `.table-actions` alias is retired.
9. No action-specific class may hide the mobile label pseudo-element.

## Responsive behavior

### Desktop and tablet, 768px and wider

- The table keeps semantic table layout.
- `operations-table` establishes a 760px minimum content width.
- `.organizer-table-wrap` contains horizontal overflow rather than compressing columns into collisions.
- Headers and values remain left aligned except the compact action column.
- The action column uses intrinsic width, stays right aligned, and keeps desktop button labels on one line.
- Multi-action groups remain in one row when space is available.

### Mobile, below 768px

- The wrapper does not cause page-level horizontal scrolling.
- The header is visually hidden while remaining available to assistive technology.
- Each record becomes one bordered card.
- Each cell is a two-column label/value grid.
- The pseudo-label always occupies column one.
- Every real child of the cell occupies column two, including secondary lines.
- The action cell remains labelled and its inner group wraps into full-width, 44px-minimum controls as needed.

## CMS banner presentation

The banner row will use structured content:

- title and optional subtitle grouped as the primary value;
- schedule displayed with `Starts` and `Ends` labels;
- valid timestamps rendered through `<time>` using `M j, Y, g:i A`;
- missing start rendered as `Immediately`;
- missing end rendered as `No end date`;
- delivery state derived by a pure presenter using one controller-provided clock in the configured application timezone:
  - Disabled when `is_active` is false;
  - Scheduled when enabled and start is in the future;
  - Ended when enabled and end is in the past;
  - Live when enabled and within its delivery window;
  - Unknown when an enabled legacy row has a malformed or reversed schedule.

Start and end equality with the current instant are inside the live window, matching the repository's inclusive public-visibility boundaries. Strict parsing accepts only stored `Y-m-d H:i:s` values. Machine-readable `<time>` values include the configured UTC offset. Malformed or inconsistent legacy schedules render `Schedule unavailable` and no `<time>` element; the UI never guesses, rewrites, or loosely parses a stored date.

This separates public delivery truth from the persisted enable flag without changing storage or activation routes.

## Visual styling

- Reuse existing OEMS surfaces, borders, status tones, radii, and button components.
- Do not add shadows, gradients, animations, or new dependencies.
- Keep rows compact and vertically centered.
- Use semantic status chips: Live uses success, Scheduled uses warning, and Ended, Disabled, and Unknown use neutral.
- Keep destructive Disable/Deactivate actions in the existing danger button treatment.
- Use tabular date numerals only where already inherited from the project typography.

## Accessibility

- Preserve native table semantics on larger screens.
- Captions identify table purpose.
- Column headers use `scope="col"`.
- Mobile visual labels come from the same text as the headers.
- Action groups remain keyboard reachable in DOM order.
- Buttons retain existing labels, confirmation behavior, CSRF inputs, and minimum target size.
- `<time datetime>` exposes machine-readable timestamps.
- Status meaning is always present in text and never conveyed by color alone.

## Scope

In scope:

- all 25 `.organizer-table` instances across the 21 current view files;
- project-wide table markup invariants;
- CMS, Categories, and Coupons multi-action migration;
- participant caption correction;
- shared desktop/mobile table CSS;
- banner schedule and delivery-state presentation;
- source and compiled CSS parity;
- cache/version publishing required by a CSS change.

Out of scope:

- URL, route, controller endpoint, or form-field changes;
- changing stored banner dates;
- deleting suspicious historical records;
- replacing operational tables with a third-party grid library;
- pagination, sorting, or filtering features not requested here;
- redesigning analytics charts, forms, public event cards, or unrelated page shells.

## Verification

Automated coverage must prove:

- every operational table uses the shared classes, caption, scoped headers, and responsive labels;
- action headers and cells match;
- `.admin-table-actions` never appears directly on a `<td>`;
- `.table-actions` no longer appears;
- the shared source and compiled CSS contain desktop containment, safe values, mobile label/value placement, and responsive action-group rules;
- CMS banner states render truthfully for disabled, scheduled, ended, and live fixtures;
- malformed, non-scalar, reversed, and exact-boundary schedule fixtures are deterministic under a frozen `Asia/Dhaka` clock;
- existing CSRF and action form behavior remains intact;
- asset versions and service-worker precache references match.

Browser checks cover CMS, Categories, Coupons, and Participants at 390, 768, 1280, and 2048 CSS pixels in light and dark themes, plus long content and empty states. Acceptance requires no document overflow, no detached controls, no column overlap, readable dates, truthful banner status, and stable keyboard order.
