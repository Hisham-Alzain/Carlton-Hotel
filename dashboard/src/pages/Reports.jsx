import { useEffect } from 'react';
import { BarChart2, TrendingUp } from 'lucide-react';
import { Badge, Card, PageHeader, Skeleton, ErrorState } from '../components/ui/Primitives.jsx';
import { useReportsStore } from '../store/reportsStore.js';
import { useAuthStore } from '../store/authStore.js';
import { pickLocalized } from '../utils/i18n.js';
import { formatMoney } from '../utils/money.js';

const Reports = () => {
  const { report, isLoading, error, fetchReport } = useReportsStore();
  const locale = useAuthStore((s) => s.locale);
  const t = (en, ar) => pickLocalized({ en, ar }, locale);

  useEffect(() => { fetchReport(); }, [fetchReport]);

  if (isLoading || !report) return <Skeleton lines={8} />;
  if (error) {
    return (
      <ErrorState
        title={t('Failed to load reports', 'فشل تحميل التقارير')}
        message={error.message}
        requestId={error.request_id}
      />
    );
  }

  const kpis = report.kpis ?? {};
  const last14 = (report.daily_breakdown ?? []).slice(-14);
  const maxRevpar = Math.max(...last14.map((d) => d.revpar), 1);
  const totalSourceRevenue = (report.sources ?? []).reduce((sum, s) => sum + s.revenue, 0) || 1;

  return (
    <>
      <PageHeader
        title={t('GM Reports', 'تقارير المدير العام')}
        subtitle={t('Period: ' + report.period, 'الفترة: ' + report.period)}
        actions={<Badge variant="neutral">{t('Live', 'مباشر')}</Badge>}
      />

      <div className="rpt-kpi-grid">
        <Card padded>
          <div className="rpt-kpi-value">{Math.round((kpis.occupancy_rate ?? 0) * 100)}%</div>
          <div className="rpt-kpi-label">{t('Occupancy', 'الإشغال')}</div>
        </Card>
        <Card padded>
          <div className="rpt-kpi-value">{formatMoney(kpis.adr)}</div>
          <div className="rpt-kpi-label">{t('ADR', 'متوسط سعر الغرفة')}</div>
        </Card>
        <Card padded>
          <div className="rpt-kpi-value">{formatMoney(kpis.revpar)}</div>
          <div className="rpt-kpi-label">{t('RevPAR', 'الإيراد لكل غرفة')}</div>
        </Card>
        <Card padded>
          <div className="rpt-kpi-value">{formatMoney(kpis.revenue_today)}</div>
          <div className="rpt-kpi-label">{t('Revenue Today', 'إيرادات اليوم')}</div>
        </Card>
        <Card padded>
          <div className="rpt-kpi-value">{formatMoney(kpis.revenue_mtd)}</div>
          <div className="rpt-kpi-label">{t('Revenue MTD', 'إيرادات الشهر')}</div>
        </Card>
        <Card padded>
          <div className="rpt-kpi-value">{formatMoney(kpis.revenue_ytd)}</div>
          <div className="rpt-kpi-label">{t('Revenue YTD', 'إيرادات السنة')}</div>
        </Card>
      </div>

      <Card className="data-card rpt-section-card">
        <h2 className="rpt-section-title">
          <TrendingUp size={15} />
          {t('Daily RevPAR — Last 14 Days', 'RevPAR اليومي — آخر 14 يوم')}
        </h2>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: '4px', height: '100px', marginBottom: '28px' }}>
          {last14.map((d) => {
            const barH = Math.max(4, Math.round((d.revpar / maxRevpar) * 96));
            return (
              <div key={d.date} style={{ flex: 1, position: 'relative' }}>
                <div
                  style={{ width: '100%', height: `${barH}px`, background: '#08414C', borderRadius: '3px 3px 0 0' }}
                  title={`${d.date}: ${formatMoney(d.revpar)}`}
                />
                <span style={{ position: 'absolute', top: '100%', left: 0, right: 0, textAlign: 'center', fontSize: '10px', color: '#7A8B8E', fontVariantNumeric: 'tabular-nums', lineHeight: '20px' }}>
                  {d.date.slice(8)}
                </span>
              </div>
            );
          })}
        </div>
      </Card>

      <Card className="data-card rpt-section-card">
        <h2 className="rpt-section-title">
          <BarChart2 size={15} />
          {t('Top Room Types', 'أفضل أنواع الغرف')}
        </h2>
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                <th>{t('Room Type', 'نوع الغرفة')}</th>
                <th>{t('Revenue', 'الإيراد')}</th>
                <th>{t('Nights', 'الليالي')}</th>
                <th>{t('Occupancy', 'الإشغال')}</th>
              </tr>
            </thead>
            <tbody>
              {(report.top_room_types ?? []).map((rt) => (
                <tr key={rt.room_type_name}>
                  <td>{rt.room_type_name}</td>
                  <td>{formatMoney(rt.revenue)}</td>
                  <td>{rt.nights}</td>
                  <td>{Math.round(rt.occupancy_rate * 100)}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Card className="data-card rpt-section-card">
        <h2 className="rpt-section-title">
          {t('Revenue by Source', 'الإيرادات حسب المصدر')}
        </h2>
        <div className="rpt-source-list">
          {(report.sources ?? []).map((s) => {
            const pct = Math.round((s.revenue / totalSourceRevenue) * 100);
            return (
              <div key={s.source} className="rpt-source-row">
                <div className="rpt-source-meta">
                  <span className="rpt-source-name">{s.source.replace(/_/g, ' ')}</span>
                  <span className="rpt-source-revenue">{formatMoney(s.revenue)} ({pct}%)</span>
                </div>
                <div className="rpt-source-bar">
                  <div className="rpt-source-fill" style={{ width: `${pct}%` }} />
                </div>
              </div>
            );
          })}
        </div>
      </Card>
    </>
  );
};

export default Reports;
