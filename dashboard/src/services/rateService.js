import { apiClient } from './apiClient.js';

export function getRateGrid(params = {}) {
  return apiClient.get('/rates/grid', { params });
}

export function getRateForDate(room_type_id, date) {
  return apiClient.get('/rates/grid/' + room_type_id + '/' + date);
}

export const rateService = { getGrid: getRateGrid, getRateForDate };
