import { apiClient } from "./apiClient.js";

export const ticketService = {
  list: (params) => apiClient.get("/support-tickets", { params }),
  get: (id) => apiClient.get(`/support-tickets/${id}`),
  updateStatus: (id, status) => apiClient.patch(`/support-tickets/${id}/status`, { status }),
  assignOwner: (id, owner) => apiClient.patch(`/support-tickets/${id}/assign`, { owner }),
  escalate: (id, payload) => apiClient.post(`/support-tickets/${id}/escalate`, payload),
  logRecoveryAction: (id, payload) => apiClient.post(`/support-tickets/${id}/recovery-actions`, payload),
  reply: (id, body) => apiClient.post(`/support-tickets/${id}/reply`, { body }),
};
