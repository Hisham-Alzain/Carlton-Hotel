import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  ArrowLeft,
  Building2,
  CheckCircle2,
  CircleDollarSign,
  Circle,
  Clock3,
  ClipboardList,
  Mail,
  Phone,
} from 'lucide-react';
import { Badge, Button, Card, EmptyState, ErrorState, Field, PageHeader, Skeleton, Table, Textarea } from '../components/ui/Primitives.jsx';
import { useEventsStore } from '../store/eventsStore.js';
import { useAuthStore } from '../store/authStore.js';
import { usePermissionStore } from '../store/permissionStore.js';
import { PERMISSIONS } from '../utils/permissions.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDateTime } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

const STATUS_META = {
  rfp: { label: { en: 'RFP', ar: 'طلب عرض' }, variant: 'neutral' },
  tentative: { label: { en: 'Tentative', ar: 'مبدئي' }, variant: 'warning' },
  confirmed: { label: { en: 'Confirmed', ar: 'مؤكد' }, variant: 'success' },
  cancelled: { label: { en: 'Cancelled', ar: 'ملغى' }, variant: 'danger' },
};

const EVENT_TYPE_LABELS = {
  conference: { en: 'Conference', ar: 'مؤتمر' },
  wedding: { en: 'Wedding', ar: 'حفل زفاف' },
  corporate: { en: 'Corporate', ar: 'شركة' },
  social: { en: 'Social', ar: 'اجتماعي' },
};

const STATUS_SEQUENCE = ['rfp', 'tentative', 'confirmed', 'cancelled'];

const checklistWrapStyle = { display: 'grid', gap: 12 };
const checklistRowStyle = {
  display: 'grid',
  gridTemplateColumns: 'auto minmax(0, 1fr)',
  gap: 12,
  alignItems: 'start',
  padding: '12px 0',
  borderTop: '1px solid var(--color-border)',
};

const ChecklistToggle = ({ item, canManage, busy, onToggle, locale }) => {
  const done = Boolean(item.done);

  return (
    <div style={checklistRowStyle}>
      <button
        type="button"
        onClick={() => onToggle(item)}
        disabled={!canManage || busy}
        aria-pressed={done}
        aria-label={done ? 'Mark checklist item as pending' : 'Mark checklist item as done'}
        style={{
          width: 34,
          height: 34,
          display: 'grid',
          placeItems: 'center',
          borderRadius: 10,
          border: `1px solid ${done ? 'rgba(38,135,101,0.28)' : 'var(--color-border)'}`,
          background: done ? 'rgba(231,243,238,0.88)' : 'rgba(255,255,255,0.88)',
          color: done ? 'var(--color-success)' : 'var(--color-text-muted)',
          flexShrink: 0,
          opacity: canManage ? 1 : 0.55,
        }}
      >
        {done ? <CheckCircle2 size={18} /> : <Circle size={18} />}
      </button>

      <div style={{ display: 'grid', gap: 6, minWidth: 0 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, alignItems: 'start' }}>
          <div style={{ display: 'grid', gap: 2, minWidth: 0 }}>
            <strong style={{ color: 'var(--color-text-strong)', fontSize: 13, fontWeight: 650 }}>
              {item.label}
            </strong>
            <span style={{ color: 'var(--color-text-muted)', fontSize: 12 }}>
              {item.owner}
              {item.due_at ? ` · ${formatDateTime(item.due_at, { locale })}` : ''}
            </span>
          </div>
          <Badge variant={done ? 'success' : 'warning'}>{done ? pickLocalized({ en: 'Done', ar: 'مكتمل' }, locale) : pickLocalized({ en: 'Pending', ar: 'معلق' }, locale)}</Badge>
        </div>

        {item.note && <p style={{ margin: 0, color: 'var(--color-text-body)', fontSize: 12 }}>{item.note}</p>}
        {done && item.completed_by && (
          <span style={{ color: 'var(--color-text-muted)', fontSize: 11 }}>
            {pickLocalized({ en: 'Completed by', ar: 'أُنجز بواسطة' }, locale)} {item.completed_by}
            {item.completed_at ? ` · ${formatDateTime(item.completed_at, { locale })}` : ''}
          </span>
        )}
      </div>
    </div>
  );
};

const EventDetail = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const locale = useAuthStore((state) => state.locale);
  const canManage = usePermissionStore((state) => state.can(PERMISSIONS.EVENTS_MANAGE));
  const { current, isLoadingDetail, error, fetchEvent, updateStatus, updateNotes, toggleChecklistItem, markDepositReceived } =
    useEventsStore();
  const [notesDraft, setNotesDraft] = useState('');
  const [savingNotes, setSavingNotes] = useState(false);
  const [savingStatus, setSavingStatus] = useState(null);
  const [savingDeposit, setSavingDeposit] = useState(false);
  const [savingChecklistId, setSavingChecklistId] = useState(null);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  useEffect(() => {
    let isActive = true;

    void fetchEvent(id)
      .then((event) => {
        if (isActive) {
          setNotesDraft(event?.notes || '');
        }
      })
      .catch(() => {});

    return () => {
      isActive = false;
    };
  }, [fetchEvent, id]);

  const handleStatusChange = async (status) => {
    if (!current || status === current.status) return;
    setSavingStatus(status);
    try {
      await updateStatus(current.id, status);
    } finally {
      setSavingStatus(null);
    }
  };

  const handleSaveNotes = async () => {
    if (!current) return;
    setSavingNotes(true);
    try {
      const updated = await updateNotes(current.id, notesDraft);
      setNotesDraft(updated.notes || '');
    } finally {
      setSavingNotes(false);
    }
  };

  const handleDepositReceived = async () => {
    if (!current) return;
    setSavingDeposit(true);
    try {
      await markDepositReceived(current.id, { amount: current.deposit_amount });
    } finally {
      setSavingDeposit(false);
    }
  };

  const handleChecklistToggle = async (item) => {
    if (!current) return;
    setSavingChecklistId(item.id);
    try {
      await toggleChecklistItem(current.id, item.id, !item.done);
    } finally {
      setSavingChecklistId(null);
    }
  };

  if (isLoadingDetail || (!current && !error)) {
    return <Skeleton lines={9} />;
  }

  if (error) {
    return (
      <ErrorState
        title={t('Failed to load event', 'فشل تحميل الفعالية')}
        message={error.message}
        requestId={error.request_id}
        action={(
          <Button variant="secondary" onClick={() => navigate('/events')}>
            {t('Back to events', 'العودة إلى الفعاليات')}
          </Button>
        )}
      />
    );
  }

  if (!current) {
    return (
      <EmptyState
        title={t('Event not found', 'الفعالية غير موجودة')}
        message={t('The selected event record could not be loaded.', 'تعذر تحميل سجل الفعالية المحدد.')}
        action={(
          <Button variant="secondary" onClick={() => navigate('/events')}>
            {t('Back to events', 'العودة إلى الفعاليات')}
          </Button>
        )}
      />
    );
  }

  const statusMeta = STATUS_META[current.status] || STATUS_META.rfp;
  const typeLabel = pickLocalized(EVENT_TYPE_LABELS[current.event_type] || { en: current.event_type, ar: current.event_type }, locale);
  const timingBlocks = current.timing_blocks || [];
  const checklist = current.checklist || [];
  const spaces = current.space_plan || [];
  const attendeeSummary = `${current.guaranteed_attendees || 0} / ${current.expected_attendees || 0}`;
  const depositRemaining = Math.max((current.revenue_estimate || 0) - (current.deposit_paid ? current.deposit_amount : 0), 0);

  return (
    <div className="res-detail">
      <div className="folio-nav">
        <Link to="/events" className="tkt-back-link">
          <ArrowLeft size={15} />
          <span>{t('Events', 'الفعاليات')}</span>
        </Link>
        <span className="table-note">{current.assigned_to ? `${t('Owned by', 'مسند إلى')} ${current.assigned_to}` : t('Unassigned', 'غير مسند')}</span>
      </div>

      <PageHeader
        title={current.title}
        subtitle={`${current.reference} · ${current.client_company} · ${formatDateTime(current.event_date, { locale })} · ${attendeeSummary} ${t('guaranteed', 'مضمون')}`}
        actions={(
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
            <Badge variant={statusMeta.variant}>{pickLocalized(statusMeta.label, locale)}</Badge>
            <Badge variant={current.deposit_paid ? 'success' : 'warning'}>
              {current.deposit_paid ? t('Deposit received', 'تم استلام الوديعة') : t('Deposit pending', 'الوديعة معلقة')}
            </Badge>
            <Badge variant="info">{typeLabel}</Badge>
          </div>
        )}
      />

      <div className="res-detail-grid">
        <div className="res-detail-main">
          <Card padded className="rd-card">
            <p className="rd-section-label">{t('BEO snapshot', 'ملخص محضر التنفيذ')}</p>
            <div className="rd-meta-grid">
              <div className="rd-meta-item">
                <span>{t('Client', 'العميل')}</span>
                <strong>{current.client_company}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Contact', 'جهة الاتصال')}</span>
                <strong>{current.contact_name}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Function date', 'تاريخ المناسبة')}</span>
                <strong>{formatDateTime(current.event_date, { locale })}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Setup time', 'وقت التجهيز')}</span>
                <strong>{formatDateTime(current.setup_date, { locale })}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Teardown', 'الإخلاء')}</span>
                <strong>{formatDateTime(current.teardown_date, { locale })}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Attendees', 'الحضور')}</span>
                <strong>
                  {current.guaranteed_attendees || 0} {t('guaranteed', 'مضمون')}
                </strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Spaces', 'القاعات')}</span>
                <strong>{spaces.length || 0}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Revenue', 'الإيراد')}</span>
                <strong>{formatMoney(current.revenue_estimate)}</strong>
              </div>
            </div>
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Timing', 'التوقيت')}</p>
            {timingBlocks.length === 0 ? (
              <div style={{ padding: '0.25rem 0', color: 'var(--color-text-muted)', fontSize: 13 }}>
                {t('Timing will appear once the event is scheduled.', 'ستظهر الأوقات عند تثبيت الجدولة.')}
              </div>
            ) : (
              <div style={{ display: 'grid', gap: 10 }}>
                {timingBlocks.map((block) => (
                  <div
                    key={block.id}
                    style={{
                      display: 'grid',
                      gridTemplateColumns: 'auto minmax(0, 1fr)',
                      gap: 12,
                      padding: '12px 0',
                      borderTop: '1px solid var(--color-border)',
                    }}
                  >
                    <div
                      style={{
                        width: 34,
                        height: 34,
                        display: 'grid',
                        placeItems: 'center',
                        borderRadius: 10,
                        border: '1px solid var(--color-border)',
                        background: 'rgba(255,255,255,0.88)',
                        color: 'var(--color-teal)',
                      }}
                    >
                      <Clock3 size={18} />
                    </div>
                    <div style={{ display: 'grid', gap: 4 }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                        <strong style={{ color: 'var(--color-text-strong)', fontSize: 13 }}>{block.label}</strong>
                        <span style={{ color: 'var(--color-text-muted)', fontSize: 12, whiteSpace: 'nowrap' }}>
                          {formatDateTime(block.time, { locale })}
                        </span>
                      </div>
                      <span style={{ color: 'var(--color-text-muted)', fontSize: 12 }}>
                        {block.owner}
                        {block.note ? ` · ${block.note}` : ''}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Spaces', 'القاعات')}</p>
            <Table
              rows={spaces}
              columns={[
                { key: 'name', label: t('Space', 'المساحة') },
                { key: 'purpose', label: t('Purpose', 'الاستخدام') },
                { key: 'setup_style', label: t('Setup', 'التجهيز') },
                {
                  key: 'capacity',
                  label: t('Capacity', 'السعة'),
                  render: (row) => row.capacity,
                },
                {
                  key: 'window',
                  label: t('Window', 'النافذة الزمنية'),
                  render: (row) => `${formatDateTime(row.start_at, { locale })} → ${formatDateTime(row.end_at, { locale })}`,
                },
                {
                  key: 'note',
                  label: t('Notes', 'ملاحظات'),
                  render: (row) => row.note || '—',
                },
              ]}
              empty={t('No spaces assigned yet.', 'لم يتم تخصيص قاعات بعد.')}
            />
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Operations notes', 'ملاحظات التشغيل')}</p>
            <Field label={t('Internal notes', 'ملاحظات داخلية')}>
              <Textarea
                value={notesDraft}
                onChange={(event) => setNotesDraft(event.target.value)}
                rows={5}
                placeholder={t('Add BEO follow-up notes, menu changes, or special handling.', 'أضف ملاحظات المتابعة أو التعديلات أو المتطلبات الخاصة.')}
                disabled={!canManage}
              />
            </Field>
            {canManage && (
              <div className="rd-action-footer">
                <Button variant="secondary" isLoading={savingNotes} disabled={savingNotes} onClick={handleSaveNotes} icon={ClipboardList}>
                  {t('Save notes', 'حفظ الملاحظات')}
                </Button>
              </div>
            )}
          </Card>
        </div>

        <div className="res-detail-side">
          <Card padded className="rd-card rd-guest-card">
            <p className="rd-section-label">{t('Client contact', 'جهة الاتصال')}</p>
            <div className="rd-guest-header">
              <div className="rd-guest-avatar">{(current.contact_name || current.client_company || '?').charAt(0)}</div>
              <div className="rd-guest-names">
                <span className="rd-guest-name">{current.contact_name}</span>
                <span className="rd-guest-name-ar" dir="ltr">
                  {current.contact_title}
                </span>
              </div>
            </div>
            <div className="rd-guest-attrs">
              <div className="rd-guest-attr">
                <span>{t('Company', 'الشركة')}</span>
                <strong>{current.client_company}</strong>
              </div>
              <div className="rd-guest-attr">
                <span>{t('Email', 'البريد')}</span>
                <strong className="rd-masked">{current.contact_email_masked}</strong>
              </div>
              <div className="rd-guest-attr">
                <span>{t('Phone', 'الهاتف')}</span>
                <strong className="rd-masked">{current.contact_phone_masked}</strong>
              </div>
              <div className="rd-guest-attr">
                <span>{t('Coordinator', 'المنسق')}</span>
                <strong>{current.assigned_to || t('Unassigned', 'غير مسند')}</strong>
              </div>
            </div>
            <div style={{ display: 'grid', gap: 8, marginTop: 12 }}>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <Mail size={14} style={{ color: 'var(--color-text-muted)' }} />
                <span className="rd-masked">{current.contact_email_masked}</span>
              </div>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <Phone size={14} style={{ color: 'var(--color-text-muted)' }} />
                <span className="rd-masked">{current.contact_phone_masked}</span>
              </div>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <Building2 size={14} style={{ color: 'var(--color-text-muted)' }} />
                <span>{current.client_company}</span>
              </div>
            </div>
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Status actions', 'إجراءات الحالة')}</p>
            <div style={{ display: 'grid', gap: 8 }}>
              {STATUS_SEQUENCE.map((status) => {
                const meta = STATUS_META[status];
                const isActive = current.status === status;

                return (
                  <Button
                    key={status}
                    variant={isActive ? 'primary' : status === 'cancelled' ? 'danger' : 'secondary'}
                    disabled={!canManage || savingStatus === status || isActive}
                    isLoading={savingStatus === status}
                    onClick={() => handleStatusChange(status)}
                    style={{ width: '100%' }}
                  >
                    {pickLocalized(meta.label, locale)}
                  </Button>
                );
              })}
            </div>
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Deposit & revenue', 'الوديعة والإيراد')}</p>
            <div className="rd-meta-grid">
              <div className="rd-meta-item">
                <span>{t('Revenue', 'الإيراد')}</span>
                <strong>{formatMoney(current.revenue_estimate)}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Deposit', 'الوديعة')}</span>
                <strong>{formatMoney(current.deposit_amount)}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('Deposit status', 'حالة الوديعة')}</span>
                <strong>{current.deposit_paid ? t('Received', 'تم الاستلام') : t('Pending', 'معلقة')}</strong>
              </div>
              <div className="rd-meta-item">
                <span>{t('After deposit', 'المتبقي بعد الوديعة')}</span>
                <strong>{formatMoney(depositRemaining)}</strong>
              </div>
            </div>
            {current.deposit_paid && (
              <div style={{ display: 'grid', gap: 4, color: 'var(--color-text-muted)', fontSize: 12 }}>
                <span>
                  {t('Received by', 'استلمها')} {current.deposit_received_by || '—'}
                </span>
                {current.deposit_paid_at && (
                  <span>{formatDateTime(current.deposit_paid_at, { locale })}</span>
                )}
              </div>
            )}
            {canManage && !current.deposit_paid && (
              <div className="rd-action-footer">
                <Button
                  variant="secondary"
                  isLoading={savingDeposit}
                  disabled={savingDeposit}
                  onClick={handleDepositReceived}
                  icon={CircleDollarSign}
                >
                  {t('Mark deposit received', 'تأكيد استلام الوديعة')}
                </Button>
              </div>
            )}
          </Card>

          <Card padded className="rd-card">
            <p className="rd-section-label">{t('Checklist', 'قائمة التنفيذ')}</p>
            <div style={checklistWrapStyle}>
              {checklist.length === 0 ? (
                <div style={{ padding: '0.25rem 0', color: 'var(--color-text-muted)', fontSize: 13 }}>
                  {t('Checklist items will appear once the BEO is built.', 'ستظهر عناصر التنفيذ بعد إعداد المحضر.')}
                </div>
              ) : (
                checklist.map((item) => (
                  <ChecklistToggle
                    key={item.id}
                    item={item}
                    canManage={canManage}
                    busy={savingChecklistId === item.id}
                    onToggle={handleChecklistToggle}
                    locale={locale}
                  />
                ))
              )}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default EventDetail;
