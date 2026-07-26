import { useEffect, useState } from 'react';
import { Banknote, Building2, CalendarCheck, CreditCard, DoorOpen, LogIn, Moon } from 'lucide-react';
import { Badge, Button, Card, PageHeader, Skeleton } from '../components/ui/Primitives.jsx';
import { useFrontDeskStore } from '../store/frontDeskStore.js';
import { formatMoney } from '../utils/money.js';
import { formatDate, formatTime } from '../utils/date.js';

const VIP_BADGE = { platinum: 'warning', gold: 'info' };

const SOURCE_LABEL = {
  direct: 'Direct',
  booking_com: 'Booking.com',
  phone: 'Phone',
  corporate: 'Corporate',
};

const PAYMENT_METHODS = [
  { id: 'cash', label: 'Cash', Icon: Banknote },
  { id: 'credit_card', label: 'Credit card', Icon: CreditCard },
  { id: 'bank_transfer', label: 'Bank transfer', Icon: Building2 },
];

const ARRIVAL_LANES = [
  {
    lane: 'Rooms',
    steps: ['Block room', 'Inspect room', 'Release for check-in'],
  },
  {
    lane: 'Front Desk',
    steps: ['Verify ID', 'Collect deposit', 'Issue key'],
  },
  {
    lane: 'Guest Care',
    steps: ['Greet guest', 'Assist luggage', 'Escort to room'],
  },
  {
    lane: 'Control',
    steps: ['Check VIP requests', 'Flag special needs'],
  },
];

function ArrivalChecklist({ reservationId }) {
  const storageKey = `arrival_checklist_${reservationId}`;
  const [checked, setChecked] = useState(() => {
    try { return JSON.parse(sessionStorage.getItem(storageKey)) || {}; } catch { return {}; }
  });

  const toggle = (key) => {
    setChecked((prev) => {
      const next = { ...prev, [key]: !prev[key] };
      try { sessionStorage.setItem(storageKey, JSON.stringify(next)); } catch { /* ignore */ }
      return next;
    });
  };

  const total = ARRIVAL_LANES.reduce((n, l) => n + l.steps.length, 0);
  const done = Object.values(checked).filter(Boolean).length;

  return (
    <div className="fd-checklist">
      <div className="fd-checklist-header">
        <span className="fd-checklist-title">Parallel arrival checklist</span>
        <span className="fd-checklist-progress">{done}/{total}</span>
      </div>
      <div className="fd-checklist-lanes">
        {ARRIVAL_LANES.map((l) => (
          <div key={l.lane} className="fd-checklist-lane">
            <span className="fd-checklist-lane-label">{l.lane}</span>
            {l.steps.map((step) => {
              const key = `${l.lane}:${step}`;
              return (
                <label key={key} className={`fd-checklist-item${checked[key] ? ' is-done' : ''}`}>
                  <input
                    type="checkbox"
                    checked={!!checked[key]}
                    onChange={() => toggle(key)}
                  />
                  <span>{step}</span>
                </label>
              );
            })}
          </div>
        ))}
      </div>
    </div>
  );
}

function ArrivalCard({ reservation }) {
  const {
    expandedId,
    selectedRoom,
    actionLoading,
    roomsForId,
    setExpanded,
    setSelectedRoom,
    checkIn,
  } = useFrontDeskStore();

  const id = reservation.id;
  const isExpanded = expandedId === id;
  const rooms = roomsForId[id] ?? [];
  const vipLevel = reservation.guest?.vip_level;

  return (
    <div className={`fd-guest-card${isExpanded ? ' is-expanded' : ''}`}>
      <div className="fd-card-body">
        <div className="fd-card-top">
          <div className="fd-card-identity">
            <span className="fd-card-name">{reservation.guest?.full_name}</span>
            {reservation.guest?.full_name_ar && (
              <span className="fd-card-name-ar" dir="rtl">
                {reservation.guest.full_name_ar}
              </span>
            )}
          </div>
          <div className="fd-card-badges">
            {vipLevel && vipLevel !== 'standard' && (
              <Badge variant={VIP_BADGE[vipLevel] || 'neutral'}>{vipLevel}</Badge>
            )}
            {reservation.source && (
              <Badge variant="neutral">
                {SOURCE_LABEL[reservation.source] || reservation.source}
              </Badge>
            )}
          </div>
        </div>
        <div className="fd-card-meta">
          <span>
            <strong>{reservation.room_type?.name}</strong>
          </span>
          <span>
            <Moon size={11} />
            {' '}
            <strong>{reservation.nights}</strong>
            {' '}
            night{reservation.nights !== 1 ? 's' : ''}
          </span>
          <span>
            <strong>{reservation.reservation_code}</strong>
          </span>
        </div>
      </div>
      <div className="fd-card-foot">
        {reservation.arrival_at ? (
          <span className="fd-arrival-time">Expected {formatTime(reservation.arrival_at)}</span>
        ) : (
          <span />
        )}
        <Button variant="ghost" onClick={() => setExpanded(id)}>
          <LogIn size={14} />
          Assign room &amp; check in
        </Button>
      </div>
      {isExpanded && (
        <div className="fd-expand">
          <ArrivalChecklist reservationId={id} />
          <span className="fd-expand-label">Select room</span>
          {rooms.length > 0 ? (
            <div className="fd-room-options">
              {rooms.map((room) => (
                <button
                  key={room.id}
                  type="button"
                  className={`fd-room-option${selectedRoom[id] === room.id ? ' is-selected' : ''}`}
                  onClick={() => setSelectedRoom(id, room.id)}
                >
                  <span className="fd-room-option-num">{room.number}</span>
                  <span className="fd-room-option-type">{room.room_type?.name}</span>
                </button>
              ))}
            </div>
          ) : (
            <span className="fd-card-meta">No available rooms matching room type</span>
          )}
          <div className="fd-expand-actions">
            <Button
              disabled={!selectedRoom[id] || !!actionLoading[id]}
              isLoading={!!actionLoading[id]}
              onClick={() => checkIn(id)}
            >
              Confirm check-in
            </Button>
            <Button variant="ghost" onClick={() => setExpanded(null)}>
              Cancel
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

function DueOutCard({ reservation }) {
  const {
    expandedId,
    selectedPayment,
    actionLoading,
    setExpanded,
    setSelectedPayment,
    checkOut,
  } = useFrontDeskStore();

  const id = reservation.id;
  const isExpanded = expandedId === id;
  const hasBalance = reservation.folio_balance > 0;
  const vipLevel = reservation.guest?.vip_level;

  return (
    <div className="fd-guest-card due-out">
      <div className="fd-card-body">
        <div className="fd-card-top">
          <div className="fd-card-identity">
            <span className="fd-card-name">{reservation.guest?.full_name}</span>
            {reservation.guest?.full_name_ar && (
              <span className="fd-card-name-ar" dir="rtl">
                {reservation.guest.full_name_ar}
              </span>
            )}
          </div>
          <div className="fd-card-badges">
            {vipLevel && vipLevel !== 'standard' && (
              <Badge variant={VIP_BADGE[vipLevel] || 'neutral'}>{vipLevel}</Badge>
            )}
            {reservation.assigned_room && (
              <Badge variant="neutral">Room {reservation.assigned_room.number}</Badge>
            )}
          </div>
        </div>
        <div className="fd-card-meta">
          <span>
            <strong>{reservation.room_type?.name}</strong>
          </span>
          <span>
            <Moon size={11} />
            {' '}
            <strong>{reservation.nights}</strong>
            {' '}
            night{reservation.nights !== 1 ? 's' : ''}
          </span>
          <span>
            <strong>{reservation.reservation_code}</strong>
          </span>
          {reservation.departure_at && (
            <span>Departs {formatTime(reservation.departure_at)}</span>
          )}
        </div>
      </div>
      {hasBalance && (
        <div className="fd-folio-strip">
          <span className="fd-folio-label">Outstanding</span>
          <span className="fd-folio-amount">{formatMoney(reservation.folio_balance)}</span>
        </div>
      )}
      <div className="fd-card-foot">
        <span />
        <Button variant="ghost" onClick={() => setExpanded(id)}>
          <DoorOpen size={14} />
          {hasBalance ? 'Settle & check out' : 'Check out'}
        </Button>
      </div>
      {isExpanded && (
        <div className="fd-expand">
          {hasBalance && (
            <>
              <span className="fd-expand-label">Payment method</span>
              <div className="fd-payment-options">
                {PAYMENT_METHODS.map(({ id: pmId, label, Icon }) => (
                  <label
                    key={pmId}
                    className={`fd-payment-option${selectedPayment[id] === pmId ? ' is-selected' : ''}`}
                  >
                    <input
                      type="radio"
                      name={`payment_${id}`}
                      value={pmId}
                      checked={selectedPayment[id] === pmId}
                      onChange={() => setSelectedPayment(id, pmId)}
                    />
                    <Icon size={18} />
                    <span>{label}</span>
                  </label>
                ))}
              </div>
            </>
          )}
          <div className="fd-expand-actions">
            <Button
              disabled={(hasBalance && !selectedPayment[id]) || !!actionLoading[id]}
              isLoading={!!actionLoading[id]}
              onClick={() => checkOut(id)}
            >
              Complete check-out
            </Button>
            <Button variant="ghost" onClick={() => setExpanded(null)}>
              Cancel
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

function RoomBoard() {
  const { roomBoard, boardSummary, isLoadingBoard, markRoomClean } = useFrontDeskStore();

  if (isLoadingBoard) return <Skeleton lines={4} />;

  const byFloor = roomBoard.reduce((acc, room) => {
    const floor = room.floor ?? Math.floor(Number(room.number) / 100);
    if (!acc.has(floor)) acc.set(floor, []);
    acc.get(floor).push(room);
    return acc;
  }, new Map());

  const floors = [...byFloor.keys()].sort((a, b) => b - a);

  const chipClass = (status) => {
    if (status === 'available') return 'avl';
    if (status === 'occupied') return 'occ';
    return 'cln';
  };

  return (
    <div className="fd-board-card">
      <p className="fd-board-title">Room Board</p>
      {boardSummary && (
        <p className="fd-card-meta">
          {boardSummary.available} of {boardSummary.total} available
        </p>
      )}
      <div className="fd-board-floors">
        {floors.map((floor) => (
          <div key={floor} className="fd-floor">
            <span className="fd-floor-label">Floor {floor}</span>
            <div className="fd-floor-rooms">
              {byFloor.get(floor).map((room) =>
                room.status === 'cleaning' ? (
                  <div
                    key={room.id}
                    className="fd-room-chip cln"
                    onClick={() => markRoomClean(room.id)}
                    title="Click to mark clean"
                    style={{ cursor: 'pointer' }}
                  >
                    <span className="fd-chip-num">{room.number}</span>
                    <span className="fd-chip-type">{room.room_type?.abbr}</span>
                    <span style={{ fontSize: '10px', opacity: 0.7 }}>✓</span>
                  </div>
                ) : (
                  <div key={room.id} className={`fd-room-chip ${chipClass(room.status)}`}>
                    <span className="fd-chip-num">{room.number}</span>
                    <span className="fd-chip-type">{room.room_type?.abbr}</span>
                    {room.status === 'occupied' && room.occupant?.name && (
                      <span className="fd-chip-guest">
                        {room.occupant.name.split(' ')[0]}
                      </span>
                    )}
                  </div>
                )
              )}
            </div>
          </div>
        ))}
      </div>
      <div className="fd-board-legend">
        <span className="fd-legend-item">
          <span className="fd-legend-dot avl" />
          Available
        </span>
        <span className="fd-legend-item">
          <span className="fd-legend-dot occ" />
          Occupied
        </span>
        <span className="fd-legend-item">
          <span className="fd-legend-dot cln" />
          Cleaning
        </span>
      </div>
    </div>
  );
}

const FrontDesk = () => {
  const {
    arrivals,
    dueOuts,
    boardSummary,
    isLoadingArrivals,
    isLoadingDueOuts,
    fetchArrivals,
    fetchDueOuts,
    fetchRoomBoard,
  } = useFrontDeskStore();

  useEffect(() => {
    Promise.all([fetchArrivals(), fetchDueOuts(), fetchRoomBoard()]);
  }, [fetchArrivals, fetchDueOuts, fetchRoomBoard]);

  const isLoading = isLoadingArrivals || isLoadingDueOuts;

  if (isLoading) return <Skeleton lines={6} />;

  const today = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });

  return (
    <div>
      <PageHeader
        title="Front Desk"
        subtitle={`Damascus property · ${today}`}
        actions={
          <div className="res-detail-badges">
            <Badge variant="info">{arrivals.length} arrivals</Badge>
            <Badge variant="warning">{dueOuts.length} due out</Badge>
          </div>
        }
      />

      <div className="fd-strip">
        <div className="fd-strip-item">
          <span className="fd-strip-label">Arrivals today</span>
          <span className="fd-strip-value">{arrivals.length}</span>
        </div>
        <div className="fd-strip-sep" />
        <div className="fd-strip-item">
          <span className="fd-strip-label">Due out</span>
          <span className={`fd-strip-value${dueOuts.length > 0 ? ' warn' : ''}`}>
            {dueOuts.length}
          </span>
        </div>
        <div className="fd-strip-sep" />
        <div className="fd-strip-item">
          <span className="fd-strip-label">Available rooms</span>
          <span className="fd-strip-value">{boardSummary?.available ?? '—'}</span>
        </div>
        <div className="fd-strip-sep" />
        <div className="fd-strip-item">
          <span className="fd-strip-label">Cleaning</span>
          <span className="fd-strip-value">{boardSummary?.cleaning ?? '—'}</span>
        </div>
      </div>

      <div className="fd-grid">
        <div className="fd-left">
          <div>
            <div className="fd-section-head">
              <span className="fd-section-title">
                <CalendarCheck size={16} />
                Arrivals
              </span>
            </div>
            {arrivals.length === 0 ? (
              <div className="fd-empty-panel">All arrivals are settled for today</div>
            ) : (
              <div className="fd-cards">
                {arrivals.map((r) => (
                  <ArrivalCard key={r.id} reservation={r} />
                ))}
              </div>
            )}
          </div>

          <div>
            <div className="fd-section-head">
              <span className="fd-section-title">
                <DoorOpen size={16} />
                Due Outs
              </span>
            </div>
            {dueOuts.length === 0 ? (
              <div className="fd-empty-panel">No pending departures</div>
            ) : (
              <div className="fd-cards">
                {dueOuts.map((r) => (
                  <DueOutCard key={r.id} reservation={r} />
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="fd-right">
          <RoomBoard />
        </div>
      </div>
    </div>
  );
};

export default FrontDesk;
