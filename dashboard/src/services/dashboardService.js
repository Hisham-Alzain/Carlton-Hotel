import { apiClient } from "./apiClient.js";

export function getDashboardSummary(params = {}) {
  return apiClient.get("/dashboard/summary", { params });
}

export const dashboardService = {
  summary: getDashboardSummary,
  getSummary: getDashboardSummary,
  getDashboardSummary,
};
