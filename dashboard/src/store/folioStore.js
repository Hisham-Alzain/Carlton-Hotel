import { create } from 'zustand';
import { folioService } from '../services/folioService.js';

export const useFolioStore = create((set) => ({
  folio: null,
  folios: [],
  meta: null,
  isLoading: false,
  error: null,
  posting: false,
  settling: false,

  fetchFolioByReservation: async (reservationId) => {
    set({ isLoading: true, error: null });
    try {
      const response = await folioService.getByReservation(reservationId);
      set({ folio: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  fetchFolios: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const response = await folioService.list(params);
      set({ folios: response.data.items, meta: response.data.meta, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  addLineItem: async (folioId, data) => {
    set({ posting: true });
    try {
      const response = await folioService.postLineItem(folioId, data);
      set({ folio: response.data, posting: false });
    } catch (error) {
      set({ posting: false });
      throw error;
    }
  },

  disputeItem: async (folioId, lineItemId) => {
    try {
      const response = await folioService.disputeLineItem(folioId, lineItemId);
      set({ folio: response.data });
    } catch (error) {
      throw error;
    }
  },

  settle: async (folioId, data) => {
    set({ settling: true });
    try {
      const response = await folioService.settle(folioId, data);
      set({ folio: response.data, settling: false });
    } catch (error) {
      set({ settling: false });
      throw error;
    }
  },
}));
