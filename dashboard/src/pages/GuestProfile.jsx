import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AlertTriangle, ClipboardCheck, History, Plane, Sparkles, Star, UserCircle } from 'lucide-react';
import { Badge, Button, Card, EmptyState, ErrorState, PageHeader, Skeleton, Textarea } from '../components/ui/Primitives.jsx';
import { useGuestStore } from '../store/guestStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDate, formatDateTime } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

const VIP_BADGE = { platinum: 'warning', gold: 'info', standard: 'neutral' };
const ACTIVE_STATUSES = ['checked_in', 'due_out', 'arriving_today', 'upcoming'];
const PLAN_BADGE = {
  complete: 'success',
  ready: 'success',
  blocked: 'danger',
  in_progress: 'warning',
  pending: 'neutral',
};

const DETAIL_BADGE = {
  ready: 'success',
  done: 'success',
  delivered: 'success',
  confirmed: 'success',
  packed: 'info',
  planned: 'warning',
  requested: 'warning',
  blocked: 'danger',
  needs_attention: 'warning',
  in_progress: 'warning',
  pending: 'neutral',
  not_needed: 'neutral',
};

const STATUS_COPY = {
  complete: { en: 'Complete', ar: 'مكتملة' },
  ready: { en: 'Ready', ar: 'جاهزة' },
  blocked: { en: 'Blocked', ar: 'متوقفة' },
  in_progress: { en: 'In progress', ar: 'قيد التنفيذ' },
  pending: { en: 'Pending', ar: 'قيد الانتظار' },
  confirmed: { en: 'Confirmed', ar: 'مؤكدة' },
  requested: { en: 'Requested', ar: 'مطلوبة' },
  delivered: { en: 'Delivered', ar: 'تم التسليم' },
  packed: { en: 'Packed', ar: 'معبأة' },
  planned: { en: 'Planned', ar: 'مخططة' },
  needs_attention: { en: 'Needs attention', ar: 'بحاجة متابعة' },
  not_needed: { en: 'Not needed', ar: 'غير مطلوب' },
  ready_for_arrival: { en: 'Ready for arrival', ar: 'جاهزة للوصول' },
};

const TRANSFER_MODE_COPY = {
  airport_pickup: { en: 'Airport pickup', ar: 'استقبال من المطار' },
  hotel_car: { en: 'Hotel car', ar: 'سيارة الفندق' },
  private_car: { en: 'Private car', ar: 'سيارة خاصة' },
  not_required: { en: 'Not required', ar: 'غير مطلوب' },
};

const CHECKLIST_ORDER = {
  blocked: 0,
  in_progress: 1,
  pending: 2,
  ready: 3,
  done: 4,
};

function localizedValue(value, locale) {
  if (value == null) return '—';
  if (typeof value === 'object') {
    return pickLocalized(value, locale) || value.en || value.ar || '—';
  }
  return value;
}

function personLabel(person, locale) {
  if (!person) return '—';
  return pickLocalized({ en: person.name, ar: person.name_ar }, locale) || person.name || '—';
}

function personTitle(person, locale) {
  if (!person) return '';
  return pickLocalized({ en: person.title, ar: person.title_ar }, locale) || '';
}

function statusLabel(status, locale) {
  return localizedValue(STATUS_COPY[status] || { en: status ? status.replaceAll('_', ' ') : '—' }, locale);
}

function statusVariant(status) {
  return DETAIL_BADGE[status] || PLAN_BADGE[status] || 'neutral';
}

function transferModeLabel(mode, locale) {
  return localizedValue(TRANSFER_MODE_COPY[mode] || { en: mode ? mode.replaceAll('_', ' ') : '—' }, locale);
}

function checklistProgress(checklist = []) {
  if (!checklist.length) {
    return { done: 0, total: 0, status: 'pending', progress: 0, blocked: false };
  }

  const done = checklist.filter((item) => ['ready', 'done'].includes(item.status)).length;
  const blocked = checklist.some((item) => item.status === 'blocked');
  const status = blocked ? 'blocked' : done === checklist.length ? 'ready' : 'in_progress';

  return {
    done,
    total: checklist.length,
    status,
    progress: Math.round((done / checklist.length) * 100),
    blocked,
  };
}

function clonePlan(plan) {
  return JSON.parse(JSON.stringify(plan));
}

const CARD_GRID_STYLE = { display: 'grid', gap: '0.75rem' };
const SUMMARY_GRID_STYLE = {
  display: 'grid',
  gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
  gap: '0.75rem',
};
const SUMMARY_BOX_STYLE = {
  border: '1px solid var(--color-border)',
  borderRadius: '8px',
  padding: '0.75rem',
  background: 'rgba(8, 65, 76, 0.03)',
};
const SUMMARY_LABEL_STYLE = {
  fontSize: '0.72rem',
  textTransform: 'uppercase',
  letterSpacing: 0,
  color: 'var(--color-text-muted)',
};
const SUMMARY_VALUE_STYLE = {
  fontSize: '0.92rem',
  fontWeight: 700,
  color: 'var(--color-text-strong)',
  display: 'flex',
  flexDirection: 'column',
  gap: '0.15rem',
};
const SPLIT_GRID_STYLE = {
  display: 'grid',
  gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
  gap: '0.75rem',
};
const BOX_STYLE = {
  border: '1px solid var(--color-border)',
  borderRadius: '8px',
  padding: '0.75rem',
  background: 'var(--color-surface)',
  display: 'grid',
  gap: '0.4rem',
};
const NEXT_ACTION_STYLE = {
  border: '1px solid rgba(192, 160, 96, 0.35)',
  borderInlineStart: '3px solid var(--color-muted-gold)',
  borderRadius: '8px',
  padding: '0.75rem',
  background: 'rgba(192, 160, 96, 0.08)',
  display: 'grid',
  gap: '0.25rem',
};
const ACTION_ROW_STYLE = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: '0.5rem',
  alignItems: 'center',
};
const PROGRESS_TRACK_STYLE = {
  width: '100%',
  height: '8px',
  borderRadius: '999px',
  background: 'rgba(8, 65, 76, 0.12)',
  overflow: 'hidden',
};
const PROGRESS_FILL_STYLE = {
  height: '100%',
  borderRadius: '999px',
  background: 'var(--color-deep-teal)',
};

function nextPreArrivalStatus(status) {
  if (status === 'ready') return 'done';
  return 'ready';
}

function PreArrivalPlanner({ guest, locale, saving, onUpdateItem, onPatch, currentUser }) {
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const plan = guest.pre_arrival_plan;

  if (!plan) {
    return null;
  }

  const checklist = Array.isArray(plan.checklist) ? plan.checklist : [];
  const progress = checklistProgress(checklist);
  const workflowStatus = plan.status || progress.status || plan.readiness || 'pending';
  const owner = plan.owner || { name: plan.coordinator, name_ar: plan.coordinator };
  const transfer = plan.transfer || plan.airport_transfer || {};
  const readiness = plan.readiness_detail || {
    status: plan.readiness || progress.status,
    score: progress.progress,
    due_at: plan.arrival_date,
    note: null,
  };
  const amenity = plan.welcome_amenity_detail || {
    status: workflowStatus === 'complete' || workflowStatus === 'ready' ? 'delivered' : 'planned',
    item: typeof plan.welcome_amenity === 'object'
      ? plan.welcome_amenity
      : { en: plan.welcome_amenity, ar: plan.welcome_amenity },
    owner,
    note: null,
  };
  const amenityText = typeof amenity.item === 'object'
    ? amenity.item.en || amenity.item.ar || localizedValue(amenity.item, locale)
    : amenity.item;
  const sortedChecklist = [...checklist].sort(
    (a, b) => (CHECKLIST_ORDER[a.status] ?? 99) - (CHECKLIST_ORDER[b.status] ?? 99)
  );
  const readinessLabel = statusLabel(readiness.status, locale);
  const readinessPercent = progress.total ? progress.progress : readiness.score || 0;
  const ownerName = personLabel(owner, locale);
  const ownerRole = personTitle(owner, locale);
  const arrivalText = plan.arrival_date ? formatDateTime(plan.arrival_date) : plan.arrival_window || '—';
  const updatedText = plan.updated_at ? formatDateTime(plan.updated_at) : '—';
  const transferLabel = transferModeLabel(transfer.mode || plan.transfer_mode, locale);
  const transferStatus = transfer.status || plan.airport_transfer?.status || 'pending';
  const amenityStatus = amenity.status || 'pending';
  const transferTimeText = transfer.pickup_at
    ? formatDateTime(transfer.pickup_at)
    : transfer.eta || plan.airport_transfer?.eta || '—';
  const readyActionLabel = workflowStatus === 'complete'
    ? t('Reconfirm ready', 'إعادة تأكيد الجاهزية')
    : t('Mark ready', 'تأكيد الجاهزية');
  const transferActionLabel = transferStatus === 'confirmed'
    ? t('Reconfirm transfer', 'إعادة تأكيد النقل')
    : t('Confirm transfer', 'تأكيد النقل');
  const amenityActionLabel = amenityStatus === 'delivered'
    ? t('Reconfirm amenity', 'إعادة تأكيد الضيافة')
    : t('Confirm amenity', 'تأكيد الضيافة');

  const updatePlan = (patchBuilder) => {
    if (!onPatch) return;
    const nextPlan = clonePlan(plan);
    patchBuilder(nextPlan);
    nextPlan.updated_at = new Date().toISOString();
    onPatch(nextPlan);
  };

  const handleOwnerClaim = () => {
    updatePlan((nextPlan) => {
      nextPlan.owner = {
        name: currentUser?.name || owner.name,
        name_ar: currentUser?.name_ar || currentUser?.name || owner.name_ar || owner.name,
        title: currentUser?.title || owner.title || '',
        title_ar: currentUser?.title_ar || owner.title_ar || owner.title || '',
        department: currentUser?.department || owner.department || null,
      };
      nextPlan.coordinator = nextPlan.owner.name;
    });
  };

  const handleMarkReady = () => {
    updatePlan((nextPlan) => {
      nextPlan.status = workflowStatus === 'complete' ? 'complete' : 'ready';
      nextPlan.readiness = 'ready';
      nextPlan.readiness_detail = {
        ...nextPlan.readiness_detail,
        status: 'ready',
        score: 100,
        note: t(
          'All arrival touchpoints are confirmed and ready.',
          'تم تأكيد جميع نقاط الوصول وأصبحت جاهزة.'
        ),
      };
      nextPlan.transfer = { ...transfer, status: 'confirmed' };
      nextPlan.airport_transfer = { ...nextPlan.airport_transfer, status: 'confirmed' };
      nextPlan.welcome_amenity_detail = { ...amenity, status: 'delivered' };
      nextPlan.welcome_amenity = amenityText;
      nextPlan.checklist = checklist.map((item) => ({ ...item, status: 'ready' }));
      nextPlan.next_action = t(
        'Welcome the guest at the room once they arrive.',
        'استقبال الضيف في الغرفة عند الوصول.'
      );
    });
  };

  const handleTransferConfirm = () => {
    updatePlan((nextPlan) => {
      nextPlan.transfer = {
        ...transfer,
        status: 'confirmed',
      };
      nextPlan.airport_transfer = {
        ...nextPlan.airport_transfer,
        status: 'confirmed',
        eta: transfer.pickup_at || transfer.eta || nextPlan.airport_transfer?.eta || null,
        provider: transfer.provider || nextPlan.airport_transfer?.provider || null,
      };
    });
  };

  const handleAmenityConfirm = () => {
    updatePlan((nextPlan) => {
      nextPlan.welcome_amenity_detail = {
        ...amenity,
        status: 'delivered',
      };
      nextPlan.welcome_amenity = amenityText;
    });
  };

  return (
    <Card className="gp-prearrival-card" padded>
      <div style={{ display: 'grid', gap: '1rem' }}>
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '0.75rem', flexWrap: 'wrap' }}>
          <div style={{ display: 'grid', gap: '0.2rem' }}>
            <h2 className="gp-card-heading" style={{ display: 'flex', alignItems: 'center', gap: '0.45rem', margin: 0 }}>
              <Star size={16} />
              {t('VIP pre-arrival plan', 'خطة ما قبل الوصول لكبار الشخصيات')}
            </h2>
            <p className="gp-card-subtitle" style={{ margin: 0, color: 'var(--color-text-muted)' }}>
              {t(
                'Coordinate arrival readiness across front office, concierge, kitchen, and housekeeping.',
                'تنسيق جاهزية الوصول بين الاستقبال والكونسيرج والمطبخ والتدبير المنزلي.'
              )}
            </p>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'flex-end' }}>
            <Badge variant={PLAN_BADGE[workflowStatus] || 'neutral'}>
              {statusLabel(workflowStatus, locale)}
            </Badge>
            <Badge variant="neutral">
              {progress.done}/{progress.total || checklist.length || 0}
            </Badge>
            {plan.reservation_id && (
              <Link
                to={`/reservations/${plan.reservation_id}`}
                style={{ color: 'var(--color-deep-teal)', fontWeight: 600, textDecoration: 'none' }}
              >
                {t('Open reservation', 'فتح الحجز')}
              </Link>
            )}
          </div>
        </div>

        <div style={SUMMARY_GRID_STYLE}>
          <div style={SUMMARY_BOX_STYLE}>
            <span style={SUMMARY_LABEL_STYLE}>{t('Arrival', 'الوصول')}</span>
            <strong style={SUMMARY_VALUE_STYLE}>{arrivalText}</strong>
            <span style={{ color: 'var(--color-text-muted)', fontSize: '0.84rem' }}>
              {plan.arrival_window || t('Arrival window not set', 'لم تُحدد نافذة الوصول')}
            </span>
          </div>

          <div style={SUMMARY_BOX_STYLE}>
            <span style={SUMMARY_LABEL_STYLE}>{t('Owner', 'المسؤول')}</span>
            <strong style={SUMMARY_VALUE_STYLE}>{ownerName}</strong>
            {ownerRole && (
              <span style={{ color: 'var(--color-text-muted)', fontSize: '0.84rem' }}>{ownerRole}</span>
            )}
          </div>

          <div style={SUMMARY_BOX_STYLE}>
            <span style={SUMMARY_LABEL_STYLE}>{t('Arrival readiness', 'جاهزية الوصول')}</span>
            <strong style={SUMMARY_VALUE_STYLE}>
              <span>{readinessLabel}</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem', flexWrap: 'wrap' }}>
                <Badge variant={statusVariant(readiness.status)}>{readinessPercent}%</Badge>
                <span style={{ color: 'var(--color-text-muted)', fontSize: '0.84rem' }}>
                  {readiness.due_at ? formatDateTime(readiness.due_at) : t('No due time', 'لا يوجد موعد استحقاق')}
                </span>
              </span>
            </strong>
            <div style={PROGRESS_TRACK_STYLE} aria-hidden="true">
              <div style={{ ...PROGRESS_FILL_STYLE, width: `${readinessPercent}%` }} />
            </div>
          </div>

          <div style={SUMMARY_BOX_STYLE}>
            <span style={SUMMARY_LABEL_STYLE}>{t('Updated', 'آخر تحديث')}</span>
            <strong style={SUMMARY_VALUE_STYLE}>{updatedText}</strong>
            <span style={{ color: 'var(--color-text-muted)', fontSize: '0.84rem' }}>
              {t('Last synchronized plan state', 'آخر حالة متزامنة للخطة')}
            </span>
          </div>
        </div>

        <div style={NEXT_ACTION_STYLE}>
          <span style={SUMMARY_LABEL_STYLE}>{t('Next action', 'الإجراء التالي')}</span>
          <strong style={{ color: 'var(--color-text-strong)' }}>
            {localizedValue(plan.next_action || plan.next_action_note || plan.welcome_amenity_detail?.note, locale)}
          </strong>
          {plan.room_preference && (
            <span style={{ color: 'var(--color-text-body)', fontSize: '0.9rem' }}>
              {t('Room setup', 'تجهيز الغرفة')}: {plan.room_preference}
            </span>
          )}
        </div>

        <div style={ACTION_ROW_STYLE}>
          <Button
            icon={ClipboardCheck}
            isLoading={saving}
            disabled={saving}
            onClick={handleMarkReady}
          >
            {readyActionLabel}
          </Button>
          <Button
            variant="secondary"
            icon={Plane}
            isLoading={saving}
            disabled={saving}
            onClick={handleTransferConfirm}
          >
            {transferActionLabel}
          </Button>
          <Button
            variant="secondary"
            icon={Sparkles}
            isLoading={saving}
            disabled={saving}
            onClick={handleAmenityConfirm}
          >
            {amenityActionLabel}
          </Button>
          <Button
            variant="secondary"
            icon={UserCircle}
            isLoading={saving}
            disabled={saving}
            onClick={handleOwnerClaim}
          >
            {t('Assign to me', 'إسناد إلي')}
          </Button>
        </div>

        <div style={SPLIT_GRID_STYLE}>
          <div style={BOX_STYLE}>
            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '0.5rem', flexWrap: 'wrap' }}>
              <strong style={{ color: 'var(--color-text-strong)' }}>
                {t('Transfer', 'النقل')}
              </strong>
              <Badge variant={statusVariant(transferStatus)}>{statusLabel(transferStatus, locale)}</Badge>
            </div>
            <div style={{ display: 'grid', gap: '0.25rem', fontSize: '0.88rem' }}>
              <span>{transferLabel}</span>
              <span>{transfer.provider || plan.airport_transfer?.provider || '—'}</span>
              <span>
                {transfer.flight_no || plan.flight_no || t('Flight not set', 'لم يتم تحديد الرحلة')}
                {(transfer.pickup_at || transfer.eta || plan.airport_transfer?.eta) ? ` · ${transferTimeText}` : ''}
              </span>
              {transfer.note && (
                <span style={{ color: 'var(--color-text-muted)' }}>
                  {localizedValue(transfer.note, locale)}
                </span>
              )}
            </div>
          </div>

          <div style={BOX_STYLE}>
            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '0.5rem', flexWrap: 'wrap' }}>
              <strong style={{ color: 'var(--color-text-strong)' }}>
                {t('Welcome amenity', 'ضيافة الترحيب')}
              </strong>
              <Badge variant={statusVariant(amenityStatus)}>{statusLabel(amenityStatus, locale)}</Badge>
            </div>
            <div style={{ display: 'grid', gap: '0.25rem', fontSize: '0.88rem' }}>
              <span>{amenityText}</span>
              <span>{personLabel(amenity.owner || owner, locale)}</span>
              {personTitle(amenity.owner || owner, locale) && (
                <span style={{ color: 'var(--color-text-muted)' }}>
                  {personTitle(amenity.owner || owner, locale)}
                </span>
              )}
              {amenity.note && (
                <span style={{ color: 'var(--color-text-muted)' }}>
                  {localizedValue(amenity.note, locale)}
                </span>
              )}
            </div>
          </div>
        </div>

        <div style={CARD_GRID_STYLE}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.5rem', flexWrap: 'wrap' }}>
            <strong style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}>
              <ClipboardCheck size={14} />
              {t('Room-prep items', 'عناصر تجهيز الغرفة')}
            </strong>
            <Badge variant="neutral">
              {progress.done}/{progress.total || checklist.length || 0} {t('ready', 'جاهزة')}
            </Badge>
          </div>
          <div style={{ display: 'grid', gap: '0.5rem' }}>
            {sortedChecklist.map((item) => (
              <div
                key={item.id}
                style={{
                  border: '1px solid var(--color-border)',
                  borderRadius: '8px',
                  padding: '0.75rem',
                  display: 'grid',
                  gap: '0.45rem',
                  background: 'var(--color-surface)',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '0.75rem', flexWrap: 'wrap' }}>
                  <div style={{ display: 'grid', gap: '0.2rem', minWidth: 0 }}>
                    <strong style={{ color: 'var(--color-text-strong)' }}>
                      {localizedValue({ en: item.label, ar: item.label_ar }, locale)}
                    </strong>
                    <span style={{ color: 'var(--color-text-body)', fontSize: '0.84rem' }}>
                      {item.owner}
                      {item.notes ? ` · ${item.notes}` : ''}
                    </span>
                  </div>
                  <Badge variant={statusVariant(item.status)}>{statusLabel(item.status, locale)}</Badge>
                </div>
                <div style={ACTION_ROW_STYLE}>
                  {item.status !== 'done' && (
                    <Button
                      variant="secondary"
                      disabled={saving}
                      onClick={() => onUpdateItem(item.id, nextPreArrivalStatus(item.status))}
                    >
                      {item.status === 'ready'
                        ? t('Mark done', 'وضع كمكتمل')
                        : t('Mark ready', 'وضع كجاهز')}
                    </Button>
                  )}
                  {item.status !== 'blocked' && item.status !== 'done' && (
                    <Button
                      variant="ghost"
                      disabled={saving}
                      onClick={() => onUpdateItem(item.id, 'blocked')}
                    >
                      {t('Block', 'تعطيل')}
                    </Button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>

        {progress.blocked && (
          <div className="gp-alert-bar danger">
            <AlertTriangle size={14} />
            <span>
              {t(
                'Arrival plan has blockers that need owner follow-up before guest arrival.',
                'توجد عوائق في خطة الوصول وتتطلب متابعة من المسؤول قبل وصول الضيف.'
              )}
            </span>
          </div>
        )}
      </div>
    </Card>
  );
}

const GuestProfile = () => {
  const { id } = useParams();
  const { guest, isLoading, error, savingNote, savingPreArrival, fetchGuest, addNote, updatePreArrival } = useGuestStore();
  const currentUser = useAuthStore((s) => s.user);
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const [noteInput, setNoteInput] = useState('');

  useEffect(() => {
    fetchGuest(id);
  }, [fetchGuest, id]);

  if (isLoading) return <Skeleton lines={8} />;
  if (error) {
    return (
      <ErrorState
        title={t('Failed to load guest', 'تعذر تحميل الضيف')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }
  if (!guest) return <EmptyState title={t('Guest not found', 'الضيف غير موجود')} />;

  const sortedHistory = [...(guest.stay_history || [])].sort(
    (a, b) => new Date(b.check_in) - new Date(a.check_in)
  );
  const sortedNotes = [...(guest.staff_notes || [])].sort(
    (a, b) => new Date(b.posted_at) - new Date(a.posted_at)
  );
  const hasActiveStay = sortedHistory.some((stay) => ACTIVE_STATUSES.includes(stay.status));
  const plan = guest.pre_arrival_plan;
  const showPreArrival = Boolean(plan && (guest.vip_level !== 'standard' || hasActiveStay));
  const planStatus = plan?.status || plan?.readiness || checklistProgress(plan?.checklist || []).status || 'pending';

  return (
    <div className="gp-layout">
      <PageHeader
        title={guest.full_name}
        subtitle={t('Guest profile, stay history, and pre-arrival planning', 'ملف الضيف وتاريخ الإقامات والتخطيط قبل الوصول')}
        actions={
          <div style={ACTION_ROW_STYLE}>
            <Badge variant={VIP_BADGE[guest.vip_level] || 'neutral'}>{guest.vip_level}</Badge>
            {hasActiveStay && <Badge variant="info">{t('Active stay', 'إقامة نشطة')}</Badge>}
            {showPreArrival && <Badge variant={PLAN_BADGE[planStatus] || 'neutral'}>{statusLabel(planStatus, locale)}</Badge>}
          </div>
        }
      />

      <div className="gp-grid">
        <div className="gp-left">
          <Card className="gp-identity-card" padded>
            <div className="gp-avatar">{guest.full_name.charAt(0)}</div>
            <div className="gp-names">
              <span className="gp-name">{guest.full_name}</span>
              {guest.full_name_ar && <span className="gp-name-ar" dir="rtl">{guest.full_name_ar}</span>}
            </div>
            <div className="gp-identity-badges">
              <Badge variant={VIP_BADGE[guest.vip_level] || 'neutral'}>{guest.vip_level}</Badge>
              {guest.nationality && <Badge variant="neutral">{guest.nationality}</Badge>}
              {hasActiveStay && <Badge variant="info">{t('Active stay', 'إقامة نشطة')}</Badge>}
            </div>
            <div className="gp-attrs">
              {guest.phone_masked && (
                <div className="gp-attr">
                  <span>{t('Phone', 'الهاتف')}</span>
                  <strong>{guest.phone_masked}</strong>
                </div>
              )}
              {guest.email_masked && (
                <div className="gp-attr">
                  <span>{t('Email', 'البريد الإلكتروني')}</span>
                  <strong>{guest.email_masked}</strong>
                </div>
              )}
            </div>
            <div className="gp-stats">
              <div className="gp-stat">
                <span>{guest.total_stays}</span>
                <small>{t('Stays', 'إقامات')}</small>
              </div>
              <div className="gp-stat">
                <span>{guest.total_nights}</span>
                <small>{t('Nights', 'ليالٍ')}</small>
              </div>
              <div className="gp-stat">
                <span>{formatMoney(guest.total_spend)}</span>
                <small>{t('Lifetime spend', 'الإجمالي')}</small>
              </div>
            </div>
          </Card>

          <Card className="gp-prefs-card" padded>
            <h2 className="gp-card-heading">{t('Preferences', 'التفضيلات')}</h2>
            <div className="gp-prefs-grid">
              <div className="gp-pref">
                <span>{t('Pillow type', 'نوع الوسادة')}</span>
                <strong>{guest.preferences?.pillow_type || '—'}</strong>
              </div>
              <div className="gp-pref">
                <span>{t('Floor preference', 'تفضيل الطابق')}</span>
                <strong>{guest.preferences?.floor_preference || '—'}</strong>
              </div>
              <div className="gp-pref">
                <span>{t('Dietary', 'النظام الغذائي')}</span>
                <strong>{guest.preferences?.dietary || '—'}</strong>
              </div>
              {guest.preferences?.notes && (
                <div className="gp-pref gp-pref-full">
                  <span>{t('Notes', 'ملاحظات')}</span>
                  <strong>{guest.preferences.notes}</strong>
                </div>
              )}
            </div>
          </Card>

          <Card className="gp-notes-card" padded>
            <h2 className="gp-card-heading">{t('Staff Notes', 'ملاحظات الموظفين')}</h2>
            {sortedNotes.length ? (
              <div className="gp-notes-list">
                {sortedNotes.map((note) => (
                  <div key={note.id} className="gp-note">
                    <div className="gp-note-meta">
                      <span>{note.posted_by}</span>
                      <span>{formatDate(note.posted_at)}</span>
                    </div>
                    <p>{note.note}</p>
                  </div>
                ))}
              </div>
            ) : (
              <p className="gp-no-notes"><em>{t('No notes yet', 'لا ملاحظات بعد')}</em></p>
            )}
            <div className="gp-note-form">
              <Textarea
                value={noteInput}
                onChange={(e) => setNoteInput(e.target.value)}
                rows={3}
                placeholder={t('Write a note about this guest…', 'اكتب ملاحظة حول هذا الضيف...')}
              />
              <Button
                isLoading={savingNote}
                disabled={!noteInput.trim() || savingNote}
                onClick={async () => {
                  await addNote(guest.id, noteInput);
                  setNoteInput('');
                }}
              >
                {t('Add note', 'إضافة ملاحظة')}
              </Button>
            </div>
          </Card>
        </div>

        <div className="gp-right">
          {showPreArrival && (
            <PreArrivalPlanner
              guest={guest}
              locale={locale}
              saving={savingPreArrival}
              currentUser={currentUser}
              onUpdateItem={(itemId, status) => updatePreArrival(guest.id, { item_id: itemId, status })}
              onPatch={(patch) => updatePreArrival(guest.id, { patch })}
            />
          )}

          <Card className="gp-history-card" padded>
            <h2 className="gp-card-heading">
              <History size={16} />
              {t('Stay History', 'تاريخ الإقامات')}
              <Badge variant="neutral">{sortedHistory.length}</Badge>
            </h2>
            {sortedHistory.length ? (
              <div className="gp-history-list">
                {sortedHistory.map((stay) => (
                  <div key={stay.reservation_id} className="gp-history-item">
                    <div className="gp-history-top">
                      <span className="gp-history-room">{stay.room_type_name}</span>
                      <span className="gp-history-nights">
                        {stay.nights} {t('nights', 'ليالٍ')}
                      </span>
                    </div>
                    <div className="gp-history-dates">
                      {formatDate(stay.check_in)} to {formatDate(stay.check_out)}
                    </div>
                    <div className="gp-history-footer">
                      <span className="gp-history-amount">{formatMoney(stay.amount)}</span>
                      <Badge variant="neutral">{stay.status.replaceAll('_', ' ')}</Badge>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p><em>{t('No previous stays', 'لا إقامات سابقة')}</em></p>
            )}
          </Card>
        </div>
      </div>
    </div>
  );
};

export default GuestProfile;
