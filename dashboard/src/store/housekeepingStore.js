import { create } from 'zustand';
import { housekeepingService } from '../services/housekeepingService.js';

const replaceTask = (tasks, updated) =>
  tasks.map((t) => (t.id === updated.id ? updated : t));

export const useHousekeepingStore = create((set, get) => ({
  tasks: [],
  meta: null,
  isLoading: false,
  error: null,
  floorFilter: 'all',
  statusFilter: 'all',

  fetchTasks: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const { floorFilter, statusFilter } = get();
      const response = await housekeepingService.list({
        ...params,
        floor: floorFilter === 'all' ? undefined : floorFilter,
        status: statusFilter === 'all' ? undefined : statusFilter,
      });
      set({
        tasks: response.data?.items ?? response.items ?? [],
        meta: response.data?.meta ?? response.meta ?? null,
        isLoading: false,
      });
    } catch (error) {
      set({ error: error.payload || { message: error.message }, isLoading: false });
    }
  },

  assignTask: async (id, attendant) => {
    const response = await housekeepingService.assign(id, attendant);
    const updated = response.data ?? response;
    set((state) => ({ tasks: replaceTask(state.tasks, updated) }));
    return updated;
  },

  updateStatus: async (id, status) => {
    const response = await housekeepingService.updateStatus(id, status);
    const updated = response.data ?? response;
    set((state) => ({ tasks: replaceTask(state.tasks, updated) }));
    return updated;
  },

  addNote: async (id, note) => {
    const response = await housekeepingService.addNote(id, note);
    const updated = response.data ?? response;
    set((state) => ({ tasks: replaceTask(state.tasks, updated) }));
    return updated;
  },

  setFloorFilter: (v) => set({ floorFilter: v }),

  setStatusFilter: (v) => set({ statusFilter: v }),
}));
