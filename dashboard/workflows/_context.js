// Shared context string injected into every build-agent prompt.
// Not a real module — imported as a template string by each track workflow.
export const CTX = `
CARLTON BUILD CONTEXT — read carefully before writing any file.
Working directory: C:\\Users\\TECH SHOP\\Documents\\Carlton
No TypeScript. React 19, Zustand 5, plain CSS, no Tailwind.

PRIMITIVES (src/components/ui/Primitives.jsx):
  Badge({ children, variant='neutral' })
  Button({ children, variant='primary'|'secondary'|'ghost'|'danger', isLoading, icon, onClick, disabled })
  Card({ children, padded, className })
  Field({ label, children, error })
  Input(props), Select(props), Textarea(props)
  PageHeader({ title, subtitle, actions })
  EmptyState({ title, message, action })
  ErrorState({ title, message, requestId, action })
  Skeleton({ lines=3 })
  Table({ columns=[{key,label,render?}], rows, onRowClick, empty })

CSS TOKENS (src/styles/tokens.css):
  --color-deep-teal: #08414C   --color-muted-gold: #C0A060
  --color-bg: #F7F5F0          --color-surface: #FFFFFF
  --color-border: #E5DED2      --color-text-strong: #102F35
  --color-text-body: #38545A   --color-text-muted: #7A8B8E
  --color-success: #268765     --color-warning: #B7791F
  --color-danger: #B44545      --color-info: #2E8799
  --color-critical: #C75C3A

UTILITIES:
  pickLocalized({ en, ar }, locale)  — from '../utils/i18n.js'
  formatDate(value, locale)          — from '../utils/date.js'
  formatRelativeAge(value, locale)   — from '../utils/date.js'
  minutesAgo(value)                  — from '../utils/date.js'
  formatMoney(value)                 — from '../utils/money.js'

BILINGUAL PATTERN (use in every page):
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

ZUSTAND STORE PATTERN:
  import { create } from 'zustand';
  import { xService } from '../services/xService.js';
  export const useXStore = create((set, get) => ({
    items: [], meta: null, current: null, isLoading: false, error: null,
    fetchAll: async (params) => {
      set({ isLoading: true, error: null });
      try {
        const res = await xService.list(params);
        set({ items: res.data?.items || [], meta: res.data?.meta || null, isLoading: false });
      } catch (err) { set({ error: err.payload || { message: err.message }, isLoading: false }); }
    },
    fetchOne: async (id) => {
      set({ isLoading: true, error: null });
      try {
        const res = await xService.get(id);
        set({ current: res.data, isLoading: false });
        return res.data;
      } catch (err) { set({ error: err.payload || { message: err.message }, isLoading: false }); throw err; }
    },
  }));

SERVICE PATTERN:
  import { apiClient } from './apiClient.js';
  export function listX(params = {}) { return apiClient.get('/x', { params }); }
  export function getX(id) { return apiClient.get('/x/' + id); }
  export function createX(data) { return apiClient.post('/x', data); }
  export function updateX(id, data) { return apiClient.patch('/x/' + id, data); }
  export const xService = { list: listX, get: getX, create: createX, update: updateX };

MOCK DATA PATTERN (src/mocks/data/xxx.js):
  import { addMinutesUtc } from '../../utils/date.js';
  import { cloneMockData, matchesSearch, paginateItems, throwMockError } from '../envelope.js';
  const baseDay = '2026-06-28T00:00:00.000Z';
  function at(minutes) { return addMinutesUtc(baseDay, minutes); }
  const seed = [...]; let data = cloneMockData(seed);
  export function listItems(params={}) { /* filter + paginateItems(cloneMockData(filtered), params) */ }
  export function getById(id) { const item = data.find(i=>i.id===id); return item ? cloneMockData(item) : null; }
  export function resetData() { data = cloneMockData(seed); }

CARLTON ENVELOPE: { success, message, data, request_id, error_code, errors }
Paginated: { data: { items: [...], meta: { page, per_page, total, total_pages } } }

PERMISSIONS (string literals — also in src/utils/permissions.js PERMISSIONS object):
  'dashboard.view', 'operations_queue.view', 'operations_queue.manage', 'operations_queue.assign'
  'reservations.view', 'reservations.manage', 'reservations.check_in', 'reservations.check_out', 'reservations.create'
  'availability.view', 'folios.view', 'folios.settle'
  'service_requests.view', 'service_requests.manage'
  'tickets.view', 'tickets.manage'
  'guests.view', 'rates.view', 'rates.manage'
  'reports.view', 'events.view', 'events.manage'
  'guest_chat.view', 'settings.view'

PERSONA PERMISSIONS (who can do what):
  Nadia (super_admin): everything
  Omar Mansour (reception): dashboard, reservations.*, availability, queue.*, service_requests.*, tickets.view, chat.*, folios.*, guests.view
  Mira (kitchen): dashboard, queue.*, service_requests.*
  Layth (housekeeping, locale=ar): dashboard, queue.*, service_requests.*, reservations.view
  Omar Nasser (concierge, locale=ar): dashboard, queue.*, service_requests.*, chat.*, service_bookings.*, guests.view
  Karim (sales): dashboard, events.*, reports.view, cms.*, service_bookings.view

PAGE REQUIRED STATES (every page must handle):
  1. Loading: return <Skeleton lines={6} />
  2. Error: return <ErrorState title={t('...','')} message={error.message} requestId={error.request_id} />
  3. Empty: <EmptyState title={t('...','')} message={t('...','')} />
  4. Data: render normally
  All user-visible strings must be bilingual using t()
`;
