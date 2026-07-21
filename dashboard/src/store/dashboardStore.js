import { create } from 'zustand';
import { dashboardService } from '../services/dashboardService.js';

export const useDashboardStore = create((set) => ({
  summary: null,
  isLoading: false,
  error: null,

  fetchSummary: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await dashboardService.summary();
      set({ summary: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },
}));
