import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Badge, Card, Field, Input, PageHeader, Select, Table } from '../components/ui/Primitives.jsx';
import { useReservationStore } from '../store/reservationStore.js';
import { formatDate } from '../utils/date.js';
import { formatMoney } from '../utils/money.js';

const statusVariant = {
  arriving_today: 'info',
  checked_in: 'success',
  due_out: 'warning',
  upcoming: 'neutral',
  departed: 'neutral',
};

const formatStatus = (status) => status.replaceAll('_', ' ');

const Reservations = () => {
  const navigate = useNavigate();
  const { reservations, meta, fetchReservations } = useReservationStore();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');

  useEffect(() => {
    const timer = setTimeout(() => fetchReservations({ query: search, status, per_page: 10 }), 220);
    return () => clearTimeout(timer);
  }, [fetchReservations, search, status]);

  return (
    <>
      <PageHeader
        title="Reservations"
        subtitle="Inspect stays, arrival readiness, and guest handoff details."
        actions={meta && <Badge variant="neutral">{meta.total} stays in view</Badge>}
      />
      <Card className="data-card">
        <div className="toolbar">
          <div className="field-row">
            <Field label="Search">
              <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Guest or booking code" />
            </Field>
            <Field label="Status">
              <Select value={status} onChange={(event) => setStatus(event.target.value)}>
                <option value="all">All statuses</option>
                <option value="arriving_today">Arriving today</option>
                <option value="checked_in">Checked in</option>
                <option value="due_out">Due out</option>
                <option value="upcoming">Upcoming</option>
              </Select>
            </Field>
          </div>
          <span className="table-note">Desk records · UTC converted locally</span>
        </div>
        <Table
          rows={reservations}
          onRowClick={(row) => navigate(`/reservations/${row.id}`)}
          columns={[
            { key: 'reservation_code', label: 'Code' },
            { key: 'guest', label: 'Guest', render: (row) => row.guest.full_name },
            { key: 'assigned_room', label: 'Room', render: (row) => row.assigned_room?.number ?? '—' },
            { key: 'arrival_at', label: 'Arrival', render: (row) => formatDate(row.arrival_at) },
            { key: 'departure_at', label: 'Departure', render: (row) => formatDate(row.departure_at) },
            { key: 'status', label: 'Status', render: (row) => <Badge variant={statusVariant[row.status] || 'neutral'}>{formatStatus(row.status)}</Badge> },
            { key: 'total_amount', label: 'Total', render: (row) => formatMoney(row.total_amount) },
          ]}
        />
      </Card>
    </>
  );
};

export default Reservations;
