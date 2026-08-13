# Admin Filter Toolbar Alignment Design

## Goal

Align the result count, labeled filter controls, and filter action button along one consistent lower edge on desktop while preserving the existing stacked mobile layout.

## Root cause

The shared `.organizer-toolbar` correctly bottom-aligns its direct children at the responsive breakpoint. Its nested form overrides that intent with `sm:items-center`. Each field group includes a label above its control, while the action button has no label. Centering those unequal-height children raises the button above the input and select baselines.

Measured at the reported 2048 x 432 viewport:

- Search input bottom: 333.5 px
- Apply filters button bottom: 319.5 px
- Difference: 14 px
- Computed form alignment: `center`

## Approaches considered

1. Correct the shared responsive form alignment to `items-end`. Recommended because it fixes the underlying layout contract for every shared toolbar.
2. Add a page-specific Users override. Rejected because Organizers, Events, and Reviews use the same broken pattern.
3. Add an invisible label spacer above each action button. Rejected because decorative markup would hide the CSS defect and complicate accessibility.

## Design

Keep the current HTML, control dimensions, spacing, colors, and breakpoint behavior. Change only the desktop form cross-axis alignment from centered to bottom-aligned. Mobile remains a single column because the responsive alignment applies only at `sm` and wider.

The result count remains aligned by the toolbar parent. Labeled input and select controls, plus the unlabeled submit action, share the same lower edge. No routes, field names, labels, focus behavior, or submission behavior change.

## Verification

1. Add a regression test against the compiled production stylesheet that requires the responsive toolbar form rule to use `align-items:flex-end`.
2. Observe the test fail against the existing centered rule.
3. Update the shared source rule and rebuild Tailwind assets.
4. Run the focused test and full relevant layout suite.
5. Reload `/admin/users` at 2048 x 432 and measure control bottoms.
6. Check a mobile viewport to ensure controls remain stacked and full width.

## Scope

This change applies only to the shared organizer-style filter toolbar. It does not redesign other forms or alter page content.
