# Role-aware organizer navigation

## Problem

The shared public header renders both desktop and mobile “For organizers” links with a fixed `/register?role=organizer` destination. That is correct for guests, but it sends authenticated super administrators, organizers, and participants back into account creation instead of their existing workspace.

## Considered approaches

1. **Use the existing `/dashboard` dispatcher for authenticated users — selected.** The public layout only decides whether a session exists. `DashboardController::index()` remains the single source of truth for role-specific destinations.
2. Map each role directly inside the public layout. This avoids one redirect but duplicates authorization-aware routing in presentation code and can drift when roles change.
3. Send every authenticated user to `/organizer/dashboard`. This is incorrect for participants and administrators and would rely on middleware rejection rather than useful navigation.

## Design

The shared public layout derives one organizer-menu destination and label:

- guest: label `For organizers`, destination `/register?role=organizer`;
- authenticated user: label `Dashboard`, destination `/dashboard`.

The same values are used by the desktop primary navigation and mobile menu. `/dashboard` continues to redirect by authenticated role:

- `super-admin` → `/admin/dashboard`;
- `organizer` → `/organizer/dashboard`;
- `participant` → `/participant/dashboard`.

The authenticated menu item replaces the prior duplicate dashboard CTA, leaving one dashboard entry in each responsive header. Guest Log in and Get started actions remain unchanged. Homepage marketing calls to create an organizer account remain outside this narrowly scoped menu fix.

## Accessibility and UX

Desktop and mobile expose the same label and destination. The authenticated mobile item uses a dashboard icon instead of the organizer-stage icon so the visual cue matches its destination. Each responsive header contains one dashboard link, avoiding redundant keyboard stops. No JavaScript is required, and touch-target sizing remains unchanged.

## Testing

Tests render the public layout as a guest and as each authenticated role, assert the desktop and mobile menu targets and labels, and exercise the `/dashboard` dispatcher for all supported roles. Focused layout/dashboard tests, the complete PHP suite, syntax checks, and real-browser guest/authenticated header checks must pass.
