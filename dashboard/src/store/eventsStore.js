import { create } from 'zustand';
import { eventsService } from '../services/eventsService.js';

const unwrapList = (response) => ({
  items: response.items || response.data?.items || [],
  meta: response.meta || response.data?.meta || null,
});

const patchEventInList = (events, updated, statusFilter = 'all') =>
  events
    .map((event) => (event.id === updated.id ? updated : event))
    .filter((event) => statusFilter === 'all' || event.status === statusFilter);

export const useEventsStore = create((set, get) => ({
  events: [],
  meta: null,
  current: null,
  isLoading: false,
  isLoadingDetail: false,
  error: null,
  statusFilter: 'all',

  fetchEvents: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const { statusFilter } = get();
      const response = await eventsService.list({
        ...params,
        status: statusFilter === 'all' ? undefined : statusFilter,
      });
      const { items, meta } = unwrapList(response);
      set({ events: items, meta, isLoading: false });
      return items;
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
      return [];
    }
  },

  fetchEvent: async (id) => {
    set({ isLoadingDetail: true, error: null, current: null });
    try {
      const response = await eventsService.get(id);
      set({ current: response.data, isLoadingDetail: false });
      return response.data;
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoadingDetail: false });
      throw error;
    }
  },

  updateStatus: async (id, status) => {
    const response = await eventsService.updateStatus(id, status);
    const updated = response.data;
    const { statusFilter } = get();

    set((state) => ({
      events: patchEventInList(state.events, updated, statusFilter),
      current: state.current?.id === id ? updated : state.current,
    }));

    return updated;
  },

  updateNotes: async (id, notes) => {
    const response = await eventsService.updateNotes(id, notes);
    const updated = response.data;

    set((state) => ({
      events: state.events.map((event) =>
        event.id === id ? { ...event, notes: updated.notes, updated_at: updated.updated_at } : event,
      ),
      current: state.current?.id === id ? updated : state.current,
    }));

    return updated;
  },

  toggleChecklistItem: async (id, itemId, done) => {
    const response = await eventsService.updateChecklistItem(id, itemId, done);
    const updated = response.data;

    set((state) => ({
      events: state.events.map((event) =>
        event.id === id
          ? { ...event, checklist: updated.checklist, updated_at: updated.updated_at }
          : event,
      ),
      current: state.current?.id === id ? updated : state.current,
    }));

    return updated;
  },

  markDepositReceived: async (id, payload = {}) => {
    const response = await eventsService.updateDeposit(id, payload);
    const updated = response.data;

    set((state) => ({
      events: state.events.map((event) =>
        event.id === id
          ? {
              ...event,
              deposit_paid: updated.deposit_paid,
              deposit_amount: updated.deposit_amount,
              deposit_paid_at: updated.deposit_paid_at,
              deposit_received_by: updated.deposit_received_by,
              checklist: updated.checklist,
              updated_at: updated.updated_at,
            }
          : event,
      ),
      current: state.current?.id === id ? updated : state.current,
    }));

    return updated;
  },

  createEvent: async (data) => {
    const response = await eventsService.create(data);
    const created = response.data;
    set((state) => ({ events: [created, ...state.events] }));
    return created;
  },

  setStatusFilter: (value) => set({ statusFilter: value }),
}));
