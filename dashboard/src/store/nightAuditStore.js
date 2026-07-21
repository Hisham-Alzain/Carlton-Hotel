import { create } from 'zustand';
import { nightAuditService } from '../services/nightAuditService.js';

function normalizeError(error) {
  return error?.payload || error?.envelope || { message: error?.message || 'Request failed.' };
}

export const useNightAuditStore = create((set) => ({
  audit: null,
  isLoading: false,
  error: null,

  fetchAudit: async () => {
    set({ isLoading: true, error: null });
    try {
      const response = await nightAuditService.get();
      set({ audit: response.data, isLoading: false });
      return response.data;
    } catch (error) {
      set({ error: normalizeError(error), isLoading: false });
      throw error;
    }
  },

  updateCheck: async (id, payload) => {
    try {
      const response = await nightAuditService.updateCheck(id, payload);
      set({ audit: response.data, error: null });
      return response.data;
    } catch (error) {
      set({ error: normalizeError(error) });
      throw error;
    }
  },

  resolveBlocker: async (id, payload) => {
    try {
      const response = await nightAuditService.resolveBlocker(id, payload);
      set({ audit: response.data, error: null });
      return response.data;
    } catch (error) {
      set({ error: normalizeError(error) });
      throw error;
    }
  },
}));
