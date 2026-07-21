# Carlton Dashboard Execution Plan

Date: 2026-06-28

Purpose: build a frontend-only Carlton Hotel staff dashboard prototype that feels real enough to evaluate product flow, visual identity, and operational ergonomics before a backend is available.

## Strategy

This phase is mock-first, not backend-first. The app should use the same frontend architecture we would use in production, but data will come from local mock services shaped like the intended API contracts.

The prototype should prove:

- The Carlton visual identity works for a dense staff/admin product.
- Permission-driven navigation and actions are understandable.
- Hotel operations workflows can be imagined and reviewed end to end.
- Arabic/English and RTL/LTR behavior are designed from the beginning.
- Live operational surfaces can be demonstrated without Firebase by using a mock real-time layer.
- Later backend integration will be a service-layer swap, not a UI rewrite.

## Technical Baseline

Use the CartX admin app as the structural reference:

- Vite + React.
- React Router route tree.
- Zustand stores.
- Service modules per domain.
- Central API client.
- Permission gate wrapper.
- Sidebar, header, page header, pagination, modal, table, input, badge, loading, empty, and error primitives.

For Carlton, change the domain and contracts:

- Roles: `super_admin` and `staff`.
- Gate by permissions, never role presets.
- API envelope: `success`, `message`, `data`, `request_id`, `error_code`, `errors`.
- Pagination: `data.items` and `data.meta`.
- Locale header: `Accept-Language`.
- Full RTL support.
- Mock real-time utility for queue, tickets, service requests, and chat.

## Prototype Data Layer

Create a mock API layer that mirrors the future backend:

- `mockClient` returns Carlton-style envelopes.
- Mock services expose the same method names expected from real services.
- Seed data uses UUIDs, UTC ISO timestamps, permission arrays, and realistic hotel entities.
- List endpoints support pagination and basic filters.
- Mutations simulate latency and validation errors.
- Permission errors can be simulated for staff accounts.
- Real-time mock subscriptions use timers or an event emitter to insert/update queue and chat items.

This keeps UI code honest while avoiding backend dependency.

## Milestones

### M0 - Project Setup

Goal: establish a runnable Carlton frontend project using the CartX-proven structure.

Deliverables:

- Vite React app scaffold in the Carlton workspace.
- Base folder structure: `src/components`, `src/pages`, `src/services`, `src/store`, `src/styles`, `src/utils`, `src/mocks`.
- Lint/build scripts.
- Initial Git-friendly project files.

Done when:

- `npm run build` passes.
- App renders a placeholder shell route.

### M1 - Design System And Shell

Goal: translate the Carlton brand into reusable dashboard UI.

Deliverables:

- Global CSS variables using Carlton tokens.
- English and Arabic font stack decisions.
- Sidebar, header, content shell, responsive layout.
- RTL/LTR direction handling.
- Shared UI primitives: button, input, select, textarea, badge, table, modal, tabs, pagination, skeleton, error state, empty state.
- Login screen with Carlton identity and mock auth.

Done when:

- Super admin can log in with mock credentials.
- Staff can log in with restricted permissions.
- Layout mirrors correctly in Arabic mode.
- Shared primitives have loading, disabled, error, and focus states.

### M2 - Auth, Permissions, And Mock API

Goal: make the prototype behave like a permissioned staff product.

Deliverables:

- Auth store with token, user, permissions, and locale.
- Permission store and `RequirePermission`.
- Navigation model generated from permission metadata.
- Mock API client with envelope unwrap and error mapping.
- Mock `/auth/login`, `/auth/me`, `/auth/logout`.
- Mock staff personas: super admin, reception, kitchen, housekeeping, concierge, sales.

Done when:

- Nav and route access change by persona.
- 401, 403, and 422 states can be demonstrated.
- Request ID appears in error UI when available.

### M3 - Overview And Reservations Core

Goal: build the first meaningful hotel operations flow.

Deliverables:

- Overview dashboard with permission-aware summary cards.
- Reservations list with filters and pagination.
- Reservation detail with guest, rooms, stay dates, payment state, and timeline.
- Check-in room assignment flow using matching available rooms.
- Check-out/folio settlement entry point.
- Availability calendar or grid for room-type availability.

Done when:

- Reception persona can review arrivals, assign rooms, and simulate check-in.
- Calendar and room picker use mock availability data.
- Restricted personas do not see reservation pages or actions.

### M4 - Live Operations Prototype

Goal: prove the real-time operational centerpiece.

Deliverables:

- Live Operations Queue with list and kanban modes.
- Mock real-time item arrival/update stream.
- Filters for department, status, source, and priority.
- Claim, assign, and status update actions.
- Service Requests page using the same queue model.
- Support Tickets list and ticket detail.

Done when:

- New queue items appear without refresh.
- Status updates reconcile through the mock real-time layer.
- Department personas see relevant operational work.

### M5 - Chat And Guest Context

Goal: make communication workflows reviewable.

Deliverables:

- Guest chat inbox.
- Conversation thread with mock live messages.
- Send message and image attachment UI.
- Ticket conversation view with bot summary and staff reply.
- Guest context side panel with reservation/room.

Done when:

- Staff can switch conversations, reply, and see simulated live responses.
- Tickets and chats share visual language but remain distinct.

### M6 - Content, Pricing, Folios, And Service Modules

Goal: add the remaining management areas in a prototype-appropriate depth.

Deliverables:

- CMS content list/forms with paired EN/AR fields.
- Room types and pricing rules screens.
- Folios list/detail and settlement modal.
- Event/RFP inquiries.
- Pre-arrival approval review.
- Service bookings and bookable catalog screens.

Done when:

- Each module has realistic list/detail/create/edit flows.
- Bilingual CMS forms are clear and usable.
- Sensitive document views avoid persistent caching behavior in UI code.

### M7 - Staff, Reports, Polish, And Demo Readiness

Goal: make the prototype coherent enough to present and iterate.

Deliverables:

- Staff and permissions management.
- Guests directory.
- Reports dashboard with mock charts.
- Profile/settings.
- Responsive pass for desktop/tablet/mobile.
- Accessibility pass for keyboard, focus, labels, contrast, and RTL.
- Demo seed scenarios and personas documented.

Done when:

- A reviewer can walk through all main personas.
- `npm run build` passes.
- Critical screens are visually checked in English and Arabic.

## Working Rules

- Keep UI code independent from mock data shape quirks; normalize in services/stores.
- Avoid backend-specific shortcuts in components.
- Preserve realistic data constraints: UUIDs, UTC timestamps, permissions, validation errors, pagination, and request IDs.
- Build the actual staff workspace first, not a landing page.
- Keep dashboard screens data-dense and operational.
- Use hotel imagery only on login or carefully controlled brand moments.
- Do not let the jasmine motif become decoration on operational pages.

## Review Rhythm

Use `CARLTON_DASHBOARD_TRACKER.md` as the source of task status.

Recommended cadence:

- Update status whenever a task starts or finishes.
- Add a note when assumptions change.
- Mark blockers explicitly.
- Keep a short decision log for product or design calls.
- Verify each milestone with build and visual review before moving on.

