import { useEffect, useState } from 'react';
import { AlertTriangle, BedDouble, CheckCircle2, CircleDollarSign, Moon, ShieldAlert } from 'lucide-react';
import { Badge, Button, Card, ErrorState, PageHeader, Skeleton, Textarea } from '../components/ui/Primitives.jsx';
import { useNightAuditStore } from '../store/nightAuditStore.js';
import { useAuthStore } from '../store/authStore.js';
import { usePermissionStore } from '../store/permissionStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDate, formatDateTime } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';
import { PERMISSIONS } from '../utils/permissions.js';

const BLOCKER_BADGE = {
  critical: 'danger',
  high: 'warning',
  normal: 'neutral',
};

const AUDIT_BADGE = {
  ready_to_close: 'success',
  needs_attention: 'warning',
  closed: 'neutral',
};

const ROOM_STATUS_BADGE = {
  available: 'success',
  occupied: 'info',
  cleaning: 'warning',
  maintenance: 'danger',
  out_of_order: 'danger',
};

const FOLIO_STATUS_BADGE = {
  open: 'warning',
  disputed: 'danger',
  settled: 'success',
};

const cardTone = {
  display: 'grid',
  gap: '0.35rem',
  padding: '0.75rem 0.875rem',
  borderRadius: '8px',
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
};

function Stat({ label, value }) {
  return (
    <div style={cardTone}>
      <span style={{ color: 'var(--color-text-muted)', fontSize: '0.72rem', textTransform: 'uppercase' }}>{label}</span>
      <strong style={{ color: 'var(--color-text-strong)', fontSize: '1rem' }}>{value}</strong>
    </div>
  );
}

function SummaryTable({ columns, rows, empty }) {
  return (
    <div style={{ overflowX: 'auto' }}>
      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.82rem' }}>
        <thead>
          <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
            {columns.map((column) => (
              <th
                key={column.key}
                style={{
                  padding: '0.55rem 0.5rem',
                  textAlign: 'start',
                  fontSize: '0.68rem',
                  fontWeight: 800,
                  letterSpacing: '0.08em',
                  textTransform: 'uppercase',
                  color: 'var(--color-text-muted)',
                  whiteSpace: 'nowrap',
                }}
              >
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} style={{ padding: '1rem 0.5rem', color: 'var(--color-text-muted)' }}>
                {empty}
              </td>
            </tr>
          ) : rows.map((row) => (
            <tr key={row.id} style={{ borderBottom: '1px solid var(--color-border)' }}>
              {columns.map((column) => (
                <td key={column.key} style={{ padding: '0.7rem 0.5rem', color: 'var(--color-text-body)', verticalAlign: 'top' }}>
                  {column.render ? column.render(row) : row[column.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

const NightAudit = () => {
  const { audit, isLoading, error, fetchAudit, updateCheck, resolveBlocker } = useNightAuditStore();
  const locale = useAuthStore((state) => state.locale);
  const user = useAuthStore((state) => state.user);
  const canUpdate = usePermissionStore(
    (state) => state.can(PERMISSIONS.FOLIOS_SETTLE) || state.can(PERMISSIONS.RESERVATIONS_MANAGE),
  );
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const [pendingCheck, setPendingCheck] = useState(null);
  const [pendingBlocker, setPendingBlocker] = useState(null);
  const [resolutionDrafts, setResolutionDrafts] = useState({});

  useEffect(() => {
    fetchAudit();
  }, [fetchAudit]);

  if (isLoading || (!audit && !error)) {
    return <Skeleton lines={8} />;
  }

  if (error) {
    return (
      <ErrorState
        title={t('Failed to load night audit', 'تعذر تحميل المراجعة الليلية')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const unresolvedBlockers = audit.blockers.filter((blocker) => blocker.status !== 'resolved');
  const allChecksDone = audit.checks.every((check) => check.status === 'done');
  const roomRows = [...audit.rooms.rows]
    .sort((a, b) => {
      const issueRank = { 'Inspection pending': 0, 'Due out': 1, 'Occupied': 2, 'Ready': 3 };
      const issueDiff = (issueRank[a.issue] ?? 9) - (issueRank[b.issue] ?? 9);
      if (issueDiff) return issueDiff;
      return String(a.number).localeCompare(String(b.number), undefined, { numeric: true });
    });
  const roomSummaryRows = roomRows.slice(0, 6);
  const folioSummaryRows = [...audit.folios.rows]
    .sort((a, b) => (b.balance - a.balance) || (b.disputed_lines - a.disputed_lines) || String(a.guest_name || '').localeCompare(String(b.guest_name || '')))
    .slice(0, 6);

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHeader
        title={t('Night Audit', 'المراجعة الليلية')}
        subtitle={`${t('Business date', 'تاريخ العمل')} ${formatDate(audit.business_date, { locale, timeZone: 'UTC' })} · ${audit.shift_window}`}
        actions={(
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
            <Badge variant={AUDIT_BADGE[audit.status] || 'neutral'}>{audit.status.replaceAll('_', ' ')}</Badge>
            <Badge variant={unresolvedBlockers.length ? 'danger' : 'success'}>
              {unresolvedBlockers.length} {t('open blockers', 'عوائق مفتوحة')}
            </Badge>
          </div>
        )}
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', gap: '0.75rem' }}>
        <Stat label={t('Occupied rooms', 'الغرف المشغولة')} value={audit.totals.occupied_rooms} />
        <Stat label={t('Unsettled folios', 'الحسابات غير المسواة')} value={audit.totals.unsettled_folios} />
        <Stat label={t('Room revenue', 'إيراد الغرف')} value={formatMoney(audit.totals.room_revenue)} />
        <Stat label={t('Cash variance', 'فرق النقدية')} value={formatMoney(audit.totals.cash_variance)} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: '1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1rem' }}>
          <Card padded>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', alignItems: 'center' }}>
              <div>
                <p className="tkt-desc-label">{t('Closeout checklist', 'قائمة إغلاق اليوم')}</p>
                <h2 style={{ margin: 0, color: 'var(--color-text-strong)', fontSize: '1rem' }}>{t('What must be clean before close', 'ما يجب أن يكون جاهزًا قبل الإغلاق')}</h2>
              </div>
              <Badge variant={allChecksDone ? 'success' : 'warning'}>{allChecksDone ? t('Ready', 'جاهز') : t('Pending', 'معلق')}</Badge>
            </div>

            <div style={{ display: 'grid', gap: '0.75rem', marginTop: '1rem' }}>
              {audit.checks.map((check) => (
                <div key={check.id} style={{ ...cardTone, background: 'var(--color-surface)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', alignItems: 'start' }}>
                    <div style={{ display: 'grid', gap: '0.25rem' }}>
                      <strong style={{ color: 'var(--color-text-strong)', fontSize: '0.9rem' }}>{t(check.label, check.label_ar)}</strong>
                      <span style={{ color: 'var(--color-text-muted)', fontSize: '0.78rem' }}>{check.owner}</span>
                    </div>
                    <Badge variant={check.status === 'done' ? 'success' : 'warning'}>{check.status}</Badge>
                  </div>
                  <p style={{ margin: 0, color: 'var(--color-text-body)', fontSize: '0.82rem', lineHeight: 1.5 }}>{check.note}</p>
                  {check.completed_at && (
                    <span style={{ color: 'var(--color-text-muted)', fontSize: '0.72rem' }}>
                      {check.completed_by} · {formatDateTime(check.completed_at, { locale })}
                    </span>
                  )}
                  {canUpdate && (
                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                      <Button
                        variant={check.status === 'done' ? 'ghost' : 'secondary'}
                        isLoading={pendingCheck === check.id}
                        disabled={pendingCheck === check.id}
                        onClick={async () => {
                          setPendingCheck(check.id);
                          try {
                            await updateCheck(check.id, { done: check.status !== 'done', actor: user?.name });
                          } finally {
                            setPendingCheck(null);
                          }
                        }}
                      >
                        {check.status === 'done' ? t('Reopen', 'إعادة فتح') : t('Mark done', 'وضع كمكتمل')}
                      </Button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </Card>

          <Card padded>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', alignItems: 'center' }}>
              <div>
                <p className="tkt-desc-label">{t('Open blockers', 'العوائق المفتوحة')}</p>
                <h2 style={{ margin: 0, color: 'var(--color-text-strong)', fontSize: '1rem' }}>{t('Items preventing close', 'العناصر التي تمنع الإغلاق')}</h2>
              </div>
              <ShieldAlert size={16} />
            </div>

            <div style={{ display: 'grid', gap: '0.75rem', marginTop: '1rem' }}>
              {audit.blockers.map((blocker) => (
                <div key={blocker.id} style={{ ...cardTone, background: 'var(--color-surface)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', alignItems: 'start' }}>
                    <div style={{ display: 'grid', gap: '0.25rem' }}>
                      <strong style={{ color: 'var(--color-text-strong)', fontSize: '0.9rem' }}>{blocker.title}</strong>
                      <span style={{ color: 'var(--color-text-muted)', fontSize: '0.78rem' }}>
                        {blocker.owner} · {blocker.reference} · {blocker.source}
                      </span>
                    </div>
                    <Badge variant={BLOCKER_BADGE[blocker.severity] || 'neutral'}>
                      {blocker.status === 'resolved' ? t('resolved', 'تم الحل') : blocker.severity}
                    </Badge>
                  </div>
                  <p style={{ margin: 0, color: 'var(--color-text-body)', fontSize: '0.82rem', lineHeight: 1.5 }}>{blocker.detail}</p>
                  {blocker.status === 'resolved' && blocker.resolution_note ? (
                    <span style={{ color: 'var(--color-text-muted)', fontSize: '0.74rem' }}>
                      {blocker.resolved_by} · {blocker.resolution_note}
                    </span>
                  ) : canUpdate ? (
                    <div style={{ display: 'grid', gap: '0.5rem' }}>
                      <Textarea
                        rows={2}
                        value={resolutionDrafts[blocker.id] || ''}
                        onChange={(event) => setResolutionDrafts((current) => ({ ...current, [blocker.id]: event.target.value }))}
                        placeholder={t('Add the closeout note before resolving.', 'أضف ملاحظة الإغلاق قبل الحل.')}
                      />
                      <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button
                          variant="secondary"
                          isLoading={pendingBlocker === blocker.id}
                          disabled={pendingBlocker === blocker.id || !(resolutionDrafts[blocker.id] || '').trim()}
                          onClick={async () => {
                            setPendingBlocker(blocker.id);
                            try {
                              await resolveBlocker(blocker.id, {
                                actor: user?.name,
                                note: resolutionDrafts[blocker.id],
                              });
                            } finally {
                              setPendingBlocker(null);
                            }
                          }}
                        >
                          {t('Resolve blocker', 'حل العائق')}
                        </Button>
                      </div>
                    </div>
                  ) : null}
                </div>
              ))}
            </div>
          </Card>
        </div>

        <div style={{ display: 'grid', gap: '1rem' }}>
          <Card padded>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.45rem', marginBottom: '0.5rem' }}>
              <CircleDollarSign size={16} />
              <p className="tkt-desc-label" style={{ margin: 0 }}>{t('Revenue controls', 'ضوابط الإيراد')}</p>
            </div>
            <div style={{ display: 'grid', gap: '0.75rem' }}>
              <Stat label={t('Pending postings', 'الترحيلات المعلقة')} value={audit.totals.pending_postings} />
              <Stat label={t('Expected cash drop', 'الإيداع المتوقع')} value={formatMoney(audit.totals.expected_cash_drop)} />
              <Stat label={t('Recorded cash drop', 'الإيداع المسجل')} value={formatMoney(audit.totals.recorded_cash_drop)} />
              <Stat label={t('Incidentals revenue', 'إيراد الخدمات')} value={formatMoney(audit.totals.incidentals_revenue)} />
            </div>
          </Card>

          <Card padded>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.45rem', marginBottom: '0.5rem' }}>
              <BedDouble size={16} />
              <p className="tkt-desc-label" style={{ margin: 0 }}>{t('Room summary', 'ملخص الغرف')}</p>
            </div>
            <div style={{ display: 'grid', gap: '0.65rem' }}>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.4rem' }}>
                <Badge variant="neutral">{audit.rooms.summary.total} {t('rooms', 'غرف')}</Badge>
                <Badge variant={audit.rooms.summary.occupied ? 'info' : 'success'}>{audit.rooms.summary.occupied} {t('occupied', 'مشغولة')}</Badge>
                <Badge variant={audit.rooms.summary.cleaning ? 'warning' : 'success'}>{audit.rooms.summary.cleaning} {t('cleaning', 'تنظيف')}</Badge>
                <Badge variant={audit.rooms.summary.due_out ? 'warning' : 'neutral'}>{audit.rooms.summary.due_out} {t('due out', 'مغادرة اليوم')}</Badge>
              </div>
              <SummaryTable
                empty={t('No room exceptions are pending.', 'لا توجد استثناءات غرف معلقة.')}
                columns={[
                  { key: 'room', label: t('Room', 'الغرفة'), render: (row) => row.number },
                  { key: 'guest', label: t('Guest', 'الضيف'), render: (row) => row.guest_name || '—' },
                  {
                    key: 'status',
                    label: t('Status', 'الحالة'),
                    render: (row) => <Badge variant={ROOM_STATUS_BADGE[row.status] || 'neutral'}>{row.status.replaceAll('_', ' ')}</Badge>,
                  },
                  {
                    key: 'issue',
                    label: t('Issue', 'الملاحظة'),
                    render: (row) => (
                      <span style={{ color: row.issue === 'Ready' ? 'var(--color-text-muted)' : 'var(--color-text-strong)' }}>{row.issue}</span>
                    ),
                  },
                ]}
                rows={roomSummaryRows}
              />
            </div>
          </Card>

          <Card padded>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.45rem', marginBottom: '0.5rem' }}>
              <CircleDollarSign size={16} />
              <p className="tkt-desc-label" style={{ margin: 0 }}>{t('Folio summary', 'ملخص الحسابات')}</p>
            </div>
            <div style={{ display: 'grid', gap: '0.65rem' }}>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.4rem' }}>
                <Badge variant="neutral">{audit.folios.summary.total} {t('folios', 'حسابات')}</Badge>
                <Badge variant={audit.folios.summary.open ? 'warning' : 'success'}>{audit.folios.summary.open} {t('open', 'مفتوحة')}</Badge>
                <Badge variant={audit.folios.summary.disputed ? 'danger' : 'success'}>{audit.folios.summary.disputed} {t('disputed', 'محل اعتراض')}</Badge>
                <Badge variant={audit.folios.summary.open_balance ? 'danger' : 'success'}>{formatMoney(audit.folios.summary.open_balance)} {t('open balance', 'رصيد مفتوح')}</Badge>
              </div>
              <SummaryTable
                empty={t('No open folio items remain.', 'لا توجد بنود مفتوحة في الحسابات.')}
                columns={[
                  { key: 'guest', label: t('Guest', 'الضيف'), render: (row) => row.guest_name },
                  { key: 'room', label: t('Room', 'الغرفة'), render: (row) => row.room_number || '—' },
                  {
                    key: 'balance',
                    label: t('Balance', 'الرصيد'),
                    render: (row) => (
                      <span style={{ color: row.balance > 0 ? 'var(--color-danger)' : 'var(--color-text-strong)', fontVariantNumeric: 'tabular-nums' }}>
                        {formatMoney(row.balance)}
                      </span>
                    ),
                  },
                  {
                    key: 'status',
                    label: t('Status', 'الحالة'),
                    render: (row) => <Badge variant={FOLIO_STATUS_BADGE[row.status] || 'neutral'}>{row.status.replaceAll('_', ' ')}</Badge>,
                  },
                ]}
                rows={folioSummaryRows}
              />
            </div>
          </Card>

          <Card padded>
            <p className="tkt-desc-label">{t('Handoff notes', 'ملاحظات التسليم')}</p>
            <div style={{ display: 'grid', gap: '0.75rem' }}>
              {audit.handoff_notes.map((note) => (
                <div key={note.id} style={{ ...cardTone, background: 'var(--color-surface)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem' }}>
                    <strong style={{ color: 'var(--color-text-strong)', fontSize: '0.85rem' }}>{note.author}</strong>
                    <span style={{ color: 'var(--color-text-muted)', fontSize: '0.72rem' }}>{formatDateTime(note.posted_at, { locale })}</span>
                  </div>
                  <p style={{ margin: 0, color: 'var(--color-text-body)', fontSize: '0.82rem', lineHeight: 1.5 }}>{note.note}</p>
                </div>
              ))}
            </div>
          </Card>

          <Card padded>
            <div style={{ display: 'grid', gap: '0.5rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <Moon size={16} />
                {audit.status === 'ready_to_close' ? <CheckCircle2 size={16} /> : <AlertTriangle size={16} />}
                <strong style={{ color: 'var(--color-text-strong)', fontSize: '0.92rem' }}>
                  {audit.status === 'ready_to_close'
                    ? t('Business date can be closed', 'يمكن إغلاق تاريخ العمل')
                    : t('Business date must remain open', 'يجب أن يظل تاريخ العمل مفتوحًا')}
                </strong>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gap: '0.5rem' }}>
                <Stat label={t('Checks done', 'التحققات المكتملة')} value={`${audit.readiness.checklist_done}/${audit.readiness.checklist_total}`} />
                <Stat label={t('Open blockers', 'العوائق المفتوحة')} value={audit.readiness.blockers_open} />
                <Stat label={t('Close state', 'حالة الإغلاق')} value={audit.readiness.can_close ? t('Clear', 'جاهز') : t('Hold', 'معلق')} />
              </div>
              <span style={{ color: 'var(--color-text-body)', fontSize: '0.82rem', lineHeight: 1.5 }}>
                {audit.status === 'ready_to_close'
                  ? t('All checks are complete and no blockers remain in the closeout queue.', 'اكتملت جميع التحققات ولم تعد هناك عوائق في قائمة الإغلاق.')
                  : t('Resolve the remaining blockers and finish all pending closeout checks before closing the business date.', 'قم بحل العوائق المتبقية وأنهِ جميع عناصر الإغلاق المعلقة قبل إغلاق تاريخ العمل.')}
              </span>
              <Badge variant={AUDIT_BADGE[audit.status] || 'neutral'}>{audit.status.replaceAll('_', ' ')}</Badge>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default NightAudit;
