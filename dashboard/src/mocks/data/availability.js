import { ROOM_TYPES, ROOMS, getReservationRecords } from './reservations.js';

const ACTIVE_STATUSES = new Set(['checked_in', 'due_out', 'arriving_today']);

function utcDateToStr(date) {
  const y = date.getUTCFullYear();
  const m = String(date.getUTCMonth() + 1).padStart(2, '0');
  const d = String(date.getUTCDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function parseUtcDateStr(dateStr) {
  const [y, mo, d] = dateStr.split('-').map(Number);
  return Date.UTC(y, mo - 1, d);
}

export function getAvailabilityGrid({ start_date, days } = {}) {
  const startDateStr = start_date || utcDateToStr(new Date());
  const numDays = Math.max(1, parseInt(days, 10) || 14);
  const baseMs = parseUtcDateStr(startDateStr);

  const reservations = getReservationRecords();

  const daysArray = [];

  for (let i = 0; i < numDays; i++) {
    const dateMs = baseMs + i * 86400000;
    const dateStr = utcDateToStr(new Date(dateMs));

    const types = ROOM_TYPES.map((roomType) => {
      const total = ROOMS.filter((r) => r.room_type_id === roomType.id).length;

      const occupied = reservations.filter((res) => {
        if (!ACTIVE_STATUSES.has(res.status)) return false;

        const arrivalDate = res.arrival_at.slice(0, 10);
        const departureDate = res.departure_at.slice(0, 10);

        if (!(arrivalDate <= dateStr && dateStr < departureDate)) return false;

        const typeId = res.assigned_room?.room_type_id || res.room_type_id;
        return typeId === roomType.id;
      }).length;

      return {
        type_id: roomType.id,
        type_name: roomType.name,
        total,
        occupied,
        available: Math.max(0, total - occupied),
      };
    });

    daysArray.push({ date: dateStr, types });
  }

  return { start_date: startDateStr, days: daysArray };
}
