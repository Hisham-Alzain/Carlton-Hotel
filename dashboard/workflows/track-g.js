export const meta = {
  name: 'carlton-track-g',
  description: 'Guest Profile: cross-stay history, preferences, VIP notes, contact masking',
  phases: [
    { title: 'Build', detail: 'GuestProfile page + service + store + mock data (parallel)' },
    { title: 'Integrate', detail: 'Wire App.jsx, AppShell, mockClient, global.css' },
    { title: 'Review', detail: 'Naive code review of all Track G files' },
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
Write src/mocks/data/guests.js — guest profile data linked to reservations.

A guest record: id, full_name, full_name_ar (optional), nationality, vip_level ('standard'|'gold'|'platinum'),
phone_masked, email_masked, preferences: { pillow_type, floor_preference, dietary, notes },
total_stays, total_nights, total_spend, first_stay_at, last_stay_at,
stay_history: [{reservation_id, room_type_name, check_in, check_out, nights, amount, status}],
staff_notes: [{id, note, posted_by, posted_at}]

Seed 4 guests (matching existing reservation guest data):
  guest_001: full_name='Lina Salem', full_name_ar='لينا سالم', nationality='LB', vip_level='gold',
    phone_masked='+961 **** 1234', email_masked='l***@gmail.com',
    preferences: { pillow_type:'soft', floor_preference:'high', dietary:'vegetarian', notes:'Prefers quiet rooms, away from elevator.' },
    total_stays=4, total_nights=12, total_spend=2140, first_stay_at=at(-720*24*60), last_stay_at=at(-5),
    stay_history: [
      {reservation_id:'res_1001', room_type_name:'Deluxe King', check_in:at(-2880), check_out:at(2*1440-2880), nights:3, amount:540, status:'checked_in'},
      {reservation_id:'res_0012', room_type_name:'Deluxe Twin', check_in:at(-180*1440), check_out:at(-177*1440), nights:3, amount:450, status:'departed'},
    ],
    staff_notes: [{id:'sn_g001_1', note:'Upgraded on arrival due to VIP status. Left glowing review.', posted_by:'Omar Mansour', posted_at:at(-177*1440)}],

  guest_002: full_name='Samir Khoury', full_name_ar='سمير خوري', nationality='SY', vip_level='standard',
    phone_masked='+963 **** 9900', email_masked='s***@yahoo.com',
    preferences: { pillow_type:'firm', floor_preference:'any', dietary:null, notes:null },
    total_stays=1, total_nights=1, total_spend=350, first_stay_at=at(-1440), last_stay_at:at(-1440),
    stay_history: [{reservation_id:'res_1002', room_type_name:'Premium Suite', check_in:at(-1440), check_out:at(0), nights:1, amount:350, status:'due_out'}],
    staff_notes: [],

  guest_003: full_name='Dana Abboud', full_name_ar='دانا عبود', nationality='AE', vip_level='platinum',
    phone_masked='+971 **** 4455', email_masked='d***@icloud.com',
    preferences: { pillow_type:'memory_foam', floor_preference:'executive', dietary:'halal', notes:'VIP corporate guest — always upgrade if available. Airport transfer arranged by default.' },
    total_stays=8, total_nights=24, total_spend=7200, first_stay_at=at(-365*1440), last_stay_at:at(-5),
    stay_history: [
      {reservation_id:'res_1003', room_type_name:'Executive Suite', check_in:at(-2880), check_out:at(2*1440-2880), nights:3, amount:890, status:'checked_in'},
      {reservation_id:'res_0008', room_type_name:'Executive Suite', check_in:at(-90*1440), check_out:at(-87*1440), nights:3, amount:870, status:'departed'},
    ],
    staff_notes: [{id:'sn_g003_1', note:'Always request early turndown service. Has dietary restrictions — halal meals only. Personal driver drops off at 11am.', posted_by:'Nadia Hariri', posted_at:at(-90*1440)}],

  guest_004: full_name='Carlos Rivera', nationality='ES', vip_level='standard',
    phone_masked='+34 **** 7721', email_masked='c***@outlook.com',
    preferences: { pillow_type:null, floor_preference:null, dietary:null, notes:null },
    total_stays=2, total_nights=6, total_spend=1200, first_stay_at:at(-200*1440), last_stay_at:at(-180*1440),
    stay_history: [{reservation_id:'res_1004', room_type_name:'Deluxe King', check_in:at(-3*1440), check_out:at(0), nights:3, amount:690, status:'upcoming'}],
    staff_notes: [],

let nextNoteId = 9001;

Export:
  listGuests(params) — filter vip_level if params.vip_level, matchesSearch([full_name,email_masked,nationality], query), paginateItems
  getGuestById(id)
  getGuestByReservation(reservation_id) — find guest whose stay_history includes that reservation_id
  addGuestNote(guest_id, note, posted_by) — push {id:'sn_g'+nextNoteId++, note, posted_by, posted_at:new Date().toISOString()} to staff_notes; return cloneMockData(guest)
  updateGuestPreferences(guest_id, preferences) — merge preferences; return clone
  resetGuestMockData()
`, { label: 'build-data-G' }),

  () => agent(CTX + `
Write src/services/guestService.js

import { apiClient } from './apiClient.js';

Named exports:
  listGuests(params={}) { return apiClient.get('/guests', { params }); }
  getGuest(id) { return apiClient.get('/guests/' + id); }
  getGuestByReservation(reservationId) { return apiClient.get('/reservations/' + reservationId + '/guest'); }
  addGuestNote(id, note) { return apiClient.post('/guests/' + id + '/notes', { note }); }
  updatePreferences(id, preferences) { return apiClient.patch('/guests/' + id + '/preferences', preferences); }

export const guestService = { list: listGuests, get: getGuest, getByReservation: getGuestByReservation, addNote: addGuestNote, updatePreferences };
`, { label: 'build-service-G' }),

  () => agent(CTX + `
Write src/store/guestStore.js

State: guest (current guest object), guests (list), meta, isLoading, error, savingNote (bool), savingPrefs (bool)

Actions:
  fetchGuest(id) — call guestService.get(id), set guest=res.data
  fetchGuestByReservation(reservationId) — call guestService.getByReservation(reservationId), set guest=res.data
  fetchGuests(params) — call guestService.list(params), set guests/meta
  addNote(id, note) — set savingNote=true, call guestService.addNote(id, note), set guest=res.data, savingNote=false
  updatePreferences(id, prefs) — set savingPrefs=true, call guestService.updatePreferences(id, prefs), set guest=res.data, savingPrefs=false

import { guestService } from '../services/guestService.js';
export const useGuestStore = create((set, get) => ({ ... }));
`, { label: 'build-store-G' }),

  () => agent(CTX + `
Write src/pages/GuestProfile.jsx — guest profile page with stay history, preferences, notes.

Imports:
  import { useEffect, useState } from 'react';
  import { useParams } from 'react-router-dom';
  import { History, Star, UserCircle } from 'lucide-react';
  import { Badge, Button, Card, Field, PageHeader, Skeleton, ErrorState, EmptyState, Textarea } from '../components/ui/Primitives.jsx';
  import { useGuestStore } from '../store/guestStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatDate } from '../utils/date.js';
  import { formatMoney } from '../utils/money.js';

const VIP_BADGE = { platinum: 'warning', gold: 'info', standard: 'neutral' };

Component GuestProfile:
  const { id } = useParams();
  Destructure guest, isLoading, error, savingNote, fetchGuest, addNote from useGuestStore.
  const currentUser = useAuthStore((s) => s.user);
  locale from useAuthStore, t helper.
  const [noteInput, setNoteInput] = useState('');
  const [editingPrefs, setEditingPrefs] = useState(false);
  useEffect → fetchGuest(id) on mount.

  Loading: <Skeleton lines={8} />
  Error: <ErrorState ... />
  if !guest: <EmptyState title=t('Guest not found','الضيف غير موجود') />

  Layout: two-column div (className="gp-grid")

  Left column:
    Card className="gp-identity-card":
      Avatar div (className="gp-avatar") showing first initial
      Name (+ arabic name if exists dir=rtl)
      Badges: VIP level, nationality
      Attrs: phone_masked, email_masked
      Lifetime stats strip: total_stays, total_nights, formatMoney(total_spend)
      first_stay_at / last_stay_at (formatDate)

    Card className="gp-prefs-card":
      Heading t('Preferences','التفضيلات')
      Grid showing: pillow_type, floor_preference, dietary, notes
      Each pref as label+value pair (—  if null)

    Card className="gp-notes-card":
      Heading t('Staff Notes','ملاحظات الموظفين')
      List of staff_notes (newest first): posted_by + posted_at (formatDate) + note text
      If no notes: italic t('No notes yet','لا ملاحظات بعد')
      Add note form: Textarea value=noteInput onChange=setNoteInput rows=3
        Button onClick=async()=>{ await addNote(id, noteInput); setNoteInput(''); } isLoading=savingNote
        t('Add note','إضافة ملاحظة')

  Right column:
    Card className="gp-history-card":
      Heading with History icon + t('Stay History','تاريخ الإقامات') + Badge count
      For each stay in guest.stay_history (newest first by check_in):
        div className="gp-history-item":
          room_type_name | nights | formatDate(check_in) → formatDate(check_out)
          formatMoney(amount) | Badge for status
          Link to /reservations/:reservation_id if status is active ('checked_in','due_out','arriving_today','upcoming')
      If empty: italic t('No previous stays','لا إقامات سابقة')

export default GuestProfile;

Also write src/pages/GuestsList.jsx — guest list page (simpler, table view).

Imports: same pattern + Table from Primitives, Link from react-router-dom.
Component GuestsList:
  useEffect → fetchGuests() on mount
  if isLoading → <Skeleton lines={4} />
  if error → <ErrorState ... />
  Table with columns: full_name (link to /guests/:id), nationality, vip_level (Badge), total_stays, total_nights, formatMoney(total_spend)
  PageHeader title=t('Guests','الضيوف') subtitle=t('Guest profiles and stay histories.','ملفات الضيوف وتاريخ إقاماتهم.')

export default GuestsList;
`, { label: 'build-pages-G' }),
])

phase('Integrate')
await agent(`
Integration agent — Track G: Guest Profiles.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

Track G new files (already written): src/pages/GuestProfile.jsx, src/pages/GuestsList.jsx,
src/services/guestService.js, src/store/guestStore.js, src/mocks/data/guests.js

Modify these 4 shared files:

### 1. src/App.jsx
Read file.
Add imports:
  import GuestsList from './pages/GuestsList.jsx';
  import GuestProfile from './pages/GuestProfile.jsx';
Add routes (inside AppShell group):
  <Route path="/guests" element={<RequirePermission permission="guests.view"><GuestsList /></RequirePermission>} />
  <Route path="/guests/:id" element={<RequirePermission permission="guests.view"><GuestProfile /></RequirePermission>} />

### 2. src/components/layout/AppShell.jsx
Read file. Find where guest-related nav items would go (likely in 'Guests & Services' group or create new group 'Guests').
Add to navGroups (or create a 'Guests' group before 'Reports' or similar):
  { label: 'Guests', path: '/guests', icon: Users, permission: 'guests.view' },
Add Users to the lucide-react import if not present.

### 3. src/mocks/mockClient.js
Read file.
Add import: import { listGuests, getGuestById, getGuestByReservation, addGuestNote, updateGuestPreferences } from './data/guests.js';

In routeReservationRequest (or the main routing function), add guest routes before the final throw:

  if (method === "GET" && pathname === "/guests") {
    requirePermission(session, "guests.view", locale);
    return listGuests(params);
  }
  const guestByIdMatch = pathname.match(/^\\/guests\\/([^/]+)$/);
  if (method === "GET" && guestByIdMatch && !pathname.includes('/notes') && !pathname.includes('/preferences')) {
    requirePermission(session, "guests.view", locale);
    const g = getGuestById(guestByIdMatch[1]);
    if (!g) throwMockError({ message: "Guest not found", error_code: "not_found" }, 404);
    return g;
  }
  const guestByResMatch = pathname.match(/^\\/reservations\\/([^/]+)\\/guest$/);
  if (method === "GET" && guestByResMatch) {
    requirePermission(session, "guests.view", locale);
    const g = getGuestByReservation(guestByResMatch[1]);
    if (!g) throwMockError({ message: "Guest not found", error_code: "not_found" }, 404);
    return g;
  }
  const guestNoteMatch = pathname.match(/^\\/guests\\/([^/]+)\\/notes$/);
  if (method === "POST" && guestNoteMatch) {
    requirePermission(session, "guests.view", locale);
    return addGuestNote(guestNoteMatch[1], body?.note, session.user.name);
  }
  const guestPrefsMatch = pathname.match(/^\\/guests\\/([^/]+)\\/preferences$/);
  if (method === "PATCH" && guestPrefsMatch) {
    requirePermission(session, "guests.view", locale);
    return updateGuestPreferences(guestPrefsMatch[1], body);
  }

### 4. src/styles/global.css
Read file. Append at end:
/* === Track G: Guest Profile === */
.gp-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.25rem; align-items: start; }
@media (max-width: 900px) { .gp-grid { grid-template-columns: 1fr; } }
.gp-identity-card { margin-bottom: 1rem; }
.gp-avatar { width: 56px; height: 56px; border-radius: 50%; background: var(--color-deep-teal); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
.gp-name { font-size: 1.2rem; font-weight: 700; color: var(--color-text-strong); }
.gp-name-ar { font-size: 0.95rem; color: var(--color-text-muted); direction: rtl; }
.gp-badges { display: flex; gap: 0.5rem; margin: 0.5rem 0; flex-wrap: wrap; }
.gp-attrs { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.85rem; color: var(--color-text-muted); margin-top: 0.5rem; }
.gp-stats-strip { display: flex; gap: 1.5rem; padding: 0.5rem 0; border-top: 1px solid var(--color-border); margin-top: 0.75rem; flex-wrap: wrap; }
.gp-stat { display: flex; flex-direction: column; gap: 2px; }
.gp-stat-label { font-size: 0.7rem; text-transform: uppercase; color: var(--color-text-muted); }
.gp-stat-value { font-size: 0.95rem; font-weight: 700; color: var(--color-text-strong); }
.gp-prefs-card { margin-bottom: 1rem; }
.gp-prefs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.85rem; }
.gp-pref-label { color: var(--color-text-muted); }
.gp-pref-value { color: var(--color-text-strong); font-weight: 500; }
.gp-notes-card { display: flex; flex-direction: column; gap: 0.75rem; }
.gp-note { border-left: 3px solid var(--color-border); padding-left: 0.75rem; }
.gp-note-meta { font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 2px; }
.gp-note-text { font-size: 0.875rem; color: var(--color-text-body); }
.gp-history-card { }
.gp-history-item { display: flex; flex-direction: column; gap: 4px; padding: 0.625rem 0; border-bottom: 1px solid var(--color-border); font-size: 0.85rem; }
.gp-history-item:last-child { border-bottom: none; }
.gp-history-top { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.gp-history-meta { color: var(--color-text-muted); }
`, { label: 'integrate-G' })

phase('Review')
const reviewG = await agent(`
Naive reviewer — Track G (Guest Profiles). Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
Read these files and report bugs:
1. src/mocks/data/guests.js — check getGuestByReservation searches stay_history correctly, addGuestNote mutates guests array not original seed
2. src/services/guestService.js — check URL paths, export object keys
3. src/store/guestStore.js — check fetchGuest vs fetchGuestByReservation both set 'guest' state key (not 'current')
4. src/pages/GuestProfile.jsx — check useParams key is 'id' (matching route /guests/:id), key props on history list and notes list
5. src/pages/GuestsList.jsx — check import of useGuestStore.fetchGuests action exists in store
6. src/mocks/mockClient.js — check guestByIdMatch regex does not accidentally match /guests/:id/notes and /guests/:id/preferences (those should be caught by later more specific patterns — verify ordering)
7. src/App.jsx and AppShell.jsx — check Users icon imported

Report numbered findings. "Clean: [file]" if no issues.
`, { label: 'review-G' })

log('Track G complete. Review: ' + String(reviewG).substring(0, 300))
return { track: 'G', review: reviewG }
