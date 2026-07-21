import { create } from 'zustand';
import { reservationsService } from '../services/reservationsService.js';

const unwrapList = (response) => ({
  items: response.items || response.data?.items || [],
  meta: response.meta || response.data?.meta || null,
});

export const useReservationStore = create((set) => ({
  reservations: [],
  meta: null,
  current: null,
  rooms: [],
  isLoading: false,
  error: null,

  fetchReservations: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const requestParams = {
        ...params,
        query: params?.query || params?.search || undefined,
        status: params?.status === 'all' ? undefined : params?.status,
      };
      delete requestParams.search;

      const response = await reservationsService.list(requestParams);
      const { items, meta } = unwrapList(response);
      set({ reservations: items, meta, isLoading: false });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  fetchReservation: async (id) => {
    set({ isLoading: true, error: null });
    try {
      const response = await reservationsService.get(id);
      set({ current: response.data, isLoading: false });
      return response.data;
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
      throw error;
    }
  },

  fetchAvailableRooms: async (reservationId) => {
    const response = await reservationsService.getAvailableRoomsForReservation(reservationId);
    set({ rooms: response.data?.items || response.items || [] });
  },

  checkIn: async (id, roomId) => {
    const response = await reservationsService.checkIn(id, { room_id: roomId });
    set({ current: response.data });
    return response.data;
  },

  checkOut: async (id, payload = {}) => {
    const response = await reservationsService.checkOut(id, payload);
    set({ current: response.data });
    return response.data;
  },

  updateNotes: async (id, notes) => {
    const response = await reservationsService.updateNotes(id, notes);
    set({ current: response.data });
    return response.data;
  },
}));
