# OEMS Homepage Experience Redesign

## Objective

Redesign the complete public homepage as a professional, responsive event-discovery experience while preserving OEMS branding, routes, CMS content, event data, theme support, accessibility, and server-rendered PHP architecture.

## Direction: Editorial Event Concierge

The homepage should feel like a calm, contemporary guide to what is happening in Dhaka—not a generic SaaS template or a dense marketplace. Real event photography, asymmetric editorial composition, restrained blue accents, clear typography, and purposeful whitespace remain the visual foundation.

Design dials:

- visual variance: 5/10
- interface density: 5/10
- motion intensity: 3/10

## Experience hierarchy

1. **Discover quickly.** The hero introduces OEMS, gives event exploration the primary action, preserves organizer entry, and exposes search without consuming an entire mobile viewport.
2. **Notice timely information.** CMS announcements render as stable, accessible editorial panels. Long titles, subtitles, title-only entries, and multiple banners must remain readable without oversized media.
3. **Browse by interest.** Categories receive a clear heading and compact, consistent shortcuts rather than appearing as an unexplained navigation strip.
4. **Evaluate featured events.** Featured events use a deliberate lead/supporting composition with clear date, place, price, save, and detail actions.
5. **Understand both journeys.** The process section separates participant and organizer workflows so neither audience has to infer whether the content applies to them.
6. **Convert with confidence.** The organizer callout explains concrete platform value and retains one clear account-creation action.

## Responsive behavior

- The desktop hero uses a compact two-column layout with a controlled media height.
- The mobile hero stacks content and image but avoids a near-full-viewport image treatment.
- Announcement media uses a controlled height range on desktop and a compact aspect ratio on mobile; copy can grow independently.
- Categories form a two-column mobile grid and four-column desktop grid with minimum 44px touch targets.
- Featured cards stack naturally on mobile and use an asymmetric two-column composition on larger screens.
- Participant and organizer journeys stack on mobile and sit side by side on desktop.
- No viewport between 320px and 1440px may have horizontal overflow.

## CMS announcement resilience

- Render banners in provider order and never assume only one banner exists.
- Escape banner title, subtitle, image path, alternative text, and link URL.
- Omit subtitle and link markup when values are empty.
- Apply `overflow-wrap: anywhere` to announcement copy.
- Cap desktop media height independently from copy length so valid 180-character titles and 255-character subtitles do not create oversized images.
- Preserve readable content rather than clipping it.

## Accessibility and interaction

- Keep one semantic `h1` and logical section headings.
- Preserve labeled search and native form submission.
- Keep visible keyboard focus, minimum touch targets, descriptive image alternatives, and theme contrast.
- Decorative icons remain hidden from assistive technology.
- Motion is limited to small hover/reveal transitions and respects reduced-motion behavior already provided by the application.

## Content rules

- Do not invent event counts, customer logos, ratings, testimonials, or success statistics.
- Use only platform capabilities already present: discovery, registration, tickets, event review, guest management, and QR check-in.
- Keep routes unchanged: `/events`, `/register?role=organizer`, and `/#how-it-works`.

## Acceptance criteria

- The homepage has distinct hero, announcements, categories, featured events, participant/organizer journeys, and organizer conversion sections.
- Desktop and mobile layouts are visually coherent in light and dark themes.
- Long and multiple CMS announcements render safely and in order.
- Empty featured-event state remains useful.
- Existing event favorite behavior and CSRF fields remain intact.
- Automated PHP and JavaScript tests, CSS build, syntax checks, and browser overflow checks pass.
