import { apiClient } from './apiClient.js';

export function listDepartureItems(params = {}) {
  return apiClient.get('/departure-services', { params });
}

export function getDepartureItem(id) {
  return apiClient.get('/departure-services/' + id);
}

export function updateDepartureStatus(id, status) {
  return apiClient.patch('/departure-services/' + id + '/status', { status });
}

export function createDepartureItem(data) {
  return apiClient.post('/departure-services', data);
}

export const departureService = {
  list: listDepartureItems,
  get: getDepartureItem,
  updateStatus: updateDepartureStatus,
  create: createDepartureItem,
};
