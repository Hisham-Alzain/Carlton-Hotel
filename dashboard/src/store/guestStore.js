import { create } from 'zustand';
import { guestService } from '../services/guestService.js';

export const useGuestStore = create((set) => ({
  guest: null,
  guests: [],
  meta: null,
  isLoading: false,
  error: null,
  savingNote: false,
  savingPrefs: false,
  savingPreArrival: false,

  fetchGuest: async (id) => {
    set({ isLoading: true, error: null });
    try {
      const response = await guestService.get(id);
      set({ guest: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  fetchGuestByReservation: async (reservationId) => {
    set({ isLoading: true, error: null });
    try {
      const response = await guestService.getByReservation(reservationId);
      set({ guest: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  fetchGuests: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const response = await guestService.list(params);
      set({ guests: response.data.items, meta: response.data.meta, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  addNote: async (id, note) => {
    set({ savingNote: true });
    try {
      const response = await guestService.addNote(id, note);
      set({ guest: response.data, savingNote: false });
    } catch (error) {
      set({ savingNote: false });
      throw error;
    }
  },

  updatePreferences: async (id, prefs) => {
    set({ savingPrefs: true });
    try {
      const response = await guestService.updatePreferences(id, prefs);
      set({ guest: response.data, savingPrefs: false });
    } catch (error) {
      set({ savingPrefs: false });
      throw error;
    }
  },

  updatePreArrival: async (id, payload) => {
    set({ savingPreArrival: true });
    try {
      const response = await guestService.updatePreArrival(id, payload);
      set({ guest: response.data, savingPreArrival: false });
    } catch (error) {
      set({ savingPreArrival: false });
      throw error;
    }
  },
}));
