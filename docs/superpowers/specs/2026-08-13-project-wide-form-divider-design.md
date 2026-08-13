# Project-wide form divider design

## Problem

Sectioned forms can render two horizontal rules before their action footer. The current stylesheet puts a bottom border on every content section and a top border on the action footer. It tries to remove the final content border with `:last-of-type`, but that selector cannot match the profile form's last content `<div>` because the following action footer is also a `<div>`.

The existing regression test checks only that the ineffective source selector exists. It does not verify the CSS that the browser receives.

## Scope

The project has two shared sectioned-form families:

- `profile-form-section` with `profile-form-actions`
- `organizer-form__section` with `organizer-form__actions`

The organizer family is reused by organizer event, venue, and coupon editors and by admin category, CMS banner, CMS FAQ, CMS page, and platform settings forms. Other entry, filter, and action forms do not combine content-section bottom borders with a footer top border and therefore do not exhibit this defect.

## Chosen design

Separators belong to the content that follows them:

1. Content sections have no bottom border.
2. Every content section after the first receives one top border and matching top padding.
3. The action footer retains one top border.
4. Form grid gaps continue to provide the outer spacing.

The rule uses same-family general sibling selectors, so validation summaries or required-field notes before the first section do not create an unwanted divider. It also remains correct if non-section content appears between two sections.

## Alternatives considered

### Remove only the final border with `:has()`

This is a small change, but it depends on exact adjacency between the final section and action footer. It also leaves the fragile bottom-border model in place.

### Add a new shared class to every sectioned form view

This would provide a single semantic API, but it would modify nine templates without changing their behavior. The two existing shared families already provide sufficient scope for a low-risk correction.

## Verification

- Replace the source-presence assertion with a regression test against compiled CSS.
- Prove both section families have no base bottom border.
- Prove later sibling sections receive one top border.
- Prove both action footers retain one top border.
- Rebuild the production stylesheet and update its cache version in every layout and the service worker.
- Run the focused UI layout test, JavaScript/PWA tests, the full PHP suite, syntax checks, Composer validation, and a representative browser inspection when the local application is available.

## Success criteria

Every sectioned form shows exactly one horizontal rule between content sections and exactly one rule before its action footer, with no paired or stacked rule at any viewport size or theme.
