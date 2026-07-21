import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, matchesSearch, paginateItems, throwMockError } from "../envelope.js";

const baseTime = "2026-06-28T08:00:00.000Z";
const listeners = new Set();

function at(minutes) {
  return addMinutesUtc(baseTime, minutes);
}

function nowIso() {
  return new Date().toISOString();
}

function titleize(value) {
  return String(value || "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (match) => match.toUpperCase())
    .trim();
}

function ownerObject(owner, item, fallbackActor) {
  if (!owner) return null;

  if (typeof owner === "string") {
    return {
      id: owner.toLowerCase().replace(/\s+/g, "_"),
      name: owner,
      title: titleize(item?.department || "Staff"),
      team: titleize(item?.department || "Operations"),
    };
  }

  return {
    id: owner.id || owner.value || owner.slug || owner.name?.toLowerCase().replace(/\s+/g, "_") || null,
    name: owner.name || owner.label || owner.title || fallbackActor || null,
    title: owner.title || owner.role || titleize(item?.department || "Staff"),
    team: owner.team || owner.department || titleize(item?.department || "Operations"),
  };
}

function createTrailEntry({ kind, label, detail, actor, at: timestamp }) {
  return {
    id: `trail_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`,
    kind,
    label,
    detail,
    actor: actor || null,
    at: timestamp || nowIso(),
  };
}

function createRecoveryAction({ action_type, label, detail, actor, amount, currency, note, at: timestamp }) {
  return {
    id: `recovery_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`,
    action_type,
    label,
    detail,
    actor: actor || null,
    amount: Number.isFinite(Number(amount)) ? Number(amount) : 0,
    currency: currency || "USD",
    note: note || null,
    at: timestamp || nowIso(),
  };
}

function createRecoveryState({
  stage,
  owner,
  escalationLevel = 0,
  escalationTarget = null,
  objective,
  nextStep,
  playbook = [],
  actions = [],
  trail = [],
  compensationTotal = 0,
  compensationCurrency = "USD",
  formDefaults = {},
}) {
  return {
    stage,
    owner,
    escalation_level: escalationLevel,
    escalation_target: escalationTarget,
    objective,
    next_step: nextStep,
    playbook,
    actions,
    trail,
    compensation_total: compensationTotal,
    compensation_currency: compensationCurrency,
    form_defaults: formDefaults,
  };
}

const queueSeed = [
  {
    id: "q_2001",
    reference: "SR-2001",
    type: "service_request",
    source: "guest_app",
    department: "housekeeping",
    priority: "urgent",
    status: "open",
    room_number: "405",
    guest_name: "Samir Khoury",
    reservation_id: "res_1002",
    title: "Extra towels requested",
    description: "Guest requested two bath towels before checkout.",
    assigned_to: null,
    created_at: at(-42),
    updated_at: at(-42),
    due_at: at(-12),
  },
  {
    id: "q_2002",
    reference: "SR-2002",
    type: "service_request",
    source: "front_desk",
    department: "kitchen",
    priority: "normal",
    status: "in_progress",
    room_number: "701",
    guest_name: "Dana Abboud",
    reservation_id: "res_1003",
    title: "Breakfast tray follow-up",
    description: "Confirm gluten-free tray was sent to family suite.",
    assigned_to: "Mira Haddad",
    created_at: at(-75),
    updated_at: at(-20),
    due_at: at(15),
  },
  {
    id: "q_2003",
    reference: "TK-3101",
    type: "support_ticket",
    source: "whatsapp",
    department: "concierge",
    priority: "high",
    status: "in_progress",
    room_number: null,
    guest_name: "Amal Nassar",
    reservation_id: "res_1004",
    title: "Airport transfer question",
    description: "Upcoming guest asks whether late-night pickup is available.",
    assigned_to: "Omar Nasser",
    guest_profile: {
      tier: "platinum",
      nationality: "Lebanese",
      language: "English",
      preferences: ["Late arrival handling", "Text confirmation"],
    },
    recovery: createRecoveryState({
      stage: "active",
      owner: { id: "concierge", name: "Omar Nasser", title: "Operations Concierge", team: "Guest Experience" },
      escalationLevel: 0,
      objective: "Lock down a trusted late-night transfer and remove arrival uncertainty before the guest lands.",
      nextStep: "Send driver confirmation and pickup point after the booking is locked.",
      playbook: [
        { id: "pb_3001", label: "Confirm flight time", status: "done", note: "Pickup requested for 23:00." },
        { id: "pb_3002", label: "Quote and approve rate", status: "done", note: "Quoted $35 one-way." },
        { id: "pb_3003", label: "Reserve vehicle", status: "active", note: "Car held for the arrival window." },
        { id: "pb_3004", label: "Send confirmation", status: "pending", note: "Guest to receive driver details." },
      ],
      actions: [
        createRecoveryAction({
          action_type: "transport_hold",
          label: "Transfer reserved",
          detail: "Vehicle held for the late-night arrival window and main entrance pickup.",
          actor: "Concierge",
          amount: 35,
          currency: "USD",
          at: "2026-06-28T08:03:00.000Z",
        }),
      ],
      trail: [
        createTrailEntry({
          kind: "owner",
          label: "Claimed by Concierge",
          detail: "Omar Nasser took ownership of the transfer request.",
          actor: "Omar Nasser",
          at: "2026-06-28T07:56:00.000Z",
        }),
      ],
      compensationTotal: 0,
      compensationCurrency: "USD",
      formDefaults: {
        action_type: "transport_hold",
        amount: "35",
        owner_id: "concierge",
        escalation_level: "0",
        escalation_owner_id: "concierge",
      },
    }),
    created_at: at(-18),
    updated_at: at(-4),
    due_at: at(42),
  },
  {
    id: "q_2004",
    reference: "TK-3102",
    type: "support_ticket",
    source: "email",
    department: "reception",
    priority: "critical",
    status: "in_progress",
    room_number: "701",
    guest_name: "Dana Abboud",
    reservation_id: "res_1003",
    title: "Folio discrepancy",
    description: "Guest disputes minibar charge before checkout.",
    assigned_to: "Nadia Hariri",
    guest_profile: {
      tier: "gold",
      nationality: "Jordanian",
      language: "English",
      preferences: ["Quiet room", "Late checkout", "Fast billing confirmation"],
    },
    recovery: createRecoveryState({
      stage: "active",
      owner: { id: "duty_manager", name: "Nadia Hariri", title: "Duty Manager", team: "Executive Office" },
      escalationLevel: 2,
      escalationTarget: { id: "front_desk_supervisor", name: "Omar Mansour", title: "Front Desk Supervisor", team: "Front Office" },
      objective: "Protect the departure experience, reverse the incorrect charge, and close the loop before checkout.",
      nextStep: "Call the guest back after the credit posts and confirm the folio is clean.",
      playbook: [
        { id: "pb_4001", label: "Verify folio charge", status: "done", note: "Reviewed minibar posting against housekeeping log." },
        { id: "pb_4002", label: "Reverse incorrect item", status: "done", note: "Removed the 45 SAR minibar charge." },
        { id: "pb_4003", label: "Send apology amenity", status: "done", note: "Fruit plate and still water dispatched to room 701." },
        { id: "pb_4004", label: "Manager callback", status: "active", note: "Duty manager to confirm the correction before checkout." },
        { id: "pb_4005", label: "Close after confirmation", status: "pending", note: "Final follow-up once the guest acknowledges." },
      ],
      actions: [
        createRecoveryAction({
          action_type: "folio_credit",
          label: "Folio credit posted",
          detail: "Removed the incorrect minibar charge from the guest folio.",
          actor: "Reception",
          amount: 45,
          currency: "SAR",
          at: "2026-06-28T08:04:00.000Z",
        }),
        createRecoveryAction({
          action_type: "courtesy_amenity",
          label: "Courtesy amenity arranged",
          detail: "Sent fruit plate and still water to 701 with a handwritten apology card.",
          actor: "Guest Relations",
          amount: 0,
          currency: "SAR",
          at: "2026-06-28T08:07:00.000Z",
        }),
      ],
      trail: [
        createTrailEntry({
          kind: "owner",
          label: "Claimed by Front Desk",
          detail: "Omar Mansour opened the case and verified the dispute.",
          actor: "Omar Mansour",
          at: "2026-06-28T07:58:00.000Z",
        }),
        createTrailEntry({
          kind: "escalation",
          label: "Escalated to Duty Manager",
          detail: "Charge reversal required manager review before posting.",
          actor: "Omar Mansour",
          at: "2026-06-28T08:02:00.000Z",
        }),
        createTrailEntry({
          kind: "owner",
          label: "Handoff to Duty Manager",
          detail: "Nadia Hariri now oversees the recovery until confirmation.",
          actor: "Nadia Hariri",
          at: "2026-06-28T08:05:00.000Z",
        }),
      ],
      compensationTotal: 45,
      compensationCurrency: "SAR",
      formDefaults: {
        action_type: "folio_credit",
        amount: "45",
        owner_id: "duty_manager",
        escalation_level: "2",
        escalation_owner_id: "duty_manager",
      },
    }),
    created_at: at(-9),
    updated_at: at(-1),
    due_at: at(6),
  },
];

const queueAssignableStaff = [
  {
    id: "staff_3001",
    name: "Omar Mansour",
    title: "Front Desk Supervisor",
    department: "reception",
    shift: "Evening",
  },
  {
    id: "staff_3002",
    name: "Mira Haddad",
    title: "Kitchen Coordinator",
    department: "kitchen",
    shift: "Day",
  },
  {
    id: "staff_3003",
    name: "Layth Saleh",
    title: "Housekeeping Lead",
    department: "housekeeping",
    shift: "Evening",
  },
  {
    id: "staff_3004",
    name: "Omar Nasser",
    title: "Operations Concierge",
    department: "concierge",
    shift: "Evening",
  },
  {
    id: "staff_3005",
    name: "Karim Azzam",
    title: "Events Sales Manager",
    department: "sales",
    shift: "Day",
  },
  {
    id: "staff_3006",
    name: "Hala Farouk",
    title: "Duty Manager",
    department: "executive",
    shift: "Evening",
  },
];

let queueItems = cloneMockData(queueSeed);
const queueAssignees = [
  { id: "staff_reception", name: "Omar Mansour", department: "reception", coverage: ["reception", "front_desk"] },
  { id: "staff_kitchen", name: "Mira Haddad", department: "kitchen", coverage: ["kitchen"] },
  { id: "staff_housekeeping", name: "Layth Saleh", department: "housekeeping", coverage: ["housekeeping"] },
  { id: "staff_concierge", name: "Omar Nasser", department: "concierge", coverage: ["concierge", "transport"] },
  { id: "staff_sales", name: "Karim Azzam", department: "sales", coverage: ["events"] },
];

function emitQueueChange(event) {
  const snapshot = cloneMockData(event);
  listeners.forEach((listener) => listener(snapshot));
}

function findQueueItem(id) {
  return queueItems.find((item) => item.id === id) || null;
}

function ensureRecoveryState(item) {
  if (!item.recovery) {
    item.recovery = createRecoveryState({
      stage: "active",
      owner: item.assigned_to ? ownerObject(item.assigned_to, item, item.assigned_to) : null,
      escalationLevel: 0,
      objective: "",
      nextStep: "",
      playbook: [],
      actions: [],
      trail: [],
      compensationTotal: 0,
      formDefaults: {},
    });
  }

  item.recovery.playbook = Array.isArray(item.recovery.playbook) ? item.recovery.playbook : [];
  item.recovery.actions = Array.isArray(item.recovery.actions) ? item.recovery.actions : [];
  item.recovery.trail = Array.isArray(item.recovery.trail) ? item.recovery.trail : [];
  item.recovery.form_defaults = item.recovery.form_defaults || {};
  item.recovery.compensation_total = Number(item.recovery.compensation_total || 0);
  item.recovery.escalation_level = Number(item.recovery.escalation_level || 0);

  return item.recovery;
}

function updateQueueItem(id, updater) {
  const item = findQueueItem(id);

  if (!item) {
    throwMockError({ message: "Queue item not found", error_code: "queue_item_not_found" }, 404);
  }

  const next = updater(item) || item;
  next.updated_at = nowIso();

  emitQueueChange({ type: "updated", item: next });
  return cloneMockData(next);
}

function recordTicketTrail(item, entry) {
  const recovery = ensureRecoveryState(item);
  recovery.trail.unshift(createTrailEntry(entry));
}

function recordTicketAction(item, action) {
  const recovery = ensureRecoveryState(item);
  const record = createRecoveryAction(action);
  recovery.actions.unshift(record);
  recovery.compensation_total = Number(recovery.compensation_total || 0) + Number(record.amount || 0);
  if (!recovery.compensation_currency && record.currency) {
    recovery.compensation_currency = record.currency;
  }
  if (record.amount > 0 && !recovery.last_compensation) {
    recovery.last_compensation = record.amount;
  }
  return record;
}

function syncSupportTicketOwner(item, owner, actorName, detail, kind = "owner") {
  const recovery = ensureRecoveryState(item);
  const ownerRecord = ownerObject(owner, item, actorName);

  if (ownerRecord) {
    item.assigned_to = ownerRecord.name;
    recovery.owner = ownerRecord;
    if (!item.assigned_to && ownerRecord.name) {
      item.assigned_to = ownerRecord.name;
    }
  }

  recordTicketTrail(item, {
    kind,
    label: kind === "escalation" ? `Escalated to ${ownerRecord?.name || "next owner"}` : `Assigned to ${ownerRecord?.name || "owner"}`,
    detail,
    actor: actorName || ownerRecord?.name,
  });

  item.status = item.status === "open" ? "in_progress" : item.status;
  recovery.stage = kind === "escalation" ? "escalated" : "active";

  return item;
}

function mapStatusToRecoveryStage(status) {
  switch (status) {
    case "waiting_guest":
      return "awaiting_guest";
    case "resolved":
      return "recovering";
    case "closed":
      return "closed";
    default:
      return "active";
  }
}

export function resetQueueMockData() {
  queueItems = cloneMockData(queueSeed);
  emitQueueChange({ type: "reset", item: null });
}

export function getQueueRecords() {
  return cloneMockData(queueItems);
}

export function listQueueItems(params = {}) {
  const filtered = queueItems
    .filter((item) => !params.type || item.type === params.type)
    .filter((item) => !params.status || item.status === params.status)
    .filter((item) => !params.department || item.department === params.department)
    .filter((item) => !params.priority || item.priority === params.priority)
    .filter((item) =>
      matchesSearch(
        [
          item.reference,
          item.title,
          item.guest_name,
          item.room_number,
          item.description,
          item.assigned_to,
          item.recovery?.objective,
          item.recovery?.next_step,
          item.recovery?.owner?.name,
        ],
        params.query
      )
    )
    .sort((a, b) => {
      const priorityRank = { critical: 0, urgent: 1, high: 2, normal: 3, low: 4 };
      return (priorityRank[a.priority] ?? 5) - (priorityRank[b.priority] ?? 5)
        || new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
    });

  return paginateItems(cloneMockData(filtered), params);
}

export function listAssignableQueueStaff(params = {}) {
  const filtered = queueAssignableStaff
    .filter((person) => !params.department || person.department === params.department)
    .filter((person) => matchesSearch([person.name, person.title, person.department, person.shift], params.query))
    .sort((a, b) => a.department.localeCompare(b.department) || a.name.localeCompare(b.name));

  return paginateItems(cloneMockData(filtered), params);
}

export function getQueueItemById(id) {
  const item = findQueueItem(id);
  return item ? cloneMockData(item) : null;
}

export function listQueueAssignees(department) {
  const filtered = department
    ? queueAssignees.filter((person) => person.coverage.includes(department) || person.department === department)
    : queueAssignees;
  return cloneMockData(filtered);
}

function resolveAssigneeName(assignee) {
  if (!assignee) return null;
  if (typeof assignee === "string") return assignee.trim() || null;

  if (typeof assignee === "object") {
    return String(assignee.name || assignee.label || assignee.value || "").trim() || null;
  }

  return null;
}

export function claimQueueItem(id, actorName = "Mock Staff") {
  return updateQueueItem(id, (item) => {
    item.assigned_to = actorName;
    item.status = item.status === "open" ? "in_progress" : item.status;
    if (item.type === "support_ticket" || item.recovery) {
      const recovery = ensureRecoveryState(item);
      recovery.owner = ownerObject({ name: actorName, title: titleize(item.department), team: titleize(item.department) }, item, actorName);
      recordTicketTrail(item, {
        kind: "owner",
        label: `Claimed by ${actorName}`,
        detail: "Ownership moved to the active staff member.",
        actor: actorName,
      });
      recovery.stage = mapStatusToRecoveryStage(item.status);
    }
    return item;
  });
}

export function assignQueueItem(id, assignee) {
  const assigneeName = resolveAssigneeName(assignee);

  if (!assigneeName) {
    throwMockError({
      message: "Assignee is required.",
      error_code: "validation_failed",
      errors: { assignee: ["Select a staff member."] },
    }, 422);
  }

  const rosterNames = new Set(queueAssignableStaff.map((person) => person.name));

  if (!rosterNames.has(assigneeName)) {
    throwMockError({
      message: "Choose a staff member from the roster.",
      error_code: "validation_failed",
      errors: { assignee: ["Choose an on-duty staff member."] },
    }, 422);
  }

  return updateQueueItem(id, (item) => {
    item.assigned_to = assigneeName;
    item.status = item.status === "open" ? "in_progress" : item.status;
    if (item.type === "support_ticket" || item.recovery) {
      const recovery = ensureRecoveryState(item);
      recovery.owner = ownerObject(assignee, item, item.assigned_to);
      recordTicketTrail(item, {
        kind: "owner",
        label: `Assigned to ${recovery.owner?.name || item.assigned_to}`,
        detail: "Staff ownership updated from the control desk.",
        actor: recovery.owner?.name || item.assigned_to,
      });
      recovery.stage = mapStatusToRecoveryStage(item.status);
    }
    return item;
  });
}

export function updateQueueItemStatus(id, status) {
  const allowedStatuses = ["open", "in_progress", "waiting_guest", "resolved", "closed"];

  if (!allowedStatuses.includes(status)) {
    throwMockError({
      message: "Unsupported queue status.",
      error_code: "validation_failed",
      errors: { status: ["Choose a valid queue status."] },
    }, 422);
  }

  return updateQueueItem(id, (item) => {
    item.status = status;
    if (item.type === "support_ticket" || item.recovery) {
      const recovery = ensureRecoveryState(item);
      recovery.stage = mapStatusToRecoveryStage(status);
      recordTicketTrail(item, {
        kind: "status",
        label: `Status set to ${titleize(status)}`,
        detail: "Operational status updated from the detail workspace.",
        actor: item.assigned_to || "Staff",
      });
    }
    return item;
  });
}

export function assignSupportTicketOwner(id, owner, actorName = "Mock Staff") {
  if (!owner || !(owner.name || owner.label || owner.title || owner.value || typeof owner === "string")) {
    throwMockError({
      message: "Owner is required.",
      error_code: "validation_failed",
      errors: { owner: ["Select a staff member."] },
    }, 422);
  }

  return updateQueueItem(id, (item) => {
    if (item.type !== "support_ticket") {
      throwMockError({ message: "Ticket not found", error_code: "ticket_not_found" }, 404);
    }

    syncSupportTicketOwner(
      item,
      owner,
      actorName,
      "Ownership reassigned from the ticket recovery desk.",
      "owner"
    );
    return item;
  });
}

export function escalateSupportTicket(id, payload = {}, actorName = "Mock Staff") {
  const level = Number(payload.level || payload.escalation_level || 0);
  if (![1, 2, 3].includes(level)) {
    throwMockError({
      message: "Unsupported escalation level.",
      error_code: "validation_failed",
      errors: { level: ["Choose escalation level 1, 2, or 3."] },
    }, 422);
  }

  const target = payload.target_owner || payload.owner || payload.assignee;
  if (!target) {
    throwMockError({
      message: "Escalation target is required.",
      error_code: "validation_failed",
      errors: { target_owner: ["Select a target owner."] },
    }, 422);
  }

  return updateQueueItem(id, (item) => {
    if (item.type !== "support_ticket") {
      throwMockError({ message: "Ticket not found", error_code: "ticket_not_found" }, 404);
    }

    const recovery = ensureRecoveryState(item);
    const targetOwner = ownerObject(target, item, actorName);

    item.assigned_to = targetOwner?.name || item.assigned_to;
    item.status = item.status === "open" ? "in_progress" : item.status;
    recovery.owner = targetOwner;
    recovery.escalation_level = level;
    recovery.escalation_target = targetOwner;
    recovery.stage = "escalated";
    recordTicketTrail(item, {
      kind: "escalation",
      label: `Escalated to level ${level}`,
      detail: payload.reason || payload.note || "Escalated for manager attention.",
      actor: actorName,
    });
    return item;
  });
}

export function logSupportTicketRecoveryAction(id, payload = {}, actorName = "Mock Staff") {
  if (!payload.action_type) {
    throwMockError({
      message: "Recovery action is required.",
      error_code: "validation_failed",
      errors: { action_type: ["Choose a recovery action."] },
    }, 422);
  }

  return updateQueueItem(id, (item) => {
    if (item.type !== "support_ticket") {
      throwMockError({ message: "Ticket not found", error_code: "ticket_not_found" }, 404);
    }

    const recovery = ensureRecoveryState(item);
    const action = recordTicketAction(item, {
      action_type: payload.action_type,
      label: payload.label || titleize(payload.action_type),
      detail: payload.detail || payload.note || payload.description || "Recovery action recorded from the detail workspace.",
      actor: actorName,
      amount: payload.amount,
      currency: payload.currency || item.currency || "USD",
      note: payload.note || null,
    });

    if (payload.follow_up) {
      recovery.next_step = payload.follow_up;
    }

    if (payload.owner) {
      recovery.owner = ownerObject(payload.owner, item, actorName) || recovery.owner;
    }

    item.status = item.status === "open" ? "in_progress" : item.status;
    recovery.stage = mapStatusToRecoveryStage(item.status);
    recordTicketTrail(item, {
      kind: "action",
      label: action.label,
      detail: action.detail,
      actor: actorName,
    });
    return item;
  });
}

export function subscribeToQueueMock(callback, options = {}) {
  if (typeof callback !== "function") {
    return () => {};
  }

  listeners.add(callback);

  if (options.emitInitial !== false) {
    callback({ type: "snapshot", items: getQueueRecords() });
  }

  return () => {
    listeners.delete(callback);
  };
}
