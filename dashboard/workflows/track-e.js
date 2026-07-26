export const meta = {
  name: 'carlton-track-e',
  description: 'Folio Line Items: charge posting, breakdown, dispute, settlement per reservation',
  phases: [
    { title: 'Build', detail: 'FolioDetail page + service + store + mock data (parallel)' },
    { title: 'Integrate', detail: 'Wire App.jsx, AppShell, mockClient, global.css' },
    { title: 'Review', detail: 'Naive code review of all Track E files' },
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
Write src/mocks/data/folios.js — mock folio data per reservation.

A folio has: id, reservation_id, guest_name, room_number, status ('open'|'settled'|'disputed'),
total_charges (sum of line items), total_payments, balance (charges - payments), currency='USD',
line_items: [{id, folio_id, category ('room'|'food'|'beverage'|'spa'|'transport'|'misc'|'tax'),
  description, amount, quantity, posted_at, posted_by, disputed: false}],
payments: [{id, folio_id, method ('cash'|'credit_card'|'bank_transfer'), amount, paid_at, reference}],
created_at, updated_at

Seed 4 folios:
  fol_1001: reservation_id='res_1001', guest_name='Lina Salem', room_number='302',
    status='open', currency='USD',
    line_items: [
      {id:'li_1001_1', category:'room', description:'Deluxe King x3 nights', amount:540, quantity:3, posted_at:at(-2880), posted_by:'System', disputed:false},
      {id:'li_1001_2', category:'food', description:'In-room dining — breakfast', amount:45, quantity:1, posted_at:at(-300), posted_by:'Mira Haddad', disputed:false},
      {id:'li_1001_3', category:'beverage', description:'Minibar', amount:22, quantity:1, posted_at:at(-120), posted_by:'Layth Saleh', disputed:false},
    ],
    payments: [{id:'pay_1001_1', method:'credit_card', amount:270, paid_at:at(-2880), reference:'PRE-AUTH-8821'}],

  fol_1002: reservation_id='res_1002', guest_name='Samir Khoury', room_number='405',
    status='open', currency='USD',
    line_items: [
      {id:'li_1002_1', category:'room', description:'Premium Suite x1 night', amount:230, quantity:1, posted_at:at(-1440), posted_by:'System', disputed:false},
      {id:'li_1002_2', category:'spa', description:'Spa treatment — 60min', amount:120, quantity:1, posted_at:at(-60), posted_by:'Omar Nasser', disputed:false},
    ],
    payments: [],

  fol_1003: reservation_id='res_1003', guest_name='Dana Abboud', room_number='701',
    status='disputed', currency='USD',
    line_items: [
      {id:'li_1003_1', category:'room', description:'Executive Suite x3 nights', amount:890, quantity:3, posted_at:at(-4320), posted_by:'System', disputed:false},
      {id:'li_1003_2', category:'transport', description:'Airport pickup', amount:75, quantity:1, posted_at:at(-2000), posted_by:'Omar Nasser', disputed:false},
      {id:'li_1003_3', category:'misc', description:'Laundry service', amount:35, quantity:1, posted_at:at(-1000), posted_by:'Omar Mansour', disputed:true},
    ],
    payments: [{id:'pay_1003_1', method:'bank_transfer', amount:500, paid_at:at(-3000), reference:'WIRE-2026-0602'}],

  fol_1004: reservation_id='res_1004', guest_name='Carlos Rivera', room_number=null,
    status='settled', currency='USD',
    line_items: [
      {id:'li_1004_1', category:'room', description:'Deluxe King x3 nights', amount:690, quantity:3, posted_at:at(-5760), posted_by:'System', disputed:false},
      {id:'li_1004_2', category:'food', description:'Restaurant dinner x2', amount:96, quantity:2, posted_at:at(-4800), posted_by:'Mira Haddad', disputed:false},
      {id:'li_1004_3', category:'tax', description:'Tourism tax 5%', amount:39, quantity:1, posted_at:at(-5760), posted_by:'System', disputed:false},
    ],
    payments: [{id:'pay_1004_1', method:'credit_card', amount:825, paid_at:at(-480), reference:'CHG-8834'}],

Compute total_charges = sum of line_items amounts, total_payments = sum of payments amounts, balance = total_charges - total_payments.
Include created_at and updated_at on each folio (at appropriate times).

let nextLineItemId = 9001;

Export:
  listFolios(params) — filter by status, reservation_id if provided, matchesSearch([guest_name,room_number,reservation_id], query), paginateItems
  getFolioByReservation(reservation_id) — return clone of folio matching reservation_id or null
  getFolioById(id)
  postLineItem(folio_id, body) — add line item: {id:'li_'+nextLineItemId++, folio_id, ...body, posted_at:new Date().toISOString(), disputed:false}; recompute balance
  disputeLineItem(folio_id, line_item_id) — toggle disputed on item; if any item disputed set folio status='disputed'; recompute
  settlePayment(folio_id, body) — add payment: {id:'pay_'+folio_id+'_'+Date, ...body, paid_at:new Date().toISOString()}; recompute balance; if balance<=0 set status='settled'
  resetFolioMockData()
`, { label: 'build-data-E' }),

  () => agent(CTX + `
Write src/services/folioService.js

import { apiClient } from './apiClient.js';

Named exports:
  listFolios(params={}) { return apiClient.get('/folios', { params }); }
  getFolioByReservation(reservationId) { return apiClient.get('/reservations/' + reservationId + '/folio'); }
  postLineItem(folioId, data) { return apiClient.post('/folios/' + folioId + '/line-items', data); }
  disputeLineItem(folioId, lineItemId) { return apiClient.patch('/folios/' + folioId + '/line-items/' + lineItemId + '/dispute'); }
  settlePayment(folioId, data) { return apiClient.post('/folios/' + folioId + '/payments', data); }

export const folioService = { list: listFolios, getByReservation: getFolioByReservation, postLineItem, disputeLineItem, settle: settlePayment };
`, { label: 'build-service-E' }),

  () => agent(CTX + `
Write src/store/folioStore.js

State: folio (current folio object), folios (list), meta, isLoading, error, posting (bool for post line item), settling (bool for payment)

Actions:
  fetchFolioByReservation(reservationId) — call folioService.getByReservation(reservationId), set folio=res.data
  fetchFolios(params) — call folioService.list(params), set folios=res.data.items, meta=res.data.meta
  addLineItem(folioId, data) — set posting=true, call folioService.postLineItem, set folio=res.data, posting=false
  disputeItem(folioId, lineItemId) — call folioService.disputeLineItem, set folio=res.data
  settle(folioId, data) — set settling=true, call folioService.settle, set folio=res.data, settling=false

import { folioService } from '../services/folioService.js';
export const useFolioStore = create((set, get) => ({ ... }));
`, { label: 'build-store-E' }),

  () => agent(CTX + `
Write src/pages/FolioDetail.jsx — folio detail page accessed from /reservations/:uuid/folio.

Imports:
  import { useEffect, useState } from 'react';
  import { useParams } from 'react-router-dom';
  import { AlertCircle, CheckCircle, CircleDollarSign, Plus } from 'lucide-react';
  import { Badge, Button, Card, Field, Input, PageHeader, Select, Skeleton, ErrorState } from '../components/ui/Primitives.jsx';
  import { useFolioStore } from '../store/folioStore.js';
  import { useAuthStore } from '../store/authStore.js';
  import { pickLocalized } from '../utils/i18n.js';
  import { formatDate } from '../utils/date.js';
  import { formatMoney } from '../utils/money.js';

const CATEGORY_LABELS = {
  room: 'Room', food: 'Food', beverage: 'Beverage', spa: 'Spa',
  transport: 'Transport', misc: 'Misc', tax: 'Tax',
};
const CATEGORY_BADGE = {
  room: 'info', food: 'neutral', beverage: 'neutral', spa: 'success',
  transport: 'neutral', misc: 'neutral', tax: 'neutral',
};
const STATUS_BADGE = { open: 'warning', settled: 'success', disputed: 'danger' };

Component FolioDetail:
  const { uuid } = useParams();
  Destructure folio, isLoading, error, posting, settling, fetchFolioByReservation, addLineItem, disputeItem, settle from useFolioStore.
  locale from useAuthStore, t helper.
  useEffect → fetchFolioByReservation(uuid) on mount (uuid dep).

  Local state: showPostForm (bool), postData { category:'room', description:'', amount:'', quantity:'1' },
    showPayForm (bool), payData { method:'credit_card', amount:'' }

  Loading: return <Skeleton lines={8} />
  Error: return <ErrorState ... />
  If folio is null after load: return <EmptyState title="No folio found" message="This reservation does not have a folio yet." />

  Render:
    PageHeader title=t('Folio','الفاتورة') + folio.id subtitle=folio.guest_name+' · Room '+folio.room_number
      actions: Badge variant=STATUS_BADGE[folio.status] text=folio.status + Badge showing balance=formatMoney(folio.balance)

    Summary strip: Total charges | Total payments | Balance (danger if>0 else success)

    Line items Card:
      Heading with + post charge button (Button ghost icon=Plus onClick=()=>setShowPostForm(v=>!v))
      If showPostForm: form with Select(category), Input(description), Input(amount type=number), Input(quantity type=number, default 1)
        Button onClick=async()=>{ await addLineItem(folio.id, { ...postData, amount:parseFloat(postData.amount), quantity:parseInt(postData.quantity) }); setShowPostForm(false); setPostData({category:'room',description:'',amount:'',quantity:'1'}); }
        isLoading=posting
      Table columns: category (Badge), description, amount (formatMoney(item.amount*item.quantity)), posted_by, posted_at (formatDate),
        actions: if !item.disputed → Button ghost "Dispute" onClick=()=>disputeItem(folio.id,item.id), if item.disputed → Badge danger "Disputed"
      Each row gets a class if item.disputed: background tint

    Payments Card:
      If folio.status !== 'settled':
        Button ghost "+ Record payment" onClick=()=>setShowPayForm(v=>!v)
        If showPayForm: form with Select(method: cash/credit_card/bank_transfer), Input(amount, number)
          Button "Record" onClick=async()=>{ await settle(folio.id,{...payData,amount:parseFloat(payData.amount)}); setShowPayForm(false); }
          isLoading=settling
      List payments as rows: method | amount (formatMoney) | paid_at (formatDate) | reference
      If no payments: italic note t('No payments recorded','لا مدفوعات مسجلة')

    Bottom strip: if balance>0 → danger strip "Outstanding: formatMoney(balance)"; if settled → success strip + CheckCircle "Folio settled"

export default FolioDetail;
`, { label: 'build-page-E' }),
])

phase('Integrate')
await agent(`
Integration agent — Track E: Folio Line Items.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

Track E new files (already written): src/pages/FolioDetail.jsx, src/services/folioService.js,
src/store/folioStore.js, src/mocks/data/folios.js

Modify these 4 shared files:

### 1. src/App.jsx
Read file. Add import: import FolioDetail from './pages/FolioDetail.jsx';
Add route inside the Routes > AppShell group (after existing reservation routes):
  <Route path="/reservations/:uuid/folio" element={<RequirePermission permission="folios.view"><FolioDetail /></RequirePermission>} />

### 2. src/components/layout/AppShell.jsx
Read file. Find the nav item for Reservations (path '/reservations'). After it (or in the same group), add a nav item:
  { label: 'Folios', path: '/folios', icon: Receipt, anyOf: ['folios.view','folios.settle'] },
Also add Receipt to the lucide-react import if not present.

### 3. src/mocks/mockClient.js
Read file.
Add import line: import { listFolios, getFolioById, getFolioByReservation, postLineItem, disputeLineItem, settlePayment } from './data/folios.js';

In routeReservationRequest (find that function), before the final throw/return null, add folio routes:

  // Folio: get folio by reservation
  const folioByResMatch = pathname.match(/^\\/reservations\\/([^/]+)\\/folio$/);
  if (method === "GET" && folioByResMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_VIEW, locale);
    const fol = getFolioByReservation(folioByResMatch[1]);
    if (!fol) throwMockError({ message: "Folio not found", error_code: "not_found" }, 404);
    return fol;
  }
  // Folio list
  if (method === "GET" && pathname === "/folios") {
    requirePermission(session, PERMISSIONS.FOLIOS_VIEW, locale);
    return listFolios(params);
  }
  // Post line item
  const lineItemMatch = pathname.match(/^\\/folios\\/([^/]+)\\/line-items$/);
  if (method === "POST" && lineItemMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_SETTLE, locale);
    return postLineItem(lineItemMatch[1], body);
  }
  // Dispute line item
  const disputeMatch = pathname.match(/^\\/folios\\/([^/]+)\\/line-items\\/([^/]+)\\/dispute$/);
  if (method === "PATCH" && disputeMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_SETTLE, locale);
    return disputeLineItem(disputeMatch[1], disputeMatch[2]);
  }
  // Record payment
  const paymentMatch = pathname.match(/^\\/folios\\/([^/]+)\\/payments$/);
  if (method === "POST" && paymentMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_SETTLE, locale);
    return settlePayment(paymentMatch[1], body);
  }

Note: Check if PERMISSIONS object has FOLIOS_VIEW and FOLIOS_SETTLE. If not, look for how permissions are referenced (may be string literals like "folios.view" used inline — use those instead).

### 4. src/styles/global.css
Read file. Append at end:
/* === Track E: Folio === */
.folio-summary-strip { display: flex; gap: 2rem; padding: 0.875rem 1rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; margin-bottom: 1rem; flex-wrap: wrap; }
.folio-strip-item { display: flex; flex-direction: column; gap: 2px; }
.folio-strip-label { font-size: 0.75rem; color: var(--color-text-muted); }
.folio-strip-value { font-size: 1.1rem; font-weight: 700; color: var(--color-text-strong); }
.folio-strip-value.danger { color: var(--color-danger); }
.folio-strip-value.success { color: var(--color-success); }
.folio-line-disputed td { background: #fff5f5; color: var(--color-danger); }
.folio-post-form { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border); margin-bottom: 0.5rem; }
.folio-pay-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem; align-items: end; padding: 0.75rem 0; }
.folio-settled-strip { display: flex; align-items: center; gap: 0.5rem; color: var(--color-success); font-size: 0.875rem; padding: 0.5rem 0; }
.folio-balance-strip { display: flex; align-items: center; gap: 0.5rem; color: var(--color-danger); font-size: 0.875rem; font-weight: 600; padding: 0.5rem 0; }
`, { label: 'integrate-E' })

phase('Review')
const reviewE = await agent(`
Naive reviewer — Track E (Folio). Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
Read these files and report bugs (not style issues):
1. src/mocks/data/folios.js — check that balance computation is correct (charges-payments), settlePayment uses new Date() not at(), dispute toggle logic
2. src/services/folioService.js — check URL paths match mock routes, export object keys
3. src/store/folioStore.js — check that fetchFolioByReservation sets folio (not current), isLoading pattern
4. src/pages/FolioDetail.jsx — check useParams destructuring matches route param :uuid, missing null check for folio after load, key props on lists
5. src/mocks/mockClient.js — check PERMISSIONS references exist (may need to use string literals instead)
6. src/App.jsx — check route path matches what FolioDetail expects from useParams

Report numbered findings. "Clean: [file]" if no issues.
`, { label: 'review-E' })

log('Track E complete. Review: ' + String(reviewE).substring(0, 300))
return { track: 'E', review: reviewE }
