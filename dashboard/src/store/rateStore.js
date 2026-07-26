import { create } from 'zustand';
import { rateService } from '../services/rateService.js';

export const useRateStore = create((set) => ({
  grid: [],
  isLoading: false,
  error: null,

  fetchGrid: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const res = await rateService.getGrid(params);
      set({ grid: res.data?.items ?? res.data ?? [], isLoading: false });
    } catch (err) {
      set({ error: err.payload || { message: err.message }, isLoading: false });
    }
  },
}));
