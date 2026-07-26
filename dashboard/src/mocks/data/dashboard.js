import { PERMISSIONS } from "../../utils/permissions.js";
import { getQueueRecords } from "./queue.js";
import { ROOMS, getReservationRecords } from "./reservations.js";

export function getDashboardSummary() {
  const reservations = getReservationRecords();
  const queueItems = getQueueRecords();

  const arrivalsToday = reservations.filter((reservation) => reservation.status === "arriving_today").length;
  const departuresToday = reservations.filter((reservation) => reservation.status === "due_out").length;
  const openQueue = queueItems.filter((item) => ["open", "in_progress", "waiting_guest"].includes(item.status)).length;
  const criticalQueue = queueItems.filter((item) => ["critical", "urgent"].includes(item.priority) && item.status !== "closed").length;
  const folioAlerts = reservations.filter((reservation) => reservation.folio_balance > 0).length;

  const occupiedRes = reservations.filter((r) => ["checked_in", "due_out"].includes(r.status));
  const adr = occupiedRes.length
    ? Math.round(occupiedRes.reduce((sum, r) => sum + (r.nightly_rate || 0), 0) / occupiedRes.length)
    : 0;
  const occupancyRate = occupiedRes.length / ROOMS.length;
  const revpar = Math.round(adr * occupancyRate);
  const revenueToday = reservations
    .filter((r) => ["arriving_today", "checked_in", "due_out"].includes(r.status))
    .reduce((sum, r) => sum + (r.nightly_rate || 0), 0);

  return {
    property_day: "2026-06-28",
    occupancy: {
      total_rooms: ROOMS.length,
      occupied_rooms: reservations.filter((r) => ["checked_in", "due_out"].includes(r.status)).length,
      available_rooms: ROOMS.filter((r) => r.status === "available").length,
      cleaning_rooms: ROOMS.filter((r) => r.status === "cleaning").length,
      occupancy_rate: reservations.filter((r) => ["checked_in", "due_out"].includes(r.status)).length / ROOMS.length,
    },
    cards: [
      {
        id: "arrivals",
        label: "Arrivals today",
        value: arrivalsToday,
        tone: "info",
        permission: PERMISSIONS.RESERVATIONS_VIEW,
      },
      {
        id: "departures",
        label: "Departures today",
        value: departuresToday,
        tone: "warning",
        permission: PERMISSIONS.RESERVATIONS_VIEW,
      },
      {
        id: "live_queue",
        label: "Open queue",
        value: openQueue,
        tone: criticalQueue ? "danger" : "info",
        permission: PERMISSIONS.QUEUE_VIEW,
      },
      {
        id: "folio_alerts",
        label: "Folio alerts",
        value: folioAlerts,
        tone: "warning",
        permission: PERMISSIONS.FOLIOS_VIEW,
      },
    ],
    kpis: {
      adr,
      revpar,
      revenue_today: revenueToday,
      currency: "USD",
    },
    alerts: [
      {
        id: "alert_queue_critical",
        tone: criticalQueue ? "danger" : "success",
        title: criticalQueue ? "Critical queue items need attention" : "No critical queue items",
        permission: PERMISSIONS.QUEUE_VIEW,
      },
      {
        id: "alert_folio_due_out",
        tone: folioAlerts ? "warning" : "success",
        title: folioAlerts ? "Departures include unsettled folios" : "Folios are clear",
        permission: PERMISSIONS.FOLIOS_VIEW,
      },
    ],
  };
}
