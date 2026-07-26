import { useEffect, useMemo, useState } from 'react';
import { Badge, Button, EmptyState, ErrorState, Input, Field, PageHeader, Select, Skeleton } from '../components/ui/Primitives.jsx';
import { useServiceRequestStore } from '../store/serviceRequestStore.js';
import { useQueueStore } from '../store/queueStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { minutesAgo } from '../utils/date.js';

const SECTIONS = [
  ['open', { en: 'Open', ar: 'مفتوح' }],
  ['in_progress', { en: 'In progress', ar: 'قيد التنفيذ' }],
  ['waiting_guest', { en: 'Waiting guest', ar: 'انتظار الضيف' }],
];

const ESCALATION_THRESHOLD = 120;

const CHARGE_CATEGORIES = [
  { value: 'misc', label: 'Misc' },
  { value: 'food', label: 'Food' },
  { value: 'beverage', label: 'Beverage' },
  { value: 'spa', label: 'Spa' },
  { value: 'transport', label: 'Transport' },
];

const HANDOFF_STAGES = [
  { key: 'received', en: 'Received', ar: 'مُستلم' },
  { key: 'assigned', en: 'Assigned', ar: 'مُكلَّف' },
  { key: 'active', en: 'Active', ar: 'نشط' },
  { key: 'done', en: 'Done', ar: 'منجز' },
];

function handoffIndex(item) {
  if (item.status === 'resolved' || item.status === 'closed') return 3;
  if (item.status === 'in_progress' || item.status === 'waiting_guest') return 2;
  if (item.assigned_to) return 1;
  return 0;
}

const priorityVariant = (priority) => {
  if (priority === 'critical' || priority === 'urgent') return 'danger';
  if (priority === 'high') return 'warning';
  return 'neutral';
};

const cardClass = (createdAt) => {
  const age = minutesAgo(createdAt);
  if (age >= ESCALATION_THRESHOLD * 2) return 'sr-card is-critical';
  if (age >= ESCALATION_THRESHOLD) return 'sr-card is-escalated';
  return 'sr-card';
};

const AgeStamp = ({ createdAt }) => {
  const age = minutesAgo(createdAt);
  const hours = Math.floor(age / 60);
  const mins = age % 60;
  const label = hours > 0 ? `${hours}h ${mins}m` : `${age}m`;
  const cls =
    age >= ESCALATION_THRESHOLD * 2
      ? 'sr-age crit'
      : age >= ESCALATION_THRESHOLD
        ? 'sr-age warn'
        : 'sr-age';
  return <span className={cls}>{label}</span>;
};

function HandoffTrail({ item, locale }) {
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const current = handoffIndex(item);
  return (
    <div className="sr-handoff-trail">
      {HANDOFF_STAGES.map((stage, i) => {
        const done = i < current;
        const active = i === current;
        return (
          <div key={stage.key} className="sr-handoff-step">
            {i > 0 && <div className={`sr-handoff-line${done ? ' is-done' : ''}`} />}
            <div className={`sr-handoff-dot${done ? ' is-done' : active ? ' is-active' : ''}`} />
            <span className={`sr-handoff-label${done ? ' is-done' : active ? ' is-active' : ''}`}>
              {t(stage.en, stage.ar)}
            </span>
          </div>
        );
      })}
    </div>
  );
}

function ResolveForm({ item, locale, onResolve, onCancel }) {
  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const [chargeable, setChargeable] = useState(false);
  const [category, setCategory] = useState('misc');
  const [description, setDescription] = useState(item.title || '');
  const [amount, setAmount] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    setSaving(true);
    const chargeData = chargeable && parseFloat(amount) > 0
      ? { category, description, amount: parseFloat(amount) }
      : null;
    await onResolve(item.id, chargeData);
    setSaving(false);
  };

  return (
    <div className="sr-resolve-form">
      <p className="sr-resolve-heading">{t('Resolve request', 'إغلاق الطلب')}</p>
      <label className="sr-resolve-toggle">
        <input
          type="checkbox"
          checked={chargeable}
          onChange={(e) => setChargeable(e.target.checked)}
        />
        <span>{t('Post charge to guest folio', 'إضافة رسوم على فاتورة الضيف')}</span>
      </label>
      {chargeable && (
        <div className="sr-resolve-charge">
          <Field label={t('Category', 'الفئة')} style={{ minWidth: '130px' }}>
            <Select value={category} onChange={(e) => setCategory(e.target.value)}>
              {CHARGE_CATEGORIES.map((c) => (
                <option key={c.value} value={c.value}>{c.label}</option>
              ))}
            </Select>
          </Field>
          <Field label={t('Description', 'الوصف')} style={{ flex: '1 1 160px' }}>
            <Input
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder={t('Charge description', 'وصف الرسوم')}
            />
          </Field>
          <Field label={t('Amount (USD)', 'المبلغ')} style={{ minWidth: '110px' }}>
            <Input
              type="number"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="0.00"
              min="0"
            />
          </Field>
        </div>
      )}
      <div className="sr-resolve-actions">
        <Button
          variant="primary"
          isLoading={saving}
          disabled={saving || (chargeable && !parseFloat(amount))}
          onClick={handleSubmit}
        >
          {chargeable
            ? t('Resolve & post charge', 'إغلاق وإضافة رسوم')
            : t('Mark resolved', 'تعيين منجزاً')
          }
        </Button>
        <Button variant="ghost" onClick={onCancel} disabled={saving}>
          {t('Cancel', 'إلغاء')}
        </Button>
      </div>
    </div>
  );
}

const ServiceRequests = () => {
  const { items, isLoading, error, deptFilter, fetchAll, updateStatus, resolve, setDeptFilter } =
    useServiceRequestStore();
  const { connected } = useQueueStore();
  const locale = useAuthStore((s) => s.locale);
  const user = useAuthStore((s) => s.user);

  const t = (en, ar) => pickLocalized({ en, ar }, locale);
  const [resolveId, setResolveId] = useState(null);

  useEffect(() => {
    fetchAll({ per_page: 50 });
    if (user?.department) {
      setDeptFilter(user.department);
    }
  }, [fetchAll, setDeptFilter, user?.department]);

  const departments = useMemo(() => {
    const seen = new Set();
    items.forEach((item) => { if (item.department) seen.add(item.department); });
    return ['all', ...Array.from(seen).sort()];
  }, [items]);

  const filtered = useMemo(
    () => (deptFilter === 'all' ? items : items.filter((item) => item.department === deptFilter)),
    [items, deptFilter]
  );

  const grouped = useMemo(
    () =>
      Object.fromEntries(
        SECTIONS.map(([status]) => [
          status,
          filtered
            .filter((item) => item.status === status)
            .sort((a, b) => minutesAgo(b.created_at) - minutesAgo(a.created_at)),
        ])
      ),
    [filtered]
  );

  if (isLoading) return <Skeleton lines={6} />;

  if (error) {
    return (
      <ErrorState
        title={t('Could not load service requests', 'تعذّر تحميل طلبات الخدمة')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const totalVisible = filtered.length;

  return (
    <>
      <PageHeader
        title={t('Service requests', 'طلبات الخدمة')}
        subtitle={t(
          'Open and active service requests across all departments.',
          'طلبات الخدمة المفتوحة والنشطة عبر جميع الأقسام.'
        )}
        actions={
          <Badge variant={connected ? 'success' : 'warning'}>
            {connected ? t('Live', 'مباشر') : t('Disconnected', 'غير متصل')}
          </Badge>
        }
      />

      <div className="sr-toolbar">
        <Select
          value={deptFilter}
          onChange={(e) => setDeptFilter(e.target.value)}
          aria-label={t('Filter by department', 'تصفية حسب القسم')}
        >
          {departments.map((d) => (
            <option key={d} value={d}>
              {d === 'all' ? t('All departments', 'جميع الأقسام') : d}
            </option>
          ))}
        </Select>
        <span className="sr-count">
          {totalVisible} {totalVisible !== 1 ? t('requests', 'طلبات') : t('request', 'طلب')}
        </span>
      </div>

      {totalVisible === 0 && (
        <EmptyState
          title={t('No service requests', 'لا توجد طلبات خدمة')}
          message={t('No service requests match this filter.', 'لا تتطابق أي طلبات خدمة مع هذا الفلتر.')}
        />
      )}

      <div className="sr-list">
        {SECTIONS.map(([status, labelObj]) => {
          const sectionItems = grouped[status];
          if (!sectionItems?.length) return null;
          return (
            <section className="sr-section" key={status}>
              <div className="sr-section-head">
                <h2 className="sr-section-title">{pickLocalized(labelObj, locale)}</h2>
                <Badge variant="neutral">{sectionItems.length}</Badge>
              </div>
              {sectionItems.map((item) => (
                <article className={cardClass(item.created_at)} key={item.id}>
                  <div className="sr-card-top">
                    <Badge variant={priorityVariant(item.priority)}>{item.priority}</Badge>
                    <AgeStamp createdAt={item.created_at} />
                    <span className="sr-card-ref">{item.reference}</span>
                  </div>
                  <p className="sr-card-title">{item.title}</p>
                  <div className="sr-card-meta">
                    {item.guest_name && <span>{item.guest_name}</span>}
                    {item.room_number && <strong>{t('Room', 'غرفة')} {item.room_number}</strong>}
                    {item.department && (
                      <span className="sr-dept-chip">{item.department}</span>
                    )}
                    {item.assigned_to && (
                      <span className="sr-assigned">{item.assigned_to}</span>
                    )}
                  </div>

                  <HandoffTrail item={item} locale={locale} />

                  {resolveId === item.id ? (
                    <ResolveForm
                      item={item}
                      locale={locale}
                      onResolve={async (id, chargeData) => {
                        await resolve(id, chargeData);
                        setResolveId(null);
                      }}
                      onCancel={() => setResolveId(null)}
                    />
                  ) : (
                    <div className="sr-card-foot">
                      {status === 'open' && (
                        <Button
                          variant="secondary"
                          onClick={() => updateStatus(item.id, 'in_progress')}
                        >
                          {t('Begin', 'ابدأ')}
                        </Button>
                      )}
                      {status === 'in_progress' && (
                        <>
                          <Button
                            variant="secondary"
                            onClick={() => updateStatus(item.id, 'waiting_guest')}
                          >
                            {t('Await guest', 'انتظر الضيف')}
                          </Button>
                          <Button
                            variant="ghost"
                            onClick={() => setResolveId(item.id)}
                          >
                            {t('Resolve', 'أغلق')}
                          </Button>
                        </>
                      )}
                      {status === 'waiting_guest' && (
                        <Button
                          variant="ghost"
                          onClick={() => setResolveId(item.id)}
                        >
                          {t('Resolve', 'أغلق')}
                        </Button>
                      )}
                    </div>
                  )}
                </article>
              ))}
            </section>
          );
        })}
      </div>
    </>
  );
};

export default ServiceRequests;
