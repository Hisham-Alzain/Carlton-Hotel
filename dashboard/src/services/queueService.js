import { subscribeToQueueMock } from "../mocks/mockClient.js";
import { apiClient } from "./apiClient.js";

export function listQueueItems(params = {}) {
  return apiClient.get("/operations/queue", { params });
}

export function getQueueItem(id) {
  return apiClient.get(`/operations/queue/${id}`);
}

export function listAssignableStaff(params = {}) {
  return apiClient.get("/operations/queue/staff", { params });
}

export function claimQueueItem(id) {
  return apiClient.patch(`/operations/queue/${id}/claim`, {});
}

export function assignQueueItem(id, assignee) {
  return apiClient.patch(`/operations/queue/${id}/assign`, { assignee });
}

export function updateQueueItemStatus(id, status) {
  return apiClient.patch(`/operations/queue/${id}/status`, { status });
}

export function listServiceRequests(params = {}) {
  return apiClient.get("/service-requests", { params });
}

export function listSupportTickets(params = {}) {
  return apiClient.get("/support-tickets", { params });
}

export function subscribeToQueueItems(callback, options = {}) {
  return subscribeToQueueMock(callback, options);
}

export const queueService = {
  list: listQueueItems,
  subscribe: subscribeToQueueItems,
  updateStatus: updateQueueItemStatus,
  listQueueItems,
  get: getQueueItem,
  getQueueItem,
  listAssignableStaff,
  claim: claimQueueItem,
  claimQueueItem,
  assign: assignQueueItem,
  assignQueueItem,
  updateQueueItemStatus,
  listServiceRequests,
  listSupportTickets,
  subscribeToQueueItems,
};
