# Maintenance Confirmation Form Design

## Goal

Correct the admin operations maintenance form so its confirmation field follows the project-wide form layout, communicates the destructive action clearly, and presents client-side and server-side errors without detached controls or duplicated instructions.

## Root cause

The maintenance form uses the obsolete `form-label`, `form-input`, `form-help`, and `form-error` class names. Those selectors are not defined by the current stylesheet. The form also lacks the shared `form-stack` and `field-group` wrappers used by working forms. As a result, the label, input, help text, error, and button are independent inline elements instead of a single vertical field group.

The client validator derives its field name from the visible label. Because that label currently contains the complete instruction, `Type ENABLE MAINTENANCE to continue`, the generated required message repeats the instruction in both the summary label and the validation message.

## Design direction

This is a preserve redesign of a trust-first administrator control. It keeps the existing dashboard theme, semantic danger color, typography, spacing scale, button, and error-summary component.

- Design variance: 3. The field uses the predictable shared form hierarchy.
- Motion intensity: 2. Existing focus and press feedback are sufficient.
- Visual density: 5. The control remains compact but has clear separation between instruction, input, feedback, and operational context.

## Form hierarchy

The form will use `form-stack` for vertical rhythm. The confirmation control will use one `field-group` with this order:

1. Label: `Confirmation phrase`
2. Text input
3. Exact phrase instruction
4. Inline server or client error
5. Operational availability note

The input will provide `data-form-label="Confirmation phrase"` so client errors use a concise field name. The required field remains visible, keyboard accessible, and associated with its help and error text through `aria-describedby`.

## Validation contract

The exact phrase remains determined by the server from the intended next state:

- `ENABLE MAINTENANCE` when maintenance is inactive
- `DISABLE MAINTENANCE` when maintenance is active

The server remains authoritative and continues to reject any non-exact value with HTTP 422. The input remains required in the browser. The field copy will explain that the phrase must be entered exactly, while the server message will identify the failed requirement without repeating the whole label sentence.

## Responsive and theme behavior

The shared `field-group` control is full width and stacks at every viewport width. Existing semantic tokens provide the light and dark surfaces, border, focus ring, helper text, and error contrast. No page-specific layout rule or new design token is needed.

## Accessibility

- A persistent label remains above the input.
- Error and help IDs are included in `aria-describedby`.
- Invalid server responses set `aria-invalid="true"`.
- The existing linked error summary continues to target the input.
- The exact phrase is text, not placeholder-only content.
- Spellcheck is disabled and character autocapitalization is requested for the exact administrative phrase.

## Testing

An integration-style controller test will render the invalid response and assert the user-visible form contract: shared wrappers, concise label, exact phrase instruction, full-width shared input styling hook, linked descriptions, invalid state, and non-legacy class names. The existing valid and invalid maintenance state tests will continue to prove that a rejected phrase does not change state and an exact phrase does.

Browser verification will cover the validation-error state at desktop and mobile widths in light and dark themes. It will confirm vertical order, full-width control sizing, readable summary copy, focus visibility, and absence of horizontal overflow.

## Out of scope

- Changing maintenance authorization or routing
- Changing which endpoints remain available during maintenance
- Changing the global form-error summary component
- Adding page-specific CSS or a new component library
