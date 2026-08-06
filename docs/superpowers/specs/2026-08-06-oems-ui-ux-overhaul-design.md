# OEMS UI/UX Overhaul Design

**Date:** 2026-08-06
**Status:** Approved for implementation by the user's instruction to complete the redesign without further questions

## Objective

Turn OEMS into a cohesive, accessible event product for participants, organizers, and administrators. Preserve the existing routes, field order, account workflows, content meaning, role permissions, self-hosted Manrope typeface, and real event photography while replacing the placeholder visual layer and correcting interaction defects.

The result should feel energetic enough for event discovery and dependable enough for account, security, and administration tasks.

## Approaches Considered

### 1. Targeted cosmetic polish

Keep the existing components and adjust colors, spacing, shadows, and selected labels.

- Lowest implementation risk
- Does not solve the weak brand, dashboard hierarchy, icon inconsistency, or navigation behavior as a system
- Rejected because the user requested a complete visual and UX improvement

### 2. Cohesive custom product system

Build a semantic Tailwind design layer for the existing PHP views, add one locally bundled icon family, create an OEMS logo, and redesign each surface around the same tokens and interaction contracts.

- Preserves the lightweight stack and current workflows
- Produces a recognizable brand without framework lock-in
- Supports public pages, forms, and product dashboards with one system
- Selected as the best balance of quality, maintainability, and implementation risk

### 3. Enterprise design-system import

Adopt a complete external system such as Carbon or Fluent and retrofit all views.

- Strong dashboard patterns out of the box
- Adds substantial CSS and conceptual overhead to a small vanilla PHP application
- Makes the public event experience feel less distinctive
- Rejected because the cost and visual constraints exceed the current product's needs

## Visual Direction

### Design dials

- Design variance: 6/10
- Motion intensity: 4/10
- Visual density: 5/10

The interface should be distinctive through proportion, typography, imagery, and detail rather than decoration. Public pages can use larger editorial composition. Dashboard and settings pages should be denser, quieter, and strongly task oriented.

### Brand

Create a code-native SVG logo made from simple geometric shapes. The mark represents an open event aperture: four rounded segments around a central gathering point. It must remain legible at 24px, work in one color, and pair with a bold OEMS wordmark.

Use three lockups:

- Default cobalt mark with dark wordmark
- Inverse white lockup for photography or dark surfaces
- Compact mark-only treatment where space is constrained

The logo is decorative inside a link whose accessible name is `OEMS home`. It must not duplicate visible brand text for assistive technology.

### Color system

Use a single cobalt brand accent with neutral, slightly cool surfaces. Semantic success, warning, and error colors are reserved for state communication and are not decorative alternatives.

Light mode:

- Canvas: `#F5F7FB`
- Surface: `#FFFFFF`
- Subtle surface: `#EDF1F7`
- Strong ink: `#142033`
- Muted ink: `#5B6678`
- Border: `#D9E0EA`
- Accent: `#3157D5`
- Accent hover: `#2445B3`
- Accent soft: `#E8EDFF`
- On accent: `#FFFFFF`

Dark mode:

- Canvas: `#0D1420`
- Surface: `#131D2C`
- Subtle surface: `#192538`
- Strong ink: `#F2F5FA`
- Muted ink: `#A7B2C3`
- Border: `#2A384D`
- Accent: `#8DA7FF`
- Accent hover: `#A7BAFF`
- Accent soft: `#202F5A`
- On accent: `#101A36`

All text, interactive states, borders that communicate state, and focus indicators must satisfy WCAG 2.1 AA contrast.

### Typography

Continue using locally bundled Manrope. Use weight and scale to create hierarchy rather than a second font.

- Public display: 48px to 72px desktop, 40px mobile, tight tracking, maximum two lines on desktop
- Product page title: 30px to 40px
- Section heading: 20px to 26px
- Body: 15px to 17px with 1.6 line height
- Supporting copy: 13px to 14px
- Labels and navigation: 13px to 15px, medium or semibold

Avoid all-caps display copy. Short uppercase labels may be used only for compact metadata when letter spacing improves scanning.

### Shape and depth

Use a documented radius hierarchy:

- Buttons and form controls: 12px
- Cards and panels: 18px
- Large image containers and feature surfaces: 24px
- Badges, avatars, and compact toggles: full pill or circle

Use borders as the default separator. Shadows should be soft and limited to floating navigation, menus, and emphasized cards. Do not stack multiple bordered cards inside another bordered card without a functional reason.

### Icons

Use the locally bundled Phosphor icon web package exclusively. Icons should usually be 18px to 20px and use regular weight, with bold weight for high-emphasis controls. Every icon-only control needs a precise accessible label and tooltip through `title` when the action is not otherwise obvious.

No emoji, mixed icon families, or hand-drawn interface icons.

## Component System

### Buttons

Provide consistent primary, secondary, quiet, danger, and icon-button variants.

- Minimum target height: 44px
- Primary button uses the single accent color and white text in light mode
- Secondary button uses a neutral surface and border
- Quiet button uses no permanent border but gains a surface on hover
- Icon and text spacing is 8px
- Disabled controls communicate unavailable future work without looking active
- Hover must not change label contrast unpredictably
- Pressed state uses a small scale change, not a vertical jump
- Every state keeps a visible keyboard focus ring

### Form fields

- Labels remain above fields
- Controls are at least 48px high
- Optional status sits in muted label text
- Helper and error messages appear directly below the control
- Invalid controls use both color and icon/message text
- Read-only fields use a distinct quiet surface and remain readable
- Password controls use an eye icon, update their accessible label, and announce the state through text available to assistive technology
- Radio role cards include icons and a clear selected state
- Checkboxes and radio controls retain native semantics and visible focus
- Textareas have a sensible minimum size and resize vertically

### Navigation

Public navigation is a 72px sticky header with logo, primary destinations, theme control, and account actions. The mobile menu is a real disclosure with synchronized `aria-expanded`, Escape support, outside-click close, link close, focus restoration, and scroll-safe behavior.

Dashboard navigation uses a persistent desktop rail and mobile drawer. Links include icons and visible active state. The drawer follows the same keyboard and focus contract as the public menu. The user identity block and log-out action remain anchored to the bottom of the rail.

Correct the current broken non-home `How it works` link by routing it to `/#how-it-works` everywhere.

### Theme control

Replace the text-only theme button with a sun/moon icon control while preserving an explicit accessible label. Initialize theme before paint, catch storage access failures, update the browser theme color, and respect `prefers-color-scheme` when no preference exists.

### Feedback and empty states

Flash messages use semantic icons and an icon-only dismiss control with an accessible label. Empty states should explain the next useful action with a compact visual, one clear message, and one action. They should not rely on large dashed placeholders.

## Page Designs

### Home

Keep the split hero and real event photography, but increase brand presence and make the search experience a deliberate discovery dock below the main hero copy. The primary action is `Explore events`; organizer registration is secondary. Keep the first viewport focused on the headline, concise support copy, actions, search, and image.

Show categories as compact icon chips. Present featured events as an asymmetric editorial pair with date, location, audience, and a clear directional action. Replace the generic three-card explanation with one connected process section. Keep the organizer callout as a strong dark surface with a single primary action.

### Events

Use a compact discovery header rather than another oversized hero. Give the search input a leading icon and clear label. Event cards retain the real images, visible metadata, strong title hierarchy, and one primary route. The static Week 1 search remains clearly presented without implying filters that do not work.

### Authentication

Retain the desktop split composition, real photography, and form order. Add the new inverse brand on the visual side and a compact brand plus theme control on smaller screens. Use icon-supported fields where helpful without cluttering every input. Make registration role cards more scannable and tighten the long form on desktop while keeping generous mobile spacing.

### Dashboard

Treat dashboard pages as product UI, not marketing pages. Use a quieter canvas, icon navigation, a compact identity header, and metric cards with semantic icons and clearer number hierarchy. Metrics remain data driven.

Participant and organizer dashboards keep their current content but gain clear task prioritization, useful empty states, and directional quick actions. Disabled Week 2 functionality stays disabled and explicitly labelled. The admin overview stays intentionally concise and must never show hard-coded placeholder totals.

### Profile and security

Break the long profile into visually distinct sections without changing field order or names. Add a compact identity summary, section icons, a readable content width, and a persistent action area on larger screens. Keep all existing error relationships and help text.

The password page uses the same section shell and exposes current/new password requirements clearly. It must not visually imply that security settings include options not yet implemented.

## Motion and Interaction

Use 150ms to 240ms transitions for hover, drawer, and feedback states. Public reveal motion is limited to opacity plus a maximum 12px vertical translation. Respect `prefers-reduced-motion` by disabling nonessential transitions and reveal effects.

Do not animate large layout geometry, use scroll hijacking, or make content depend on animation completing. Content remains visible without JavaScript.

## Responsive Behavior

- Mobile: single-column layout, 20px page gutters, full-width critical actions, drawer navigation, compact display type
- Tablet: two-column event and form groups where readable, dashboard rail remains a drawer
- Desktop: public content width up to 1180px, product content up to 1280px, persistent 264px dashboard rail
- Interactive targets remain at least 44px in both dimensions
- No horizontal overflow at 320px
- Hero headlines may use three lines on small screens but must remain within the first viewport with the core action

## Defects Included in Scope

1. Fix `How it works` navigation from non-home routes.
2. Synchronize mobile menu and dashboard drawer ARIA state.
3. Add Escape, outside-click, navigation-click, and focus-restoration behavior to drawers.
4. Prevent theme storage failures from breaking initialization.
5. Give theme, password, menu, close, and dismiss controls accurate icon and accessible states.
6. Keep reveal content available when JavaScript is missing or reduced motion is enabled.
7. Remove hover states that unexpectedly reduce contrast.
8. Correct dashboard visual hierarchy and mobile overflow.
9. Preserve all server-side validation, CSRF, and form error associations.

## Acceptance Criteria

- All public, authentication, dashboard, profile, security, flash, error, and empty-state surfaces use the new system.
- A single SVG OEMS brand mark is reused through a shared PHP component.
- Phosphor is the only interface icon family and is bundled locally.
- Every public and product route renders correctly in light and dark mode.
- Public and dashboard navigation are keyboard operable and expose correct state.
- Buttons, forms, disabled states, feedback, and focus rings are consistent and accessible.
- Existing account workflows and role routing continue to pass all automated tests.
- New render tests cover brand semantics, navigation targets, and accessible control state.
- Browser QA covers desktop and mobile home, events, login, registration, participant dashboard, profile, security, and admin rendering where credentials permit.
- There is no horizontal overflow at 390px and no unusable control at 200% zoom.
- `npm run build`, the full PHP test suite, and relevant static checks pass.
- Each implementation step is committed, only project files are staged, and the completed commits are pushed to the public GitHub repository.

