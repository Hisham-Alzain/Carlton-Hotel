import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, throwMockError } from "../envelope.js";
import { getQueueRecords, updateQueueItemStatus } from "./queue.js";
import { getRoomBoard, getReservationRecords, updateRoomStatus } from "./reservations.js";
import { getFolioById, listFolios, settlePayment, disputeLineItem } from "./folios.js";
import { listDepartureItems, updateDepartureItemStatus } from "./departureItems.js";

const baseDay = "2026-06-28T18:00:00.000Z";

function at(minutes) {
  return addMinutesUtc(baseDay, minutes);
}

const GROUP_ORDER = ["front_office", "rooms", "finance", "operations", "handover"];

const GROUP_LABELS = {
  front_office: "Front Office",
  rooms: "Rooms",
  finance: "Finance",
  operations: "Operations",
  handover: "Handover",
};

const BLOCKER_OWNER_LABELS = {
  folio_balance: "Finance",
  folio_dispute: "Finance",
  room_status: "Housekeeping",
  departure_request: "Front Office",
  queue_item: "Operations",
};

const BLOCKER_REFERENCE_LABELS = {
  folio_balance_1003: "FOL-1003",
  folio_dispute_1003: "FOL-1003",
  room_303: "ROOM-303",
  departure_ds_4001: "DEP-4001",
  queue_q_2004: "TK-2004",
};

const BLOCKER_SOURCE_LABELS = {
  folio_balance: "Folio",
  folio_dispute: "Folio",
  room_status: "Room",
  departure_request: "Departure",
  queue_item: "Queue",
};

const CHECKLIST_SEED = [
  {
    id: "check_cash",
    group: "finance",
    label: {
      en: "Verify cash drawer balance",
      ar: "التحقق من رصيد صندوق النقد",
    },
    detail: {
      en: "Cash count was logged by reception earlier in the shift.",
      ar: "تم تسجيل عدّ النقد من قبل الاستقبال في وقت سابق من المناوبة.",
    },
    checked: true,
    linked_blocker_id: null,
    completed_at: at(15),
    completed_by: "Reception",
  },
  {
    id: "check_snapshot",
    group: "front_office",
    label: {
      en: "Load the night audit snapshot",
      ar: "تحميل لقطة تدقيق الليل",
    },
    detail: {
      en: "Pull the latest room, folio, and blocker state before closeout.",
      ar: "اسحب آخر حالة للغرف والحسابات والعوائق قبل الإغلاق.",
    },
    checked: true,
    linked_blocker_id: null,
    completed_at: at(16),
    completed_by: "System",
  },
  {
    id: "check_due_outs",
    group: "front_office",
    label: {
      en: "Confirm due-out folios are settled",
      ar: "تأكيد تسوية حسابات المغادرة",
    },
    detail: {
      en: "Room 701 is still carrying a departure balance.",
      ar: "ما زالت غرفة 701 تحمل رصيد مغادرة.",
    },
    checked: false,
    linked_blocker_id: "folio_balance_1003",
    completed_at: null,
    completed_by: null,
  },
  {
    id: "check_disputes",
    group: "finance",
    label: {
      en: "Clear disputed folio items",
      ar: "إزالة البنود محل الاعتراض من الحساب",
    },
    detail: {
      en: "Review minibar and incident charges before locking the day.",
      ar: "راجع رسوم الميني بار والحوادث قبل إقفال اليوم.",
    },
    checked: false,
    linked_blocker_id: "folio_dispute_1003",
    completed_at: null,
    completed_by: null,
  },
  {
    id: "check_rooms",
    group: "rooms",
    label: {
      en: "Release rooms after inspection",
      ar: "إتاحة الغرف بعد الفحص",
    },
    detail: {
      en: "Room 303 still needs a release or maintenance escalation.",
      ar: "الغرفة 303 ما زالت تحتاج إتاحة أو تصعيدًا إلى الصيانة.",
    },
    checked: false,
    linked_blocker_id: "room_303",
    completed_at: null,
    completed_by: null,
  },
  {
    id: "check_late_checkout",
    group: "front_office",
    label: {
      en: "Close late checkout requests",
      ar: "إقفال طلبات المغادرة المتأخرة",
    },
    detail: {
      en: "Confirm approved extensions are logged before closeout.",
      ar: "تأكد من تسجيل التمديدات المعتمدة قبل الإغلاق.",
    },
    checked: false,
    linked_blocker_id: "departure_ds_4001",
    completed_at: null,
    completed_by: null,
  },
  {
    id: "check_queue",
    group: "operations",
    label: {
      en: "Resolve critical guest-facing tickets",
      ar: "حل التذاكر الحرجة المرتبطة بالضيف",
    },
    detail: {
      en: "The folio discrepancy ticket tied to room 701 is still open.",
      ar: "تذكرة فرق الحساب المرتبطة بالغرفة 701 ما زالت مفتوحة.",
    },
    checked: false,
    linked_blocker_id: "queue_q_2004",
    completed_at: null,
    completed_by: null,
  },
  {
    id: "check_note",
    group: "handover",
    label: {
      en: "Record the morning handover note",
      ar: "تسجيل ملاحظة التسليم للمناوبة الصباحية",
    },
    detail: {
      en: "Capture unresolved follow-ups for the next supervisor.",
      ar: "دوّن المتابعات غير المكتملة للمشرف التالي.",
    },
    checked: false,
    linked_blocker_id: null,
    completed_at: null,
    completed_by: null,
  },
  {
    id: "check_lock",
    group: "handover",
    label: {
      en: "Lock the audit once everything is clear",
      ar: "إقفال التدقيق بعد اكتمال كل شيء",
    },
    detail: {
      en: "Final sign-off should happen only after all blockers are gone.",
      ar: "يجب أن تتم الموافقة النهائية بعد زوال جميع العوائق.",
    },
    checked: false,
    linked_blocker_id: null,
    completed_at: null,
    completed_by: null,
  },
];

const BLOCKER_DEFINITIONS = [
  {
    id: "folio_balance_1003",
    type: "folio_balance",
    severity: "critical",
    group: "finance",
    source: {
      en: "Folio",
      ar: "الحساب",
    },
    title: {
      en: "Room 701 folio balance remains open",
      ar: "ما زال رصيد غرفة 701 مفتوحًا",
    },
    detail: {
      en: "Dana Abboud still has an unpaid balance on folio fol_1003.",
      ar: "ما زالت دانا عبود تملك رصيدًا غير مسدد على الحساب fol_1003.",
    },
    room_number: "701",
    reservation_id: "res_1003",
    folio_id: "fol_1003",
    linked_checklist_id: "check_due_outs",
    action: {
      en: "Settle balance",
      ar: "تسوية الرصيد",
    },
  },
  {
    id: "folio_dispute_1003",
    type: "folio_dispute",
    severity: "warning",
    group: "finance",
    source: {
      en: "Folio",
      ar: "الحساب",
    },
    title: {
      en: "Minibar charge on room 701 is disputed",
      ar: "رسوم الميني بار في غرفة 701 محل اعتراض",
    },
    detail: {
      en: "Line item li_1003_3 must be cleared before lock.",
      ar: "يجب إزالة البند li_1003_3 قبل الإقفال.",
    },
    room_number: "701",
    reservation_id: "res_1003",
    folio_id: "fol_1003",
    line_item_id: "li_1003_3",
    linked_checklist_id: "check_disputes",
    action: {
      en: "Clear dispute",
      ar: "إزالة الاعتراض",
    },
  },
  {
    id: "room_303",
    type: "room_status",
    severity: "warning",
    group: "rooms",
    source: {
      en: "Rooms",
      ar: "الغرف",
    },
    title: {
      en: "Room 303 is still marked cleaning",
      ar: "الغرفة 303 ما زالت قيد التنظيف",
    },
    detail: {
      en: "Night audit needs the room released or escalated to maintenance.",
      ar: "يجب إتاحة الغرفة أو تصعيدها إلى الصيانة خلال التدقيق.",
    },
    room_number: "303",
    room_id: "room_303",
    linked_checklist_id: "check_rooms",
    action: {
      en: "Release room",
      ar: "إتاحة الغرفة",
    },
  },
  {
    id: "departure_ds_4001",
    type: "departure_request",
    severity: "info",
    group: "front_office",
    source: {
      en: "Departures",
      ar: "المغادرات",
    },
    title: {
      en: "Late checkout request is unresolved for room 701",
      ar: "طلب المغادرة المتأخرة للغرفة 701 ما زال مفتوحًا",
    },
    detail: {
      en: "Departure service ds_4001 is still open.",
      ar: "خدمة المغادرة ds_4001 ما زالت مفتوحة.",
    },
    room_number: "701",
    departure_id: "ds_4001",
    linked_checklist_id: "check_late_checkout",
    action: {
      en: "Resolve request",
      ar: "حل الطلب",
    },
  },
  {
    id: "queue_q_2004",
    type: "queue_item",
    severity: "critical",
    group: "operations",
    source: {
      en: "Queue",
      ar: "القائمة",
    },
    title: {
      en: "Support ticket for folio discrepancy remains open",
      ar: "تذكرة فرق الحساب ما زالت مفتوحة",
    },
    detail: {
      en: "Critical ticket q_2004 is tied to room 701.",
      ar: "التذكرة الحرجة q_2004 مرتبطة بالغرفة 701.",
    },
    room_number: "701",
    queue_id: "q_2004",
    linked_checklist_id: "check_queue",
    action: {
      en: "Resolve ticket",
      ar: "حل التذكرة",
    },
  },
];

const HANDOFF_NOTES_SEED = [
  {
    id: "handoff_001",
    author: "Omar Mansour",
    posted_at: at(10),
    note: "Room 303 is still pending release after final inspection.",
  },
  {
    id: "handoff_002",
    author: "Mira Haddad",
    posted_at: at(12),
    note: "Room 701 has a disputed minibar charge and an open balance.",
  },
  {
    id: "handoff_003",
    author: "Layth Saleh",
    posted_at: at(14),
    note: "Late checkout approval for room 701 is still waiting on closeout.",
  },
];

const auditSeed = {
  id: "night_audit_20260628",
  property_day: "2026-06-28",
  opened_at: at(0),
  closed_at: null,
  closed_by: null,
  status: "in_progress",
  history: [
    {
      id: "audit_evt_open",
      kind: "opened",
      label: {
        en: "Audit opened",
        ar: "تم فتح التدقيق",
      },
      detail: {
        en: "Night audit workspace initialized for the current property day.",
        ar: "تم تهيئة مساحة تدقيق الليل ليوم التشغيل الحالي.",
      },
      at: at(2),
      actor: "System",
    },
    {
      id: "audit_evt_snapshot",
      kind: "snapshot",
      label: {
        en: "Snapshot loaded",
        ar: "تم تحميل اللقطة",
      },
      detail: {
        en: "Room, folio, and blocker data were pulled for review.",
        ar: "تم سحب بيانات الغرف والحسابات والعوائق للمراجعة.",
      },
      at: at(8),
      actor: "System",
    },
  ],
  checklist: cloneMockData(CHECKLIST_SEED),
  handoff_notes: cloneMockData(HANDOFF_NOTES_SEED),
  blocker_resolutions: {},
};

let auditState = cloneMockData(auditSeed);
let actionSequence = 1;

function nextHistoryId(prefix = "audit_evt") {
  const id = `${prefix}_${String(actionSequence).padStart(3, "0")}`;
  actionSequence += 1;
  return id;
}

function getChecklistItem(id) {
  return auditState.checklist.find((item) => item.id === id) || null;
}

function getBlockerDefinition(id) {
  return BLOCKER_DEFINITIONS.find((definition) => definition.id === id) || null;
}

function getCurrentFolio(definition) {
  if (definition.folio_id) {
    return getFolioById(definition.folio_id);
  }

  if (definition.reservation_id) {
    const reservationFolio = listFolios({ reservation_id: definition.reservation_id, per_page: 1 }).items?.[0] || null;
    return reservationFolio;
  }

  return null;
}

function buildBlockerSnapshot(definition) {
  let resolved = false;
  let summary = "";
  let resolution_note = null;
  let resolved_by = null;
  let resolved_at = null;

  const resolution = auditState.blocker_resolutions?.[definition.id] || null;

  switch (definition.type) {
    case "folio_balance": {
      const folio = getCurrentFolio(definition);
      const balance = folio?.balance || 0;
      resolved = balance <= 0;
      summary = resolved ? "Balance settled" : `Open balance ${balance}`;
      break;
    }
    case "folio_dispute": {
      const folio = getCurrentFolio(definition);
      const disputedItem = folio?.line_items?.find((lineItem) => lineItem.id === definition.line_item_id) || null;
      resolved = !disputedItem?.disputed;
      summary = resolved ? "No disputed items" : `Disputed item ${definition.line_item_id}`;
      break;
    }
    case "room_status": {
      const roomBoard = getRoomBoard();
      const room = roomBoard.rooms.find((roomItem) => roomItem.id === definition.room_id) || null;
      resolved = room ? room.status !== "cleaning" : false;
      summary = room ? `Room status ${room.status}` : "Room not found";
      break;
    }
    case "departure_request": {
      const departure = listDepartureItems({ per_page: 100 }).items.find((item) => item.id === definition.departure_id) || null;
      resolved = departure ? departure.status === "resolved" : false;
      summary = departure ? `Request ${departure.status}` : "Departure request missing";
      break;
    }
    case "queue_item": {
      const queueItem = getQueueRecords().find((item) => item.id === definition.queue_id) || null;
      resolved = queueItem ? ["resolved", "closed"].includes(queueItem.status) : false;
      summary = queueItem ? `Ticket ${queueItem.status}` : "Queue item missing";
      break;
    }
    default:
      summary = "Unknown blocker";
  }

  if (resolution) {
    resolution_note = resolution.note || null;
    resolved_by = resolution.actor || null;
    resolved_at = resolution.at || null;
  }

  return {
    id: definition.id,
    type: definition.type,
    severity: definition.severity,
    group: definition.group,
    source: BLOCKER_SOURCE_LABELS[definition.type] || "Queue",
    source_label: definition.source,
    owner: BLOCKER_OWNER_LABELS[definition.type] || "Operations",
    reference: BLOCKER_REFERENCE_LABELS[definition.id] || definition.id.toUpperCase(),
    title: definition.title.en,
    title_ar: definition.title.ar,
    detail: definition.detail.en,
    detail_ar: definition.detail.ar,
    summary,
    room_number: definition.room_number || null,
    status: resolved ? "resolved" : "open",
    action: definition.action.en,
    action_ar: definition.action.ar,
    linked_checklist_id: definition.linked_checklist_id || null,
    resolution_note,
    resolved_by,
    resolved_at,
  };
}

function buildRoomRows() {
  const board = getRoomBoard();
  const reservations = getReservationRecords();
  const reservationByRoom = new Map(
    reservations
      .filter((reservation) => ["checked_in", "due_out"].includes(reservation.status) && reservation.assigned_room_id)
      .map((reservation) => [reservation.assigned_room_id, reservation]),
  );

  return board.rooms.map((room) => {
    const reservation = reservationByRoom.get(room.id) || null;

    return {
      id: room.id,
      number: room.number,
      floor: room.floor,
      room_type: room.room_type?.name || "",
      status: room.status,
      guest_name: room.occupant?.name || null,
      reservation_code: room.occupant?.reservation_code || null,
      departure_at: room.occupant?.departure_at || null,
      issue: room.status === "cleaning" ? "Inspection pending" : reservation?.status === "due_out" ? "Due out" : room.status === "occupied" ? "Occupied" : "Ready",
      reservation_id: reservation?.id || null,
    };
  });
}

function buildFolioRows() {
  const folioItems = listFolios({ per_page: 100 }).items;
  const reservations = getReservationRecords();
  const reservationById = new Map(reservations.map((reservation) => [reservation.id, reservation]));

  return folioItems
    .map((folio) => {
      const reservation = reservationById.get(folio.reservation_id) || null;
      const disputedLines = (folio.line_items || []).filter((lineItem) => lineItem.disputed).length;

      return {
        id: folio.id,
        guest_name: folio.guest_name || reservation?.guest?.full_name || "Unknown guest",
        room_number: folio.room_number || reservation?.assigned_room?.number || null,
        reservation_code: reservation?.reservation_code || folio.reservation_id,
        status: folio.status,
        balance: folio.balance || 0,
        disputed_lines: disputedLines,
        payment_count: folio.payments?.length || 0,
        reservation_id: folio.reservation_id,
      };
    })
    .sort((a, b) => b.balance - a.balance || a.guest_name.localeCompare(b.guest_name));
}

function buildSnapshot() {
  const roomBoard = getRoomBoard();
  const roomRows = buildRoomRows();
  const folioRows = buildFolioRows();
  const blockers = BLOCKER_DEFINITIONS.map(buildBlockerSnapshot).sort((a, b) => {
    const severityRank = { critical: 0, warning: 1, info: 2, neutral: 3 };
    return (severityRank[a.severity] ?? 9) - (severityRank[b.severity] ?? 9) || a.title.localeCompare(b.title);
  });

  const checks = auditState.checklist
    .map((item, index) => {
      const linkedBlocker = item.linked_blocker_id ? blockers.find((blocker) => blocker.id === item.linked_blocker_id) || null : null;
      const checked = item.checked || linkedBlocker?.status === "resolved";
      const groupLabel = GROUP_LABELS[item.group] || item.group;
      const completedAt = item.completed_at || linkedBlocker?.resolved_at || null;
      const completedBy = item.completed_by || linkedBlocker?.resolved_by || null;

      return {
        id: item.id,
        order: index + 1,
        group: item.group,
        group_label: groupLabel,
        label: item.label.en,
        label_ar: item.label.ar,
        owner: groupLabel,
        note: item.detail.en,
        note_ar: item.detail.ar,
        status: checked ? "done" : "pending",
        completed_at: checked ? completedAt : null,
        completed_by: checked ? completedBy : null,
        linked_blocker_id: item.linked_blocker_id || null,
        linked_blocker: linkedBlocker,
      };
    })
    .sort((a, b) => {
      const groupRank = GROUP_ORDER.indexOf(a.group) - GROUP_ORDER.indexOf(b.group);
      return groupRank || a.order - b.order;
    });

  const checklistDone = checks.filter((item) => item.status === "done").length;
  const openBlockers = blockers.filter((blocker) => blocker.status === "open");
  const canClose = openBlockers.length === 0 && checklistDone === checks.length && auditState.status !== "closed";
  const status = auditState.status === "closed" ? "closed" : canClose ? "ready_to_close" : "needs_attention";

  const openBalance = folioRows
    .filter((row) => row.balance > 0)
    .reduce((sum, row) => sum + row.balance, 0);

  const disputedLines = folioRows.reduce((sum, row) => sum + row.disputed_lines, 0);
  const occupiedRooms = roomBoard.summary.occupied;
  const unsettledFolios = folioRows.filter((row) => row.balance > 0 || row.disputed_lines > 0).length;
  const roomRevenue = getReservationRecords()
    .filter((reservation) => ["checked_in", "due_out", "arriving_today"].includes(reservation.status))
    .reduce((sum, reservation) => sum + (reservation.nightly_rate || 0), 0);
  const incidentalsRevenue = folioRows.reduce((sum, row) => {
    const folio = getFolioById(row.id);
    const incidentalTotal = (folio?.line_items || [])
      .filter((lineItem) => !lineItem.disputed && lineItem.category !== "room")
      .reduce((innerSum, lineItem) => innerSum + lineItem.amount * (lineItem.quantity || 1), 0);
    return sum + incidentalTotal;
  }, 0);
  const expectedCashDrop = roomRevenue + incidentalsRevenue;
  const recordedCashDrop = Math.max(0, expectedCashDrop - 120);
  const pendingPostings = getQueueRecords().filter((item) => ["open", "in_progress", "waiting_guest"].includes(item.status)).length;
  const handoffNotes = cloneMockData(auditState.handoff_notes || []);

  return {
    id: auditState.id,
    business_date: auditState.property_day,
    property_day: auditState.property_day,
    shift_window: "18:00-06:00",
    opened_at: auditState.opened_at,
    closed_at: auditState.closed_at,
    closed_by: auditState.closed_by,
    status,
    checks,
    checklist: checks,
    blockers,
    handoff_notes: handoffNotes,
    totals: {
      occupied_rooms: occupiedRooms,
      unsettled_folios: unsettledFolios,
      room_revenue: roomRevenue,
      cash_variance: expectedCashDrop - recordedCashDrop,
      expected_cash_drop: expectedCashDrop,
      recorded_cash_drop: recordedCashDrop,
      incidentals_revenue: incidentalsRevenue,
      pending_postings: pendingPostings,
    },
    rooms: {
      summary: {
        total: roomBoard.summary.total,
        available: roomBoard.summary.available,
        occupied: roomBoard.summary.occupied,
        cleaning: roomBoard.summary.cleaning,
        due_out: getReservationRecords().filter((reservation) => reservation.status === "due_out").length,
      },
      rows: roomRows,
    },
    folios: {
      summary: {
        total: folioRows.length,
        open: folioRows.filter((row) => row.status === "open").length,
        disputed: folioRows.filter((row) => row.status === "disputed").length,
        settled: folioRows.filter((row) => row.status === "settled").length,
        open_balance: openBalance,
        disputed_lines: disputedLines,
      },
      rows: folioRows,
    },
    readiness: {
      checklist_done: checklistDone,
      checklist_total: checks.length,
      blockers_open: openBlockers.length,
      can_close: canClose,
    },
    activity: cloneMockData(auditState.history.slice(0, 8)),
  };
}

function recordHistory(kind, label, detail, actor) {
  auditState.history.unshift({
    id: nextHistoryId(),
    kind,
    label,
    detail,
    at: at(15 + actionSequence * 3),
    actor: actor || "Night Audit",
  });
}

function completeChecklist(linkedChecklistId, actor) {
  if (!linkedChecklistId) return;

  const item = getChecklistItem(linkedChecklistId);
  if (!item) return;

  if (!item.checked) {
    item.checked = true;
    item.completed_at = at(20 + actionSequence * 2);
    item.completed_by = actor || "Night Audit";
    recordHistory(
      "checklist",
      {
        en: "Checklist item completed",
        ar: "تم إكمال بند القائمة",
      },
      {
        en: item.label.en,
        ar: item.label.ar,
      },
      actor || "Night Audit",
    );
  }
}

export function getNightAuditBlockerDefinition(id) {
  return cloneMockData(getBlockerDefinition(id));
}

export function getNightAuditSnapshot() {
  return cloneMockData(buildSnapshot());
}

export function updateNightAuditCheck(id, payload = {}) {
  const actor = payload.actor || "Night Audit";
  if (auditState.status === "closed") {
    throwMockError({
      message: "Night audit is already closed.",
      error_code: "night_audit_closed",
    }, 422);
  }

  const item = getChecklistItem(id);

  if (!item) {
    throwMockError({
      message: "Checklist item not found.",
      error_code: "night_audit_checklist_not_found",
    }, 404);
  }

  const nextChecked = typeof payload.done === "boolean" ? payload.done : !item.checked;
  item.checked = nextChecked;
  item.completed_at = nextChecked ? at(20 + actionSequence * 2) : null;
  item.completed_by = nextChecked ? actor : null;

  recordHistory(
    "checklist",
    nextChecked
      ? {
          en: "Checklist item checked",
          ar: "تم وضع علامة على بند القائمة",
        }
      : {
          en: "Checklist item unchecked",
          ar: "تم إلغاء علامة بند القائمة",
        },
    {
      en: item.label.en,
      ar: item.label.ar,
    },
    actor,
  );

  return getNightAuditSnapshot();
}

export const toggleNightAuditChecklistItem = updateNightAuditCheck;

export function resolveNightAuditBlocker(id, payload = {}) {
  const actor = payload.actor || "Night Audit";
  const note = typeof payload.note === "string" ? payload.note.trim() : "";

  if (auditState.status === "closed") {
    throwMockError({
      message: "Night audit is already closed.",
      error_code: "night_audit_closed",
    }, 422);
  }

  const blocker = getBlockerDefinition(id);

  if (!blocker) {
    throwMockError({
      message: "Blocker not found.",
      error_code: "night_audit_blocker_not_found",
    }, 404);
  }

  if (!note) {
    throwMockError({
      message: "Resolution note is required.",
      error_code: "validation_failed",
      errors: { note: ["Add a short closeout note before resolving."] },
    }, 422);
  }

  switch (blocker.type) {
    case "folio_balance": {
      const folio = getFolioById(blocker.folio_id);
      if (folio && folio.balance > 0) {
        settlePayment(blocker.folio_id, {
          method: "cash",
          amount: folio.balance,
          reference: `NIGHT-AUDIT-${blocker.id}`,
        });
      }
      break;
    }
    case "folio_dispute": {
      const folio = getFolioById(blocker.folio_id);
      const disputedItem = folio?.line_items?.find((lineItem) => lineItem.id === blocker.line_item_id);
      if (disputedItem?.disputed) {
        disputeLineItem(blocker.folio_id, blocker.line_item_id);
      }
      break;
    }
    case "room_status": {
      const roomBoard = getRoomBoard();
      const room = roomBoard.rooms.find((roomItem) => roomItem.id === blocker.room_id);
      if (room && room.status === "cleaning") {
        updateRoomStatus(blocker.room_id, "available");
      }
      break;
    }
    case "departure_request": {
      const departure = listDepartureItems({ per_page: 100 }).items.find((item) => item.id === blocker.departure_id);
      if (departure && departure.status !== "resolved") {
        updateDepartureItemStatus(blocker.departure_id, "resolved");
      }
      break;
    }
    case "queue_item": {
      const queueItem = getQueueRecords().find((item) => item.id === blocker.queue_id);
      if (queueItem && !["resolved", "closed"].includes(queueItem.status)) {
        updateQueueItemStatus(blocker.queue_id, "resolved");
      }
      break;
    }
    default:
      throwMockError({
        message: "Unsupported blocker type.",
        error_code: "night_audit_blocker_unsupported",
      }, 422);
  }

  auditState.blocker_resolutions[blocker.id] = {
    actor,
    note,
    at: at(40 + actionSequence * 2),
  };

  auditState.handoff_notes.unshift({
    id: nextHistoryId("handoff"),
    author: actor,
    posted_at: at(42 + actionSequence * 2),
    note,
  });

  completeChecklist(blocker.linked_checklist_id, actor);

  recordHistory(
    "blocker",
    {
      en: "Blocker resolved",
      ar: "تم حل العائق",
    },
    {
      en: blocker.title.en,
      ar: blocker.title.ar,
    },
    actor,
  );

  return getNightAuditSnapshot();
}

export function closeNightAudit(actor = "Night Audit") {
  if (auditState.status === "closed") {
    return getNightAuditSnapshot();
  }

  const snapshot = buildSnapshot();

  if (!snapshot.readiness.can_close) {
    throwMockError({
      message: "Night audit is not ready to close.",
      error_code: "night_audit_not_ready",
      errors: {
        blockers: snapshot.readiness.blockers_open > 0 ? ["Resolve all blockers first."] : [],
        checklist: snapshot.readiness.checklist_done < snapshot.readiness.checklist_total ? ["Complete the checklist first."] : [],
      },
    }, 422);
  }

  auditState.status = "closed";
  auditState.closed_at = at(60 + actionSequence * 2);
  auditState.closed_by = actor;

  recordHistory(
    "close",
    {
      en: "Audit closed",
      ar: "تم إغلاق التدقيق",
    },
    {
      en: "Night audit lock completed.",
      ar: "تم إكمال قفل تدقيق الليل.",
    },
    actor,
  );

  return getNightAuditSnapshot();
}

export function reopenNightAudit(actor = "Night Audit") {
  if (auditState.status !== "closed") {
    return getNightAuditSnapshot();
  }

  auditState.status = "in_progress";
  auditState.closed_at = null;
  auditState.closed_by = null;

  recordHistory(
    "reopen",
    {
      en: "Audit reopened",
      ar: "تم إعادة فتح التدقيق",
    },
    {
      en: "The closeout lock was released for additional review.",
      ar: "تم تحرير قفل الإغلاق لمراجعة إضافية.",
    },
    actor,
  );

  return getNightAuditSnapshot();
}

export function resetNightAuditMockData() {
  auditState = cloneMockData(auditSeed);
  actionSequence = 1;
}
