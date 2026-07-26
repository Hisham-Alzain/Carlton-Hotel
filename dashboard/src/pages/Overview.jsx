import { useEffect } from 'react';
import { ArrowRight, CalendarCheck, CircleDollarSign, ConciergeBell, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge, Button, Card, PageHeader, Skeleton } from '../components/ui/Primitives.jsx';
import RequirePermission from '../components/RequirePermission.jsx';
import { useAuthStore } from '../store/authStore.js';
import { useDashboardStore } from '../store/dashboardStore.js';
import { formatMoney } from '../utils/money.js';

const iconByCard = {
  arrivals: CalendarCheck,
  departures: ArrowRight,
  live_queue: ConciergeBell,
  folio_alerts: CircleDollarSign,
};

const Overview = () => {
  const { summary, fetchSummary, isLoading } = useDashboardStore();
  const user = useAuthStore((state) => state.user);

  useEffect(() => {
    fetchSummary();
  }, [fetchSummary]);

  if (isLoading || !summary) return <Skeleton lines={6} />;

  return (
    <>
      <section className="overview-hero">
        <div>
          <p className="panel-kicker">Carlton Command</p>
          <h1>Operations overview</h1>
          <p>A quiet command surface for today&apos;s arrivals, guest care, queue movement, and folio attention.</p>
        </div>
        <div className="hero-signal">
          <Sparkles size={18} />
          <span>{user?.type === 'super_admin' ? 'All access' : 'Permission scoped'}</span>
        </div>
      </section>

      <div className="grid stats">
        {(summary.cards || []).map((card) => {
          const Icon = iconByCard[card.id] || ConciergeBell;
          return (
          <Card className={`stat-card tone-${card.tone || 'info'}`} key={card.id}>
            <div className="stat-meta">
              <span>{card.label}</span>
              <Icon size={18} />
            </div>
            <p className="stat-value">{card.value}</p>
          </Card>
          );
        })}
        <Card className="stat-card">
          <div className="stat-meta">
            <span>Occupancy</span>
            <Badge variant="neutral">{summary.occupancy?.occupied_rooms || 0} rooms</Badge>
          </div>
          <p className="stat-value">{Math.round((summary.occupancy?.occupancy_rate || 0) * 100)}%</p>
        </Card>
      </div>

      <div className="operations-strip">
        <span>Property day</span>
        <strong>{summary.property_day}</strong>
        <span>Occupied rooms</span>
        <strong>{summary.occupancy?.occupied_rooms || 0}</strong>
        <span>Available rooms</span>
        <strong>{summary.occupancy?.available_rooms || 0}</strong>
      </div>

      <RequirePermission permission="reports.view">
        <div className="operations-strip">
          <span>ADR</span>
          <strong>{summary.kpis ? formatMoney(summary.kpis.adr) : '—'}</strong>
          <span>RevPAR</span>
          <strong>{summary.kpis ? formatMoney(summary.kpis.revpar) : '—'}</strong>
          <span>Revenue today</span>
          <strong>{summary.kpis ? formatMoney(summary.kpis.revenue_today) : '—'}</strong>
        </div>
      </RequirePermission>

      <PageHeader title="Quick actions" />

      <div className="grid two">
        <RequirePermission permission="reservations.view">
          <Card className="action-card" padded>
            <PageHeader title="Front Desk" subtitle="Manage today's arrivals, departures, and room assignments." />
            <Link to="/front-desk" className="button secondary">Open Front Desk</Link>
          </Card>
        </RequirePermission>
        <RequirePermission anyOf={['queue.view', 'service_requests.view', 'tickets.view']}>
          <Card className="action-card live" padded>
            <PageHeader title="Live Queue" subtitle="Monitor and action live guest requests across all departments." />
            <Link to="/operations/queue"><Button>View live queue</Button></Link>
          </Card>
        </RequirePermission>
      </div>
    </>
  );
};

export default Overview;
