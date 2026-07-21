import { cloneMockData } from '../envelope.js';

const daily_breakdown = [];
for (let i = 0; i < 28; i++) {
  const day = String(i + 1).padStart(2, '0');
  const date = `2026-06-${day}`;
  const rawOccupancy = 0.45 + (Math.sin(i * 0.7) * 0.2 + 0.1);
  const occupancy_rate = Math.min(0.95, Math.max(0.3, rawOccupancy));
  const adr = Math.round(180 + Math.sin(i * 0.5) * 40);
  const revpar = Math.round(adr * occupancy_rate);
  const revenue = Math.round(revpar * 11);
  daily_breakdown.push({ date, occupancy_rate, adr, revpar, revenue });
}

const reportData = {
  period: '2026-06-01 to 2026-06-28',
  kpis: {
    occupancy_rate: 0.72,
    occupied_rooms: 8,
    total_rooms: 11,
    adr: 218,
    revpar: 157,
    revenue_today: 1745,
    revenue_mtd: 48600,
    revenue_ytd: 312000,
    currency: 'USD',
  },
  daily_breakdown,
  top_room_types: [
    { room_type_name: 'Executive Suite', revenue: 8400, nights: 28, occupancy_rate: 0.84 },
    { room_type_name: 'Premium Suite', revenue: 6900, nights: 30, occupancy_rate: 0.71 },
    { room_type_name: 'Deluxe King', revenue: 18000, nights: 100, occupancy_rate: 0.69 },
    { room_type_name: 'Deluxe Twin', revenue: 11200, nights: 70, occupancy_rate: 0.61 },
  ],
  sources: [
    { source: 'direct', count: 42, revenue: 19800 },
    { source: 'booking_com', count: 38, revenue: 17100 },
    { source: 'corporate', count: 21, revenue: 9400 },
    { source: 'phone', count: 11, revenue: 4800 },
  ],
};

export function getReport(_params) {
  return cloneMockData(reportData);
}

export function resetReportMockData() {}
