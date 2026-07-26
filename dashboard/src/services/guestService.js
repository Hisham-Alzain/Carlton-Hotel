import { apiClient } from './apiClient.js';

export function listGuests(params = {}) {
  return apiClient.get('/guests', { params });
}

export function getGuest(id) {
  return apiClient.get('/guests/' + id);
}

export function getGuestByReservation(reservationId) {
  return apiClient.get('/reservations/' + reservationId + '/guest');
}

export function addGuestNote(id, note) {
  return apiClient.post('/guests/' + id + '/notes', { note });
}

export function updatePreferences(id, preferences) {
  return apiClient.patch('/guests/' + id + '/preferences', preferences);
}

export function updatePreArrival(id, payload) {
  return apiClient.patch('/guests/' + id + '/pre-arrival', payload);
}

export const guestService = {
  list: listGuests,
  get: getGuest,
  getByReservation: getGuestByReservation,
  addNote: addGuestNote,
  updatePreferences,
  updatePreArrival,
};
