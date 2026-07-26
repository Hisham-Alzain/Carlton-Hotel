export const meta = {
  name: 'carlton-track-d',
  description: 'Departure Services: late-checkout, luggage storage, airport transport queue page',
  phases: [
    { title: 'Build', detail: 'DepartureServices page + service + store + mock data (parallel)' },
    { title: 'Integrate', detail: 'Wire App.jsx, AppShell, mockClient, global.css' },
    { title: 'Review', detail: 'Naive code review of all Track D files' },
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
Write src/mocks/data/departureItems.js — mock departure service requests.

Each item: id, reference, type='departure_service', sub_type ('late_checkout'|'luggage_storage'|'transport'),
status ('open'|'in_progress'|'resolved'), priority ('normal'|'urgent'),
room_number, guest_name, reservation_id, title, description, requested_time (ISO), assigned_to, created_at, updated_at

Seed (4 items):
  ds_4001: sub_type=late_checkout, room_number='701', guest_name='Dana Abboud', reservation_id='res_1003',
    title='Late checkout requested', description='Guest requests checkout by 14:00 instead of 12:00.',
    requested_time=at(660+120), priority='urgent', status='open', assigned_to=null, created_at=at(-90), updated_at=at(-90)
  ds_4002: sub_type=luggage_storage, room_number=null, guest_name='Lina Salem', reservation_id='res_1001',
    title='Luggage drop before arrival', description='Guest arriving at 15:00 wants to drop bags early.',
    requested_time=at(900-60), priority='normal', status='open', assigned_to=null, created_at=at(-30), updated_at=at(-30)
  ds_4003: sub_type=transport, room_number='405', guest_name='Samir Khoury', reservation_id='res_1002',
    title='Airport transfer at 23:00', description='Guest needs airport taxi after late checkout.',
    requested_time=at(780+900), priority='normal', status='in_progress', assigned_to='Omar Nasser', created_at=at(-120), updated_at=at(-20)
  ds_4004: sub_type=late_checkout, room_number='701', guest_name='Dana Abboud', reservation_id='res_1003',
    title='Minibar restocking', description='Previous late checkout resolved.',
    requested_time=at(660), priority='normal', status='resolved', assigned_to='Layth Saleh', created_at=at(-200), updated_at=at(-180)

References: ds_4001 → DEP-4001, ds_4002 → DEP-4002, ds_4003 → DEP-4003, ds_4004 → DEP-4004

let nextId = 4005;

Export:
  listDepartureItems(params) — filter by status, sub_type, matchesSearch([reference,title,guest_name,room_number], params.query), paginate
  getDepartureItemById(id)
  createDepartureItem(body) — generate id='ds_'+nextId++, reference='DEP-'+id.replace('ds_',''), status='open', created_at/updated_at=new Date().toISOString(); push to array; return clone
  updateDepartureItemStatus(id, status) — validate status in allowed list, update item, return clone; throw 404 if not found
  resetDepartureMockData()
`, { label: 'build-data-D' }),

  () => agent(CTX + `
Write src/services/departureService.js

import { apiClient } from './apiClient.js';

Named exports:
  listDepartureItems(params = {}) { return apiClient.get('/departure-services', { params }); }
  getDepartureItem(id) { return apiClient.get('/departure-services/' + id); }
  updateDepartureStatus(id, status) { return apiClient.patch('/departure-services/' + id + '/status', { status }); }
  createDepartureItem(data) { return apiClient.post('/departure-services', data); }

export const departureService = { list: listDepartureItems, get: getDepartureItem, updateStatus: updateDepartureStatus, create: createDepartureItem };
`, { label: 'build-service-D' }),

  () => agent(CTX + `
Write src/store/departureStore.js

State: items[], meta, isLoading, error, subTypeFilter ('all'|'late_checkout'|'luggage_storage'|'transport')

Actions:
  fetchAll(params) — call departureService.list({ ...params, sub_type: get().subTypeFilter === 'all' ? undefined : get().subTypeFilter })
  updateStatus(id, status) — call departureService.updateStatus(id, status), replace item in items with response.data
  createItem(data) — call departureService.create(data), unshift new item into items
  setSubTypeFilter(v) — set subTypeFilter

import { departureService } from '../services/departureService.js';
export const useDepartureStore = create((set, get) => ({ ... }));
`, { label: 'build-store-D' }),

  () => agent(CTX + `
Write src/pages/DepartureServices.jsx — departure service requests kanban page.

Imports:
  import { useEffect, useMemo } from 'react';
  import { Luggage, PlaneTakeoff, Clock } from 'lucide-react';
  import { Badge, Button, Card, EmptyState, ErrorState, PageHeader, Select, Skeleton } from '../components/ui/Primitives.jsx';
  import { useDepartureStore } from '../store/departureStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatDate, minutesAgo } from '../utils/date.js';

const SUB_TYPE_META = {
  late_checkout: { label: { en: 'Late checkout', ar: 'مغادرة متأخرة' }, icon: Clock, variant: 'warning' },
  luggage_storage: { label: { en: 'Luggage storage', ar: 'تخزين أمتعة' }, icon: Luggage, variant: 'neutral' },
  transport: { label: { en: 'Transport', ar: 'نقل' }, icon: PlaneTakeoff, variant: 'info' },
};

const SECTIONS = [
  ['open', { en: 'Open', ar: 'مفتوح' }],
  ['in_progress', { en: 'In progress', ar: 'جارٍ' }],
  ['resolved', { en: 'Resolved', ar: 'تم الحل' }],
];

Component DepartureServices:
  Destructure items, meta, isLoading, error, subTypeFilter, fetchAll, updateStatus, setSubTypeFilter from useDepartureStore.
  locale from useAuthStore, t helper.
  useEffect → fetchAll() on mount.

  grouped = useMemo: group items by status into { open:[], in_progress:[], resolved:[] }

  Return:
    PageHeader title=t('Departure Services','خدمات المغادرة') subtitle=t('Late checkouts, luggage, and transport requests.','طلبات المغادرة المتأخرة والأمتعة والنقل.')
      actions: Badge variant=(items.filter(i=>i.status==='open').length > 0 ? 'warning':'neutral') showing open count

    Toolbar div with Select for sub_type filter (value=subTypeFilter, onChange=setSubTypeFilter):
      options: all=t('All types','جميع الأنواع'), late_checkout=t('Late checkout','مغادرة متأخرة'),
               luggage_storage=t('Luggage','أمتعة'), transport=t('Transport','نقل')

    For each [status, labelObj] in SECTIONS:
      section with heading pickLocalized(labelObj, locale) + Badge count
      If grouped[status] empty: small italic note t('None','لا يوجد')
      Each item card (div className="dep-card" + priority=urgent adds "is-urgent"):
        Top row: Badge for sub_type (variant from SUB_TYPE_META), age (minutesAgo formatted)
        Title: item.title
        Meta: guest_name + room_number + requested_time (formatDate)
        assigned_to if set
        Foot buttons:
          open → Button secondary onClick=()=>updateStatus(item.id,'in_progress') t('Begin','ابدأ')
          in_progress → Button secondary onClick=()=>updateStatus(item.id,'resolved') t('Complete','أتمم')

  Loading: return <Skeleton lines={6} />
  Error: return <ErrorState title={t('Could not load','تعذّر التحميل')} message={error.message} requestId={error.request_id} />

export default DepartureServices;
`, { label: 'build-page-D' }),
])

phase('Integrate')
await agent(`
Integration agent — Track D: Departure Services.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

Track D new files (already written): src/pages/DepartureServices.jsx, src/services/departureService.js,
src/store/departureStore.js, src/mocks/data/departureItems.js

Modify these 4 shared files:

### 1. src/App.jsx
Read file. Add import: import DepartureServices from './pages/DepartureServices.jsx';
Add route inside the Routes > AppShell group (after the tickets/:id route):
  <Route path="/operations/departures" element={<RequirePermission anyOf={['operations_queue.view','operations_queue.manage']}><DepartureServices /></RequirePermission>} />

### 2. src/components/layout/AppShell.jsx
Read file. In the 'Operations' navGroup items, after the Support Tickets item add:
  { label: 'Departures', path: '/operations/departures', icon: LogOut, anyOf: ['operations_queue.view','operations_queue.manage'] },
Add LogOut to the lucide-react import if not already present.

### 3. src/mocks/mockClient.js
Read file.
Add to the imports from ./data/departureItems.js (new import line after existing data imports):
  import { listDepartureItems, getDepartureItemById, createDepartureItem, updateDepartureItemStatus } from './data/departureItems.js';

In routeQueueRequest (find the function), before the final "return null", add:

  if (method === "GET" && pathname === "/departure-services") {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    return listDepartureItems(params);
  }
  const depDetailMatch = pathname.match(/^\\/departure-services\\/([^/]+)$/);
  if (method === "GET" && depDetailMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    const item = getDepartureItemById(depDetailMatch[1]);
    if (!item) throwMockError({ message: "Not found", error_code: "not_found" }, 404);
    return item;
  }
  const depStatusMatch = pathname.match(/^\\/departure-services\\/([^/]+)\\/status$/);
  if (method === "PATCH" && depStatusMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return updateDepartureItemStatus(depStatusMatch[1], body?.status);
  }
  if (method === "POST" && pathname === "/departure-services") {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return createDepartureItem({ ...body, actor: session.user.name });
  }

### 4. src/styles/global.css
Read file. Append at end:
/* === Track D: Departure Services === */
.dep-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; padding: 0.875rem; margin-bottom: 0.5rem; }
.dep-card.is-urgent { border-inline-start: 3px solid var(--color-warning); }
.dep-card-top { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
.dep-card-meta { font-size: 0.8rem; color: var(--color-text-muted); margin: 0.25rem 0; line-height: 1.5; }
.dep-card-foot { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
.dep-section { margin-bottom: 2rem; }
.dep-section-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
.dep-section-head h2 { font-size: 0.9rem; font-weight: 600; color: var(--color-text-body); margin: 0; }
`, { label: 'integrate-D' })

phase('Review')
const reviewD = await agent(`
Naive reviewer — Track D (Departure Services). Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
Read these files and report bugs (not style issues):
1. src/mocks/data/departureItems.js — check seed structure, export names, paginateItems call
2. src/services/departureService.js — check endpoint paths, export object
3. src/store/departureStore.js — check service import name, action implementations
4. src/pages/DepartureServices.jsx — check store destructure names match store exports, lucide imports exist, key props on lists, missing imports
5. src/mocks/mockClient.js — check import of departure data functions matches export names in departureItems.js
6. src/App.jsx — check route uses correct page import name
7. src/components/layout/AppShell.jsx — check icon import exists

Report numbered findings. "Clean: [file]" if no issues.
`, { label: 'review-D' })

log('Track D complete. Review: ' + String(reviewD).substring(0, 300))
return { track: 'D', review: reviewD }
