import { apiClient } from "./apiClient.js";

export function listReservations(params = {}) {
  return apiClient.get("/reservations", { params });
}

export function getReservation(id) {
  return apiClient.get(`/reservations/${id}`);
}

export function getAvailableRoomsForReservation(id, params = {}) {
  return apiClient.get(`/reservations/${id}/available-rooms`, { params });
}

export function getAvailableRooms(params = {}) {
  return apiClient.get("/availability/rooms", { params });
}

export function checkInReservation(id, payload = {}) {
  return apiClient.post(`/reservations/${id}/check-in`, payload);
}

export function checkOutReservation(id, payload = {}) {
  return apiClient.post(`/reservations/${id}/check-out`, payload);
}

export function updateReservationNotes(id, notes) {
  return apiClient.patch(`/reservations/${id}/notes`, { notes });
}

export function createReservation(data) {
  return apiClient.post('/reservations', data);
}

export const reservationsService = {
  list: listReservations,
  listReservations,
  get: getReservation,
  getReservation,
  getAvailableRooms,
  getAvailableRoomsForReservation,
  checkIn: checkInReservation,
  checkInReservation,
  checkOut: checkOutReservation,
  checkOutReservation,
  updateNotes: updateReservationNotes,
  updateReservationNotes,
  create: createReservation,
  createReservation,
};
