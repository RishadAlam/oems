# OEMS Analytics Dashboard Redesign

**Date:** 2026-08-14  
**Scope:** `/admin/analytics`, `/organizer/analytics`, and their shared chart component  
**Mode:** Preserve existing reporting semantics, routes, filters, exports, themes, and Chart.js dependency

## Problem

The analytics pages currently render two equal-height chart cards followed by always-expanded raw tables. The default 30-day series contains many zero periods, so the timeline is visually noisy and the table becomes the dominant element. A single-category result expands into an oversized bar chart and an almost-empty card. The timeline also compares event counts with monetary values on a second axis, which makes the chart difficult to scan and easy to misinterpret.

The result is technically complete but weak as an operational dashboard: hierarchy is unclear, sparse data looks broken, key findings are not summarized, and the page becomes unnecessarily long.

## Design Direction

Use a restrained, information-dense enterprise analytics pattern inspired by Carbon and Fluent dashboards while staying inside the OEMS design system. The page should answer three questions in order:

1. What range and filters am I viewing?
2. What are the primary outcomes?
3. Where and when did activity occur?

The design must remain professional in light and dark themes, work without JavaScript, and avoid decorative effects that compete with the data.

## Information Architecture

### 1. Page header

Keep the existing shared dashboard heading and current role-specific actions. Export links and the admin reports link remain unchanged.

### 2. Analysis controls

Convert the filter card into a compact `analytics-filter` panel:

- Keep the icon, heading, helper copy, fields, query names, action URLs, and validation behavior.
- Add an applied-range summary showing the selected start and end dates.
- Use a responsive grid with one-column mobile controls and bottom-aligned desktop actions.
- Apply and Reset remain explicit; no automatic submission.

### 3. KPI overview

Replace four generic dashboard panels with four shared `analytics-kpi` cards:

- each card has a compact icon, uppercase label, primary value, and one line of supporting context;
- values use tabular numerals and never depend on color alone;
- the cards use subtle dividers/tokens rather than strong decorative shadows;
- the admin and organizer pages keep their existing metrics and truthful supporting copy.

### 4. Performance overview

The shared chart component becomes a structured analytics section:

- Section header: `Performance overview`, range description, and a quiet live chart status.
- A responsive layout using a larger timeline panel and smaller category panel. Cards align to their own content height rather than stretching to match.
- Each panel includes a compact insight strip derived from the existing aggregate payload.
- Tables remain available as native `<details>` disclosures, closed by default when charts are available but fully usable without JavaScript.

#### Activity timeline

- Chart only Events, Registrations, and Attendance on one count axis.
- Do not chart currency values against counts. Verified payments remain in the dedicated payment totals section and remain present in the timeline source-data table.
- Reduce label noise with automatic tick skipping and a bounded maximum number of x-axis ticks.
- Use a readable line treatment with small points and clear tooltips.
- Insight text reports total periods, active periods, and the busiest period using deterministic aggregate calculations.

#### Category ranking

- Keep the horizontal registration ranking.
- Set chart height from the number of categories, with a compact minimum and bounded maximum.
- Bound bar thickness and use integer ticks so one category does not become a giant block.
- Insight text reports category count and the leading category.

### 5. Breakdowns and detailed tables

Keep lifecycle, registration, verified payment, top-event, top-category, and organizer event-detail sections. Improve their visual consistency with analytics section classes where necessary, but do not change persisted status semantics or report values.

## Sparse and Empty Data

- Zero-heavy timelines remain chronologically correct; the chart uses tick skipping rather than deleting periods.
- A series with no nonzero activity shows a concise empty state instead of an empty chart canvas.
- One category uses a compact chart with a fixed maximum bar thickness.
- No categories or no periods continue to use accessible empty states.
- The source-data disclosure labels include row counts so users know what is available before opening it.

## Progressive Enhancement and Accessibility

- Chart canvases remain `aria-hidden`; the exact values remain in semantic tables.
- Each data disclosure uses native `<details>/<summary>` and works without JavaScript.
- Chart loading text is exposed through one polite, atomic status region and does not announce duplicate visual fragments.
- Headings remain hierarchical: page `h1`, section `h2`, panel `h3`.
- Controls retain visible labels, 44px minimum targets, clear focus states, and existing frontend/backend validation.
- No meaning is conveyed by color alone; chart legend labels and table headers identify every series.
- Motion respects `prefers-reduced-motion`.

## Responsive Behavior

- **390px:** single-column KPIs, filters, chart panels, and disclosures; no horizontal page overflow; tables scroll only inside their wrappers.
- **768px:** two-column KPI grid; filters use available columns; chart panels remain stacked for legibility.
- **1280px and above:** four KPI cards; performance overview uses approximately 1.6fr/0.8fr columns with `items-start`; the category card never stretches to the timeline height.
- Chart containers use explicit responsive heights and `min-width: 0`.

## Theme and Visual Tokens

- Use existing OEMS variables: `--surface-raised`, `--surface-soft`, `--line`, `--line-strong`, `--ink`, `--ink-muted`, `--accent`, `--accent-soft` and existing semantic colors.
- Chart colors are read from CSS variables where possible and must remain legible in both themes.
- Do not add a UI dependency, raw light/dark palette utilities, gradients, or theme-specific inline colors.

## Data and Security Contract

- No repository, SQL, route, authorization, export, or controller contract changes are required.
- The JSON payload remains aggregate-only and encoded with the current JSON safety flags.
- No participant PII is introduced into chart payloads or markup.
- Currency values remain separated by currency and are never summed across currencies.

## Acceptance Criteria

1. Both analytics pages use the shared filter, KPI, and performance-overview patterns.
2. The timeline chart contains exactly the three count series and no money axis.
3. Payment values remain accessible in the source-data table and dedicated payment section.
4. Source data is inside native disclosures with truthful row counts.
5. Sparse category data produces a compact, bounded chart.
6. Charts unavailable or malformed JSON leaves all data tables usable and provides a clear status.
7. Layout has no clipping, overlap, or document overflow at 390, 768, 1280, and 2048 pixels in both themes.
8. Existing filter validation, query parameters, exports, authorization, and analytics data tests remain green.
9. Source CSS and compiled CSS are synchronized, with the required asset/cache revision updated only if compiled output changes.

