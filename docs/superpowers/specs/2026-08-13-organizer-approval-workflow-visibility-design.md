# Organizer Approval Workflow Visibility Design

## Problem

The organizer approval lifecycle exists in the backend, but its state is fragmented across the product. An organizer cannot see a useful approval summary on the organizer dashboard. A super administrator cannot see pending organizer work on the admin dashboard. On the organizer review page, the approval button disappears when the application is ineligible, so an unverified email looks like a missing feature instead of a clear blocker.

## Decision

Keep the existing trust rule: a super administrator may approve only an active user with the organizer role and a verified email address. Do not let an administrator use organization approval as a substitute for email ownership verification.

Make the lifecycle visible and actionable in three places:

1. The organizer dashboard shows the organization approval state, the current blocker or readiness message, and a link to the most relevant next step.
2. The super-admin dashboard shows a pending organizer metric and a compact review queue with direct links to organizer evidence pages.
3. The organizer evidence page always explains approval readiness. Eligible applications show the approval form. Ineligible pending or rejected applications show the disabled approval control plus a checklist that identifies each unmet requirement. Rejection remains available where the existing lifecycle permits it.

## Status and Copy Model

- `pending` and eligible: `Ready for administrator review` with informational styling.
- `pending` and email unverified: `Email verification required` with warning styling and a direct organizer link to the verification guidance.
- `pending` and inactive: `Account activation required` with warning styling.
- `approved`: `Organization approved` with success styling.
- `rejected`: `Changes requested` with error styling and the stored rejection reason.

Status chips keep their existing semantic colors. Warning copy must not use error-red unless a submitted decision failed or the application was rejected.

## Data Contract

`DashboardMetricsRepository` owns the two read models because it already supplies both dashboards:

- `organizerApprovalForUser(int $userId): array` returns organizer id, organization name, approval status, rejection reason, account status, and email verification timestamp for the signed-in organizer only.
- `pendingOrganizerApplications(int $limit = 4): array` returns the oldest pending applications with organizer id, organization name, contact name, email verification timestamp, and application date.
- `totals(): array` also returns `pending_organizers`.

All queries exclude soft-deleted users. The review list uses a bounded integer limit and deterministic oldest-first ordering.

## Interaction and Accessibility

- A disabled approval button remains discoverable but cannot submit.
- The button references the readiness explanation with `aria-describedby`.
- Readiness requirements use text and icons, never color alone.
- Queue links use descriptive accessible names such as `Review Community Events`.
- Cards wrap cleanly on narrow screens and preserve a minimum 44px target for actions.
- No new JavaScript or third-party dependency is required.

## Verification

- Repository unit tests prove scoping, pending counts, ordering, and soft-delete exclusion.
- View/controller tests prove organizer and administrator visibility and both eligible and blocked action states.
- The focused tests must fail before production changes and pass afterward.
- Run the complete PHP test suite, asset build, and browser checks for organizer and admin dashboards before completion.

