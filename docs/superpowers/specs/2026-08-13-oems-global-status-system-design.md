# OEMS Global Status System Design

**Date:** 2026-08-13

**Mode:** Preserve-mode product UI redesign

**Scope:** Every user-visible lifecycle, approval, fulfillment, availability, health, and delivery status across public, participant, organizer, and administrator surfaces

## Design read

OEMS is a trust-first event-management product with dense operational surfaces. The appropriate direction is a restrained Fluent/Carbon-style semantic status language inside the existing OEMS token and component system.

- Design variance: 3
- Motion intensity: 2
- Visual density: 7

The current Manrope typography, Phosphor icon family, spacing, radii, routes, data values, labels, and interaction behavior remain unchanged.

## Evidence and root cause

The screenshot comes from the Operations page. Its overall `Ready` label uses the shared badge system, but the three `Passing` values use raw Tailwind emerald utilities. Those utilities bypass OEMS semantic variables.

OEMS selects its theme with `html[data-theme]`. Tailwind emitted the raw `dark:` utilities under the operating-system `prefers-color-scheme` media query. When the operating system is dark but the saved OEMS theme is light, the `Passing` values render as bright mint on a near-white surface at about 1.42:1 contrast. The error equivalent also fails. This is both a theme-authority defect and a visual consistency defect.

The existing OEMS semantic foreground and soft-background pairs already meet WCAG AA in both themes. Therefore the palette does not need replacement; status output needs one authority and complete coverage.

## Considered approaches

### One shared semantic contract (selected)

Keep domain status values in server-rendered PHP and map them to five semantic tones through the existing status chip and badge components. Contextual states use an explicit tone class when one word can mean different things. This prevents theme drift, preserves truthful labels, and gives unknown values a safe neutral fallback.

### Per-view recoloring

Each view could select arbitrary utilities. It would be quick locally, but it caused the current defect and cannot guarantee theme or semantic consistency.

### New frontend component library

Installing Fluent or Carbon would provide mature status components, but replacing OEMS server-rendered components for this focused defect would add significant dependency and migration risk. The design language can be followed faithfully with the existing accessible token system.

## Semantic taxonomy

Color encodes meaning, not variety. Identical states keep identical colors.

| Tone | Meaning | Domain states and contextual labels |
| --- | --- | --- |
| Informational blue | Live, valid, acknowledged, or ready for the next step | active, published, valid, sent, read, Ready, Ready to review |
| Success green | Positive decision or completed outcome | approved, confirmed, paid, completed, used, replied, subscribed, present, Passing, Verified, Coupon applied |
| Warning amber | Waiting, processing, partial outcome, or intervention may soon be required | pending, waitlisted, queued, processing, new, partially_refunded, maintenance Active |
| Danger red | Failed, blocked, rejected, revoked, suspended, or cancelled | rejected, failed, suspended, revoked, cancelled, Unavailable health, Needs attention |
| Neutral gray | Draft, inactive, historical, hidden, fully reversed, absent, or no state | draft, inactive, archived, hidden, refunded, absent, none, not_checked_in, unsubscribed, unavailable content |

`Active` is intentionally contextual: an active account or published resource is informational, while active maintenance is a warning. `Unavailable` is also contextual: failed application readiness is danger, while a no-longer-available favorite is neutral. The visible label remains the primary signal, so color is never the only meaning.

The three `Passing` checks remain the same subdued success treatment. Giving identical healthy checks different colors would falsely imply different severities.

## Component contract

`status-chip` is the lifecycle component used in tables and detail records. `status-badge` is the compact contextual component. Both share:

- a neutral default;
- compact rounded geometry;
- readable text and optional semantic icon;
- token-based foreground, soft background, and subtle border;
- no decorative dots;
- no raw palette utilities;
- no `dark:` utilities in server-rendered views.

Known domain suffixes resolve to the taxonomy above. Generic contextual aliases remain available as `info`, `success`, `warning`, `danger`, `neutral`, and `muted`.

Operations changes:

- Overall `Ready` becomes informational blue because it describes availability for normal operation.
- Each `Passing` value becomes a shared success badge.
- Each `Needs attention` value becomes a shared danger badge.
- Maintenance `Active` remains warning and `Inactive` remains neutral.

The certificate verification success icon uses OEMS success tokens instead of Tailwind emerald and `dark:` utilities.

Status columns that currently render plain text, especially organizer participant fulfillment and administrator analytics lifecycle, migrate to the same shared components so the global contract is visible everywhere a state is presented.

## Accessibility and theme behavior

- Each badge keeps explicit readable text.
- Token pairs must maintain at least 4.5:1 foreground-to-background contrast in light and dark themes.
- Theme resolution comes only from OEMS `data-theme` variables.
- Unknown or malformed status values remain visible and neutral instead of inheriting a misleading semantic color.
- Compact tags do not become focus targets because they are noninteractive.
- Layout must survive 200% zoom, long labels, and 390px viewports without clipping or horizontal overflow.

## Testing and verification

Regression coverage will:

1. render the Operations page in healthy and unhealthy states and assert the exact semantic component structure;
2. reject raw Tailwind semantic palette utilities and `dark:` theme utilities in all PHP views;
3. assert the complete state taxonomy for source and compiled CSS;
4. compute WCAG contrast from both theme token sets;
5. render representative multi-status tables so lifecycle and fulfillment columns cannot regress to plain text;
6. rebuild and version the stylesheet cache;
7. verify representative public and dashboard routes at 390, 768, 1280, and 2048 pixels in both themes, including saved-theme and operating-system preference mismatches;
8. run the full PHP, JavaScript, syntax, asset, and whitespace checks.

## Non-goals

- No route, database enum, service rule, controller transition, filter value, or visible status wording changes.
- No arbitrary rainbow colors for repeated states.
- No redesign of alerts, validation errors, buttons, charts, or non-status decorative icons unless a raw palette/theme utility violates the shared theme authority.
- No new UI dependency.
