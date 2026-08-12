# OEMS Semantic Status Colors Design

**Date:** 2026-08-13

**Mode:** Preserve-mode redesign

**Scope:** Status chips, compact status badges, profile status text, light theme, and dark theme

## Design read

OEMS is a trust-first event-management product used by participants, organizers, and administrators. Status color must communicate operational meaning quickly without making the interface decorative or relying on color alone.

Design dials:

- Design variance: 3
- Motion intensity: 2
- Visual density: 6

The existing Manrope typography, Phosphor icons, spacing, radii, routes, labels, and interaction patterns stay unchanged.

## Current problem

The current status system is incomplete:

- Approved, published, paid, valid, and used share one green treatment even though they describe different lifecycle meanings.
- Completed is neutral while other completed actions are green.
- Many legitimate statuses have no explicit rule and inherit surrounding color.
- `status-badge` appears in several views but has no source component styling.
- Some views use another status name only to obtain its color, such as using `approved` for active and `cancelled` for inactive.

## Considered approaches

### Centralized semantic CSS mapping (selected)

Keep the existing status names in markup and map every known state to a semantic tone in the shared stylesheet. This matches the server-rendered PHP architecture and prevents view-level drift.

### Per-view tone mapping

Each PHP view would translate domain states into color classes. This offers local control but repeats logic and makes future inconsistencies likely.

### JavaScript status component

A client component could transform status markup after rendering. This adds unnecessary runtime behavior, risks a flash of incorrect color, and does not fit the current architecture.

## Semantic palette

The palette uses the existing accessible theme tokens:

| Tone | Meaning | Representative states |
| --- | --- | --- |
| Informational blue | Live, available, or informational state | active, published, valid, sent |
| Success green | Positive decision or completed outcome | approved, confirmed, paid, completed, used, replied, subscribed, present |
| Warning amber | Waiting, processing, or action required | pending, waitlisted, queued, processing, new, read |
| Danger red | Rejected, failed, blocked, revoked, or cancelled | rejected, failed, suspended, revoked, cancelled |
| Neutral gray | Draft, inactive, historical, unavailable, or no state | draft, inactive, archived, hidden, refunded, partially_refunded, absent, none, not_checked_in, unsubscribed |

Green is reserved for a positive decision or completed outcome. Blue represents a currently live or valid state so operational statuses do not all appear green.

## Component behavior

`status-chip` remains the primary lifecycle component. `status-badge` becomes a compact alias with the same shape, type weight, and semantic variants.

Every component keeps visible text. Existing icons and accessible labels remain unchanged, so color is supplementary rather than the only signal.

Unknown status values receive the neutral base treatment instead of inheriting a potentially misleading color.

## View corrections

Views must use their real status class:

- Active uses `status-chip--active`.
- Inactive uses `status-chip--inactive`.
- Published uses `status-chip--published`.
- Draft uses `status-chip--draft`.

No route, business rule, database value, label, or form field changes.

## Accessibility and themes

- Foreground and soft-background pairs continue to use semantic CSS variables already defined for both themes.
- Text remains present for every status.
- No decorative status dots are introduced.
- Focus behavior and touch targets are unchanged.
- Both themes use restrained saturation and preserve WCAG-readable contrast.

## Testing

Regression tests will parse the source stylesheet and assert that representative domain states resolve to their expected semantic tokens. View tests will ensure real status names are used instead of color-proxy names. The CSS build, PHP suite, frontend suite, syntax check, and asset checks will run before completion.
