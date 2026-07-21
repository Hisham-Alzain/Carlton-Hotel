import { create } from 'zustand';
import { availabilityService } from '../services/availabilityService.js';

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

export const useAvailabilityStore = create((set, get) => ({
  grid: null,
  isLoading: false,
  error: null,
  startDate: todayIso(),

  fetchGrid: async () => {
    const { startDate } = get();
    set({ isLoading: true, error: null });
    try {
      const res = await availabilityService.getGrid({ start_date: startDate, days: 14 });
      set({ grid: res.data, isLoading: false });
    } catch (error) {
      set({ error: error.envelope || { message: error.message }, isLoading: false });
    }
  },

  setStartDate: (date) => {
    set({ startDate: date });
    get().fetchGrid();
  },
}));
