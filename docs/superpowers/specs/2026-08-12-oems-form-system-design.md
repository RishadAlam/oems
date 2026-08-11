# OEMS Professional Form System Design

**Date:** 2026-08-12  
**Status:** Approved through the user's instruction to make the best professional decisions without further questions

## Objective

Redesign and harden every OEMS form so participants, organizers, and administrators experience one consistent, accessible, forgiving form system. Every user-editable constraint must be represented in the browser for timely feedback and independently enforced by the server as the security and data-integrity authority.

The project currently contains 86 form instances across 56 view files and 269 rendered controls. Existing form controls share a visual foundation, but field markup, required-state communication, error relationships, validation timing, file inputs, filter bars, destructive actions, and backend rule placement remain inconsistent.

## Approaches Considered

### 1. Cosmetic sweep

Restyle existing inputs and add missing `required` attributes directly in each view.

- Lowest immediate effort
- Leaves validation behavior fragmented
- Encourages frontend/backend constraints to drift again
- Rejected because it treats symptoms instead of the system

### 2. Shared progressive-enhancement form system

Introduce reusable field presentation helpers, semantic form classes, a small global validation controller, explicit server validation contracts, and parity tests while preserving server-rendered PHP forms.

- Keeps the current lightweight architecture
- Works without JavaScript and improves when JavaScript is available
- Gives all roles a consistent interaction model
- Makes validation parity testable
- Selected as the best balance of usability, maintainability, accessibility, and implementation risk

### 3. Form framework rewrite

Replace the server-rendered forms with a client-side component framework and schema library.

- Could centralize schemas completely
- Adds a second rendering architecture, build complexity, and migration risk
- Unnecessary for the current product and server-rendered navigation model
- Rejected

## Design Direction

OEMS forms should feel calm, precise, and trustworthy. Public and authentication forms remain approachable and spacious; dashboard forms become slightly denser and task-oriented without looking administrative or cramped.

Design dials:

- Visual variance: 4/10
- Motion intensity: 3/10
- Information density: 6/10

The existing cobalt brand, Manrope typeface, semantic color tokens, 12px control radius, and 18px panel radius remain. The redesign improves hierarchy and behavior rather than replacing the established OEMS identity.

## Form Taxonomy

Each form belongs to one of four interaction types and receives the appropriate treatment:

1. **Data-entry forms** — authentication, profile, event, venue, coupon, CMS, contact, newsletter, review, registration, payment, and settings forms. These receive full inline validation and an error summary after unsuccessful submission.
2. **Search and filter forms** — event discovery, participant lists, analytics, reports, payments, users, blog, and organizer/admin indexes. These use compact filter bars, clear grouping, sensible submit/reset actions, and URL-safe input constraints without intrusive required-state treatment.
3. **State-change forms** — publish, approve, suspend, favorite, notification, waitlist, cancel, restore, and maintenance actions. These remain compact, communicate the exact action, prevent duplicate submission, and use confirmation where consequences are destructive or difficult to reverse.
4. **Specialized forms** — event media, venue mapping/geocoding, QR check-in, payment evidence, and announcement confirmation. These receive domain-specific guidance, status messaging, and validation while retaining the shared field contract.

## Field Anatomy

Every editable field uses the same semantic order:

1. Label
2. Optional marker when the field is not required
3. Concise hint when it prevents likely errors
4. Control or control group
5. Live constraint/status text when applicable
6. Error message

Requirements:

- Labels are always programmatically associated with controls.
- Required fields use the native `required` attribute and a form-level statement that required fields are marked; optional fields are explicitly marked only where mixed required/optional groups would otherwise be ambiguous.
- Help and error text use stable IDs and are both included in `aria-describedby` when present.
- Invalid controls receive `aria-invalid="true"`; valid controls are not decorated with success styling unless confirmation materially helps the task.
- Server errors are rendered beside the relevant field and included in a focusable error summary at the beginning of the form.
- The error summary links to invalid controls and moves focus only after a failed submission, never during typing.
- Controls preserve submitted values except passwords, secrets, file selections, and explicitly sensitive payment data.

## Visual Components

### Fields

- Minimum 48px control height and comfortable 44px interaction targets
- Consistent internal padding for text, icons, selects, dates, and numbers
- Leading icons used sparingly where they improve recognition; icons never overlap values
- Trailing controls reserve their own padding and remain keyboard accessible
- Read-only fields use a quiet surface and include explanatory text when the reason is not obvious
- Disabled fields are used only for truly unavailable controls

### Required, help, and error states

- Default: neutral border and raised surface
- Hover: stronger neutral border
- Focus: cobalt border plus visible focus ring
- Invalid: semantic error border, error icon/message, and retained focus ring
- Disabled/read-only: quiet surface, clear contrast, and appropriate cursor
- Help copy is one concise sentence whenever possible

### Form composition

- Long forms are divided into titled sections with a short purpose statement
- Related fields use responsive grids only when side-by-side scanning is natural
- Primary submit actions appear at the logical end of the form
- Long dashboard forms use a stable action bar on larger screens and a normal-flow action area on mobile
- Secondary actions do not compete visually with the primary completion action
- Destructive actions are separated from routine save actions

### File inputs

Native file chrome will be replaced by an accessible styled chooser presentation that keeps the real file input operable. It displays accepted formats, size limits, selected filenames, and image count. Client checks provide immediate feedback; the server revalidates MIME type, size, count, upload integrity, and image dimensions/policy.

## Client-Side Validation Contract

JavaScript progressively enhances forms marked with the shared form behavior. Native HTML constraints remain present and useful without JavaScript.

Validation timing:

- Do not show errors while the user is initially typing.
- Validate a field after it loses focus once it has been meaningfully interacted with.
- Validate the complete form on submit.
- Revalidate an invalid field as its value changes and clear the message immediately once corrected.
- Focus the error summary, then allow its links to move directly to each invalid field.

Constraint coverage:

- Required values and required checkbox/radio groups
- Email and HTTP(S) URL formats
- Minimum/maximum text length
- Numeric minimum, maximum, and step expectations
- Exact option membership through native select/radio values
- Password length and confirmation equality
- Event schedule ordering and registration deadline rules
- File type, file count, and file size
- Paired latitude/longitude values
- Conditional payment and registration fields

The client messages use plain, specific language and never claim the data was accepted by the server. JavaScript must not remove server-rendered errors before the user edits the corresponding field.

## Server-Side Validation Contract

Server validation is mandatory for every POST request regardless of browser validation.

- CSRF middleware remains required for every state-changing form.
- Controllers normalize request data and delegate business validation to services where a service owns the operation.
- `Core\Validator` remains the shared primitive-rule engine and gains only broadly reusable rules.
- Domain rules remain in the relevant service so business decisions are not duplicated in views.
- Uploaded files are validated exclusively from trusted upload metadata and decoded content, not filename extensions.
- Route identifiers, ownership, role permissions, expected state/version values, and database conflicts are validated server-side even when they have no client equivalent.
- Unknown or tampered option values are rejected.
- Validation failures redirect back with safe old values and field-addressable errors, or return structured 422 JSON for API endpoints.
- No validation failure may mutate persistent state.

## Validation Parity Strategy

Each data-entry form receives a written validation contract in tests containing:

- Field name
- Required or optional status
- Client constraint representation
- Server rule or domain validator
- Error rendering target
- Boundary and invalid examples

Parity means that every client-enforceable server rule has a matching browser constraint or custom client check. Server-only security and business rules are documented as server-only rather than imitated unreliably in JavaScript.

## Accessibility and Usability

- WCAG 2.1 AA contrast for text, borders that communicate state, and focus indicators
- Keyboard completion for every form and specialized control
- No placeholder-only labels
- Error meaning never communicated by color alone
- Status changes announced through restrained `aria-live` regions
- Touch targets at least 44px
- No horizontal overflow at 320px or 200% zoom
- Autocomplete tokens for identity, contact, address, and password fields
- Appropriate `inputmode`, capitalization, and spellcheck behavior
- Reduced-motion preference respected

## Submission Behavior

- A valid submit disables only the triggering submit button, marks the form busy, and changes the label to an action-specific progress phrase.
- Invalid submission never disables controls.
- Duplicate state-changing submissions are prevented while the first request is in flight.
- GET search/filter forms remain immediately reusable and do not use busy locking.
- Server responses remain the source of success confirmation.
- The existing session and CSRF protections remain intact; the form redesign must not reintroduce the prior page-expired defect.

## Implementation Phases and Commit Boundaries

1. Add executable form inventory and validation-contract tests; commit.
2. Build shared PHP field/error helpers, CSS components, and the progressive validation controller; commit.
3. Migrate public, authentication, profile, and participant forms; commit.
4. Migrate organizer event, venue, coupon, announcement, review, check-in, search, and action forms; commit.
5. Migrate administrator CMS, settings, moderation, operations, payment, newsletter, report, analytics, search, and action forms; commit.
6. Audit and close backend validation gaps with service/controller tests; commit.
7. Run automated suites and browser-based end-to-end verification across roles, fixing any discovered regressions in scoped commits.

## Acceptance Criteria

- All 86 existing form instances are classified and audited.
- All user-editable controls use a consistent visual and semantic field contract appropriate to their form type.
- Every required data-entry field is marked and validated in the browser and independently validated on the server.
- Client and server constraints match wherever parity is possible.
- Every server validation error reaches an associated field or a form-level summary.
- File, schedule, confirmation, conditional, paired-coordinate, and destructive-action workflows receive domain-specific handling.
- Forms remain fully usable without JavaScript.
- Duplicate submission protection does not block invalid submissions or GET filters.
- Light/dark themes, mobile layout, keyboard navigation, screen-reader relationships, and 200% zoom remain usable.
- PHP unit/integration tests, JavaScript tests, syntax checks, CSS build, asset checks, and browser end-to-end scenarios pass.
- Each completed implementation phase is committed without staging unrelated workspace files.
