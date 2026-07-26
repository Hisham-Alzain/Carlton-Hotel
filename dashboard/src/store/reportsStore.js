import { create } from 'zustand';
import { reportsService } from '../services/reportsService.js';

export const useReportsStore = create((set) => ({
  report: null,
  isLoading: false,
  error: null,

  fetchReport: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const response = await reportsService.get(params);
      set({ report: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },
}));
