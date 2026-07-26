# Carlton Dashboard Foundation

Date: 2026-06-28

This document combines the Carlton dashboard product spec, the hotel brand guideline PDF, and the CartX admin architecture review. Use it as the baseline before implementation.

## Source Inputs

- Product spec: `C:/Users/TECH SHOP/Downloads/Telegram Desktop/carlton-dashboard-frontend-spec.md`
- Brand guideline: `C:/Users/TECH SHOP/Downloads/Telegram Desktop/Hotel GuideLine-2_260212_133154.pdf`
- Reusable architecture reference: `C:/TupCode/CartX/cartx-admin`

## Core Product Model

Carlton is a staff/admin dashboard for hotel operations. It is not a marketing site and should not feel like a public booking landing page. The first screen after login should be a working operations surface: arrivals, departures, live queue, open requests, tickets, occupancy, and cash/folio alerts depending on permission.

The product is permission-first:

- `super_admin` bypasses all permission checks and sees everything.
- `staff` sees only pages and actions present in the login permission list.
- Role presets such as reception, kitchen, housekeeping, concierge, and events are defaults only. Do not hardcode role-based UI decisions.

The API envelope differs from CartX and should be implemented centrally:

```json
{
  "success": true,
  "message": "Success",
  "data": {},
  "request_id": "uuid"
}
```

Paginated responses put rows in `data.items` and pagination in `data.meta`.

Errors include stable `error_code`. UI should display localized `message`, but branch logic on `error_code`. Validation errors use `errors[field]`.

## Required Platform Capabilities

- Auth with Sanctum bearer token.
- Boot rehydration through `GET /auth/me`.
- Permission-gated navigation, routes, page actions, and table row actions.
- Full English/Arabic support with LTR/RTL layout mirroring.
- `Accept-Language: en|ar` on API requests.
- UTC timestamp display converted in the browser.
- Firebase real-time subscriptions for live queue, service requests, support tickets, and chat.
- REST remains authoritative for actions; Firestore is used for live updates and reconciliation.

## Build Order

1. Shell: login, API client, auth store, permission store, RTL/LTR layout, navigation, Firebase utility.
2. Reservations: reservation list, detail, check-in, check-out, availability calendar.
3. Live Operations Queue: unified real-time queue for service requests and tickets.
4. Service requests, support tickets, and guest chat.
5. CMS/content management with bilingual fields and media galleries.
6. Pricing, folios/payments, pre-arrival approvals, service bookings, events.
7. Staff/permissions, guests directory, reports, profile/settings.

## Page Inventory

- Login.
- Overview / Home.
- Live Operations Queue.
- Reservations.
- Reservation detail / check-in / check-out.
- Calendar / Availability.
- Rates & Pricing.
- Folios & Payments.
- Service Requests.
- Support Tickets.
- Guest Chat inbox.
- Event / RFP Inquiries.
- Pre-Arrival approvals.
- Service Bookings.
- CMS / Content.
- Guests directory.
- Staff & Permissions.
- Reports.
- Profile / settings.

## CartX Architecture To Reuse

Reuse these patterns from `C:/TupCode/CartX/cartx-admin`:

- Vite + React + React Router app shell.
- Lazy route loading with route fallback.
- `Layout` composed from sidebar, header, and outlet.
- Zustand stores per domain.
- Service modules per API resource.
- Central Axios client with auth, progress, error handling, and response normalization.
- Permission store and `RequirePermission` route/page wrapper.
- Permission-gated sidebar items and action buttons.
- Shared primitives: `PageHeader`, `Pagination`, `SearchFilter`, `Modal`, `Button`, `Input`, `Select`, `Textarea`, `Table`, `Badge`, `ErrorState`, skeleton loaders.
- List-page grammar: page header, filters, table, row actions, empty/error/loading states, pagination.
- Detail-page grammar: identity header, status badges, action rail, sections/tabs, modal confirmations.

Change these from CartX:

- API client unwrap must support Carlton's `success`, `data.items`, `data.meta`, `error_code`, `request_id`.
- Permission names and modules are Carlton-specific.
- The role split is `super_admin` vs `staff`, not admin/employee/seller.
- Add RTL as a first-class layout mode, not just a language selector.
- Add Firebase subscription utilities and optimistic/reconciled action patterns.
- Replace e-commerce modules with hotel operations modules.
- Replace CartX visual system with Carlton brand tokens below.

## Carlton Brand Tokens

The PDF guideline text labels repeated the same hex values on multiple swatches, so the following implementation tokens combine printed values with sampled rendered swatches.

Primary:

- Deep teal: `#08414C` / printed `#08414d`
- Muted gold: `#C0A060`
- Bright teal: `#2E8799`
- Deep teal tint: `#9CB3B8`
- Gold tint: `#E6D9BF`
- Bright teal tint: `#4393A3`

Secondary:

- Taupe: `#B2987D`
- Taupe tint: `#E0D6CB`
- Sand: `#D9CAAD`
- Sand tint: `#F0EADE`
- Soft gray: `#AAB1B2`
- Gray tint: `#B2B9BA`

Suggested dashboard neutrals:

- Page background: `#F7F5F0`
- Surface: `#FFFFFF`
- Raised surface: `#FBFAF7`
- Border: `#E5DED2`
- Text strong: `#102F35`
- Text body: `#38545A`
- Text muted: `#7A8B8E`

Operational status colors should remain functional and distinct from the brand palette:

- Success: `#268765`
- Warning: `#B7791F`
- Danger: `#B44545`
- Info: `#2E8799`
- Critical queue accent: `#C75C3A`

## Typography

Brand guideline:

- English title font: The Seasons.
- English body font: Cabinet Grotesk.
- Arabic title font: Al Qabas.
- Arabic body font: Tajawal.

Dashboard adaptation:

- Use The Seasons only for brand moments, login, large empty states, and perhaps the hotel wordmark area. Do not use it for dense tables or operational controls.
- Use Cabinet Grotesk for English UI if licensed/available. Fallback: `Cabinet Grotesk`, `Avenir Next`, `Segoe UI`, sans-serif.
- Use Tajawal for Arabic UI. Fallback: `Tajawal`, `Noto Sans Arabic`, sans-serif.
- Avoid negative letter spacing in the dashboard. Keep headings compact and readable.

## Brand Motif

The guideline uses a jasmine flower motif and hotel photography. For the admin dashboard:

- Use the jasmine mark sparingly as a logo/avatar accent, login background detail, empty state icon, or subtle watermark.
- Do not scatter flower patterns across tables, forms, or queue screens.
- Keep operational screens clean and data-dense.
- Hotel photography can work on login only. Do not use large promotional imagery inside the staff workspace.

## Dashboard Visual Direction

The staff dashboard should feel premium but operational:

- Quiet, focused, and efficient.
- Dense enough for repeated staff use.
- Teal navigation, warm neutral surfaces, gold emphasis used sparingly.
- 8px card radius for dashboard surfaces, matching admin-tool expectations.
- Minimal shadows; rely on borders, spacing, and typography hierarchy.
- Strong visible states for real-time items, priority, assignment, overdue age, and action feedback.
- No marketing hero layouts inside the authenticated dashboard.

Recommended shell:

- Left sidebar in deep teal with gold/white logo treatment.
- Header with current property/day, language toggle, notifications, and profile.
- Main content on warm off-white background.
- Cards/tables on white or raised warm surfaces.
- Action buttons use deep teal primary, gold secondary/accent, danger for destructive actions.

## Component Rules

Common primitives:

- `Button`: primary, secondary, ghost, danger, icon-only, loading.
- `Input`, `Select`, `Textarea`: field errors from `validation_failed.errors`.
- `Badge`: reservation status, queue status, priority, payment status.
- `Table`: sticky header option, dense rows, row actions, empty state.
- `Modal`: confirm actions, assign staff, settle payment, cancel with reason.
- `Tabs`: detail pages and bilingual CMS editing.
- `SegmentedControl`: list/kanban/calendar modes.
- `RealtimeIndicator`: connected, reconnecting, stale.
- `PermissionGate`: hides or disables actions based on permission.

State coverage:

- Loading skeletons.
- Empty state with direct next action when allowed.
- Error state showing localized message and request ID when available.
- 401 routes to login.
- 403 marks permission missing and removes/hides gated action.
- 422 maps to fields.
- Real-time stale state if Firestore subscription disconnects.

## Domain-Specific UX Notes

Live Operations Queue:

- This is the operational heart of the dashboard.
- Support list, kanban, and possibly compact split-pane detail.
- Prioritize age, room, guest, department, source, priority, assigned staff, and status.
- Actions: claim, assign, status update.
- Use optimistic UI only with reconciliation from REST/Firestore.

Reservations:

- List filters: status, date range, source, guest, booking code.
- Detail must make check-in and check-out flows explicit.
- Room assignment at check-in must only show available rooms of the matching room type.
- Do not calculate availability on the client.

CMS:

- Every content form needs paired EN/AR fields.
- Image galleries need upload, reorder, active/inactive, and validation states.

Staff & Permissions:

- Non-super-admin managers cannot grant permissions they do not hold.
- Non-super-admin managers cannot edit or demote super admins.
- UI should reflect backend guardrails by disabling or hiding controls.

PII and Documents:

- Guest profiles, IDs, passports, and pre-arrival documents are sensitive.
- Do not cache document images.
- Avoid exposing sensitive details in generic list rows unless needed for staff workflow.

## Immediate Implementation Plan

1. Scaffold the Carlton dashboard from the CartX admin frontend shape.
2. Replace design tokens and global CSS with Carlton tokens.
3. Implement the Carlton API client envelope and auth store.
4. Implement permission-driven route/nav model.
5. Add RTL/LTR support at the shell level.
6. Build Overview and Reservations first.
7. Add Firebase abstraction before implementing live queue/chat pages.

