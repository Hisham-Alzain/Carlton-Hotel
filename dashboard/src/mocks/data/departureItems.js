import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, matchesSearch, paginateItems, throwMockError } from "../envelope.js";

const baseDay = "2026-06-28T00:00:00.000Z";

function at(min) {
  return addMinutesUtc(baseDay, min);
}

const departureSeed = [
  {
    id: "ds_4001",
    reference: "DEP-4001",
    type: "departure_service",
    sub_type: "late_checkout",
    status: "open",
    priority: "urgent",
    room_number: "701",
    guest_name: "Dana Abboud",
    reservation_id: "res_1003",
    title: "Late checkout requested",
    description: "Guest requests checkout by 14:00 instead of 12:00.",
    requested_time: at(660 + 120),
    assigned_to: null,
    created_at: at(-90),
    updated_at: at(-90),
  },
  {
    id: "ds_4002",
    reference: "DEP-4002",
    type: "departure_service",
    sub_type: "luggage_storage",
    status: "open",
    priority: "normal",
    room_number: null,
    guest_name: "Lina Salem",
    reservation_id: "res_1001",
    title: "Luggage drop before arrival",
    description: "Guest arriving at 15:00 wants to drop bags early.",
    requested_time: at(900 - 60),
    assigned_to: null,
    created_at: at(-30),
    updated_at: at(-30),
  },
  {
    id: "ds_4003",
    reference: "DEP-4003",
    type: "departure_service",
    sub_type: "transport",
    status: "in_progress",
    priority: "normal",
    room_number: "405",
    guest_name: "Samir Khoury",
    reservation_id: "res_1002",
    title: "Airport transfer at 23:00",
    description: "Guest needs airport taxi after late checkout.",
    requested_time: at(780 + 900),
    assigned_to: "Omar Nasser",
    created_at: at(-120),
    updated_at: at(-20),
  },
  {
    id: "ds_4004",
    reference: "DEP-4004",
    type: "departure_service",
    sub_type: "late_checkout",
    status: "resolved",
    priority: "normal",
    room_number: "701",
    guest_name: "Dana Abboud",
    reservation_id: "res_1003",
    title: "Minibar restocking",
    description: "Previous late checkout resolved.",
    requested_time: at(660),
    assigned_to: "Layth Saleh",
    created_at: at(-200),
    updated_at: at(-180),
  },
];

let departureItems = departureSeed.map((item) => ({ ...item }));
let nextId = 4005;

function findDepartureItem(id) {
  return departureItems.find((item) => item.id === id) || null;
}

export function listDepartureItems(params = {}) {
  const filtered = departureItems
    .filter((item) => !params.status || item.status === params.status)
    .filter((item) => !params.sub_type || item.sub_type === params.sub_type)
    .filter((item) =>
      matchesSearch(
        [item.reference, item.title, item.guest_name, item.room_number],
        params.query
      )
    );

  return paginateItems(cloneMockData(filtered), params);
}

export function getDepartureItemById(id) {
  const item = findDepartureItem(id);
  return item ? cloneMockData(item) : null;
}

export function createDepartureItem(body) {
  const id = `ds_${nextId}`;
  const reference = `DEP-${nextId}`;
  nextId += 1;

  const now = new Date().toISOString();
  const newItem = {
    ...body,
    id,
    reference,
    type: "departure_service",
    status: "open",
    created_at: now,
    updated_at: now,
  };

  departureItems.push(newItem);
  return cloneMockData(newItem);
}

export function updateDepartureItemStatus(id, status) {
  const item = findDepartureItem(id);

  if (!item) {
    throwMockError(
      { message: "Departure item not found.", error_code: "departure_item_not_found" },
      404
    );
  }

  const allowedStatuses = ["open", "in_progress", "resolved"];

  if (!allowedStatuses.includes(status)) {
    throwMockError(
      {
        message: "Unsupported departure status.",
        error_code: "validation_failed",
        errors: { status: ["Choose a valid departure status."] },
      },
      422
    );
  }

  item.status = status;
  item.updated_at = new Date().toISOString();

  return cloneMockData(item);
}

export function resetDepartureMockData() {
  departureItems = departureSeed.map((item) => ({ ...item }));
  nextId = 4005;
}
