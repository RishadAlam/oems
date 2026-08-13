# Homepage Experience Redesign Implementation Plan

> **For Codex:** Execute this plan in order with test-first checkpoints and a commit after every completed phase.

**Goal:** Ship a professional, responsive homepage that improves event discovery, clarifies participant and organizer journeys, and safely handles real CMS announcement content.

**Architecture:** Keep the existing server-rendered `home/index.php` page and global Tailwind source. Introduce homepage-specific semantic classes, preserve controller data and routes, rebuild the committed CSS artifact, and update the public CSS cache contract. Validate markup through the existing PHP unit suite and behavior/layout through the in-app browser.

**Tech stack:** PHP 8.2, custom MVC views, Tailwind CSS 4, Node-based asset tests, native browser inspection.

---

## Phase 1: Capture regression contracts

**Files:**

- Modify: `tests/Unit/UiLayoutTest.php`

1. Extend the homepage composition test to require distinct discovery, category, featured, audience-journey, and organizer-conversion sections.
2. Render two CMS announcements, including HTML metacharacters and a title-only entry.
3. Assert banner order/count, escaping, optional-content omission, safe routes, and accessible section labels.
4. Assert source CSS contracts for long-word wrapping, controlled desktop announcement media, compact mobile hero media, and responsive audience journeys.
5. Run the focused unit test and confirm it fails against the current implementation.
6. Commit the failing regression tests.

## Phase 2: Implement the complete homepage redesign

**Files:**

- Modify: `app/Views/home/index.php`
- Modify: `resources/css/app.css`
- Modify: `app/Views/layouts/public.php`
- Modify: `app/Views/layouts/auth.php`
- Modify: `app/Views/layouts/dashboard.php`
- Modify: `app/Views/layouts/maintenance.php`
- Modify: `public/service-worker.js`
- Modify: `tests/Unit/PwaStaticPolicyTest.php`
- Modify: `tests/Unit/OrganizerVenueControllerTest.php`
- Modify: `tests/js/pwa.test.mjs`
- Rebuild: `public/assets/css/app.css`

1. Refine hero composition, supporting line, actions, search, and media sizing without changing CMS hero settings or routes.
2. Harden announcement markup and CSS for multiple, long, and optional-content variants.
3. Add a labeled category-discovery section with compact responsive shortcuts.
4. Create dedicated featured-event composition classes while preserving event metadata, favorites, and empty state.
5. Replace the ambiguous process block with separate participant and organizer journey panels.
6. Enrich the organizer callout with concise, factual capability points and one primary action.
7. Add responsive and dark-theme refinements, including overflow-safe typography and controlled section rhythm.
8. Rebuild CSS and update the cache/service-worker contracts.
9. Run focused PHP and JavaScript tests until green.
10. Commit the implementation.

## Phase 3: Verify the end-to-end experience

**Files:**

- Modify: `docs/superpowers/plans/2026-08-13-homepage-experience-redesign.md`

1. Run PHP tests, JavaScript tests, syntax checks, Composer strict validation, and production CSS build.
2. Inspect the homepage at desktop and mobile widths in light and dark themes.
3. Confirm zero horizontal overflow, compact responsive section heights, visible calls to action, correct CMS banner rendering, and intact search/event links.
4. Review the diff independently for security, accessibility, regressions, and maintainability; address all material findings.
5. Record the verification evidence below and mark every phase complete.
6. Commit the completed plan and verification record.

## Verification record

- [ ] Focused homepage tests pass.
- [ ] Full PHP suite passes.
- [ ] Full JavaScript suite passes.
- [ ] PHP syntax checks pass.
- [ ] Composer validates strictly.
- [ ] CSS production build passes.
- [ ] Desktop light and dark browser checks pass.
- [ ] Mobile light and dark browser checks pass.
- [ ] No horizontal overflow at tested viewports.
- [ ] Independent review has no unresolved critical or important findings.
