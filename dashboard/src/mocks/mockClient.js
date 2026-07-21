import { createSessionFromToken, authenticatePersona, validateLoginPayload } from "./auth.js";
import {
  CarltonMockError,
  createSuccessEnvelope,
  paginateItems,
  parseMockPath,
  throwMockError,
  wait,
} from "./envelope.js";
import { getDashboardSummary } from "./data/dashboard.js";
import {
  getNightAuditBlockerDefinition,
  getNightAuditSnapshot,
  resolveNightAuditBlocker,
  updateNightAuditCheck,
} from "./data/nightAudit.js";
import { getTicketConversation, addMessageToTicket } from "./data/ticketConversations.js";
import { getAvailabilityGrid } from './data/availability.js';
import { getRateGrid } from './data/rates.js';
import {
  checkInReservation,
  checkOutReservation,
  getAvailableRooms,
  getReservationById,
  getRoomBoard,
  listReservations,
  updateReservationNotes,
  updateRoomStatus,
} from "./data/reservations.js";
import {
  assignQueueItem,
  claimQueueItem,
  assignSupportTicketOwner,
  escalateSupportTicket,
  getQueueItemById,
  listQueueItems,
  listAssignableQueueStaff,
  logSupportTicketRecoveryAction,
  subscribeToQueueMock,
  updateQueueItemStatus,
} from "./data/queue.js";
import { listDepartureItems, getDepartureItemById, createDepartureItem, updateDepartureItemStatus } from './data/departureItems.js';
import { listHousekeepingTasks, getTaskById, assignTask, updateTaskStatus, addTaskNote } from './data/housekeeping.js';
import { listFolios, getFolioByReservation, postLineItem, disputeLineItem, settlePayment } from './data/folios.js';
import { listGuests, getGuestById, getGuestByReservation, addGuestNote, updateGuestPreferences, updateGuestPreArrival } from './data/guests.js';
import { getReport } from './data/reports.js';
import {
  listEvents,
  getEventById,
  updateEventStatus,
  updateEventNotes,
  updateEventChecklistItem,
  updateEventDeposit,
  createEvent,
} from './data/events.js';
import { hasPermission, PERMISSIONS } from "../utils/permissions.js";
import { normalizeLocale } from "../utils/i18n.js";

const messages = {
  success: { en: "Success", ar: "تم بنجاح" },
  login_success: { en: "Signed in successfully.", ar: "تم تسجيل الدخول بنجاح." },
  logout_success: { en: "Signed out successfully.", ar: "تم تسجيل الخروج بنجاح." },
  unauthenticated: { en: "Authentication is required.", ar: "تسجيل الدخول مطلوب." },
  forbidden: { en: "You do not have permission to perform this action.", ar: "ليست لديك صلاحية لتنفيذ هذا الإجراء." },
  invalid_credentials: { en: "Invalid email or password.", ar: "البريد الإلكتروني أو كلمة المرور غير صحيحة." },
  validation_failed: { en: "Validation failed.", ar: "فشل التحقق من البيانات." },
  not_found: { en: "Resource not found.", ar: "المورد غير موجود." },
};

export class MockApiError extends CarltonMockError {
  constructor(payload, status = 400) {
    const envelope = {
      success: false,
      message: payload.message,
      data: payload.data || payload.context || null,
      request_id: payload.request_id,
      error_code: payload.error_code || "request_failed",
      errors: payload.errors || {},
    };

    super(envelope, status);
    this.payload = envelope;
  }
}

export function envelope(data = null, message = "Success") {
  return createSuccessEnvelope(data, message);
}

export function fail(message, error_code = "request_failed", status = 400, context = null, errors = {}) {
  throw new MockApiError({
    success: false,
    message,
    error_code,
    context,
    errors,
    request_id: globalThis.crypto?.randomUUID?.() || `req_${Date.now()}`,
  }, status);
}

export function paginated(items, page = 1, perPage = 10) {
  return paginateItems(items, { page, per_page: perPage });
}

function t(key, locale = "en") {
  const normalizedLocale = normalizeLocale(locale);
  return messages[key]?.[normalizedLocale] || messages[key]?.en || key;
}

function createSubject(session) {
  return {
    role: session?.user?.role,
    type: session?.user?.type,
    permissions: session?.permissions || [],
  };
}

function requireAuth(token, locale) {
  const session = createSessionFromToken(token);

  if (!session) {
    throwMockError({
      message: t("unauthenticated", locale),
      error_code: "unauthenticated",
    }, 401);
  }

  return session;
}

function requirePermission(session, requiredPermission, locale) {
  if (!hasPermission(createSubject(session), requiredPermission)) {
    throwMockError({
      message: t("forbidden", locale),
      error_code: "permission_denied",
      errors: { permission: [requiredPermission].flat() },
    }, 403);
  }
}

function routeReservationRequest(method, pathname, params, body, session, locale) {
  if (method === "GET" && pathname === "/reservations") {
    requirePermission(session, PERMISSIONS.RESERVATIONS_VIEW, locale);
    return listReservations(params);
  }

  const detailMatch = pathname.match(/^\/reservations\/([^/]+)$/);

  if (method === "GET" && detailMatch) {
    requirePermission(session, PERMISSIONS.RESERVATIONS_VIEW, locale);
    const reservation = getReservationById(detailMatch[1]);
    if (!reservation) throwMockError({ message: "Reservation not found", error_code: "reservation_not_found" }, 404);
    return reservation;
  }

  const availableRoomsMatch = pathname.match(/^\/reservations\/([^/]+)\/available-rooms$/);

  if (method === "GET" && availableRoomsMatch) {
    requirePermission(session, PERMISSIONS.RESERVATIONS_CHECK_IN, locale);
    const reservation = getReservationById(availableRoomsMatch[1]);
    if (!reservation) throwMockError({ message: "Reservation not found", error_code: "reservation_not_found" }, 404);

    return {
      items: getAvailableRooms({ room_type_id: reservation.room_type_id }),
      reservation_id: reservation.id,
      room_type_id: reservation.room_type_id,
    };
  }

  const checkInMatch = pathname.match(/^\/reservations\/([^/]+)\/check-in$/);

  if (method === "POST" && checkInMatch) {
    requirePermission(session, PERMISSIONS.RESERVATIONS_CHECK_IN, locale);
    return checkInReservation(checkInMatch[1], { ...body, actor: session.user.name });
  }

  const checkOutMatch = pathname.match(/^\/reservations\/([^/]+)\/check-out$/);

  if (method === "POST" && checkOutMatch) {
    requirePermission(session, PERMISSIONS.RESERVATIONS_CHECK_OUT, locale);
    return checkOutReservation(checkOutMatch[1], { ...body, actor: session.user.name });
  }

  const notesMatch = pathname.match(/^\/reservations\/([^/]+)\/notes$/);

  if (method === "PATCH" && notesMatch) {
    requirePermission(session, PERMISSIONS.RESERVATIONS_MANAGE, locale);
    return updateReservationNotes(notesMatch[1], body?.notes);
  }

  const roomStatusMatch = pathname.match(/^\/rooms\/([^/]+)\/status$/);

  if (method === "PATCH" && roomStatusMatch) {
    requirePermission(session, PERMISSIONS.RESERVATIONS_MANAGE, locale);
    return updateRoomStatus(roomStatusMatch[1], body?.status);
  }

  if (method === "GET" && pathname === "/availability/rooms") {
    requirePermission(session, PERMISSIONS.AVAILABILITY_VIEW, locale);
    return { items: getAvailableRooms(params) };
  }

  if (method === "GET" && pathname === "/front-desk/room-board") {
    requirePermission(session, PERMISSIONS.RESERVATIONS_VIEW, locale);
    return getRoomBoard();
  }

  if (method === 'GET' && pathname === '/availability/grid') {
    requirePermission(session, PERMISSIONS.AVAILABILITY_VIEW, locale);
    return getAvailabilityGrid({
      ...params,
      start_date: params.start_date || '2026-06-28',
      days: parseInt(params.days, 10) || 14,
    });
  }

  if (method === "POST" && pathname === "/reservations") {
    requirePermission(session, PERMISSIONS.RESERVATIONS_CREATE || "reservations.create", locale);
    const newId = 'res_' + Date.now().toString().slice(-4);
    const code = 'RES-' + newId.toUpperCase().replace('RES_', '');
    const newRes = {
      id: newId, reservation_code: code, status: 'upcoming',
      guest: { full_name: body.guest_name || 'New Guest', full_name_ar: body.guest_name_ar || null, nationality: body.nationality || 'LB', vip_level: 'standard', phone_masked: '***', email_masked: '***' },
      room_type: { id: body.room_type_id || 'rt_deluxe_king', name: body.room_type_id || 'Deluxe King' },
      check_in: body.check_in, check_out: body.check_out, arrival_at: body.check_in, departure_at: body.check_out,
      nights: 1, adults: body.adults || 1, children: body.children || 0,
      source: body.source || 'direct', payment_status: body.payment_status || 'deposit_paid',
      total_amount: 0, nightly_rate: 180, folio_balance: 0, notes: body.notes || null,
      assigned_room_id: null, assigned_room: null, timeline: [],
      created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
    };
    return newRes;
  }

  if (method === "GET" && pathname === "/rates/grid") {
    requirePermission(session, "rates.view", locale);
    return getRateGrid();
  }

  // Folio: get folio by reservation
  const folioByResMatch = pathname.match(/^\/reservations\/([^/]+)\/folio$/);
  if (method === "GET" && folioByResMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_VIEW, locale);
    const fol = getFolioByReservation(folioByResMatch[1]);
    if (!fol) throwMockError({ message: "Folio not found", error_code: "not_found" }, 404);
    return fol;
  }
  // Folio list
  if (method === "GET" && pathname === "/folios") {
    requirePermission(session, PERMISSIONS.FOLIOS_VIEW, locale);
    return listFolios(params);
  }
  // Post line item
  const lineItemMatch = pathname.match(/^\/folios\/([^/]+)\/line-items$/);
  if (method === "POST" && lineItemMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_SETTLE, locale);
    return postLineItem(lineItemMatch[1], body);
  }
  // Dispute line item
  const disputeMatch = pathname.match(/^\/folios\/([^/]+)\/line-items\/([^/]+)\/dispute$/);
  if (method === "PATCH" && disputeMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_SETTLE, locale);
    return disputeLineItem(disputeMatch[1], disputeMatch[2]);
  }
  // Record payment
  const paymentMatch = pathname.match(/^\/folios\/([^/]+)\/payments$/);
  if (method === "POST" && paymentMatch) {
    requirePermission(session, PERMISSIONS.FOLIOS_SETTLE, locale);
    return settlePayment(paymentMatch[1], body);
  }

  return null;
}

function routeQueueRequest(method, pathname, params, body, session, locale) {
  if (method === "GET" && pathname === "/operations/queue") {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    return listQueueItems(params);
  }

  if (method === "GET" && pathname === "/operations/queue/staff") {
    requirePermission(session, PERMISSIONS.QUEUE_ASSIGN, locale);
    return listAssignableQueueStaff(params);
  }

  const detailMatch = pathname.match(/^\/operations\/queue\/([^/]+)$/);

  if (method === "GET" && detailMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    const item = getQueueItemById(detailMatch[1]);
    if (!item) throwMockError({ message: "Queue item not found", error_code: "queue_item_not_found" }, 404);
    return item;
  }

  const claimMatch = pathname.match(/^\/operations\/queue\/([^/]+)\/claim$/);

  if (method === "PATCH" && claimMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return claimQueueItem(claimMatch[1], session.user.name);
  }

  const assignMatch = pathname.match(/^\/operations\/queue\/([^/]+)\/assign$/);

  if (method === "PATCH" && assignMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_ASSIGN, locale);
    return assignQueueItem(assignMatch[1], body?.assignee);
  }

  const statusMatch = pathname.match(/^\/operations\/queue\/([^/]+)\/status$/);

  if (method === "PATCH" && statusMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return updateQueueItemStatus(statusMatch[1], body?.status);
  }

  if (method === "GET" && pathname === "/service-requests") {
    requirePermission(session, PERMISSIONS.SERVICE_REQUESTS_VIEW, locale);
    return listQueueItems({ ...params, type: "service_request" });
  }

  if (method === "GET" && pathname === "/support-tickets") {
    requirePermission(session, [PERMISSIONS.TICKETS_VIEW], locale);
    return listQueueItems({ ...params, type: "support_ticket" });
  }

  const ticketDetailMatch = pathname.match(/^\/support-tickets\/([^/]+)$/);

  if (method === "GET" && ticketDetailMatch) {
    requirePermission(session, PERMISSIONS.TICKETS_VIEW, locale);
    const item = getQueueItemById(ticketDetailMatch[1]);
    if (!item) throwMockError({ message: "Ticket not found", error_code: "ticket_not_found" }, 404);
    const conversation = getTicketConversation(ticketDetailMatch[1]);
    return { ...item, conversation };
  }

  const ticketStatusMatch = pathname.match(/^\/support-tickets\/([^/]+)\/status$/);

  if (method === "PATCH" && ticketStatusMatch) {
    requirePermission(session, PERMISSIONS.TICKETS_MANAGE, locale);
    return updateQueueItemStatus(ticketStatusMatch[1], body?.status);
  }

  const ticketAssignMatch = pathname.match(/^\/support-tickets\/([^/]+)\/assign$/);

  if (method === "PATCH" && ticketAssignMatch) {
    requirePermission(session, PERMISSIONS.TICKETS_MANAGE, locale);
    return assignSupportTicketOwner(ticketAssignMatch[1], body?.owner || body?.assignee, session.user.name);
  }

  const ticketEscalateMatch = pathname.match(/^\/support-tickets\/([^/]+)\/escalate$/);

  if (method === "POST" && ticketEscalateMatch) {
    requirePermission(session, PERMISSIONS.TICKETS_MANAGE, locale);
    return escalateSupportTicket(ticketEscalateMatch[1], body, session.user.name);
  }

  const ticketRecoveryMatch = pathname.match(/^\/support-tickets\/([^/]+)\/recovery-actions$/);

  if (method === "POST" && ticketRecoveryMatch) {
    requirePermission(session, PERMISSIONS.TICKETS_MANAGE, locale);
    return logSupportTicketRecoveryAction(ticketRecoveryMatch[1], body, session.user.name);
  }

  const ticketReplyMatch = pathname.match(/^\/support-tickets\/([^/]+)\/reply$/);

  if (method === "POST" && ticketReplyMatch) {
    requirePermission(session, PERMISSIONS.TICKETS_VIEW, locale);
    const ticketId = ticketReplyMatch[1];
    const item = getQueueItemById(ticketId);
    if (!item) throwMockError({ message: "Ticket not found", error_code: "ticket_not_found" }, 404);
    addMessageToTicket(ticketId, {
      id: "msg_" + Date.now(),
      author_type: "staff",
      author_name: session.user.name,
      body: body?.body,
      sent_at: new Date().toISOString(),
    });
    updateQueueItemStatus(ticketId, item.status === "open" ? "in_progress" : item.status);
    const updatedItem = getQueueItemById(ticketId);
    const conversation = getTicketConversation(ticketId);
    return { ...updatedItem, conversation };
  }

  const srStatusMatch = pathname.match(/^\/service-requests\/([^/]+)\/status$/);

  if (method === "PATCH" && srStatusMatch) {
    requirePermission(session, PERMISSIONS.SERVICE_REQUESTS_MANAGE, locale);
    return updateQueueItemStatus(srStatusMatch[1], body?.status);
  }

  const srResolveMatch = pathname.match(/^\/service-requests\/([^/]+)\/resolve$/);

  if (method === "POST" && srResolveMatch) {
    requirePermission(session, PERMISSIONS.SERVICE_REQUESTS_MANAGE, locale);
    const srId = srResolveMatch[1];
    const item = getQueueItemById(srId);
    if (!item) throwMockError({ message: "Service request not found", error_code: "not_found" }, 404);
    updateQueueItemStatus(srId, "resolved");
    let chargePosted = null;
    if (body?.charge && item.reservation_id) {
      const folio = getFolioByReservation(item.reservation_id);
      if (folio) {
        chargePosted = postLineItem(folio.id, {
          category: body.charge.category || "misc",
          description: body.charge.description || item.title,
          amount: parseFloat(body.charge.amount) || 0,
          quantity: 1,
          posted_by: session.user.name,
          posted_at: new Date().toISOString(),
        });
      }
    }
    return { resolved: true, charge_posted: !!chargePosted };
  }

  if (method === "GET" && pathname === "/departure-services") {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    return listDepartureItems(params);
  }
  const depDetailMatch = pathname.match(/^\/departure-services\/([^/]+)$/);
  if (method === "GET" && depDetailMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    const item = getDepartureItemById(depDetailMatch[1]);
    if (!item) throwMockError({ message: "Not found", error_code: "not_found" }, 404);
    return item;
  }
  const depStatusMatch = pathname.match(/^\/departure-services\/([^/]+)\/status$/);
  if (method === "PATCH" && depStatusMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return updateDepartureItemStatus(depStatusMatch[1], body?.status);
  }
  if (method === "POST" && pathname === "/departure-services") {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return createDepartureItem({ ...body, actor: session.user.name });
  }

  if (method === "GET" && pathname === "/housekeeping/tasks") {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    return listHousekeepingTasks(params);
  }
  const hkTaskMatch = pathname.match(/^\/housekeeping\/tasks\/([^/]+)$/);
  if (method === "GET" && hkTaskMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_VIEW, locale);
    const task = getTaskById(hkTaskMatch[1]);
    if (!task) throwMockError({ message: "Not found", error_code: "not_found" }, 404);
    return task;
  }
  const hkAssignMatch = pathname.match(/^\/housekeeping\/tasks\/([^/]+)\/assign$/);
  if (method === "PATCH" && hkAssignMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return assignTask(hkAssignMatch[1], body?.attendant);
  }
  const hkStatusMatch = pathname.match(/^\/housekeeping\/tasks\/([^/]+)\/status$/);
  if (method === "PATCH" && hkStatusMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return updateTaskStatus(hkStatusMatch[1], body?.status);
  }
  const hkNoteMatch = pathname.match(/^\/housekeeping\/tasks\/([^/]+)\/note$/);
  if (method === "PATCH" && hkNoteMatch) {
    requirePermission(session, PERMISSIONS.QUEUE_MANAGE, locale);
    return addTaskNote(hkNoteMatch[1], body?.note);
  }

  return null;
}

function routeNightAuditRequest(method, pathname, params, body, session, locale) {
  if (method === "GET" && pathname === "/operations/night-audit") {
    requirePermission(session, PERMISSIONS.FOLIOS_VIEW, locale);
    return getNightAuditSnapshot();
  }

  const checkMatch = pathname.match(/^\/operations\/night-audit\/checks\/([^/]+)$/);
  if (method === "PATCH" && checkMatch) {
    if (!hasPermission(createSubject(session), PERMISSIONS.FOLIOS_SETTLE) && !hasPermission(createSubject(session), PERMISSIONS.RESERVATIONS_MANAGE)) {
      throwMockError({
        message: t("forbidden", locale),
        error_code: "permission_denied",
      }, 403);
    }

    return updateNightAuditCheck(checkMatch[1], { ...body, actor: session.user.name });
  }

  const blockerMatch = pathname.match(/^\/operations\/night-audit\/blockers\/([^/]+)$/);
  if (method === "PATCH" && blockerMatch) {
    const blocker = getNightAuditBlockerDefinition(blockerMatch[1]);
    if (!blocker) {
      throwMockError({ message: "Blocker not found", error_code: "night_audit_blocker_not_found" }, 404);
    }

    if (!hasPermission(createSubject(session), PERMISSIONS.FOLIOS_SETTLE) && !hasPermission(createSubject(session), PERMISSIONS.RESERVATIONS_MANAGE)) {
      throwMockError({
        message: t("forbidden", locale),
        error_code: "permission_denied",
      }, 403);
    }

    return resolveNightAuditBlocker(blockerMatch[1], { ...body, actor: session.user.name });
  }

  return null;
}

function routeGuestRequest(method, pathname, params, body, session, locale) {
  if (method === "GET" && pathname === "/guests") {
    requirePermission(session, PERMISSIONS.GUESTS_VIEW, locale);
    return listGuests(params);
  }

  const guestDetailMatch = pathname.match(/^\/guests\/([^/]+)$/);
  if (method === "GET" && guestDetailMatch) {
    requirePermission(session, PERMISSIONS.GUESTS_VIEW, locale);
    return getGuestById(guestDetailMatch[1]);
  }

  const guestNoteMatch = pathname.match(/^\/guests\/([^/]+)\/notes$/);
  if (method === "POST" && guestNoteMatch) {
    requirePermission(session, PERMISSIONS.GUESTS_VIEW, locale);
    return addGuestNote(guestNoteMatch[1], body?.note, session.user.name);
  }

  const guestPrefsMatch = pathname.match(/^\/guests\/([^/]+)\/preferences$/);
  if (method === "PATCH" && guestPrefsMatch) {
    requirePermission(session, PERMISSIONS.GUESTS_VIEW, locale);
    return updateGuestPreferences(guestPrefsMatch[1], body);
  }

  const guestPreArrivalMatch = pathname.match(/^\/guests\/([^/]+)\/pre-arrival$/);
  if (method === "PATCH" && guestPreArrivalMatch) {
    requirePermission(session, PERMISSIONS.GUESTS_VIEW, locale);
    return updateGuestPreArrival(guestPreArrivalMatch[1], body);
  }

  const guestByResMatch = pathname.match(/^\/reservations\/([^/]+)\/guest$/);
  if (method === "GET" && guestByResMatch) {
    requirePermission(session, PERMISSIONS.GUESTS_VIEW, locale);
    return getGuestByReservation(guestByResMatch[1]);
  }

  return null;
}

function routeReportsRequest(method, pathname, params, body, session, locale) {
  if (method === "GET" && pathname === "/reports/dashboard") {
    requirePermission(session, PERMISSIONS.REPORTS_VIEW, locale);
    return getReport(params);
  }
  return null;
}

function routeEventsRequest(method, pathname, params, body, session, locale) {
  if (method === "GET" && pathname === "/events") {
    requirePermission(session, PERMISSIONS.EVENTS_VIEW, locale);
    return listEvents(params);
  }

  const evDetailMatch = pathname.match(/^\/events\/([^/]+)$/);
  if (method === "GET" && evDetailMatch) {
    requirePermission(session, PERMISSIONS.EVENTS_VIEW, locale);
    return getEventById(evDetailMatch[1]);
  }

  const evStatusMatch = pathname.match(/^\/events\/([^/]+)\/status$/);
  if (method === "PATCH" && evStatusMatch) {
    requirePermission(session, PERMISSIONS.EVENTS_MANAGE, locale);
    return updateEventStatus(evStatusMatch[1], body?.status);
  }

  const evNotesMatch = pathname.match(/^\/events\/([^/]+)\/notes$/);
  if (method === "PATCH" && evNotesMatch) {
    requirePermission(session, PERMISSIONS.EVENTS_MANAGE, locale);
    return updateEventNotes(evNotesMatch[1], body?.notes);
  }

  const evChecklistMatch = pathname.match(/^\/events\/([^/]+)\/checklist\/([^/]+)$/);
  if (method === "PATCH" && evChecklistMatch) {
    requirePermission(session, PERMISSIONS.EVENTS_MANAGE, locale);
    return updateEventChecklistItem(evChecklistMatch[1], evChecklistMatch[2], { done: body?.done, actor: session.user.name });
  }

  const evDepositMatch = pathname.match(/^\/events\/([^/]+)\/deposit$/);
  if (method === "PATCH" && evDepositMatch) {
    requirePermission(session, PERMISSIONS.EVENTS_MANAGE, locale);
    return updateEventDeposit(evDepositMatch[1], { ...body, actor: session.user.name });
  }

  if (method === "POST" && pathname === "/events") {
    requirePermission(session, PERMISSIONS.EVENTS_MANAGE, locale);
    return createEvent(body);
  }

  return null;
}

export async function mockRequest(requestOptions = {}, ms) {
  if (typeof requestOptions === "function") {
    await wait(ms);
    return requestOptions();
  }

  const { method = "GET", path, params = {}, body = null, token = null, locale = "en", latency } = requestOptions;
  const normalizedMethod = method.toUpperCase();
  const normalizedLocale = normalizeLocale(locale);
  const { pathname, params: queryParams } = parseMockPath(path, params);

  await wait(latency);

  try {
    if (normalizedMethod === "POST" && pathname === "/auth/login") {
      const validationErrors = validateLoginPayload(body);

      if (Object.keys(validationErrors).length) {
        throwMockError({
          message: t("validation_failed", normalizedLocale),
          error_code: "validation_failed",
          errors: validationErrors,
        }, 422);
      }

      const session = authenticatePersona(body);

      if (!session) {
        throwMockError({
          message: t("invalid_credentials", normalizedLocale),
          error_code: "invalid_credentials",
        }, 401);
      }

      return createSuccessEnvelope(session, t("login_success", normalizedLocale));
    }

    if (normalizedMethod === "POST" && pathname === "/auth/logout") {
      return createSuccessEnvelope(null, t("logout_success", normalizedLocale));
    }

    const session = requireAuth(token, normalizedLocale);

    if (normalizedMethod === "GET" && pathname === "/auth/me") {
      return createSuccessEnvelope(session, t("success", normalizedLocale));
    }

    if (normalizedMethod === "GET" && pathname === "/dashboard/summary") {
      requirePermission(session, PERMISSIONS.DASHBOARD_VIEW, normalizedLocale);

      const subject = createSubject(session);
      const summary = getDashboardSummary();

      return createSuccessEnvelope({
        ...summary,
        cards: summary.cards.filter((card) => hasPermission(subject, card.permission)),
        alerts: summary.alerts.filter((alert) => hasPermission(subject, alert.permission)),
      }, t("success", normalizedLocale));
    }

    const reservationResponse = routeReservationRequest(normalizedMethod, pathname, queryParams, body, session, normalizedLocale);
    if (reservationResponse) return createSuccessEnvelope(reservationResponse, t("success", normalizedLocale));

    const guestResponse = routeGuestRequest(normalizedMethod, pathname, queryParams, body, session, normalizedLocale);
    if (guestResponse) return createSuccessEnvelope(guestResponse, t("success", normalizedLocale));

    const queueResponse = routeQueueRequest(normalizedMethod, pathname, queryParams, body, session, normalizedLocale);
    if (queueResponse) return createSuccessEnvelope(queueResponse, t("success", normalizedLocale));

    const nightAuditResponse = routeNightAuditRequest(normalizedMethod, pathname, queryParams, body, session, normalizedLocale);
    if (nightAuditResponse) return createSuccessEnvelope(nightAuditResponse, t("success", normalizedLocale));

    const reportsResponse = routeReportsRequest(normalizedMethod, pathname, queryParams, body, session, normalizedLocale);
    if (reportsResponse) return createSuccessEnvelope(reportsResponse, t("success", normalizedLocale));

    const eventsResponse = routeEventsRequest(normalizedMethod, pathname, queryParams, body, session, normalizedLocale);
    if (eventsResponse) return createSuccessEnvelope(eventsResponse, t("success", normalizedLocale));

    throwMockError({
      message: t("not_found", normalizedLocale),
      error_code: "not_found",
    }, 404);
  } catch (error) {
    if (error instanceof CarltonMockError) throw error;

    throwMockError({
      message: error?.message || "Mock API error",
      error_code: "mock_error",
    }, 500);
  }
}

export { subscribeToQueueMock };

export const mockClient = {
  request: mockRequest,
  subscribeToQueue: subscribeToQueueMock,
};
