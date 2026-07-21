import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Badge, Card, PageHeader, Skeleton, ErrorState, Table } from '../components/ui/Primitives.jsx';
import { useGuestStore } from '../store/guestStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatMoney } from '../utils/money.js';

const VIP_BADGE = { platinum: 'warning', gold: 'info', standard: 'neutral' };

const GuestsList = () => {
  const { guests, isLoading, error, fetchGuests } = useGuestStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  useEffect(() => {
    fetchGuests();
  }, [fetchGuests]);

  if (isLoading) return <Skeleton lines={4} />;
  if (error) return (
    <ErrorState
      title={t('Failed to load guests', 'تعذر تحميل الضيوف')}
      message={error.message}
      requestId={error.request_id}
    />
  );

  return (
    <>
      <PageHeader
        title={t('Guests', 'الضيوف')}
        subtitle={t('Guest profiles and stay histories.', 'ملفات الضيوف وتاريخ إقاماتهم.')}
        actions={<Badge variant="neutral">{guests.length}</Badge>}
      />
      <Card className="data-card">
        <Table
          rows={guests}
          columns={[
            {
              key: 'full_name',
              label: t('Name', 'الاسم'),
              render: (row) => <Link to={`/guests/${row.id}`}>{row.full_name}</Link>,
            },
            {
              key: 'nationality',
              label: t('Nationality', 'الجنسية'),
            },
            {
              key: 'vip_level',
              label: t('VIP', 'VIP'),
              render: (row) => (
                <Badge variant={VIP_BADGE[row.vip_level] || 'neutral'}>{row.vip_level}</Badge>
              ),
            },
            {
              key: 'total_stays',
              label: t('Stays', 'الإقامات'),
            },
            {
              key: 'total_nights',
              label: t('Nights', 'الليالي'),
            },
            {
              key: 'total_spend',
              label: t('Spend', 'الإنفاق'),
              render: (row) => formatMoney(row.total_spend),
            },
          ]}
        />
      </Card>
    </>
  );
};

export default GuestsList;
