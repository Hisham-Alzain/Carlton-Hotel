const now = Date.now();

export const summary = {
  arrivals_today: 18,
  departures_today: 11,
  occupancy_rate: 82,
  open_requests: 9,
  open_tickets: 6,
  pending_folios: 4,
};

export const reservations = [
  {
    uuid: 'res-7k2m9x',
    booking_code: 'CARL-7K2M9X',
    source: 'direct',
    guest: { uuid: 'guest-001', name: 'Lina Al Masri', phone: '+963944100001' },
    check_in: '2026-07-12',
    check_out: '2026-07-14',
    status: 'confirmed',
    payment_method: 'on_arrival',
    total_usd: 240,
    rooms: [{ reservation_room_id: 'rr-1', room_type: 'Double Deluxe', room: null, price_usd: 120 }],
  },
  {
    uuid: 'res-2q4z8p',
    booking_code: 'CARL-2Q4Z8P',
    source: 'walk_in',
    guest: { uuid: 'guest-002', name: 'Samer Khalil', phone: '+963933202020' },
    check_in: '2026-07-12',
    check_out: '2026-07-13',
    status: 'checked_in',
    payment_method: 'cash',
    total_usd: 180,
    rooms: [{ reservation_room_id: 'rr-2', room_type: 'Executive King', room: '506', price_usd: 180 }],
  },
  {
    uuid: 'res-9t1c5a',
    booking_code: 'CARL-9T1C5A',
    source: 'channel',
    guest: { uuid: 'guest-003', name: 'Hiba Darwish', phone: '+963955101010' },
    check_in: '2026-07-13',
    check_out: '2026-07-16',
    status: 'pending',
    payment_method: 'on_arrival',
    total_usd: 390,
    rooms: [{ reservation_room_id: 'rr-3', room_type: 'Junior Suite', room: null, price_usd: 130 }],
  },
];

export const availableRooms = [
  { room_id: 'room-304', room: '304', room_type: 'Double Deluxe' },
  { room_id: 'room-308', room: '308', room_type: 'Double Deluxe' },
  { room_id: 'room-610', room: '610', room_type: 'Junior Suite' },
];

export const queueItems = [
  {
    uuid: 'queue-001',
    type: 'service_request',
    source: 'app',
    category: 'room_service',
    department: 'kitchen',
    status: 'open',
    priority: 3,
    guest: { uuid: 'guest-002', name: 'Samer Khalil' },
    room: '506',
    subject: 'Late dinner tray request',
    created_at: new Date(now - 18 * 60000).toISOString(),
  },
  {
    uuid: 'queue-002',
    type: 'ticket',
    source: 'chatbot',
    category: 'complaint',
    department: 'front_desk',
    status: 'assigned',
    priority: 2,
    guest: { uuid: 'guest-004', name: 'Rana Saad' },
    room: '412',
    subject: 'Guest says room key stopped working',
    created_at: new Date(now - 42 * 60000).toISOString(),
  },
  {
    uuid: 'queue-003',
    type: 'service_request',
    source: 'walk_in',
    category: 'housekeeping',
    department: 'housekeeping',
    status: 'in_progress',
    priority: 1,
    guest: { uuid: 'guest-005', name: 'Fadi Barakat' },
    room: '219',
    subject: 'Extra towels and crib setup',
    created_at: new Date(now - 64 * 60000).toISOString(),
  },
];
