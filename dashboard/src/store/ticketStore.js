import { create } from "zustand";
import { ticketService } from "../services/ticketService.js";

function normalizeTicketError(error) {
  return error?.payload
    || error?.envelope
    || {
      message: error?.message || "Request failed.",
      request_id: error?.request_id || error?.requestId || null,
    };
}

function syncTicketRecord(set, ticket) {
  if (!ticket?.id) return;

  set((state) => ({
    tickets: state.tickets.map((item) => (item.id === ticket.id ? { ...item, ...ticket } : item)),
    selected: state.selected?.id === ticket.id ? { ...state.selected, ...ticket } : state.selected,
  }));
}

export const useTicketStore = create((set) => ({
  tickets: [],
  meta: null,
  selected: null,
  isLoading: false,
  isLoadingDetail: false,
  error: null,

  fetchAll: async (params) => {
    set({ isLoading: true, error: null });
    try {
      const res = await ticketService.list(params);
      set({
        tickets: res.items || res.data?.items || [],
        meta: res.meta || res.data?.meta || null,
        isLoading: false,
      });
    } catch (error) {
      set({ error: normalizeTicketError(error), isLoading: false });
    }
  },

  fetchOne: async (id) => {
    set({ isLoadingDetail: true, error: null, selected: null });
    try {
      const res = await ticketService.get(id);
      set({ selected: res.data, isLoadingDetail: false });
      return res.data;
    } catch (error) {
      set({ error: normalizeTicketError(error), isLoadingDetail: false });
      throw error;
    }
  },

  updateStatus: async (id, status) => {
    set({ error: null });
    try {
      const res = await ticketService.updateStatus(id, status);
      syncTicketRecord(set, res.data);
      return res.data;
    } catch (error) {
      set({ error: normalizeTicketError(error) });
      throw error;
    }
  },

  assignOwner: async (id, owner) => {
    set({ error: null });
    try {
      const res = await ticketService.assignOwner(id, owner);
      syncTicketRecord(set, res.data);
      return res.data;
    } catch (error) {
      set({ error: normalizeTicketError(error) });
      throw error;
    }
  },

  escalate: async (id, payload) => {
    set({ error: null });
    try {
      const res = await ticketService.escalate(id, payload);
      syncTicketRecord(set, res.data);
      return res.data;
    } catch (error) {
      set({ error: normalizeTicketError(error) });
      throw error;
    }
  },

  logRecoveryAction: async (id, payload) => {
    set({ error: null });
    try {
      const res = await ticketService.logRecoveryAction(id, payload);
      syncTicketRecord(set, res.data);
      return res.data;
    } catch (error) {
      set({ error: normalizeTicketError(error) });
      throw error;
    }
  },

  sendReply: async (id, body) => {
    set({ error: null });
    try {
      const res = await ticketService.reply(id, body);
      syncTicketRecord(set, res.data);
      return res.data;
    } catch (error) {
      set({ error: normalizeTicketError(error) });
      throw error;
    }
  },
}));
