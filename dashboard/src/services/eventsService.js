import { apiClient } from './apiClient.js';

function listEvents(params = {}) {
  return apiClient.get('/events', { params });
}

function getEvent(id) {
  return apiClient.get('/events/' + id);
}

function updateEventStatus(id, status) {
  return apiClient.patch('/events/' + id + '/status', { status });
}

function updateEventNotes(id, notes) {
  return apiClient.patch('/events/' + id + '/notes', { notes });
}

function updateEventChecklistItem(id, itemId, done) {
  return apiClient.patch('/events/' + id + '/checklist/' + itemId, { done });
}

function updateEventDeposit(id, payload = {}) {
  return apiClient.patch('/events/' + id + '/deposit', payload);
}

function createEvent(data) {
  return apiClient.post('/events', data);
}

export const eventsService = {
  list: listEvents,
  get: getEvent,
  updateStatus: updateEventStatus,
  updateNotes: updateEventNotes,
  updateChecklistItem: updateEventChecklistItem,
  updateDeposit: updateEventDeposit,
  create: createEvent,
};
