import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button, EmptyState, ErrorState, PageHeader, Skeleton } from '../components/ui/Primitives.jsx';
import { useAvailabilityStore } from '../store/availabilityStore.js';

const DAY_ABBR = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function shiftDateIso(dateStr, days) {
  const [y, m, d] = dateStr.split('-').map(Number);
  const base = new Date(Date.UTC(y, m - 1, d));
  base.setUTCDate(base.getUTCDate() + days);
  const ny = base.getUTCFullYear();
  const nm = String(base.getUTCMonth() + 1).padStart(2, '0');
  const nd = String(base.getUTCDate()).padStart(2, '0');
  return `${ny}-${nm}-${nd}`;
}

function parseDateLabel(dateStr) {
  const [y, m, d] = dateStr.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d));
  return {
    dayName: DAY_ABBR[dt.getUTCDay()],
    dayNum: dt.getUTCDate(),
    monthName: MONTH_ABBR[dt.getUTCMonth()],
    year: dt.getUTCFullYear(),
  };
}

function formatDateRange(startStr, endStr) {
  const s = parseDateLabel(startStr);
  const e = parseDateLabel(endStr);
  return `${s.monthName} ${s.dayNum} — ${e.monthName} ${e.dayNum}, ${e.year}`;
}

function cellVariant(available, total) {
  if (total === 0) return 'avl-cell';
  if (available === 0) return 'avl-cell zero';
  if (available < total) return 'avl-cell partial';
  return 'avl-cell full';
}

const AvailabilityCalendar = () => {
  const { grid, isLoading, error, startDate, fetchGrid, setStartDate } = useAvailabilityStore();
  const [popover, setPopover] = useState(null);
  const today = todayIso();

  useEffect(() => {
    fetchGrid();
  }, [fetchGrid]);

  useEffect(() => {
    const close = () => setPopover(null);
    window.addEventListener('click', close);
    window.addEventListener('scroll', close, true);
    return () => {
      window.removeEventListener('click', close);
      window.removeEventListener('scroll', close, true);
    };
  }, []);

  const togglePopover = (typeId, day, cellData, event) => {
    event.stopPropagation();
    const rect = event.currentTarget.getBoundingClientRect();
    setPopover((prev) =>
      prev?.typeId === typeId && prev?.date === day.date
        ? null
        : { typeId, date: day.date, rect, ...cellData }
    );
  };

  const handlePrev = () => setStartDate(shiftDateIso(startDate, -7));
  const handleNext = () => setStartDate(shiftDateIso(startDate, 7));

  const dateColumns = grid?.days ?? [];
  const roomTypes = dateColumns[0]?.types?.map((t) => ({ id: t.type_id, name: t.type_name })) ?? [];

  const cellMap = {};
  dateColumns.forEach((day) => {
    day.types.forEach((t) => {
      if (!cellMap[t.type_id]) cellMap[t.type_id] = {};
      cellMap[t.type_id][day.date] = t;
    });
  });

  const endDate = dateColumns.length
    ? dateColumns[dateColumns.length - 1].date
    : shiftDateIso(startDate, 13);

  const colTemplate = `190px repeat(${dateColumns.length || 14}, minmax(50px, 1fr))`;

  return (
    <div className="avl-page">
      <PageHeader
        title="Availability"
        subtitle="14-day room type availability grid"
        actions={
          <div className="avl-controls">
            <Button variant="ghost" onClick={handlePrev} aria-label="Previous week">
              <ChevronLeft size={16} />
            </Button>
            <span className="avl-date-range">{formatDateRange(startDate, endDate)}</span>
            <Button variant="ghost" onClick={handleNext} aria-label="Next week">
              <ChevronRight size={16} />
            </Button>
          </div>
        }
      />

      {isLoading && <Skeleton lines={8} />}

      {!isLoading && error && (
        <ErrorState
          title="Failed to load availability"
          message={typeof error === 'string' ? error : error?.message}
          requestId={error?.request_id}
        />
      )}

      {!isLoading && !error && !grid && (
        <EmptyState
          title="No availability data"
          message="Try shifting to a different date range."
        />
      )}

      {!isLoading && !error && grid && (
        <div className="avl-grid-wrap">
          <div className="avl-grid" style={{ gridTemplateColumns: colTemplate }}>

            <div className="avl-header-cell avl-type-label">Room Type</div>

            {dateColumns.map((day) => {
              const { dayName, dayNum } = parseDateLabel(day.date);
              const isToday = day.date === today;
              return (
                <div
                  key={day.date}
                  className={`avl-header-cell${isToday ? ' avl-header-today' : ''}`}
                >
                  <span className="avl-day-name">{dayName}</span>
                  <span className="avl-day-num">{dayNum}</span>
                  {isToday && <span className="avl-today-label">Today</span>}
                </div>
              );
            })}

            {roomTypes.map((type) => (
              <div key={type.id} className="avl-row">
                <div className="avl-type-label">{type.name}</div>
                {dateColumns.map((day) => {
                  const cellData = cellMap[type.id]?.[day.date];
                  if (!cellData) return <div key={day.date} className="avl-cell" />;

                  const isToday = day.date === today;
                  const isActive = popover?.typeId === type.id && popover?.date === day.date;
                  const variant = cellVariant(cellData.available, cellData.total);

                  return (
                    <div
                      key={day.date}
                      className={`${variant}${isToday ? ' today' : ''}${isActive ? ' active' : ''}`}
                      role="button"
                      tabIndex={0}
                      aria-label={`${type.name} on ${day.date}: ${cellData.available} of ${cellData.total} available`}
                      onClick={(e) => togglePopover(type.id, day, cellData, e)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                          e.preventDefault();
                          togglePopover(type.id, day, cellData, e);
                        }
                      }}
                    >
                      <span className="avl-cell-count">{cellData.available}</span>
                    </div>
                  );
                })}
              </div>
            ))}

          </div>
        </div>
      )}

      {popover && (
        <div
          className="avl-popover"
          role="tooltip"
          style={{
            top: popover.rect.bottom + 6,
            left: popover.rect.left + popover.rect.width / 2,
          }}
          onClick={(e) => e.stopPropagation()}
        >
          <div className="avl-popover-title">{popover.type_name} · {popover.date}</div>
          <div className="avl-popover-row">
            <span>Total rooms</span>
            <strong>{popover.total}</strong>
          </div>
          <div className="avl-popover-row">
            <span>Occupied</span>
            <strong>{popover.occupied}</strong>
          </div>
          <div className="avl-popover-row avl-popover-available">
            <span>Available</span>
            <strong>{popover.available}</strong>
          </div>
        </div>
      )}
    </div>
  );
};

export default AvailabilityCalendar;
