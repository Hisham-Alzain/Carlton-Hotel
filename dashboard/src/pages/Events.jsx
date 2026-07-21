import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Calendar } from 'lucide-react';
import { Badge, Card, ErrorState, PageHeader, Select, Skeleton, Table } from '../components/ui/Primitives.jsx';
import { useEventsStore } from '../store/eventsStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatDate, formatDateTime } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

const STATUS_META = {
  rfp: { label: { en: 'RFP', ar: 'طلب عرض' }, variant: 'neutral' },
  tentative: { label: { en: 'Tentative', ar: 'مبدئي' }, variant: 'warning' },
  confirmed: { label: { en: 'Confirmed', ar: 'مؤكد' }, variant: 'success' },
  cancelled: { label: { en: 'Cancelled', ar: 'ملغى' }, variant: 'danger' },
};

const Events = () => {
  const navigate = useNavigate();
  const { events, meta, isLoading, error, statusFilter, fetchEvents, setStatusFilter } = useEventsStore();
  const locale = useAuthStore((state) => state.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents, statusFilter]);

  if (isLoading) return <Skeleton lines={6} />;

  if (error) {
    return (
      <ErrorState
        title={t('Failed to load events', 'فشل تحميل الفعاليات')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const confirmedCount = events.filter((event) => event.status === 'confirmed').length;
  const depositCount = events.filter((event) => event.deposit_paid).length;

  return (
    <>
      <PageHeader
        title={t('Events & BEOs', 'الفعاليات ومحاضر التنفيذ')}
        subtitle={t(
          'Track function briefs, room plans, deposits, and execution status.',
          'تابع محاضر التنفيذ، خطط القاعات، الودائع، وحالة التشغيل.',
        )}
        actions={
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
            <Badge variant="success">
              <Calendar size={13} style={{ display: 'inline', verticalAlign: 'middle', marginInlineEnd: 4 }} />
              {confirmedCount} {t('confirmed', 'مؤكد')}
            </Badge>
            <Badge variant="warning">
              {depositCount} {t('deposits', 'ودائع')}
            </Badge>
          </div>
        }
      />

      <Card className="data-card">
        <div className="toolbar">
          <div className="field-row">
            <label className="field">
              <span>{t('Status', 'الحالة')}</span>
              <Select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                <option value="all">{t('All statuses', 'جميع الحالات')}</option>
                <option value="rfp">{t('RFP', 'طلب عرض')}</option>
                <option value="tentative">{t('Tentative', 'مبدئي')}</option>
                <option value="confirmed">{t('Confirmed', 'مؤكد')}</option>
                <option value="cancelled">{t('Cancelled', 'ملغى')}</option>
              </Select>
            </label>
          </div>
          {meta && <span className="table-note">{meta.total} {t('events', 'فعالية')}</span>}
        </div>

        {events.length === 0 ? (
          <div style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--color-text-muted)' }}>
            <p style={{ margin: 0, color: 'var(--color-text-strong)', fontWeight: 650 }}>
              {t('No events found', 'لا توجد فعاليات')}
            </p>
            <p style={{ margin: '0.35rem 0 0' }}>
              {t('Try a different status filter.', 'جرّب فلتراً مختلفاً.')}
            </p>
          </div>
        ) : (
          <Table
            rows={events}
            onRowClick={(row) => navigate(`/events/${row.id}`)}
            columns={[
              {
                key: 'reference',
                label: t('Ref', 'المرجع'),
              },
              {
                key: 'title',
                label: t('Event', 'الفعالية'),
                render: (row) => <strong>{row.title}</strong>,
              },
              {
                key: 'client_company',
                label: t('Client', 'العميل'),
                render: (row) => row.client_company || '—',
              },
              {
                key: 'event_date',
                label: t('Function date', 'تاريخ المناسبة'),
                render: (row) => formatDate(row.event_date, { locale }),
              },
              {
                key: 'guaranteed_attendees',
                label: t('Guarantee', 'الضمان'),
              },
              {
                key: 'deposit_amount',
                label: t('Deposit', 'الوديعة'),
                render: (row) => (
                  <Badge variant={row.deposit_paid ? 'success' : 'warning'}>
                    {row.deposit_paid ? t('Paid', 'مدفوعة') : t('Due', 'مستحقة')} · {formatMoney(row.deposit_amount)}
                  </Badge>
                ),
              },
              {
                key: 'revenue_estimate',
                label: t('Revenue', 'الإيراد'),
                render: (row) => formatMoney(row.revenue_estimate),
              },
              {
                key: 'status',
                label: t('Status', 'الحالة'),
                render: (row) => {
                  const meta = STATUS_META[row.status];
                  return (
                    <Badge variant={meta?.variant ?? 'neutral'}>
                      {pickLocalized(meta?.label ?? { en: row.status }, locale)}
                    </Badge>
                  );
                },
              },
              {
                key: 'assigned_to',
                label: t('Assigned To', 'مسند إلى'),
                render: (row) => row.assigned_to ?? '—',
              },
              {
                key: 'updated_at',
                label: t('Updated', 'محدث'),
                render: (row) => formatDateTime(row.updated_at, { locale }),
              },
            ]}
          />
        )}
      </Card>
    </>
  );
};

export default Events;
