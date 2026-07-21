import { apiClient } from "./apiClient.js";

export function getArrivals() {
  return apiClient.get("/reservations", { params: { status: "arriving_today", per_page: 20 } });
}

export function getDueOuts() {
  return apiClient.get("/reservations", { params: { status: "due_out", per_page: 20 } });
}

export function getFrontDeskRoomBoard() {
  return apiClient.get("/front-desk/room-board");
}

export function getAvailableRoomsForReservation(id) {
  return apiClient.get(`/reservations/${id}/available-rooms`);
}

export function checkInReservation(id, roomId) {
  return apiClient.post(`/reservations/${id}/check-in`, { room_id: roomId });
}

export function checkOutReservation(id, paymentMethod) {
  return apiClient.post(`/reservations/${id}/check-out`, paymentMethod ? { payment_method: paymentMethod } : {});
}

export function markRoomClean(roomId) {
  return apiClient.patch(`/rooms/${roomId}/status`, { status: "available" });
}

export const frontDeskService = {
  getArrivals,
  getDueOuts,
  getRoomBoard: getFrontDeskRoomBoard,
  getAvailableRoomsForReservation,
  checkIn: checkInReservation,
  checkOut: checkOutReservation,
  markRoomClean,
};
