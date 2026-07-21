import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, matchesSearch, paginateItems, throwMockError } from "../envelope.js";
import { createHousekeepingTask } from "./housekeeping.js";

const baseDay = "2026-06-28T00:00:00.000Z";

function at(minutes) {
  return addMinutesUtc(baseDay, minutes);
}

export const ROOM_TYPES = Object.freeze([
  { id: "rt_deluxe_king", name: "Deluxe King", name_ar: "ديلوكس كينغ", max_occupancy: 2 },
  { id: "rt_junior_suite", name: "Junior Suite", name_ar: "جناح جونيور", max_occupancy: 3 },
  { id: "rt_family_suite", name: "Family Suite", name_ar: "جناح عائلي", max_occupancy: 5 },
]);

const ROOM_TYPE_ABBR = Object.freeze({
  rt_deluxe_king: "DLX",
  rt_junior_suite: "JR",
  rt_family_suite: "FAM",
});

const ROOMS_SEED = [
  { id: "room_301", number: "301", floor: 3, room_type_id: "rt_deluxe_king", status: "available" },
  { id: "room_302", number: "302", floor: 3, room_type_id: "rt_deluxe_king", status: "available" },
  { id: "room_303", number: "303", floor: 3, room_type_id: "rt_deluxe_king", status: "cleaning" },
  { id: "room_304", number: "304", floor: 3, room_type_id: "rt_deluxe_king", status: "available" },
  { id: "room_405", number: "405", floor: 4, room_type_id: "rt_junior_suite", status: "available" },
  { id: "room_406", number: "406", floor: 4, room_type_id: "rt_junior_suite", status: "cleaning" },
  { id: "room_407", number: "407", floor: 4, room_type_id: "rt_junior_suite", status: "available" },
  { id: "room_408", number: "408", floor: 4, room_type_id: "rt_junior_suite", status: "available" },
  { id: "room_701", number: "701", floor: 7, room_type_id: "rt_family_suite", status: "available" },
  { id: "room_702", number: "702", floor: 7, room_type_id: "rt_family_suite", status: "available" },
  { id: "room_703", number: "703", floor: 7, room_type_id: "rt_family_suite", status: "cleaning" },
];

export const ROOMS = ROOMS_SEED.map((r) => ({ ...r }));

const reservationsSeed = [
  {
    id: "res_1001",
    reservation_code: "CAR-1001",
    status: "arriving_today",
    source: "direct",
    arrival_at: at(900),
    departure_at: at(5220),
    nights: 3,
    adults: 2,
    children: 0,
    room_type_id: "rt_deluxe_king",
    assigned_room_id: null,
    payment_status: "authorized",
    folio_balance: 0,
    total_amount: 540,
    nightly_rate: 180,
    currency: "USD",
    notes: null,
    guest: {
      id: "gst_lina_salem",
      profile_id: "guest_001",
      full_name: "Lina Salem",
      full_name_ar: "لينا سالم",
      phone_masked: "+963 *** 1044",
      email_masked: "lina.s***@example.com",
      nationality: "Syrian",
      vip_level: "gold",
    },
    timeline: [
      { id: "tl_1001_1", at: at(-1800), label: "Reservation created", actor: "Website" },
      { id: "tl_1001_2", at: at(-240), label: "Payment authorized", actor: "System" },
    ],
  },
  {
    id: "res_1002",
    reservation_code: "CAR-1002",
    status: "checked_in",
    source: "booking_com",
    arrival_at: at(-1020),
    departure_at: at(780),
    nights: 1,
    adults: 1,
    children: 0,
    room_type_id: "rt_junior_suite",
    assigned_room_id: "room_405",
    payment_status: "partial",
    folio_balance: 118,
    total_amount: 230,
    nightly_rate: 230,
    currency: "USD",
    notes: null,
    guest: {
      id: "gst_samir_khoury",
      profile_id: "guest_002",
      full_name: "Samir Khoury",
      full_name_ar: "سمير خوري",
      phone_masked: "+963 *** 7712",
      email_masked: "samir.k***@example.com",
      nationality: "Lebanese",
      vip_level: "standard",
    },
    timeline: [
      { id: "tl_1002_1", at: at(-2700), label: "Reservation imported", actor: "Booking.com" },
      { id: "tl_1002_2", at: at(-1000), label: "Checked in to room 405", actor: "Omar Mansour" },
    ],
  },
  {
    id: "res_1003",
    reservation_code: "CAR-1003",
    status: "due_out",
    source: "corporate",
    arrival_at: at(-4320),
    departure_at: at(660),
    nights: 3,
    adults: 2,
    children: 2,
    room_type_id: "rt_family_suite",
    assigned_room_id: "room_701",
    payment_status: "unpaid",
    folio_balance: 890,
    total_amount: 890,
    nightly_rate: 295,
    currency: "USD",
    notes: null,
    guest: {
      id: "gst_dana_abboud",
      profile_id: "guest_003",
      full_name: "Dana Abboud",
      full_name_ar: "دانا عبود",
      phone_masked: "+971 *** 9021",
      email_masked: "dana.a***@example.com",
      nationality: "UAE",
      vip_level: "platinum",
    },
    timeline: [
      { id: "tl_1003_1", at: at(-6000), label: "Corporate booking confirmed", actor: "Sales" },
      { id: "tl_1003_2", at: at(-4200), label: "Checked in to room 701", actor: "Nadia Hariri" },
    ],
  },
  {
    id: "res_1004",
    reservation_code: "CAR-1004",
    status: "upcoming",
    source: "phone",
    arrival_at: at(2100),
    departure_at: at(6420),
    nights: 3,
    adults: 2,
    children: 1,
    room_type_id: "rt_junior_suite",
    assigned_room_id: null,
    payment_status: "deposit_paid",
    folio_balance: 0,
    total_amount: 690,
    nightly_rate: 230,
    currency: "USD",
    notes: null,
    guest: {
      id: "gst_amal_nassar",
      full_name: "Amal Nassar",
      full_name_ar: "أمل نصار",
      phone_masked: "+963 *** 2288",
      email_masked: "amal.n***@example.com",
      nationality: "Syrian",
      vip_level: "standard",
    },
    timeline: [{ id: "tl_1004_1", at: at(-120), label: "Deposit received", actor: "Reception" }],
  },
];

let reservations = cloneMockData(reservationsSeed);

function hydrateReservation(reservation) {
  const roomType = ROOM_TYPES.find((roomTypeItem) => roomTypeItem.id === reservation.room_type_id) || null;
  const assignedRoom = ROOMS.find((room) => room.id === reservation.assigned_room_id) || null;

  return {
    ...reservation,
    room_type: roomType,
    assigned_room: assignedRoom,
  };
}

export function resetReservationsMockData() {
  reservations = cloneMockData(reservationsSeed);
  ROOMS_SEED.forEach((seedRoom) => {
    const room = ROOMS.find((r) => r.id === seedRoom.id);
    if (room) room.status = seedRoom.status;
  });
}

export function getReservationRecords() {
  return cloneMockData(reservations.map(hydrateReservation));
}

export function listReservations(params = {}) {
  const filtered = reservations
    .filter((reservation) => !params.status || reservation.status === params.status)
    .filter((reservation) => !params.source || reservation.source === params.source)
    .filter((reservation) => {
      if (!params.query && !params.guest && !params.booking_code) {
        return true;
      }

      const query = params.query || params.guest || params.booking_code;
      return matchesSearch(
        [
          reservation.reservation_code,
          reservation.guest.full_name,
          reservation.guest.full_name_ar,
          reservation.guest.phone_masked,
          reservation.source,
        ],
        query,
      );
    })
    .filter((reservation) => {
      if (!params.date_from && !params.date_to) {
        return true;
      }

      const arrival = new Date(reservation.arrival_at).getTime();
      const from = params.date_from ? new Date(params.date_from).getTime() : Number.NEGATIVE_INFINITY;
      const to = params.date_to ? new Date(params.date_to).getTime() : Number.POSITIVE_INFINITY;
      return arrival >= from && arrival <= to;
    })
    .sort((a, b) => new Date(a.arrival_at).getTime() - new Date(b.arrival_at).getTime())
    .map(hydrateReservation);

  return paginateItems(cloneMockData(filtered), params);
}

export function getReservationById(id) {
  const reservation = reservations.find((item) => item.id === id);
  return reservation ? cloneMockData(hydrateReservation(reservation)) : null;
}

export function getAvailableRooms({ room_type_id, roomTypeId } = {}) {
  const targetRoomTypeId = room_type_id || roomTypeId;
  const occupiedRoomIds = new Set(
    reservations
      .filter((reservation) => ["checked_in", "due_out"].includes(reservation.status))
      .map((reservation) => reservation.assigned_room_id)
      .filter(Boolean),
  );

  return cloneMockData(
    ROOMS.filter((room) => room.status === "available")
      .filter((room) => !targetRoomTypeId || room.room_type_id === targetRoomTypeId)
      .filter((room) => !occupiedRoomIds.has(room.id))
      .map((room) => ({
        ...room,
        room_type: ROOM_TYPES.find((roomType) => roomType.id === room.room_type_id) || null,
      })),
  );
}

export function checkInReservation(id, payload = {}) {
  const reservation = reservations.find((item) => item.id === id);

  if (!reservation) {
    throwMockError({ message: "Reservation not found", error_code: "reservation_not_found" }, 404);
  }

  if (!["arriving_today", "upcoming"].includes(reservation.status)) {
    throwMockError({
      message: "Only arriving reservations can be checked in.",
      error_code: "reservation_not_check_in_ready",
      errors: { status: ["Reservation is not ready for check-in."] },
    }, 422);
  }

  if (!payload.room_id) {
    throwMockError({
      message: "Room assignment is required.",
      error_code: "validation_failed",
      errors: { room_id: ["Select an available room before check-in."] },
    }, 422);
  }

  const availableRoom = getAvailableRooms({ room_type_id: reservation.room_type_id }).find((room) => room.id === payload.room_id);

  if (!availableRoom) {
    throwMockError({
      message: "Selected room is not available.",
      error_code: "room_unavailable",
      errors: { room_id: ["Selected room is no longer available."] },
    }, 422);
  }

  reservation.status = "checked_in";
  reservation.assigned_room_id = payload.room_id;
  reservation.timeline.unshift({
    id: `tl_${reservation.id}_checkin`,
    at: new Date().toISOString(),
    label: `Checked in to room ${availableRoom.number}`,
    actor: payload.actor || "Mock Reception",
  });

  return getReservationById(id);
}

export function getRoomBoard() {
  const occupiedMap = new Map();
  for (const res of reservations) {
    if (["checked_in", "due_out"].includes(res.status) && res.assigned_room_id) {
      occupiedMap.set(res.assigned_room_id, res);
    }
  }

  const rooms = ROOMS.map((room) => {
    const occ = occupiedMap.get(room.id);
    const roomType = ROOM_TYPES.find((rt) => rt.id === room.room_type_id) || null;
    const computedStatus = occ ? "occupied" : room.status;
    return {
      ...room,
      status: computedStatus,
      room_type: roomType
        ? { ...roomType, abbr: ROOM_TYPE_ABBR[roomType.id] || roomType.id }
        : null,
      occupant: occ
        ? {
            name: occ.guest.full_name,
            reservation_code: occ.reservation_code,
            departure_at: occ.departure_at,
          }
        : null,
    };
  });

  rooms.sort((a, b) => b.floor - a.floor || a.number.localeCompare(b.number));

  const summary = {
    total: rooms.length,
    available: rooms.filter((r) => r.status === "available").length,
    occupied: rooms.filter((r) => r.status === "occupied").length,
    cleaning: rooms.filter((r) => r.status === "cleaning").length,
  };

  return { rooms: cloneMockData(rooms), summary };
}

export function checkOutReservation(id, payload = {}) {
  const reservation = reservations.find((item) => item.id === id);

  if (!reservation) {
    throwMockError({ message: "Reservation not found", error_code: "reservation_not_found" }, 404);
  }

  if (!["checked_in", "due_out"].includes(reservation.status)) {
    throwMockError({
      message: "Only checked-in reservations can be checked out.",
      error_code: "reservation_not_check_out_ready",
      errors: { status: ["Reservation is not ready for check-out."] },
    }, 422);
  }

  if (reservation.folio_balance > 0 && !payload.payment_method) {
    throwMockError({
      message: "Payment method is required before check-out.",
      error_code: "validation_failed",
      errors: { payment_method: ["Select a payment method to settle the folio."] },
    }, 422);
  }

  reservation.status = "departed";
  reservation.payment_status = reservation.folio_balance > 0 ? "paid" : reservation.payment_status;
  reservation.folio_balance = 0;
  reservation.timeline.unshift({
    id: `tl_${reservation.id}_checkout`,
    at: new Date().toISOString(),
    label: "Checked out",
    actor: payload.actor || "Mock Reception",
  });

  const assignedRoom = ROOMS.find((r) => r.id === reservation.assigned_room_id);
  if (assignedRoom) {
    assignedRoom.status = "cleaning";
    createHousekeepingTask({
      room_id: assignedRoom.id,
      room_number: assignedRoom.number,
      floor: assignedRoom.floor,
      room_type_name: ROOM_TYPES.find((rt) => rt.id === assignedRoom.room_type_id)?.name || null,
      task_type: "checkout_clean",
      priority: "rush",
      notes: `${reservation.guest.full_name} checked out · ${payload.actor || "Reception"}`,
    });
    reservation.timeline.unshift({
      id: `tl_${reservation.id}_hk`,
      at: new Date().toISOString(),
      label: `Room ${assignedRoom.number} released — housekeeping task created`,
      actor: "System",
    });
  }

  return getReservationById(id);
}

export function updateRoomStatus(id, status) {
  const room = ROOMS.find((r) => r.id === id);
  if (!room) {
    throwMockError({ message: "Room not found", error_code: "room_not_found" }, 404);
  }
  const allowed = ["available", "cleaning", "maintenance"];
  if (!allowed.includes(status)) {
    throwMockError({
      message: "Invalid room status.",
      error_code: "validation_failed",
      errors: { status: ["Choose available, cleaning, or maintenance."] },
    }, 422);
  }
  room.status = status;
  return cloneMockData(room);
}

export function updateReservationNotes(id, notes) {
  const reservation = reservations.find((r) => r.id === id);
  if (!reservation) {
    throwMockError({ message: "Reservation not found", error_code: "reservation_not_found" }, 404);
  }
  reservation.notes = typeof notes === "string" ? notes.trim() || null : null;
  reservation.timeline.unshift({
    id: `tl_${id}_note`,
    at: new Date().toISOString(),
    label: "Staff note updated",
    actor: "Staff",
  });
  return getReservationById(id);
}
