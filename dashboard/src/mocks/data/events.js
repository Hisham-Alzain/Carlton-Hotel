import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, matchesSearch, paginateItems, throwMockError } from "../envelope.js";

const baseDay = "2026-06-28T00:00:00.000Z";

function at(minutes) {
  return addMinutesUtc(baseDay, minutes);
}

function safeShift(value, minutes) {
  return value ? addMinutesUtc(value, minutes) : null;
}

function toNumber(value, fallback = 0) {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}

const VALID_STATUSES = ["rfp", "tentative", "confirmed", "cancelled"];

const eventsSeed = [
  {
    id: "ev_001",
    reference: "EVT-001",
    title: "Gulf Tech Conference 2026",
    client_company: "Gulf Tech Systems",
    contact_title: "Corporate Events Manager",
    event_type: "conference",
    status: "confirmed",
    contact_name: "Ahmad Nassif",
    contact_email_masked: "a***@gulftech.com",
    contact_phone_masked: "+971 *** 8822",
    event_date: at(5 * 1440),
    setup_date: at(4 * 1440),
    teardown_date: at(6 * 1440),
    expected_attendees: 120,
    guaranteed_attendees: 110,
    spaces_required: ["Grand Ballroom", "Breakout Room A", "Breakout Room B"],
    revenue_estimate: 18000,
    deposit_amount: 4500,
    deposit_paid: true,
    deposit_paid_at: at(3 * 1440),
    deposit_received_by: "Karim Azzam",
    notes: "AV setup required by 08:00. Catering: halal lunch x120.",
    created_at: at(-30 * 1440),
    updated_at: at(-2 * 1440),
    assigned_to: "Karim Azzam",
  },
  {
    id: "ev_002",
    reference: "EVT-002",
    title: "Al-Nassir Wedding Reception",
    client_company: "Al-Nassir Family",
    contact_title: "Family representative",
    event_type: "wedding",
    status: "confirmed",
    contact_name: "Sara Al-Nassir",
    contact_email_masked: "s***@gmail.com",
    contact_phone_masked: "+961 *** 4400",
    event_date: at(12 * 1440),
    setup_date: at(11 * 1440),
    teardown_date: at(13 * 1440),
    expected_attendees: 250,
    guaranteed_attendees: 240,
    spaces_required: ["Grand Ballroom", "Garden Terrace"],
    revenue_estimate: 32000,
    deposit_amount: 8000,
    deposit_paid: true,
    deposit_paid_at: at(8 * 1440),
    deposit_received_by: "Karim Azzam",
    notes: "Florist access from 09:00. Cake delivery 15:00.",
    created_at: at(-60 * 1440),
    updated_at: at(-5 * 1440),
    assigned_to: "Karim Azzam",
  },
  {
    id: "ev_003",
    reference: "EVT-003",
    title: "Pharma Exec Roundtable",
    client_company: "PharmaPlus Middle East",
    contact_title: "Regional administration lead",
    event_type: "corporate",
    status: "tentative",
    contact_name: "Leila Mansour",
    contact_email_masked: "l***@pharma.ae",
    contact_phone_masked: "+971 *** 5511",
    event_date: at(20 * 1440),
    setup_date: at(20 * 1440),
    teardown_date: at(20 * 1440),
    expected_attendees: 30,
    guaranteed_attendees: 24,
    spaces_required: ["Executive Boardroom"],
    revenue_estimate: 4500,
    deposit_amount: 1500,
    deposit_paid: false,
    notes: "Awaiting final headcount. May need AV upgrade.",
    created_at: at(-10 * 1440),
    updated_at: at(-1 * 1440),
    assigned_to: null,
  },
  {
    id: "ev_004",
    reference: "EVT-004",
    title: "Product Launch — Noor Cosmetics",
    client_company: "Noor Cosmetics",
    contact_title: "Brand director",
    event_type: "social",
    status: "rfp",
    contact_name: "Maya Khalil",
    contact_email_masked: "m***@noorcos.com",
    contact_phone_masked: "+961 *** 9933",
    event_date: at(35 * 1440),
    setup_date: at(35 * 1440),
    teardown_date: at(36 * 1440),
    expected_attendees: 80,
    guaranteed_attendees: 72,
    spaces_required: ["Rooftop Lounge"],
    revenue_estimate: 9000,
    deposit_amount: 2500,
    deposit_paid: false,
    notes: "Requires rooftop exclusivity. Brand install 06:00.",
    created_at: at(-3 * 1440),
    updated_at: at(-3 * 1440),
    assigned_to: null,
  },
  {
    id: "ev_005",
    reference: "EVT-005",
    title: "Annual Gala — Damascus Chamber",
    client_company: "Damascus Chamber of Commerce",
    contact_title: "Program lead",
    event_type: "social",
    status: "cancelled",
    contact_name: "Omar Barakat",
    contact_email_masked: "o***@chamber.sy",
    contact_phone_masked: "+963 *** 7700",
    event_date: at(-5 * 1440),
    setup_date: at(-6 * 1440),
    teardown_date: at(-4 * 1440),
    expected_attendees: 150,
    guaranteed_attendees: 0,
    spaces_required: ["Grand Ballroom"],
    revenue_estimate: 21000,
    deposit_amount: 0,
    deposit_paid: false,
    notes: "Cancelled by client. Deposit forfeited clause may apply.",
    created_at: at(-45 * 1440),
    updated_at: at(-8 * 1440),
    assigned_to: "Karim Azzam",
  },
];

function buildTimingBlocks(event) {
  return [
    {
      id: "setup",
      label: "Setup access",
      time: event.setup_date,
      owner: "Banquets",
      note: "Room reset, staging, and décor install.",
    },
    {
      id: "function",
      label: "Function start",
      time: event.event_date,
      owner: "Event lead",
      note: "Guest arrival, opening remarks, and service start.",
    },
    {
      id: "teardown",
      label: "Teardown complete",
      time: event.teardown_date,
      owner: "Engineering",
      note: "Strike, cleanup, and room release.",
    },
  ];
}

function buildSpacePlan(event, guaranteedAttendees) {
  const primaryCapacity = Math.max(guaranteedAttendees, Math.round(guaranteedAttendees * 1.1) || guaranteedAttendees || 1);

  return (event.spaces_required || []).map((space, index) => ({
    id: `${event.id}_space_${index + 1}`,
    name: space,
    purpose: index === 0 ? "Main function room" : index === 1 ? "Pre-function / breakout" : "Support / staging space",
    setup_style: index === 0 ? "Banquet" : index === 1 ? "Reception" : "Classroom",
    capacity: index === 0 ? primaryCapacity : Math.max(20, Math.ceil(guaranteedAttendees * 0.35) || 20),
    start_at: index === 0 ? event.setup_date : safeShift(event.setup_date, 30 + index * 15),
    end_at: index === 0 ? event.teardown_date : safeShift(event.event_date, 60 + index * 30),
    note: index === 0 ? "Primary BEO room" : "Secondary operational hold",
  }));
}

function buildChecklist(event) {
  return [
    {
      id: "contract",
      label: "Signed BEO and contract on file",
      owner: "Sales",
      done: event.status !== "rfp",
      due_at: safeShift(event.event_date, -4320),
      note: "Confirm cancellation terms and billing contact.",
    },
    {
      id: "deposit",
      label: "Deposit collected",
      owner: "Finance",
      done: Boolean(event.deposit_paid),
      due_at: safeShift(event.event_date, -2880),
      note: "Match the receipt against the contract value.",
    },
    {
      id: "guarantee",
      label: "Final guarantee received",
      owner: "Sales",
      done: event.status === "confirmed",
      due_at: event.final_guarantee_due_at,
      note: "Lock banquet counts with kitchen.",
    },
    {
      id: "beo",
      label: "BEO shared with operations",
      owner: "Banquets",
      done: event.status === "confirmed",
      due_at: event.setup_date,
      note: "Distribute the execution brief to each department.",
    },
    {
      id: "av",
      label: "AV and room setup confirmed",
      owner: "Engineering",
      done: event.status === "confirmed",
      due_at: event.setup_date,
      note: "Power, microphones, and room layout.",
    },
  ];
}

function normalizeEvent(event) {
  const revenueEstimate = toNumber(event.revenue_estimate, 0);
  const guaranteedAttendees = toNumber(event.guaranteed_attendees ?? event.expected_attendees, 0);
  const depositAmount = toNumber(event.deposit_amount, Math.round(revenueEstimate * 0.25));
  const finalGuaranteeDueAt = event.final_guarantee_due_at || safeShift(event.event_date, -1440);
  const normalized = {
    ...event,
    client_company: event.client_company || event.organization || "—",
    contact_title: event.contact_title || "Event contact",
    expected_attendees: toNumber(event.expected_attendees, 0),
    guaranteed_attendees: guaranteedAttendees,
    deposit_amount: depositAmount,
    deposit_paid: Boolean(event.deposit_paid),
    deposit_paid_at: event.deposit_paid_at || null,
    deposit_received_by: event.deposit_received_by || null,
    final_guarantee_due_at: finalGuaranteeDueAt,
    notes: event.notes || "",
  };

  normalized.timing_blocks = Array.isArray(event.timing_blocks) ? cloneMockData(event.timing_blocks) : buildTimingBlocks(normalized);
  normalized.space_plan = Array.isArray(event.space_plan) ? cloneMockData(event.space_plan) : buildSpacePlan(normalized, guaranteedAttendees);
  normalized.checklist = Array.isArray(event.checklist) ? cloneMockData(event.checklist) : buildChecklist(normalized);

  return normalized;
}

function updateStoredEvent(id, updater) {
  const index = events.findIndex((event) => event.id === id);
  if (index === -1) {
    throwMockError({ message: "Event not found", error_code: "event_not_found" }, 404);
  }

  const current = normalizeEvent(events[index]);
  const next = updater(current);
  const normalized = normalizeEvent({
    ...current,
    ...next,
  });

  events[index] = cloneMockData(normalized);
  return cloneMockData(normalized);
}

let events = cloneMockData(eventsSeed.map(normalizeEvent));
let nextEventId = 100;

export function listEvents(params = {}) {
  const { status, event_type, query } = params;

  const filtered = events.filter((event) => {
    if (status && event.status !== status) return false;
    if (event_type && event.event_type !== event_type) return false;
    if (!matchesSearch([event.title, event.client_company, event.contact_name, event.reference, event.assigned_to], query)) return false;
    return true;
  });

  return paginateItems(filtered, params);
}

export function getEventById(id) {
  const event = events.find((item) => item.id === id);
  if (!event) {
    throwMockError({ message: "Event not found", error_code: "event_not_found" }, 404);
  }

  return cloneMockData(event);
}

export function updateEventStatus(id, status) {
  if (!VALID_STATUSES.includes(status)) {
    throwMockError(
      {
        message: "Invalid status value",
        error_code: "validation_error",
        errors: { status: ["Must be one of: rfp, tentative, confirmed, cancelled"] },
      },
      422,
    );
  }

  return updateStoredEvent(id, (event) => ({
    ...event,
    status,
    updated_at: new Date().toISOString(),
  }));
}

export function updateEventNotes(id, notes) {
  return updateStoredEvent(id, (event) => ({
    ...event,
    notes: notes ?? "",
    updated_at: new Date().toISOString(),
  }));
}

export function updateEventChecklistItem(id, itemId, payload = {}) {
  return updateStoredEvent(id, (event) => {
    const now = new Date().toISOString();
    const checklist = cloneMockData(event.checklist || []);
    const item = checklist.find((entry) => entry.id === itemId);

    if (!item) {
      throwMockError({ message: "Checklist item not found", error_code: "checklist_item_not_found" }, 404);
    }

    const nextDone = typeof payload.done === "boolean" ? payload.done : !item.done;
    item.done = nextDone;
    item.updated_at = now;
    item.completed_at = nextDone ? now : null;
    item.completed_by = nextDone ? (payload.actor || null) : null;

    return {
      ...event,
      checklist,
      updated_at: now,
    };
  });
}

export function updateEventDeposit(id, payload = {}) {
  return updateStoredEvent(id, (event) => {
    const now = new Date().toISOString();
    const depositAmount = toNumber(payload.amount, event.deposit_amount);
    const checklist = cloneMockData(event.checklist || []);
    const depositItem = checklist.find((entry) => entry.id === "deposit");

    if (depositItem) {
      depositItem.done = true;
      depositItem.updated_at = now;
      depositItem.completed_at = now;
      depositItem.completed_by = payload.actor || null;
    }

    return {
      ...event,
      deposit_paid: true,
      deposit_amount: depositAmount,
      deposit_paid_at: now,
      deposit_received_by: payload.actor || event.deposit_received_by || null,
      checklist,
      updated_at: now,
    };
  });
}

export function createEvent(body = {}) {
  const numericId = nextEventId++;
  const id = `ev_${numericId}`;
  const reference = `EVT-${String(numericId).padStart(3, "0")}`;
  const now = new Date().toISOString();
  const revenueEstimate = toNumber(body.revenue_estimate, 0);
  const depositAmount = toNumber(body.deposit_amount, Math.round(revenueEstimate * 0.25));

  const event = normalizeEvent({
    id,
    reference,
    title: body.title ?? "",
    client_company: body.client_company ?? body.company_name ?? "New Client",
    contact_title: body.contact_title ?? "Event contact",
    event_type: body.event_type ?? "conference",
    status: "rfp",
    contact_name: body.contact_name ?? "",
    contact_email_masked: body.contact_email_masked ?? "",
    contact_phone_masked: body.contact_phone_masked ?? "",
    event_date: body.event_date ?? null,
    setup_date: body.setup_date ?? null,
    teardown_date: body.teardown_date ?? null,
    expected_attendees: toNumber(body.expected_attendees, 0),
    guaranteed_attendees: toNumber(body.guaranteed_attendees, toNumber(body.expected_attendees, 0)),
    spaces_required: Array.isArray(body.spaces_required) ? body.spaces_required : [],
    revenue_estimate: revenueEstimate,
    deposit_amount: depositAmount,
    deposit_paid: false,
    notes: body.notes ?? "",
    created_at: now,
    updated_at: now,
    assigned_to: body.assigned_to ?? null,
  });

  events.push(cloneMockData(event));

  return cloneMockData(event);
}

export function resetEventMockData() {
  events = cloneMockData(eventsSeed.map(normalizeEvent));
  nextEventId = 100;
}
