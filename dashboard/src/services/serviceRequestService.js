import { apiClient } from './apiClient.js';

export function listServiceRequests(params = {}) {
  return apiClient.get('/service-requests', { params });
}

export function updateServiceRequestStatus(id, status) {
  return apiClient.patch(`/service-requests/${id}/status`, { status });
}

export function resolveServiceRequest(id, data = {}) {
  return apiClient.post(`/service-requests/${id}/resolve`, data);
}

export const serviceRequestService = {
  list: listServiceRequests,
  updateStatus: updateServiceRequestStatus,
  resolve: resolveServiceRequest,
  listServiceRequests,
  updateServiceRequestStatus,
  resolveServiceRequest,
};
