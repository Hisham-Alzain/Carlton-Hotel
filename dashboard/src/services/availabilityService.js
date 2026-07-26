import { apiClient } from './apiClient.js';

export const availabilityService = {
  getGrid: (params = {}) => apiClient.get('/availability/grid', { params }),
};
