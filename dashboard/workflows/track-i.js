export const meta = {
  name: 'carlton-track-i',
  description: 'GM Reports + Events Pipeline: financial KPIs, occupancy charts, RFP event pipeline',
  phases: [
    { title: 'Build', detail: 'Reports page + Events page + service + store + mock data (parallel)' },
    { title: 'Integrate', detail: 'Wire App.jsx, AppShell, mockClient, global.css' },
    { title: 'Review', detail: 'Naive code review of all Track I files' },
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
Write src/mocks/data/reports.js — GM-level reporting data.

The reports mock returns pre-computed aggregates (no dynamic computation from reservations — standalone data).

reportData shape:
  period: '2026-06-01 to 2026-06-28',
  kpis: {
    occupancy_rate: 0.72, occupied_rooms: 8, total_rooms: 11,
    adr: 218, revpar: 157, revenue_today: 1745,
    revenue_mtd: 48600, revenue_ytd: 312000, currency: 'USD',
  },
  daily_breakdown: array of 28 objects {date (YYYY-MM-DD), occupancy_rate, adr, revpar, revenue}
    Generate for dates from 2026-06-01 to 2026-06-28.
    Use index i to produce pseudorandom but realistic data:
      occupancy = 0.45 + (Math.sin(i * 0.7) * 0.2 + 0.1) clamped to [0.3, 0.95]
      adr = Math.round(180 + Math.sin(i * 0.5) * 40) [range ~140-220]
      revpar = Math.round(adr * occupancy)
      revenue = Math.round(revpar * 11) [11 total rooms]
    Use i as the variable from a for loop, not Date.now() or Math.random().
  top_room_types: [
    { room_type_name: 'Executive Suite', revenue: 8400, nights: 28, occupancy_rate: 0.84 },
    { room_type_name: 'Premium Suite', revenue: 6900, nights: 30, occupancy_rate: 0.71 },
    { room_type_name: 'Deluxe King', revenue: 18000, nights: 100, occupancy_rate: 0.69 },
    { room_type_name: 'Deluxe Twin', revenue: 11200, nights: 70, occupancy_rate: 0.61 },
  ],
  sources: [
    { source: 'direct', count: 42, revenue: 19800 },
    { source: 'booking_com', count: 38, revenue: 17100 },
    { source: 'corporate', count: 21, revenue: 9400 },
    { source: 'phone', count: 11, revenue: 4800 },
  ],

IMPORTANT: Build daily_breakdown using a for loop with index i from 0 to 27. No Date.now() or Math.random().
Use deterministic Math.sin(i * factor) for variation.

Export:
  getReport(params) — return cloneMockData(reportData). params may have period, from_date, to_date (ignored in mock).
  resetReportMockData() — no-op (data is static)
`, { label: 'build-data-I-reports' }),

  () => agent(CTX + `
Write src/mocks/data/events.js — hotel event / RFP pipeline data.

An event: id, reference, title, event_type ('conference'|'wedding'|'corporate'|'social'),
status ('rfp'|'tentative'|'confirmed'|'cancelled'),
contact_name, contact_email_masked, contact_phone_masked,
event_date, setup_date, teardown_date,
expected_attendees, spaces_required: [string],
revenue_estimate, deposit_paid (bool),
notes, created_at, updated_at, assigned_to

Seed 5 events:
  ev_001: title='Gulf Tech Conference 2026', event_type='conference', status='confirmed',
    contact_name='Ahmad Nassif', contact_email_masked='a***@gulftech.com', contact_phone_masked='+971 *** 8822',
    event_date=at(5*1440), setup_date=at(4*1440), teardown_date=at(6*1440),
    expected_attendees=120, spaces_required=['Grand Ballroom','Breakout Room A','Breakout Room B'],
    revenue_estimate=18000, deposit_paid=true,
    notes='AV setup required by 08:00. Catering: halal lunch x120.', created_at=at(-30*1440), updated_at=at(-2*1440), assigned_to='Karim Azzam'

  ev_002: title='Al-Nassir Wedding Reception', event_type='wedding', status='confirmed',
    contact_name='Sara Al-Nassir', contact_email_masked='s***@gmail.com', contact_phone_masked='+961 *** 4400',
    event_date=at(12*1440), setup_date=at(11*1440), teardown_date=at(13*1440),
    expected_attendees=250, spaces_required:['Grand Ballroom','Garden Terrace'],
    revenue_estimate=32000, deposit_paid=true,
    notes='Florist access from 09:00. Cake delivery 15:00.', created_at=at(-60*1440), updated_at=at(-5*1440), assigned_to='Karim Azzam'

  ev_003: title='Pharma Exec Roundtable', event_type='corporate', status='tentative',
    contact_name='Leila Mansour', contact_email_masked='l***@pharma.ae', contact_phone_masked='+971 *** 5511',
    event_date=at(20*1440), setup_date=at(20*1440), teardown_date=at(20*1440),
    expected_attendees=30, spaces_required:['Executive Boardroom'],
    revenue_estimate=4500, deposit_paid=false,
    notes='Awaiting final headcount. May need AV upgrade.', created_at=at(-10*1440), updated_at=at(-1*1440), assigned_to=null

  ev_004: title='Product Launch — Noor Cosmetics', event_type='social', status='rfp',
    contact_name='Maya Khalil', contact_email_masked='m***@noorcos.com', contact_phone_masked='+961 *** 9933',
    event_date=at(35*1440), setup_date=at(35*1440), teardown_date=at(36*1440),
    expected_attendees=80, spaces_required:['Rooftop Lounge'],
    revenue_estimate=9000, deposit_paid=false,
    notes='Requires rooftop exclusivity. Brand install 06:00.', created_at=at(-3*1440), updated_at=at(-3*1440), assigned_to=null

  ev_005: title='Annual Gala — Damascus Chamber', event_type='social', status='cancelled',
    contact_name='Omar Barakat', contact_email_masked='o***@chamber.sy', contact_phone_masked='+963 *** 7700',
    event_date=at(-5*1440), setup_date=at(-6*1440), teardown_date=at(-4*1440),
    expected_attendees=150, spaces_required:['Grand Ballroom'],
    revenue_estimate:21000, deposit_paid=false,
    notes='Cancelled by client. Deposit forfeited clause may apply.', created_at=at(-45*1440), updated_at=at(-8*1440), assigned_to='Karim Azzam'

References: ev_001→EVT-001 etc.

let events = cloneMockData(eventsSeed);
let nextEventId = 100;

Export:
  listEvents(params) — filter status, event_type, matchesSearch([title,contact_name,reference], query), paginateItems
  getEventById(id)
  updateEventStatus(id, status) — validate in ['rfp','tentative','confirmed','cancelled'], update, return clone
  createEvent(body) — id='ev_'+nextEventId++, reference='EVT-'+id.replace('ev_',''), status='rfp', created_at/updated_at=new Date().toISOString(), push, return clone
  resetEventMockData()
`, { label: 'build-data-I-events' }),

  () => agent(CTX + `
Write src/services/reportsService.js and src/services/eventsService.js

### src/services/reportsService.js
import { apiClient } from './apiClient.js';
Named exports:
  getReport(params={}) { return apiClient.get('/reports/dashboard', { params }); }
export const reportsService = { get: getReport };

### src/services/eventsService.js
import { apiClient } from './apiClient.js';
Named exports:
  listEvents(params={}) { return apiClient.get('/events', { params }); }
  getEvent(id) { return apiClient.get('/events/' + id); }
  updateEventStatus(id, status) { return apiClient.patch('/events/' + id + '/status', { status }); }
  createEvent(data) { return apiClient.post('/events', data); }
export const eventsService = { list: listEvents, get: getEvent, updateStatus: updateEventStatus, create: createEvent };
`, { label: 'build-services-I' }),

  () => agent(CTX + `
Write src/store/reportsStore.js and src/store/eventsStore.js

### src/store/reportsStore.js
State: report (null), isLoading, error
Actions:
  fetchReport(params) — call reportsService.get(params), set report=res.data
import { reportsService } from '../services/reportsService.js';
export const useReportsStore = create((set) => ({ ... }));

### src/store/eventsStore.js
State: events[], meta, isLoading, error, statusFilter ('all'|'rfp'|'tentative'|'confirmed'|'cancelled')
Actions:
  fetchEvents(params) — call eventsService.list({ ...params, status: get().statusFilter==='all'?undefined:get().statusFilter })
  updateStatus(id, status) — call eventsService.updateStatus(id,status), replace event in list
  createEvent(data) — call eventsService.create(data), unshift to events
  setStatusFilter(v) — set statusFilter
import { eventsService } from '../services/eventsService.js';
export const useEventsStore = create((set, get) => ({ ... }));
`, { label: 'build-stores-I' }),

  () => agent(CTX + `
Write two page files:

### src/pages/Reports.jsx — GM financial reports dashboard
Imports:
  import { useEffect } from 'react';
  import { BarChart2, TrendingUp } from 'lucide-react';
  import { Badge, Card, PageHeader, Skeleton, ErrorState } from '../components/ui/Primitives.jsx';
  import { useReportsStore } from '../store/reportsStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatMoney } from '../utils/money.js';

Component Reports:
  Destructure report, isLoading, error, fetchReport from useReportsStore.
  locale from useAuthStore, t helper.
  useEffect → fetchReport() on mount.
  Loading → Skeleton; Error → ErrorState.

  PageHeader title=t('GM Reports','تقارير المدير العام') subtitle={report ? t('Period: '+report.period, 'الفترة: '+report.period) : ''}

  KPI grid (className="rpt-kpi-grid"):
    Cards for: Occupancy (percentage), ADR (formatMoney), RevPAR (formatMoney), Revenue Today (formatMoney), Revenue MTD (formatMoney), Revenue YTD (formatMoney)
    Each card: big number + label

  Daily trend section: simple ASCII-style bar chart using CSS.
    For daily_breakdown, show last 14 days (slice last 14).
    Each day: div with a bar div whose height is proportional to revpar (max revpar in set → 100% height).
    Bar label: date.slice(8) (day number). Title tooltip showing revpar.
    className: rpt-bar-chart

  Top room types table:
    Table columns: Room Type, Revenue (formatMoney), Nights, Occupancy (%)

  Source breakdown:
    For each source: horizontal bar showing revenue percentage of total.
    className: rpt-source-bar

export default Reports;

### src/pages/Events.jsx — hotel events pipeline
Imports:
  import { useEffect } from 'react';
  import { Calendar, PlusCircle } from 'lucide-react';
  import { Badge, Button, Card, PageHeader, Select, Skeleton, ErrorState, EmptyState, Table } from '../components/ui/Primitives.jsx';
  import { useEventsStore } from '../store/eventsStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatDate } from '../utils/date.js';
  import { formatMoney } from '../utils/money.js';

const STATUS_META = {
  rfp:       { label: { en: 'RFP',       ar: 'طلب عرض' },  variant: 'neutral' },
  tentative: { label: { en: 'Tentative', ar: 'مبدئي' },     variant: 'warning' },
  confirmed: { label: { en: 'Confirmed', ar: 'مؤكد' },      variant: 'success' },
  cancelled: { label: { en: 'Cancelled', ar: 'ملغى' },      variant: 'danger' },
};
const TYPE_LABELS = { conference:'Conference', wedding:'Wedding', corporate:'Corporate', social:'Social' };

Component Events:
  Destructure events, meta, isLoading, error, statusFilter, fetchEvents, updateStatus, setStatusFilter from useEventsStore.
  locale from useAuthStore, t helper.
  useEffect → fetchEvents() on mount + when statusFilter changes ([fetchEvents,statusFilter] deps).
  Loading → Skeleton; Error → ErrorState; Empty → EmptyState.

  PageHeader title=t('Events & Functions','الفعاليات والمناسبات') subtitle=t('Event pipeline and RFP management.','خط سير الفعاليات وإدارة طلبات العروض.')
    actions: Badge showing confirmed count (events.filter(e=>e.status==='confirmed').length)

  Filter Select for statusFilter (all/rfp/tentative/confirmed/cancelled)

  Table columns:
    reference, title (bold), event_type (TYPE_LABELS), status (Badge from STATUS_META),
    event_date (formatDate), expected_attendees, revenue_estimate (formatMoney),
    assigned_to (or '—'), actions:
      rfp → Button ghost "Tentative" onClick=()=>updateStatus(event.id,'tentative')
      tentative → Button ghost "Confirm" onClick=()=>updateStatus(event.id,'confirmed')
      (confirmed and cancelled have no action buttons)

  Each row: onRowClick not needed (no detail page this iteration)

export default Events;
`, { label: 'build-pages-I' }),
])

phase('Integrate')
await agent(`
Integration agent — Track I: Reports + Events.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

Track I new files (already written): src/pages/Reports.jsx, src/pages/Events.jsx,
src/services/reportsService.js, src/services/eventsService.js,
src/store/reportsStore.js, src/store/eventsStore.js,
src/mocks/data/reports.js, src/mocks/data/events.js

Modify these 4 shared files:

### 1. src/App.jsx
Read file.
Add imports:
  import Reports from './pages/Reports.jsx';
  import Events from './pages/Events.jsx';
Add routes (inside AppShell group):
  <Route path="/reports" element={<RequirePermission permission="reports.view"><Reports /></RequirePermission>} />
  <Route path="/events" element={<RequirePermission anyOf={['events.view','events.manage']}><Events /></RequirePermission>} />

### 2. src/components/layout/AppShell.jsx
Read file.
Add or find a 'Sales & Reports' or similar navGroup. If it does not exist, create one.
Add nav items:
  { label: 'Reports', path: '/reports', icon: BarChart2, permission: 'reports.view' },
  { label: 'Events', path: '/events', icon: Calendar, anyOf: ['events.view','events.manage'] },
Add BarChart2 and Calendar to lucide-react import if not present.

### 3. src/mocks/mockClient.js
Read file.
Add imports:
  import { getReport } from './data/reports.js';
  import { listEvents, getEventById, updateEventStatus, createEvent } from './data/events.js';

Add routes — look for where routeReservationRequest or a general routing function handles requests.
In the appropriate routing function (likely routeReservationRequest or a wrapper), add before the final throw:

  // Reports
  if (method === "GET" && pathname === "/reports/dashboard") {
    requirePermission(session, "reports.view", locale);
    return getReport(params);
  }
  // Events
  if (method === "GET" && pathname === "/events") {
    requirePermission(session, "events.view", locale);
    return listEvents(params);
  }
  const evtDetailMatch = pathname.match(/^\\/events\\/([^/]+)$/);
  if (method === "GET" && evtDetailMatch && !pathname.includes('/status')) {
    requirePermission(session, "events.view", locale);
    const ev = getEventById(evtDetailMatch[1]);
    if (!ev) throwMockError({ message: "Event not found", error_code: "not_found" }, 404);
    return ev;
  }
  const evtStatusMatch = pathname.match(/^\\/events\\/([^/]+)\\/status$/);
  if (method === "PATCH" && evtStatusMatch) {
    requirePermission(session, "events.manage", locale);
    return updateEventStatus(evtStatusMatch[1], body?.status);
  }
  if (method === "POST" && pathname === "/events") {
    requirePermission(session, "events.manage", locale);
    return createEvent({ ...body, actor: session.user.name });
  }

### 4. src/styles/global.css
Read file. Append at end:
/* === Track I: Reports + Events === */
.rpt-kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
.rpt-kpi-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; padding: 1rem; }
.rpt-kpi-label { font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 0.25rem; }
.rpt-kpi-value { font-size: 1.4rem; font-weight: 800; color: var(--color-text-strong); }
.rpt-bar-chart { display: flex; align-items: flex-end; gap: 4px; height: 100px; padding: 0.5rem 0; overflow-x: auto; }
.rpt-bar-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 24px; }
.rpt-bar { width: 20px; background: var(--color-bright-teal); border-radius: 3px 3px 0 0; min-height: 4px; transition: height 0.3s; }
.rpt-bar-date { font-size: 9px; color: var(--color-text-muted); }
.rpt-source-bar-wrap { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
.rpt-source-row { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; }
.rpt-source-label { width: 100px; color: var(--color-text-body); }
.rpt-source-bar-bg { flex: 1; background: var(--color-border); border-radius: 4px; height: 8px; }
.rpt-source-bar-fill { background: var(--color-deep-teal); height: 100%; border-radius: 4px; }
.rpt-source-amt { min-width: 80px; text-align: end; color: var(--color-text-muted); }
`, { label: 'integrate-I' })

phase('Review')
const reviewI = await agent(`
Naive reviewer — Track I (Reports + Events). Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
Read these files and report bugs:
1. src/mocks/data/reports.js — check that daily_breakdown is built with a for loop (no Date.now/Math.random), check Math.sin used with index i, length should be 28
2. src/mocks/data/events.js — check cloneMockData import, all events have valid status values
3. src/services/reportsService.js and eventsService.js — check URL paths
4. src/store/reportsStore.js — check it sets report=res.data (not items)
5. src/store/eventsStore.js — check setStatusFilter exists, fetchEvents correctly reads statusFilter from state
6. src/pages/Reports.jsx — check daily_breakdown slice logic, check formatMoney applied to currency values
7. src/pages/Events.jsx — check Table component receives correct columns + rows prop shapes (Table expects {columns, rows})
8. src/mocks/mockClient.js — check that new imports from reports.js and events.js don't shadow or conflict with existing imports

Report numbered findings. "Clean: [file]" if no issues.
`, { label: 'review-I' })

log('Track I complete. Review: ' + String(reviewI).substring(0, 300))
return { track: 'I', review: reviewI }
