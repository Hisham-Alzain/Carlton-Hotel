# Carlton Dashboard Tracker

Date: 2026-06-28

Status values: `Todo`, `In Progress`, `Blocked`, `Done`.

Current phase: mock-first operational prototype.

## Milestone Status

| ID | Milestone | Status | Notes |
| --- | --- | --- | --- |
| M0 | Project Setup | Done | Vite React app, scripts, routes, folder structure, lint/build, and git hygiene are in place. |
| M1 | Design System And Shell | Done | Carlton tokens, authenticated shell, login, responsive base, RTL/LTR direction, and shared primitives are in place. |
| M2 | Auth, Permissions, And Mock API | Done | Envelope-aware mock API, auth store, permission gates, personas, locale, and mock request errors are in place. |
| M3 | Overview And Reservations Core | In Progress | Overview, reservations list/detail, check-in, and check-out/folio settlement are implemented; availability calendar/grid remains. |
| M4 | Live Operations Prototype | In Progress | Mock real-time queue and status movement are implemented; service-request and ticket detail pages remain. |
| M5 | Chat And Guest Context | Todo | Guest conversations and ticket replies. |
| M6 | Content, Pricing, Folios, And Service Modules | Todo | Broader management areas. |
| M7 | Staff, Reports, Polish, And Demo Readiness | Todo | Presentation-quality prototype pass. |

## Task Backlog

| ID | Milestone | Task | Status | Depends On | Acceptance Criteria |
| --- | --- | --- | --- | --- | --- |
| T-001 | M0 | Create Vite React project in Carlton workspace | Done | None | App starts locally and `npm run build` passes. |
| T-002 | M0 | Establish folder structure | Done | T-001 | `src/components`, `src/pages`, `src/services`, `src/store`, `src/styles`, `src/utils`, `src/mocks` exist. |
| T-003 | M0 | Port selected CartX primitives as Carlton-owned components | Done | T-002 | Components are copied/adapted without CartX brand/domain references. |
| T-004 | M0 | Add base routes and placeholder pages | Done | T-002 | Router renders login, overview, reservations, queue, settings, and not-found placeholders. |
| T-005 | M1 | Implement Carlton CSS tokens | Done | T-001 | Global variables match foundation token set. |
| T-006 | M1 | Implement responsive app shell | Done | T-005 | Sidebar/header/content work on desktop and mobile. |
| T-007 | M1 | Add RTL/LTR direction system | Done | T-006 | English and Arabic modes mirror layout without overlap. |
| T-008 | M1 | Build shared UI primitives | Done | T-005 | Button, inputs, badges, table, modal, tabs, pagination, skeleton, empty, and error states exist. |
| T-009 | M1 | Build login screen | Done | T-008 | Mock login supports language toggle and Carlton visual identity. |
| T-010 | M2 | Implement mock API client envelope | Done | T-001 | Client unwraps data/meta and preserves error_code/request_id. |
| T-011 | M2 | Implement auth store | Done | T-010 | Token, user, permissions, locale, login/logout/rehydrate work with mocks. |
| T-012 | M2 | Implement permission store and gate | Done | T-011 | Routes and components can gate by permission. |
| T-013 | M2 | Define navigation metadata | Done | T-012 | Nav renders by permission and super_admin sees all. |
| T-014 | M2 | Create mock staff personas | Done | T-011 | Super admin, reception, kitchen, housekeeping, concierge, and sales accounts are available. |
| T-015 | M2 | Simulate auth and validation errors | Done | T-010 | UI can show 401, 403, and 422 states with request IDs. |
| T-016 | M3 | Build overview dashboard | Done | T-013 | Cards appear based on permissions and use mock `/dashboard/summary`. |
| T-017 | M3 | Build reservations list | Done | T-010 | Filters, pagination metadata, status badges, and row navigation work. |
| T-018 | M3 | Build reservation detail | Done | T-017 | Full detail page: stay meta grid, guest info panel (name/VIP/nationality/masked contacts), folio alert, activity timeline. |
| T-019 | M3 | Build check-in room assignment flow | Done | T-018 | Room picker only shows available rooms for matching room type. |
| T-020 | M3 | Build check-out and folio settlement entry | Done | T-018 | Folio balance display, styled payment method picker (cash/card/transfer), settle-and-check-out flow; status transitions to departed. |
| T-021 | M3 | Build availability calendar/grid | Todo | T-017 | Availability is read from mock endpoint, not computed in component. |
| T-022 | M4 | Implement mock real-time utility | Done | T-010 | Components can subscribe/unsubscribe and receive inserts/updates. |
| T-023 | M4 | Build Live Operations Queue | Done | T-022 | Kanban board shows live service/ticket items. |
| T-024 | M4 | Add queue actions | In Progress | T-023 | Status update (open→in_progress→waiting_guest) works; claim self-assign and staff-assign UI remain. Mock endpoints for claim/assign/status are all wired in mockClient. |
| T-025 | M4 | Build Service Requests page | Todo | T-023 | Department-scoped service request view works. |
| T-026 | M4 | Build Support Tickets page | Todo | T-023 | Ticket list/detail and status update work. |
| T-027 | M5 | Build Guest Chat inbox | Todo | T-022 | Conversations update live and staff can send mock messages. |
| T-028 | M5 | Build ticket conversation reply flow | Todo | T-026 | Staff reply appears in thread and updates ticket metadata. |
| T-029 | M5 | Build guest context side panel | Todo | T-027 | Reservation, room, and profile context are visible without crowding chat. |
| T-030 | M6 | Build CMS content pages | Todo | T-008 | Bilingual EN/AR form pattern is established. |
| T-031 | M6 | Build rates and pricing pages | Todo | T-008 | Base prices, pricing rules, promo codes, and quote preview work with mock data. |
| T-032 | M6 | Build folios and payments pages | Todo | T-008 | Folio list/detail and settle modal work. |
| T-033 | M6 | Build events/RFP pages | Todo | T-008 | Inquiry list/detail/status/assign flows work. |
| T-034 | M6 | Build pre-arrival approvals | Todo | T-008 | Document review UI avoids persistent image caching patterns. |
| T-035 | M6 | Build service bookings pages | Todo | T-008 | Booking list/detail/catalog management work. |
| T-036 | M7 | Build staff and permissions pages | Todo | T-012 | Permission guard rails are reflected in UI. |
| T-037 | M7 | Build guests directory | Todo | T-017 | Guest profile and history are viewable. |
| T-038 | M7 | Build reports pages | Todo | T-010 | Mock operational and revenue charts render. |
| T-039 | M7 | Build profile/settings pages | Todo | T-011 | Profile, password, locale, notification preferences, and device registration UI exist. |
| T-040 | M7 | Responsive and RTL visual QA | Todo | M1-M6 | Key pages checked in desktop/mobile and EN/AR. |
| T-041 | M7 | Accessibility QA | Todo | M1-M6 | Keyboard focus, labels, contrast, modals, and tables pass manual review. |
| T-042 | M7 | Demo seed scenarios | Todo | M3-M6 | Persona walkthroughs are documented and seeded. |
| T-043 | M7 | Final build verification | Todo | All implementation tasks | `npm run build` passes and major screens are visually reviewed. |

## Active Decisions

| ID | Decision | Status | Rationale |
| --- | --- | --- | --- |
| D-001 | Build frontend-only with mock services | Decided | Lets us imagine and evaluate the dashboard without waiting for backend. |
| D-002 | Preserve Carlton API envelope in mocks | Decided | Avoids a future rewrite when backend exists. |
| D-003 | Reuse CartX structure, replace domain and visual system | Decided | Keeps proven dashboard architecture while avoiding CartX brand/domain leakage. |
| D-004 | Build operations-first authenticated dashboard | Decided | Staff need a working surface, not a marketing page. |
| D-005 | Use jasmine motif sparingly | Decided | Keeps Carlton identity without harming operational density. |

## Open Questions

| ID | Question | Status | Owner | Notes |
| --- | --- | --- | --- | --- |
| Q-001 | Are Carlton brand fonts licensed and available as files? | Open | User | If not, use fallbacks in the foundation document. |
| Q-002 | Should the first prototype optimize for desktop only or include mobile from the first pass? | Open | User | Staff dashboards are usually desktop-first, but responsive shell should still exist. |
| Q-003 | Which persona should be the first demo path: reception or operations manager? | Open | User | Reception aligns with reservations; operations manager aligns with live queue. |
| Q-004 | Should mock data include Arabic names/content from the first pass? | Open | User | Recommended for RTL validation. |

## Change Log

| Date | Change |
| --- | --- |
| 2026-06-28 | Created foundation, execution plan, and tracker for mock-first Carlton dashboard prototype. |
| 2026-06-28 | Started parallel implementation with setup, design-system, and mock-platform workers. |
| 2026-06-28 | Integrated worker outputs into a runnable Carlton dashboard with auth, permissions, overview, reservations, check-in, live queue, lint/build, and browser smoke verification. |
| 2026-06-28 | Upgraded visual direction using UI/UX Pro Max and frontend-design guidance: premium Carlton shell, stronger monogram/brand treatment, richer overview command surface, polished tables, queue board, login, and visual capture workflow. |
| 2026-06-28 | Ran smart swarm review for luxury hospitality visual quality, component audit, and regression risk; replaced cheap pill/button/stat/queue treatments with quieter 5-star operations styling and fixed smoke/capture scripts. |
| 2026-06-28 | Claude Code session: created CLAUDE.md with full architecture reference and CartX comparison. Implemented T-020 (check-out + folio settlement): folio balance display, styled radio payment picker, settle-and-check-out flow, departed state. Rebuilt T-018 reservation detail with stay meta grid, guest info panel (VIP badge, masked contacts, Arabic name), folio alert, and activity timeline. Build passes. Feature-by-feature implementation mode active going forward. |
