import { addMinutesUtc } from '../../utils/date.js';
import { cloneMockData } from '../envelope.js';

const baseDay = '2026-06-28T00:00:00.000Z';

const WEEKEND_DAYS = new Set([4, 5, 11, 12]);
const FLASH_SALE_DAYS = new Set([8, 9]);
const PREMIUM_SUITE_UNAVAILABLE = new Set([3, 4]);

const ROOM_TYPES = [
  { id: 'rt_deluxe_king',      name: 'Deluxe King',      base_rate: 180 },
  { id: 'rt_deluxe_twin',      name: 'Deluxe Twin',       base_rate: 160 },
  { id: 'rt_premium_suite',    name: 'Premium Suite',     base_rate: 230 },
  { id: 'rt_executive_suite',  name: 'Executive Suite',   base_rate: 295 },
];

function buildDates() {
  return Array.from({ length: 14 }, (_, i) =>
    addMinutesUtc(baseDay, i * 1440).slice(0, 10)
  );
}

function buildRates(roomType, dates) {
  return dates.map((date, i) => {
    let rate = roomType.base_rate;

    if (WEEKEND_DAYS.has(i)) {
      rate = Math.round(rate * 1.20);
    }

    const isFlashSale = roomType.id === 'rt_deluxe_twin' && FLASH_SALE_DAYS.has(i);
    if (isFlashSale) {
      rate = Math.round(rate * 0.85);
    }

    let available = true;
    if (roomType.id === 'rt_deluxe_twin' && FLASH_SALE_DAYS.has(i)) {
      available = true;
    }
    if (roomType.id === 'rt_premium_suite' && PREMIUM_SUITE_UNAVAILABLE.has(i)) {
      available = false;
    }

    return { date, rate, available };
  });
}

const dates = buildDates();

const rateGridSeed = ROOM_TYPES.map((rt) => ({
  room_type_id: rt.id,
  room_type_name: rt.name,
  rates: buildRates(rt, dates),
}));

let rateGrid = cloneMockData(rateGridSeed);

export function getRateGrid() {
  return cloneMockData(rateGrid);
}

export function getRateForDate(room_type_id, date_string) {
  const roomType = rateGrid.find((rt) => rt.room_type_id === room_type_id);
  if (!roomType) return null;
  return roomType.rates.find((r) => r.date === date_string) || null;
}

export function resetRatesMockData() {
  rateGrid = cloneMockData(rateGridSeed);
}
