export const meta = {
  name: 'carlton-track-h',
  description: 'Reservation Creation + Rate Grid: walk-in booking form and 14-day BAR rate grid',
  phases: [
    { title: 'Build', detail: 'CreateReservation page + RateGrid page + service + store + mock data (parallel)' },
    { title: 'Integrate', detail: 'Wire App.jsx, AppShell, mockClient, global.css' },
    { title: 'Review', detail: 'Naive code review of all Track H files' },
  ],
}

const CTX = `CARLTON BUILD CONTEXT
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton — No TypeScript, React 19, Zustand 5, plain CSS.
PRIMITIVES from src/components/ui/Primitives.jsx: Badge, Button, Card, Field, Input, Select, Textarea, PageHeader, EmptyState, ErrorState, Skeleton, Table
CSS tokens: --color-deep-teal #08414C, --color-muted-gold #C0A060, --color-bg #F7F5F0, --color-surface #FFF,
  --color-border #E5DED2, --color-text-strong #102F35, --color-text-body #38545A, --color-text-muted #7A8B8E,
  --color-success #268765, --color-warning #B7791F, --color-danger #B44545, --color-info #2E8799
Bilingual: const locale = useAuthStore((s) => s.locale); const t = (en, ar) => pickLocalized({ en, ar }, locale);
Utilities: pickLocalized from '../utils/i18n.js', formatDate/minutesAgo from '../utils/date.js', formatMoney from '../utils/money.js'
Mock data imports: cloneMockData, matchesSearch, paginateItems, throwMockError from '../envelope.js'; addMinutesUtc from '../../utils/date.js'
baseDay = '2026-06-28T00:00:00.000Z'; function at(min) { return addMinutesUtc(baseDay, min); }
Carlton envelope: { success, message, data, request_id, error_code, errors } — paginated: data.items + data.meta`

phase('Build')
await parallel([
  () => agent(CTX + `
Write src/mocks/data/rates.js — BAR (best available rate) rate grid for room types.

Rate grid: for each room type, a rate per day for the next 14 days from baseDay.
Room type IDs (from reservations.js): rt_deluxe_king, rt_deluxe_twin, rt_premium_suite, rt_executive_suite.
Room type names: Deluxe King, Deluxe Twin, Premium Suite, Executive Suite.
Base rates: deluxe_king=$180, deluxe_twin=$160, premium_suite=$230, executive_suite=$295.
Weekend uplift (Fri/Sat = day 4,5,11,12 from baseDay): +20%.
Flash sale: rt_deluxe_twin day 8 and day 9 → -15%.

Generate 14 dates from baseDay: dates[0] = baseDay date, dates[1] = +1 day, ..., dates[13] = +13 days.
Use addMinutesUtc(baseDay, i * 1440) to get each date string, then slice to YYYY-MM-DD.

const ROOM_TYPES = [
  { id: 'rt_deluxe_king', name: 'Deluxe King', base_rate: 180 },
  { id: 'rt_deluxe_twin', name: 'Deluxe Twin', base_rate: 160 },
  { id: 'rt_premium_suite', name: 'Premium Suite', base_rate: 230 },
  { id: 'rt_executive_suite', name: 'Executive Suite', base_rate: 295 },
];

Build rateGridSeed: array of { room_type_id, room_type_name, rates: [{date, rate, available (bool)}] }
  For each room type, build 14 entries.
  For rt_deluxe_king: days 0-13 at base_rate with weekend uplift. available=true for all.
  For rt_deluxe_twin: base with weekend uplift, day 8 and 9 get -15% flash sale. available=true except day 8 and 9 set available=false (sold out — so rate is shown but no rooms).
  For rt_premium_suite: base + weekend uplift. available=true for days 0-13 except day 3,4 (already booked).
  For rt_executive_suite: base + weekend uplift. available=true days 0-13.

let rateGrid = cloneMockData(rateGridSeed);

Export:
  getRateGrid() — return cloneMockData(rateGrid) — shape: array of room type objects with rates
  getRateForDate(room_type_id, date_string) — return the rate entry for that date (or null)
  resetRatesMockData()

IMPORTANT: All rate computations must produce integer rates (use Math.round).
`, { label: 'build-data-H' }),

  () => agent(CTX + `
Write src/services/rateService.js and also extend src/services/reservationsService.js with a createReservation function.

### src/services/rateService.js
import { apiClient } from './apiClient.js';

Named exports:
  getRateGrid(params={}) { return apiClient.get('/rates/grid', { params }); }
  getRateForDate(room_type_id, date) { return apiClient.get('/rates/grid/' + room_type_id + '/' + date); }

export const rateService = { getGrid: getRateGrid, getRateForDate };

### Extend src/services/reservationsService.js
Read the file first.
Add this function if not already present:
  export function createReservation(data) { return apiClient.post('/reservations', data); }
And add createReservation to the named export object at the bottom (if it has one).
`, { label: 'build-services-H' }),

  () => agent(CTX + `
Write src/store/rateStore.js

State: grid (array of room type rate objects), isLoading, error

Actions:
  fetchGrid(params) — call rateService.getGrid(params), set grid=res.data (or res.data.items if paginated)

import { rateService } from '../services/rateService.js';
export const useRateStore = create((set) => ({
  grid: [], isLoading: false, error: null,
  fetchGrid: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const res = await rateService.getGrid(params);
      set({ grid: res.data || [], isLoading: false });
    } catch (err) { set({ error: err.payload || { message: err.message }, isLoading: false }); }
  },
}));
`, { label: 'build-store-H' }),

  () => agent(CTX + `
Write two page files:

### src/pages/RateGrid.jsx — 14-day BAR rate grid
Imports:
  import { useEffect } from 'react';
  import { TrendingUp } from 'lucide-react';
  import { Badge, PageHeader, Skeleton, ErrorState } from '../components/ui/Primitives.jsx';
  import { useRateStore } from '../store/rateStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatMoney } from '../utils/money.js';

Component RateGrid:
  Destructure grid, isLoading, error, fetchGrid from useRateStore.
  locale from useAuthStore, t helper.
  useEffect → fetchGrid() on mount.
  Loading → <Skeleton lines={6} />
  Error → <ErrorState ... />

  PageHeader title=t('Rate Grid','شبكة الأسعار') subtitle=t('14-day best available rates by room type.','أفضل الأسعار المتاحة لـ 14 يومًا حسب نوع الغرفة.')

  Rate grid table (className="rg-table-wrap"):
    thead: Room Type | one <th> per date (show short date: Jan 28, Jan 29 etc using date.slice(5).replace('-','/') or formatDate)
    tbody: one row per room type
      td: room_type_name
      for each rate entry: td className="rg-cell" + (not available ? " rg-unavail" : "") + (is weekend/flash? check rate vs base via simple heuristic — rate > base_rate*1.1 → "rg-high"; rate < base_rate*0.95 → "rg-low")
        show formatMoney(rate.rate) or "—" if not available
    Render "—" cells if rate not found for that date.

  Legend strip: green=available, yellow=high demand, orange=low/sale, grey=unavailable

export default RateGrid;

### src/pages/CreateReservation.jsx — walk-in booking form
Imports:
  import { useState } from 'react';
  import { useNavigate } from 'react-router-dom';
  import { Badge, Button, Card, Field, Input, Select, PageHeader } from '../components/ui/Primitives.jsx';
  import { useReservationStore } from '../store/reservationStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatMoney } from '../utils/money.js';

Component CreateReservation:
  const navigate = useNavigate();
  Destructure createReservation (action), isLoading from useReservationStore — if createReservation does not exist in reservationStore, call reservationsService directly (import { reservationsService } from '../services/reservationsService.js' and call reservationsService.create(form)).
  locale from useAuthStore, t helper.

  Form state:
    const [form, setForm] = useState({ guest_name: '', guest_name_ar: '', nationality: '', phone: '',
      room_type_id: 'rt_deluxe_king', check_in: '', check_out: '', adults: 1, children: 0,
      source: 'direct', payment_status: 'deposit_paid', notes: '' });
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [success, setSuccess] = useState(null);

  Simple validation (check guest_name, check_in, check_out, room_type_id not empty).

  handleSubmit: validate → setSubmitting(true) → call reservationsService.create(form) (import directly from service) → if success navigate to /reservations else setErrors from envelope.

  Render:
    PageHeader title=t('New Reservation','حجز جديد') subtitle=t('Create a walk-in or advance reservation.','إنشاء حجز طارئ أو مسبق.')

    Two-column form Card padded:
      Guest section: Field guest_name (required), guest_name_ar, nationality, phone
      Stay section: Select room_type_id (options: rt_deluxe_king/Deluxe King, rt_deluxe_twin/Deluxe Twin, rt_premium_suite/Premium Suite, rt_executive_suite/Executive Suite), Input check_in (type=date), Input check_out (type=date), Input adults (type=number min=1), Input children (type=number min=0)
      Booking section: Select source (direct/phone/booking_com/corporate), Select payment_status (deposit_paid/authorized/unpaid)
      Textarea notes optional

      If errors from API: show field-level errors below each Field
      Submit Button isLoading=submitting t('Create reservation','إنشاء الحجز') variant=primary

export default CreateReservation;
`, { label: 'build-pages-H' }),
])

phase('Integrate')
await agent(`
Integration agent — Track H: Reservation Creation + Rate Grid.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

Track H new files (already written): src/pages/RateGrid.jsx, src/pages/CreateReservation.jsx,
src/services/rateService.js, src/store/rateStore.js, src/mocks/data/rates.js
Modified: src/services/reservationsService.js (createReservation added)

Modify these 4 shared files:

### 1. src/App.jsx
Read file.
Add imports:
  import RateGrid from './pages/RateGrid.jsx';
  import CreateReservation from './pages/CreateReservation.jsx';
Add routes (inside AppShell group):
  <Route path="/rates" element={<RequirePermission anyOf={['rates.view','rates.manage']}><RateGrid /></RequirePermission>} />
  <Route path="/reservations/new" element={<RequirePermission permission="reservations.create"><CreateReservation /></RequirePermission>} />

IMPORTANT: The /reservations/new route MUST appear BEFORE the /reservations/:uuid route to prevent 'new' being captured as a UUID param.

### 2. src/components/layout/AppShell.jsx
Read file.
Add in the Reservations group:
  { label: 'New Booking', path: '/reservations/new', icon: PlusCircle, permission: 'reservations.create' },
  { label: 'Rate Grid', path: '/rates', icon: TrendingUp, anyOf: ['rates.view','rates.manage'] },
Add PlusCircle and TrendingUp to lucide-react import if not present.

### 3. src/mocks/mockClient.js
Read file.
Add import: import { getRateGrid } from './data/rates.js';
Also check if reservationsService creates reservations — look for POST /reservations in the mock.
If not present, in routeReservationRequest add:

  if (method === "POST" && pathname === "/reservations") {
    requirePermission(session, PERMISSIONS.RESERVATIONS_CREATE || "reservations.create", locale);
    // Create a new reservation from body
    const { reservationsSeed, reservations } = ... // actually import createMockReservation if available or write inline
    // Simplest safe approach: return a mock success envelope with a fake new reservation id
    const newId = 'res_' + Date.now().toString().slice(-4);
    const code = 'RES-' + newId.toUpperCase().replace('RES_','');
    const newRes = {
      id: newId, reservation_code: code, status: 'upcoming',
      guest: { full_name: body.guest_name || 'New Guest', full_name_ar: body.guest_name_ar || null, nationality: body.nationality || 'LB', vip_level: 'standard', phone_masked: '***', email_masked: '***' },
      room_type: { id: body.room_type_id || 'rt_deluxe_king', name: body.room_type_id || 'Deluxe King' },
      check_in: body.check_in, check_out: body.check_out, arrival_at: body.check_in, departure_at: body.check_out,
      nights: 1, adults: body.adults || 1, children: body.children || 0,
      source: body.source || 'direct', payment_status: body.payment_status || 'deposit_paid',
      total_amount: 0, nightly_rate: 180, folio_balance: 0, notes: body.notes || null,
      assigned_room_id: null, assigned_room: null, timeline: [],
      created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
    };
    return newRes;
  }

  For rate grid:
  if (method === "GET" && pathname === "/rates/grid") {
    requirePermission(session, "rates.view", locale);
    return getRateGrid();
  }

### 4. src/styles/global.css
Read file. Append at end:
/* === Track H: Rate Grid + Create Reservation === */
.rg-table-wrap { overflow-x: auto; }
.rg-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.rg-table th { background: var(--color-deep-teal); color: #fff; padding: 0.4rem 0.6rem; text-align: center; white-space: nowrap; font-weight: 600; }
.rg-table th:first-child { text-align: start; min-width: 140px; }
.rg-table td { padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--color-border); text-align: center; }
.rg-table td:first-child { text-align: start; font-weight: 600; color: var(--color-text-strong); }
.rg-cell.rg-high { background: #fffbeb; color: var(--color-warning); }
.rg-cell.rg-low { background: #f0fff4; color: var(--color-success); }
.rg-cell.rg-unavail { background: #f5f5f5; color: var(--color-text-muted); text-decoration: line-through; }
.rg-legend { display: flex; gap: 1rem; padding: 0.5rem 0; font-size: 0.8rem; flex-wrap: wrap; }
.rg-legend-item { display: flex; align-items: center; gap: 0.4rem; }
.rg-legend-dot { width: 10px; height: 10px; border-radius: 2px; }
.rg-legend-dot.avail { background: var(--color-surface); border: 1px solid var(--color-border); }
.rg-legend-dot.high { background: #fffbeb; border: 1px solid var(--color-warning); }
.rg-legend-dot.low { background: #f0fff4; border: 1px solid var(--color-success); }
.rg-legend-dot.unavail { background: #f5f5f5; border: 1px solid #ddd; }
.cr-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
@media (max-width: 640px) { .cr-form-grid { grid-template-columns: 1fr; } }
.cr-section-label { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--color-text-muted); letter-spacing: 0.05em; margin: 1rem 0 0.5rem; grid-column: 1 / -1; }
`, { label: 'integrate-H' })

phase('Review')
const reviewH = await agent(`
Naive reviewer — Track H (Rate Grid + Create Reservation). Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
Read these files and report bugs:
1. src/mocks/data/rates.js — check addMinutesUtc import from correct path (../../utils/date.js), check Math.round applied to all rates, check ROOM_TYPES array
2. src/services/rateService.js — check URL paths
3. src/store/rateStore.js — check that it sets grid=res.data (rate data may not be paginated — check that res.data is array directly)
4. src/pages/RateGrid.jsx — check that it handles the grid being an array of {room_type_id, room_type_name, rates:[]} objects correctly
5. src/pages/CreateReservation.jsx — check it imports reservationsService correctly (named import pattern from that file), check navigate after success
6. src/App.jsx — CRITICAL: verify /reservations/new route appears BEFORE /reservations/:uuid to avoid param collision
7. src/mocks/mockClient.js — check POST /reservations does not crash (no undefined imports)

Report numbered findings. "Clean: [file]" if no issues.
`, { label: 'review-H' })

log('Track H complete. Review: ' + String(reviewH).substring(0, 300))
return { track: 'H', review: reviewH }
