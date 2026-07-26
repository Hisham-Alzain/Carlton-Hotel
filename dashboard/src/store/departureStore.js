import { create } from 'zustand';
import { departureService } from '../services/departureService.js';

export const useDepartureStore = create((set, get) => ({
  items: [],
  meta: null,
  isLoading: false,
  error: null,
  subTypeFilter: 'all',

  fetchAll: async (params = {}) => {
    set({ isLoading: true, error: null });
    try {
      const subType = get().subTypeFilter;
      const response = await departureService.list({
        ...params,
        ...(subType !== 'all' && { sub_type: subType }),
      });
      set({ items: response.data.items, meta: response.data.meta, isLoading: false });
    } catch (err) {
      set({ error: err, isLoading: false });
    }
  },

  updateStatus: async (id, status) => {
    try {
      const response = await departureService.updateStatus(id, status);
      set((state) => ({
        items: state.items.map((item) => (item.id === id ? response.data : item)),
      }));
    } catch (err) {
      set({ error: err });
    }
  },

  createItem: async (data) => {
    try {
      const response = await departureService.create(data);
      set((state) => ({ items: [response.data, ...state.items] }));
    } catch (err) {
      set({ error: err });
    }
  },

  setSubTypeFilter: (v) => set({ subTypeFilter: v }),
}));
