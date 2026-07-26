import { create } from 'zustand';
import { serviceRequestService } from '../services/serviceRequestService.js';

export const useServiceRequestStore = create((set) => ({
  items: [],
  isLoading: false,
  error: null,
  deptFilter: 'all',

  fetchAll: async (params = {}) => {
    set({ isLoading: true, error: null });
    try {
      const res = await serviceRequestService.list(params);
      const items = res.items || res.data?.items || [];
      set({ items, isLoading: false });
    } catch (error) {
      set({
        error: { message: error.message, request_id: error.request_id || null },
        isLoading: false,
      });
    }
  },

  updateStatus: async (id, status) => {
    try {
      await serviceRequestService.updateStatus(id, status);
      set((state) => ({
        items: state.items.map((item) =>
          item.id === id ? { ...item, status } : item
        ),
      }));
    } catch (error) {
      set({ error: { message: error.message, request_id: error.request_id || null } });
    }
  },

  resolve: async (id, chargeData) => {
    try {
      const payload = chargeData ? { charge: chargeData } : {};
      await serviceRequestService.resolve(id, payload);
      set((state) => ({
        items: state.items.map((item) =>
          item.id === id ? { ...item, status: 'resolved' } : item
        ),
      }));
    } catch (error) {
      set({ error: { message: error.message, request_id: error.request_id || null } });
    }
  },

  setDeptFilter: (dept) => set({ deptFilter: dept }),
}));
