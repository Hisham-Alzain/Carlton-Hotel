import { useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Luggage, PlaneTakeoff, Clock, ReceiptText } from 'lucide-react';
import { Badge, Button, ErrorState, PageHeader, Select, Skeleton } from '../components/ui/Primitives.jsx';
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

const formatAge = (createdAt) => {
  const age = minutesAgo(createdAt);
  if (age >= 60) return `${Math.floor(age / 60)}h ${age % 60}m`;
  return `${age}m`;
};

const DepartureServices = () => {
  const { items, meta, isLoading, error, subTypeFilter, fetchAll, updateStatus, setSubTypeFilter } =
    useDepartureStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  useEffect(() => {
    fetchAll();
  }, [fetchAll, subTypeFilter]);

  const grouped = useMemo(() => {
    const map = { open: [], in_progress: [], resolved: [] };
    items.forEach((item) => {
      if (map[item.status]) map[item.status].push(item);
    });
    return map;
  }, [items]);

  if (isLoading) return <Skeleton lines={6} />;
  if (error) {
    return (
      <ErrorState
        title={t('Could not load', 'تعذّر التحميل')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const openCount = items.filter((i) => i.status === 'open').length;

  return (
    <>
      <PageHeader
        title={t('Departure Services', 'خدمات المغادرة')}
        subtitle={t(
          'Late checkouts, luggage, and transport requests.',
          'طلبات المغادرة المتأخرة والأمتعة والنقل.',
        )}
        actions={
          <Badge variant={openCount > 0 ? 'warning' : 'neutral'}>
            {openCount} {t('open', 'مفتوح')}
          </Badge>
        }
      />

      <div className="queue-toolbar">
        <Select
          value={subTypeFilter}
          onChange={(e) => setSubTypeFilter(e.target.value)}
          aria-label={t('Filter by type', 'تصفية حسب النوع')}
        >
          <option value="all">{t('All types', 'جميع الأنواع')}</option>
          <option value="late_checkout">{t('Late checkout', 'مغادرة متأخرة')}</option>
          <option value="luggage_storage">{t('Luggage', 'أمتعة')}</option>
          <option value="transport">{t('Transport', 'نقل')}</option>
        </Select>
      </div>

      <div className="queue-grid">
        {SECTIONS.map(([status, labelObj]) => {
          const sectionItems = grouped[status] || [];
          return (
            <section className="queue-column" key={status}>
              <h2>
                {pickLocalized(labelObj, locale)}{' '}
                <Badge variant="neutral">{sectionItems.length}</Badge>
              </h2>

              {sectionItems.length === 0 && (
                <p className="dep-none">
                  <em>{t('None', 'لا يوجد')}</em>
                </p>
              )}

              {sectionItems.map((item) => {
                const subMeta = SUB_TYPE_META[item.sub_type] || {};
                const SubIcon = subMeta.icon;
                return (
                  <div
                    key={item.id}
                    className={item.priority === 'urgent' ? 'dep-card is-urgent' : 'dep-card'}
                  >
                    <div className="queue-card-top">
                      <Badge variant={subMeta.variant || 'neutral'}>
                        {SubIcon && <SubIcon size={12} />}
                        {pickLocalized(subMeta.label, locale)}
                      </Badge>
                      <span className="queue-age">{formatAge(item.created_at)}</span>
                    </div>

                    <h3>{item.title}</h3>

                    <p className="dep-card-meta">
                      {item.guest_name}
                      {item.room_number && (
                        <>
                          {' · '}
                          <strong>
                            {t('Room', 'غرفة')} {item.room_number}
                          </strong>
                        </>
                      )}
                      {item.requested_time && (
                        <> · {formatDate(item.requested_time, { locale })}</>
                      )}
                    </p>

                    {item.assigned_to && (
                      <p className="dep-assignee">{item.assigned_to}</p>
                    )}

                    <div className="dep-card-foot">
                      {status === 'open' && (
                        <Button
                          variant="secondary"
                          onClick={() => updateStatus(item.id, 'in_progress')}
                        >
                          {t('Begin', 'ابدأ')}
                        </Button>
                      )}
                      {status === 'in_progress' && (
                        <Button
                          variant="secondary"
                          onClick={() => updateStatus(item.id, 'resolved')}
                        >
                          {t('Complete', 'أتمم')}
                        </Button>
                      )}
                      {item.reservation_id && (
                        <Link
                          to={`/reservations/${item.reservation_id}/folio`}
                          className="dep-folio-link"
                        >
                          <ReceiptText size={13} />
                          {t('Folio', 'الفاتورة')}
                        </Link>
                      )}
                    </div>
                  </div>
                );
              })}
            </section>
          );
        })}
      </div>
    </>
  );
};

export default DepartureServices;
