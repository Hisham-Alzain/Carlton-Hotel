import { apiClient } from './apiClient.js';

function getReport(params = {}) {
  return apiClient.get('/reports/dashboard', { params });
}

export const reportsService = { get: getReport };
