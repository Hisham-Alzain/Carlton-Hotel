import { useEffect } from 'react';
import { TrendingUp } from 'lucide-react';
import { Badge, PageHeader, Skeleton, ErrorState } from '../components/ui/Primitives.jsx';
import { useRateStore } from '../store/rateStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatMoney } from '../utils/money.js';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function fmtGridDate(dateStr) {
  const [, mm, dd] = dateStr.split('-');
  return `${MONTHS[+mm - 1]} ${+dd}`;
}

function inferBaseRate(rates) {
  const available = rates.filter((r) => r.available);
  if (!available.length) return 0;
  return Math.min(...available.map((r) => r.rate));
}

const CELL_STYLES = {
  available: { background: '#d1fae5', color: '#065f46' },
  high:      { background: '#fef3c7', color: '#92400e' },
  low:       { background: '#fde8d8', color: '#9a3412' },
  unavail:   { background: '#ece8e1', color: '#7A8B8E' },
};

function cellStyle(entry, baseRate) {
  if (!entry || !entry.available) return CELL_STYLES.unavail;
  if (entry.rate > baseRate * 1.1) return CELL_STYLES.high;
  if (entry.rate < baseRate * 0.95) return CELL_STYLES.low;
  return CELL_STYLES.available;
}

const LegendItem = ({ bg, color, label }) => (
  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
    <span style={{
      width: 14, height: 14, borderRadius: 3,
      background: bg, border: '1px solid rgba(0,0,0,0.09)', display: 'inline-block',
    }} />
    <span style={{ color, fontWeight: 500 }}>{label}</span>
  </span>
);

const TH_STYLE = {
  padding: '10px 8px',
  whiteSpace: 'nowrap',
  background: 'var(--color-bg)',
  borderBottom: '2px solid var(--color-border)',
  color: 'var(--color-text-muted)',
  fontWeight: 500,
  textAlign: 'center',
  minWidth: 74,
};

const TD_LABEL_STYLE = {
  padding: '9px 12px',
  whiteSpace: 'nowrap',
  fontWeight: 500,
  color: 'var(--color-text-strong)',
  borderBottom: '1px solid var(--color-border)',
};

const TD_CELL_BASE = {
  padding: '8px 6px',
  textAlign: 'center',
  borderBottom: '1px solid var(--color-border)',
  fontVariantNumeric: 'tabular-nums',
  fontSize: 12,
};

const RateGrid = () => {
  const { grid, isLoading, error, fetchGrid } = useRateStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  useEffect(() => { fetchGrid(); }, [fetchGrid]);

  if (isLoading) return <Skeleton lines={6} />;

  if (error) {
    return (
      <ErrorState
        title={t('Failed to load rate grid', 'تعذّر تحميل شبكة الأسعار')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const dates = grid[0]?.rates.map((r) => r.date) ?? [];

  return (
    <>
      <PageHeader
        title={t('Rate Grid', 'شبكة الأسعار')}
        subtitle={t(
          '14-day best available rates by room type.',
          'أفضل الأسعار المتاحة لـ 14 يومًا حسب نوع الغرفة.',
        )}
        actions={
          <Badge variant="neutral">
            <TrendingUp size={13} style={{ verticalAlign: 'middle', marginInlineEnd: 4 }} />
            {t('BAR Rates', 'أسعار BAR')}
          </Badge>
        }
      />

      <div className="card rg-table-wrap" style={{ overflowX: 'auto' }}>
        <table style={{ borderCollapse: 'collapse', width: '100%', fontSize: 13 }}>
          <thead>
            <tr>
              <th style={{ ...TH_STYLE, textAlign: 'start', padding: '10px 12px', color: 'var(--color-text-body)' }}>
                {t('Room Type', 'نوع الغرفة')}
              </th>
              {dates.map((d) => (
                <th key={d} style={TH_STYLE}>{fmtGridDate(d)}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {grid.map((row) => {
              const baseRate = inferBaseRate(row.rates);
              const rateMap = Object.fromEntries(row.rates.map((r) => [r.date, r]));
              return (
                <tr key={row.room_type_id}>
                  <td style={TD_LABEL_STYLE}>{row.room_type_name}</td>
                  {dates.map((d) => {
                    const entry = rateMap[d];
                    const style = cellStyle(entry, baseRate);
                    return (
                      <td key={d} style={{ ...TD_CELL_BASE, ...style }}>
                        {entry?.available ? formatMoney(entry.rate) : '—'}
                      </td>
                    );
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <div style={{
        display: 'flex', gap: 18, alignItems: 'center',
        marginTop: 12, flexWrap: 'wrap', fontSize: 12,
      }}>
        <LegendItem bg={CELL_STYLES.available.background} color={CELL_STYLES.available.color} label={t('Available', 'متاح')} />
        <LegendItem bg={CELL_STYLES.high.background}      color={CELL_STYLES.high.color}      label={t('High demand', 'طلب مرتفع')} />
        <LegendItem bg={CELL_STYLES.low.background}       color={CELL_STYLES.low.color}       label={t('Sale / low', 'تخفيض')} />
        <LegendItem bg={CELL_STYLES.unavail.background}   color={CELL_STYLES.unavail.color}   label={t('Unavailable', 'غير متاح')} />
      </div>
    </>
  );
};

export default RateGrid;
