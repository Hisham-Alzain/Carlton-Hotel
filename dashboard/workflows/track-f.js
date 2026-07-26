export const meta = {
  name: 'carlton-track-f',
  description: 'Housekeeping Board: room status transitions, attendant assignment, floor-level view',
  phases: [
    { title: 'Build', detail: 'HousekeepingBoard page + service + store + mock data (parallel)' },
    { title: 'Integrate', detail: 'Wire App.jsx, AppShell, mockClient, global.css' },
    { title: 'Review', detail: 'Naive code review of all Track F files' },
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
Write src/mocks/data/housekeeping.js — housekeeping tasks per room.

IMPORTANT: Do NOT export ROOMS from this file. The canonical ROOMS data is in src/mocks/data/reservations.js.
This file works with housekeeping-specific task records that reference room ids.

A housekeeping task has: id, room_id, room_number, floor, room_type_name,
status ('pending'|'in_progress'|'done'|'inspected'), priority ('normal'|'rush'),
task_type ('checkout_clean'|'stayover'|'deep_clean'|'inspection'),
assigned_to (string name or null), notes, created_at, updated_at, estimated_minutes

Seed 7 tasks (realistic hotel day):
  hk_001: room_id='room_301', room_number='301', floor=3, room_type_name='Deluxe King', status='pending', priority='rush',
    task_type='checkout_clean', assigned_to=null, notes='VIP guest Dana Abboud due out 12:00', estimated_minutes=45,
    created_at=at(-90), updated_at=at(-90)
  hk_002: room_id='room_303', room_number='303', floor=3, room_type_name='Deluxe King', status='in_progress', priority='normal',
    task_type='stayover', assigned_to='Layth Saleh', notes=null, estimated_minutes=30,
    created_at=at(-120), updated_at=at(-40)
  hk_003: room_id='room_405', room_number='405', floor=4, room_type_name='Premium Suite', status='done', priority='normal',
    task_type='checkout_clean', assigned_to='Fatima Nour', notes=null, estimated_minutes=60,
    created_at=at(-180), updated_at=at(-60)
  hk_004: room_id='room_407', room_number='407', floor=4, room_type_name='Premium Suite', status='pending', priority='normal',
    task_type='stayover', assigned_to=null, notes=null, estimated_minutes=30,
    created_at=at(-60), updated_at=at(-60)
  hk_005: room_id='room_502', room_number='502', floor=5, room_type_name='Premium Twin', status='pending', priority='rush',
    task_type='checkout_clean', assigned_to=null, notes='New arrival expected 14:00', estimated_minutes=45,
    created_at=at(-30), updated_at=at(-30)
  hk_006: room_id='room_701', room_number='701', floor=7, room_type_name='Executive Suite', status='inspected', priority='normal',
    task_type='inspection', assigned_to='Layth Saleh', notes='Pre-arrival VIP check complete', estimated_minutes=20,
    created_at=at(-200), updated_at=at(-150)
  hk_007: room_id='room_703', room_number='703', floor=7, room_type_name='Executive Suite', status='pending', priority='normal',
    task_type='deep_clean', assigned_to=null, notes='Scheduled maintenance clean', estimated_minutes=90,
    created_at=at(-10), updated_at=at(-10)

let tasks = cloneMockData(tasksSeed) — use cloneMockData for initial data copy.

Export:
  listHousekeepingTasks(params) — filter by status (params.status), floor (params.floor), assigned_to, matchesSearch([room_number,assigned_to,task_type,notes], query), paginateItems
  getTaskById(id)
  assignTask(id, attendant) — set assigned_to=attendant, updated_at=new Date().toISOString(); return clone
  updateTaskStatus(id, status) — validate status in ['pending','in_progress','done','inspected']; update; return clone; throw 404 if not found
  addTaskNote(id, note) — set notes=note; update updated_at; return clone
  resetHousekeepingMockData()
`, { label: 'build-data-F' }),

  () => agent(CTX + `
Write src/services/housekeepingService.js

import { apiClient } from './apiClient.js';

Named exports:
  listTasks(params={}) { return apiClient.get('/housekeeping/tasks', { params }); }
  getTask(id) { return apiClient.get('/housekeeping/tasks/' + id); }
  assignTask(id, attendant) { return apiClient.patch('/housekeeping/tasks/' + id + '/assign', { attendant }); }
  updateTaskStatus(id, status) { return apiClient.patch('/housekeeping/tasks/' + id + '/status', { status }); }
  addNote(id, note) { return apiClient.patch('/housekeeping/tasks/' + id + '/note', { note }); }

export const housekeepingService = { list: listTasks, get: getTask, assign: assignTask, updateStatus: updateTaskStatus, addNote };
`, { label: 'build-service-F' }),

  () => agent(CTX + `
Write src/store/housekeepingStore.js

State: tasks[], meta, isLoading, error, floorFilter ('all'|'3'|'4'|'5'|'7'), statusFilter ('all'|'pending'|'in_progress'|'done'|'inspected')

Actions:
  fetchTasks(params) — call housekeepingService.list({ ...params, floor: get().floorFilter==='all'?undefined:get().floorFilter, status: get().statusFilter==='all'?undefined:get().statusFilter })
  assignTask(id, attendant) — call housekeepingService.assign(id, attendant), replace task in tasks array
  updateStatus(id, status) — call housekeepingService.updateStatus(id, status), replace task in tasks array
  addNote(id, note) — call housekeepingService.addNote(id, note), replace task in tasks array
  setFloorFilter(v) — set floorFilter
  setStatusFilter(v) — set statusFilter

import { housekeepingService } from '../services/housekeepingService.js';
export const useHousekeepingStore = create((set, get) => ({ ... }));
`, { label: 'build-store-F' }),

  () => agent(CTX + `
Write src/pages/HousekeepingBoard.jsx — housekeeping task board page.

Imports:
  import { useEffect, useState } from 'react';
  import { BedDouble, CheckSquare, ClipboardList, UserCheck } from 'lucide-react';
  import { Badge, Button, Card, Field, Input, PageHeader, Select, Skeleton, ErrorState, EmptyState } from '../components/ui/Primitives.jsx';
  import { useHousekeepingStore } from '../store/housekeepingStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatDate } from '../utils/date.js';

const STATUS_META = {
  pending:    { label: { en: 'Pending',     ar: 'في الانتظار' }, variant: 'neutral' },
  in_progress:{ label: { en: 'In progress', ar: 'جارٍ' },        variant: 'info' },
  done:       { label: { en: 'Done',        ar: 'تم' },           variant: 'success' },
  inspected:  { label: { en: 'Inspected',   ar: 'تم التفتيش' },   variant: 'neutral' },
};
const TASK_TYPE_LABELS = {
  checkout_clean: { en: 'Checkout clean', ar: 'تنظيف ما بعد المغادرة' },
  stayover:       { en: 'Stayover',       ar: 'إقامة مستمرة' },
  deep_clean:     { en: 'Deep clean',     ar: 'تنظيف عميق' },
  inspection:     { en: 'Inspection',     ar: 'تفتيش' },
};

Component HousekeepingBoard:
  Destructure tasks, meta, isLoading, error, floorFilter, statusFilter, fetchTasks, assignTask, updateStatus, setFloorFilter, setStatusFilter from useHousekeepingStore.
  locale from useAuthStore, t helper.
  const [assigningId, setAssigningId] = useState(null);
  const [attendantInput, setAttendantInput] = useState('');
  useEffect → fetchTasks() on mount and when floorFilter/statusFilter change ([fetchTasks, floorFilter, statusFilter] deps).

  Loading: return <Skeleton lines={6} />
  Error: return <ErrorState ... />

  Stats strip at top: total tasks | pending count | in_progress count | done+inspected count
    styled using CSS class .hk-stats-strip

  Filter toolbar (className="hk-toolbar"):
    Select floorFilter (all/3/4/5/7) labeled t('Floor','الطابق')
    Select statusFilter (all/pending/in_progress/done/inspected)

  If tasks empty: EmptyState title=t('No tasks','لا مهام') message=t('All rooms are clean.','جميع الغرف نظيفة.')

  Task list (div className="hk-task-list"):
    For each task: div className="hk-task-card" + (task.priority==='rush' ? " is-rush" : "")
      Floor badge + room number + task type label (pickLocalized)
      Badge for status (from STATUS_META)
      If assigned_to: small text with UserCheck icon + attendant name
      If !assigned_to && assigningId !== task.id:
        Button ghost icon=UserCheck size=sm onClick=()=>{setAssigningId(task.id);setAttendantInput('')} t('Assign','تعيين')
      If assigningId === task.id:
        Inline form: Input value=attendantInput onChange=setAttendantInput placeholder=t('Attendant name','اسم العامل')
        Button onClick=async()=>{await assignTask(task.id,attendantInput);setAssigningId(null)} t('Confirm','تأكيد')
        Button ghost onClick=()=>setAssigningId(null) t('Cancel','إلغاء')
      Status progression buttons:
        pending → Button ghost onClick=()=>updateStatus(task.id,'in_progress') t('Begin','ابدأ')
        in_progress → Button ghost onClick=()=>updateStatus(task.id,'done') t('Done','تم')
        done → Button ghost onClick=()=>updateStatus(task.id,'inspected') t('Inspect','فتّش')
      estimated_minutes + updated_at (formatDate)

export default HousekeepingBoard;
`, { label: 'build-page-F' }),
])

phase('Integrate')
await agent(`
Integration agent — Track F: Housekeeping Board.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

Track F new files (already written): src/pages/HousekeepingBoard.jsx, src/services/housekeepingService.js,
src/store/housekeepingStore.js, src/mocks/data/housekeeping.js

Modify these 4 shared files:

### 1. src/App.jsx
Read file. Add import: import HousekeepingBoard from './pages/HousekeepingBoard.jsx';
Add route inside the Routes > AppShell group (after the departures route added by Track D, or at end of operations routes):
  <Route path="/operations/housekeeping" element={<RequirePermission anyOf={['operations_queue.view','operations_queue.manage']}><HousekeepingBoard /></RequirePermission>} />

### 2. src/components/layout/AppShell.jsx
Read file. In the 'Operations' navGroup items (after the Departures item or similar), add:
  { label: 'Housekeeping', path: '/operations/housekeeping', icon: BedDouble, anyOf: ['operations_queue.view','operations_queue.manage'] },
Add BedDouble to the lucide-react import if not already present.

### 3. src/mocks/mockClient.js
Read file.
Add import: import { listHousekeepingTasks, getTaskById, assignTask, updateTaskStatus, addTaskNote } from './data/housekeeping.js';

In routeQueueRequest (or wherever most queue routes live), before the final throw/return null, add housekeeping routes:

  if (method === "GET" && pathname === "/housekeeping/tasks") {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    return listHousekeepingTasks(params);
  }
  const hkTaskMatch = pathname.match(/^\\/housekeeping\\/tasks\\/([^/]+)$/);
  if (method === "GET" && hkTaskMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    const task = getTaskById(hkTaskMatch[1]);
    if (!task) throwMockError({ message: "Not found", error_code: "not_found" }, 404);
    return task;
  }
  const hkAssignMatch = pathname.match(/^\\/housekeeping\\/tasks\\/([^/]+)\\/assign$/);
  if (method === "PATCH" && hkAssignMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return assignTask(hkAssignMatch[1], body?.attendant);
  }
  const hkStatusMatch = pathname.match(/^\\/housekeeping\\/tasks\\/([^/]+)\\/status$/);
  if (method === "PATCH" && hkStatusMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return updateTaskStatus(hkStatusMatch[1], body?.status);
  }
  const hkNoteMatch = pathname.match(/^\\/housekeeping\\/tasks\\/([^/]+)\\/note$/);
  if (method === "PATCH" && hkNoteMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return addTaskNote(hkNoteMatch[1], body?.note);
  }

### 4. src/styles/global.css
Read file. Append at end:
/* === Track F: Housekeeping Board === */
.hk-stats-strip { display: flex; gap: 2rem; padding: 0.75rem 1rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; margin-bottom: 1rem; flex-wrap: wrap; }
.hk-stat { display: flex; flex-direction: column; gap: 2px; }
.hk-stat-label { font-size: 0.75rem; color: var(--color-text-muted); }
.hk-stat-value { font-size: 1.1rem; font-weight: 700; color: var(--color-text-strong); }
.hk-toolbar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.hk-task-list { display: grid; gap: 0.5rem; }
.hk-task-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; padding: 0.875rem; display: flex; flex-direction: column; gap: 0.5rem; }
.hk-task-card.is-rush { border-inline-start: 3px solid var(--color-danger); }
.hk-task-head { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.hk-task-room { font-size: 1rem; font-weight: 700; color: var(--color-text-strong); }
.hk-task-meta { font-size: 0.8rem; color: var(--color-text-muted); display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.hk-task-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.hk-assign-inline { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
`, { label: 'integrate-F' })

phase('Review')
const reviewF = await agent(`
Naive reviewer — Track F (Housekeeping Board). Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
Read these files and report bugs:
1. src/mocks/data/housekeeping.js — does it import cloneMockData? does it NOT import/export ROOMS (must NOT conflict with reservations.js ROOMS)?
2. src/services/housekeepingService.js — check URL paths match mock routes
3. src/store/housekeepingStore.js — check that fetchTasks uses floor/status filters from state, action signatures
4. src/pages/HousekeepingBoard.jsx — check useEffect deps array includes floorFilter & statusFilter, assigningId form renders correctly, key props on task list
5. src/mocks/mockClient.js — check new import names match housekeeping.js exports
6. src/App.jsx and AppShell.jsx — check BedDouble icon is imported

Report numbered findings. "Clean: [file]" if no issues.
`, { label: 'review-F' })

log('Track F complete. Review: ' + String(reviewF).substring(0, 300))
return { track: 'F', review: reviewF }
