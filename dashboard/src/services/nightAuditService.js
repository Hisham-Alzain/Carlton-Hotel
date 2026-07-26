import { apiClient } from './apiClient.js';

export function getNightAuditSnapshot() {
  return apiClient.get('/operations/night-audit');
}

export function updateNightAuditCheck(id, payload) {
  return apiClient.patch(`/operations/night-audit/checks/${id}`, payload);
}

export function resolveNightAuditBlocker(id, payload) {
  return apiClient.patch(`/operations/night-audit/blockers/${id}`, payload);
}

export const nightAuditService = {
  get: getNightAuditSnapshot,
  updateCheck: updateNightAuditCheck,
  resolveBlocker: resolveNightAuditBlocker,
};
