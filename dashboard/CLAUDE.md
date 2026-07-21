# Carlton Hotel Dashboard — CLAUDE.md

## What This Is

Mock-first hotel staff operations dashboard prototype. **Frontend only** — no backend required. All API calls route through a local mock layer that mirrors the intended production API contracts (Carlton envelope standard). Goal: prove UX, permissions, bilingual flows, and real-time patterns before the backend is ready.

Reference codebase: `C:\tupcode\cartx` — Carlton reuses CartX's Vite/React/Zustand/service architecture and replaces the domain logic and visual system with Carlton brand.

---

## Tech Stack

| Layer | Tool |
|---|---|
| Build | Vite 7 |
| UI | React 19 + React Router DOM 7 |
| State | Zustand 5 |
| Icons | Lucide React |
| Styling | Plain CSS (tokens + global + components) |
| Linting | ESLint 9 |
| Testing | Playwright smoke tests (Python scripts) |

No TypeScript. No CSS framework (Tailwind, etc.). No component library. All UI primitives are hand-built in `src/components/ui/`.

---

## Project Structure

```
src/
├── components/
│   ├── ui/           # Button, Input, Select, Textarea, Badge, Card, Table,
│   │                 # Modal, Tabs, Pagination, Skeleton, Form, Primitives
│   ├── layout/       # AppShell, Header, Sidebar
│   ├── common/       # PageHeader, EmptyState, ErrorState, RealtimeIndicator
│   └── RequirePermission.jsx
├── pages/            # Login, Overview, Reservations, ReservationDetail,
│                     # OperationsQueue, Settings, NotFound
├── services/         # apiClient, authService, dashboardService,
│                     # reservationsService, queueService
├── store/            # authStore, permissionStore, dashboardStore,
│                     # reservationStore, queueStore
├── mocks/            # mockClient, auth, personas, permissions, envelope,
│                     # realtime, seed, data/
├── styles/           # tokens.css, global.css (1200+ lines), base.css,
│                     # components.css, index.css
└── utils/            # permissions, i18n, date, money
scripts/
├── smoke_carlton.py       # Playwright smoke test
└── capture_carlton_screens.py  # Screenshot capture → artifacts/screens/
```

---

## Architecture Patterns

### API Layer
- `apiClient.js` is the single entry point for all requests
- In mock mode it routes to `mockClient.js` instead of hitting a real server
- All responses use the **Carlton envelope**:
  ```json
  { "success": true, "message": "...", "data": {}, "request_id": "uuid",
    "error_code": null, "errors": {} }
  ```
- Pagination lives at `data.items` + `data.meta`
- Locale sent as `Accept-Language` header

### Auth
- Sanctum bearer token pattern
- `authStore.login()` → `authService.login()` → mock validates → stores token + user + permissions in Zustand + localStorage
- `authStore.rehydrate()` fires on App mount → calls `GET /auth/me` to restore session
- 401 → redirect to login; 403 → hide/disable action, never redirect

### Permissions
- Permission-first, not role-first — `super_admin` bypasses all; `staff` check individual permissions
- `RequirePermission` wrapper gates routes and UI elements
- `permissionStore.can(permission)` for runtime checks
- Sidebar filters nav items by permission
- **Never hardcode role names in UI logic** — always check specific permissions

### State
- One Zustand store per domain: auth, permissions, dashboard, reservations, queue
- Services (API calls) are separate from stores (state)
- Stores import services; components import stores

### Real-Time (Mock)
- `src/mocks/realtime.js` — timer-based event emitter simulating WebSocket updates
- `subscribeToQueueMock()` / unsubscribe on unmount
- `RealtimeIndicator` component shows connected/reconnecting/stale state
- Future: Firebase replaces mock layer; REST remains authoritative

### RTL / i18n
- `src/utils/i18n.js` normalizes locale (en → en, ar → ar, default en)
- `applyDocumentDirection()` sets `dir="rtl"` on `<html>` if Arabic
- CSS uses logical properties (`inset-inline`, `margin-inline`, etc.)
- Font stack switches via `[dir=rtl]` selector to Arabic fonts

---

## Carlton Brand

### Colors (CSS tokens in `src/styles/tokens.css`)
```css
/* Primary */
--color-deep-teal:   #08414C   /* sidebar, primary buttons */
--color-muted-gold:  #C0A060   /* accent, highlights */
--color-bright-teal: #2E8799   /* info status */

/* Neutrals */
--color-bg:          #F7F5F0   /* page background */
--color-surface:     #FFFFFF
--color-border:      #E5DED2
--color-text-strong: #102F35
--color-text-body:   #38545A
--color-text-muted:  #7A8B8E

/* Operational Status */
--color-success:     #268765
--color-warning:     #B7791F
--color-danger:      #B44545
--color-info:        #2E8799
--color-critical:    #C75C3A   /* queue critical accent */
```

### Typography
- English display: The Seasons (login / empty states only — use sparingly)
- English body: Cabinet Grotesk → Avenir Next → Segoe UI
- Arabic display: Al Qabas
- Arabic body: Tajawal → Noto Sans Arabic

Hotel imagery only on login page or tightly controlled brand moments. Never on operational screens.

---

## Mock Personas (password: `demo1234`)

| Email | Name | Role | Notes |
|---|---|---|---|
| admin@carlton.test | Nadia Hariri | General Manager | super_admin — all permissions |
| reception@carlton.test | Omar Mansour | Front Desk Supervisor | reservations, queue, chat, folios |
| kitchen@carlton.test | Mira Haddad | Kitchen Coordinator | queue, service_requests only |
| housekeeping@carlton.test | Layth Saleh | Housekeeping Lead | locale: ar — queue, reservations (view) |
| ops@carlton.test | Omar Nasser | Operations Concierge | locale: ar — queue, chat, guests |
| sales@carlton.test | Karim Azzam | Events Sales Manager | events, reports, cms |

---

## Milestone Status (as of 2026-06-28)

| # | Milestone | Status |
|---|---|---|
| M0 | Project Setup | Done |
| M1 | Design System & Shell | Done |
| M2 | Auth, Permissions, Mock API | Done |
| M3 | Overview & Reservations Core | In Progress |
| M4 | Live Operations Prototype | In Progress |
| M5 | Chat & Guest Context | Todo |
| M6 | Content, Pricing, Folios, Services | Todo |
| M7 | Staff, Reports, Polish, Demo Readiness | Todo |

Task backlog tracked in `CARLTON_DASHBOARD_TRACKER.md` (T-001 → T-043+).

---

## Dev Commands

```bash
npm run dev       # Vite dev server → http://localhost:5173
npm run build     # Production build → dist/
npm run preview   # Preview built app
npm run lint      # ESLint
npm run smoke     # Playwright smoke test (requires dev server running)
npm run screens   # Screenshot capture → artifacts/screens/
```

---

## Planning & Task Workflow

This project runs a **hybrid workflow** — GSD-style planning docs at the root + Codex Swarm for execution tracking.

### Planning Docs (GSD-style, root level)
| File | Role |
|---|---|
| `CARLTON_DASHBOARD_FOUNDATION.md` | Product spec + brand + API contracts (source of truth) |
| `CARLTON_DASHBOARD_EXECUTION_PLAN.md` | Milestone roadmap + working rules |
| `CARLTON_DASHBOARD_TRACKER.md` | Task backlog (T-001→T-043+), decisions, open questions |

**Always read these before starting new work.** They define what to build and why.

### Codex Swarm (execution tracking)
Task management via `.codex-swarm/agentctl.py` — **do not hand-edit `.codex-swarm/tasks.json`**.

```bash
python .codex-swarm/agentctl.py --help   # Full command reference
```

Agent flow: ORCHESTRATOR → PLANNER → CODER → TESTER → REVIEWER → INTEGRATOR

Default mode: `direct` (single checkout). Switch to `branch_pr` only for explicit per-task branches.

When Claude Code (GSD) is used alongside Codex Swarm: GSD handles the discussion/plan/verify loop; Codex Swarm handles task bookkeeping. Keep `CARLTON_DASHBOARD_TRACKER.md` up to date regardless of which tool executed the work.

---

## Coding Rules

- **No TypeScript** — plain JS with JSX
- **No CSS framework** — use existing tokens and global styles in `src/styles/`
- **No comments explaining what the code does** — name things well instead
- **No role-based UI logic** — always check permissions, never `if (user.role === 'reception')`
- **Normalize API responses in services** — stores and components consume clean data
- **UI independent from mock data quirks** — don't let mock structure leak into components
- **UTC timestamps everywhere** — format in browser via `src/utils/date.js`
- **Preserve Carlton API envelope** in all new mock handlers
- Keep dashboards **data-dense and operational** — this is a staff tool, not a marketing page

## Error State Requirements

Every data surface must handle:
- Loading skeleton
- Empty state with next action
- Error state with localized message + `request_id`
- 401 → login redirect
- 403 → hide/disable the action (never redirect, never error screen)
- 422 → field-level `errors` mapping from envelope
- Real-time disconnect → stale indicator

---

## CartX Reference

Path: `C:\tupcode\cartx` — the source architecture Carlton is forked from.
Specifically `C:\tupcode\cartx\cartx-admin\` is the admin dashboard analogue.

### What Carlton inherits from CartX admin
- Vite 7 + React 19 + React Router 7 SPA shell
- Zustand 5 store-per-domain pattern (`authStore`, `permissionStore`, domain stores)
- Service modules per API domain (auth, reservations, queue vs CartX: orders, products, sellers)
- `RequirePermission` permission gate wrapper (identical pattern)
- Lucide React icons + `clsx` for classNames
- `EmptyState`, `ErrorState`, `PageHeader`, `Pagination`, `Skeleton` component shapes
- Permission-gated sidebar navigation

### Where Carlton deliberately diverges from CartX admin
| Area | CartX Admin | Carlton |
|---|---|---|
| CSS | Tailwind 3.4 + CSS Modules per component | Plain CSS — `tokens.css` + `global.css` (Carlton brand) |
| HTTP client | Axios directly in `services/api.js` | Custom `apiClient.js` wrapping fetch with Carlton envelope |
| Backend | Laravel 12 / Sanctum (real API) | Mock-first — `src/mocks/mockClient.js` |
| Services | Single `services/api.js` | Per-domain service files |
| Toast | `react-hot-toast` | Not yet added |
| Date utils | `date-fns` library | Custom `src/utils/date.js` |
| Auth roles | Admin, Seller, Driver, User | `super_admin` + permission-first `staff` (no role presets) |
| Notifications | Laravel Reverb WebSockets | Mock real-time (`src/mocks/realtime.js`) |

### How to use the CartX reference
When adding a new feature, check how CartX admin implemented the equivalent page/component for:
- Component structure and prop shapes
- State management pattern (how the store is shaped)
- Error handling and loading state coverage
- Permission gate placement

Then adapt for Carlton: replace Tailwind classes with Carlton CSS tokens, use the Carlton mock client instead of real Axios, and use Carlton's envelope pattern.
