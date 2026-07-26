import { create } from 'zustand';
import { frontDeskService } from '../services/frontDeskService.js';

export const useFrontDeskStore = create((set, get) => ({
  arrivals: [],
  dueOuts: [],
  roomBoard: [],
  boardSummary: null,
  roomsForId: {},
  expandedId: null,
  selectedRoom: {},
  selectedPayment: {},
  actionLoading: {},
  isLoadingArrivals: false,
  isLoadingDueOuts: false,
  isLoadingBoard: false,

  fetchArrivals: async () => {
    set({ isLoadingArrivals: true });
    try {
      const res = await frontDeskService.getArrivals();
      set({ arrivals: res.data?.items || res.items || [], isLoadingArrivals: false });
    } catch {
      set({ isLoadingArrivals: false });
    }
  },

  fetchDueOuts: async () => {
    set({ isLoadingDueOuts: true });
    try {
      const res = await frontDeskService.getDueOuts();
      set({ dueOuts: res.data?.items || res.items || [], isLoadingDueOuts: false });
    } catch {
      set({ isLoadingDueOuts: false });
    }
  },

  fetchRoomBoard: async () => {
    set({ isLoadingBoard: true });
    try {
      const res = await frontDeskService.getRoomBoard();
      const boardData = res.data || res;
      set({ roomBoard: boardData.rooms || [], boardSummary: boardData.summary || null, isLoadingBoard: false });
    } catch {
      set({ isLoadingBoard: false });
    }
  },

  fetchRoomsFor: async (reservationId) => {
    try {
      const res = await frontDeskService.getAvailableRoomsForReservation(reservationId);
      const rooms = res.data?.items || res.items || [];
      set((state) => ({ roomsForId: { ...state.roomsForId, [reservationId]: rooms } }));
    } catch {}
  },

  setExpanded: (id) => {
    set((state) => {
      const next = state.expandedId === id ? null : id;
      return { expandedId: next, selectedRoom: {}, selectedPayment: {} };
    });
    const { expandedId, arrivals, roomsForId, fetchRoomsFor } = get();
    if (expandedId === id) {
      const isArrival = arrivals.some((r) => r.id === id);
      if (isArrival && !roomsForId[id]) fetchRoomsFor(id);
    }
  },

  setSelectedRoom: (reservationId, roomId) =>
    set((state) => ({ selectedRoom: { ...state.selectedRoom, [reservationId]: roomId } })),

  setSelectedPayment: (reservationId, method) =>
    set((state) => ({ selectedPayment: { ...state.selectedPayment, [reservationId]: method } })),

  checkIn: async (reservationId) => {
    const { selectedRoom } = get();
    const roomId = selectedRoom[reservationId];
    if (!roomId) return;
    set((state) => ({ actionLoading: { ...state.actionLoading, [reservationId]: true } }));
    try {
      await frontDeskService.checkIn(reservationId, roomId);
      set((state) => ({
        arrivals: state.arrivals.filter((r) => r.id !== reservationId),
        expandedId: null,
        actionLoading: { ...state.actionLoading, [reservationId]: false },
      }));
      await get().fetchRoomBoard();
    } catch {
      set((state) => ({ actionLoading: { ...state.actionLoading, [reservationId]: false } }));
    }
  },

  checkOut: async (reservationId) => {
    const { selectedPayment, dueOuts } = get();
    const reservation = dueOuts.find((r) => r.id === reservationId);
    const paymentMethod = selectedPayment[reservationId];
    if (reservation?.folio_balance > 0 && !paymentMethod) return;
    set((state) => ({ actionLoading: { ...state.actionLoading, [reservationId]: true } }));
    try {
      await frontDeskService.checkOut(reservationId, paymentMethod);
      set((state) => ({
        dueOuts: state.dueOuts.filter((r) => r.id !== reservationId),
        expandedId: null,
        actionLoading: { ...state.actionLoading, [reservationId]: false },
      }));
      await get().fetchRoomBoard();
    } catch {
      set((state) => ({ actionLoading: { ...state.actionLoading, [reservationId]: false } }));
    }
  },

  markRoomClean: async (roomId) => {
    try {
      await frontDeskService.markRoomClean(roomId);
      await get().fetchRoomBoard();
    } catch {}
  },
}));
