import { create } from 'zustand';
import { queueService } from '../services/queueService.js';

const unwrapList = (response) => ({
  items: response.items || response.data?.items || [],
  meta: response.meta || response.data?.meta || null,
});

export const useQueueStore = create((set, get) => ({
  items: [],
  assignableStaff: [],
  assignableStaffMeta: null,
  assignableStaffLoading: false,
  connected: false,
  error: null,
  unsubscribe: null,

  connect: () => {
    if (get().unsubscribe) return;
    const unsubscribe = queueService.subscribe((event) => {
      if (Array.isArray(event)) {
        set({ items: event, connected: true });
        return;
      }

      if (event?.items) {
        set({ items: event.items, connected: true });
        return;
      }

      if (event?.item) {
        set((state) => ({
          items: state.items.map((item) => (item.id === event.item.id ? event.item : item)),
          connected: true,
        }));
      }
    });
    set({ unsubscribe });
  },

  disconnect: () => {
    get().unsubscribe?.();
    set({ unsubscribe: null, connected: false });
  },

  fetchAssignableStaff: async (params = {}) => {
    set({ assignableStaffLoading: true, error: null });
    try {
      const response = await queueService.listAssignableStaff(params);
      const { items, meta } = unwrapList(response);
      set({ assignableStaff: items, assignableStaffMeta: meta, assignableStaffLoading: false });
      return items;
    } catch (error) {
      set({ error: error.payload || { message: error.message }, assignableStaffLoading: false });
      throw error;
    }
  },

  updateStatus: async (id, status) => {
    const response = await queueService.updateStatus(id, status);
    return response.data;
  },

  claim: async (id) => {
    const response = await queueService.claim(id);
    return response.data;
  },

  assign: async (id, assignee) => {
    const response = await queueService.assign(id, assignee);
    return response.data;
  },
}));
