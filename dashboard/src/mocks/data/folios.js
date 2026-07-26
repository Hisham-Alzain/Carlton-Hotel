import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, matchesSearch, paginateItems, throwMockError } from "../envelope.js";

const baseDay = "2026-06-28T00:00:00.000Z";

function at(min) {
  return addMinutesUtc(baseDay, min);
}

let nextLineItemId = 9001;

function sumAmounts(items) {
  return items.reduce((acc, item) => acc + item.amount * item.quantity, 0);
}

function sumPayments(payments) {
  return payments.reduce((acc, p) => acc + p.amount, 0);
}

function recompute(folio) {
  folio.total_charges = sumAmounts(folio.line_items);
  folio.total_payments = sumPayments(folio.payments);
  folio.balance = folio.total_charges - folio.total_payments;
}

const foliosSeed = [
  {
    id: "fol_1001",
    reservation_id: "res_1001",
    guest_id: "guest_001",
    guest_name: "Lina Salem",
    room_number: "302",
    status: "open",
    currency: "USD",
    total_charges: 607,
    total_payments: 270,
    balance: 337,
    line_items: [
      {
        id: "li_1001_1",
        folio_id: "fol_1001",
        category: "room",
        description: "Deluxe King x3 nights",
        amount: 540,
        quantity: 1,
        posted_at: at(-2880),
        posted_by: "System",
        disputed: false,
      },
      {
        id: "li_1001_2",
        folio_id: "fol_1001",
        category: "food",
        description: "In-room dining — breakfast",
        amount: 45,
        quantity: 1,
        posted_at: at(-300),
        posted_by: "Mira Haddad",
        disputed: false,
      },
      {
        id: "li_1001_3",
        folio_id: "fol_1001",
        category: "beverage",
        description: "Minibar",
        amount: 22,
        quantity: 1,
        posted_at: at(-120),
        posted_by: "Layth Saleh",
        disputed: false,
      },
    ],
    payments: [
      {
        id: "pay_1001_1",
        folio_id: "fol_1001",
        method: "credit_card",
        amount: 270,
        paid_at: at(-2880),
        reference: "PRE-AUTH-8821",
      },
    ],
    created_at: at(-2880),
    updated_at: at(-120),
  },
  {
    id: "fol_1002",
    reservation_id: "res_1002",
    guest_id: "guest_002",
    guest_name: "Samir Khoury",
    room_number: "405",
    status: "open",
    currency: "USD",
    total_charges: 350,
    total_payments: 0,
    balance: 350,
    line_items: [
      {
        id: "li_1002_1",
        folio_id: "fol_1002",
        category: "room",
        description: "Premium Suite x1 night",
        amount: 230,
        quantity: 1,
        posted_at: at(-1440),
        posted_by: "System",
        disputed: false,
      },
      {
        id: "li_1002_2",
        folio_id: "fol_1002",
        category: "spa",
        description: "Spa treatment — 60min",
        amount: 120,
        quantity: 1,
        posted_at: at(-60),
        posted_by: "Omar Nasser",
        disputed: false,
      },
    ],
    payments: [],
    created_at: at(-1440),
    updated_at: at(-60),
  },
  {
    id: "fol_1003",
    reservation_id: "res_1003",
    guest_id: "guest_003",
    guest_name: "Dana Abboud",
    room_number: "701",
    status: "disputed",
    currency: "USD",
    total_charges: 1000,
    total_payments: 500,
    balance: 500,
    line_items: [
      {
        id: "li_1003_1",
        folio_id: "fol_1003",
        category: "room",
        description: "Executive Suite x3 nights",
        amount: 890,
        quantity: 1,
        posted_at: at(-4320),
        posted_by: "System",
        disputed: false,
      },
      {
        id: "li_1003_2",
        folio_id: "fol_1003",
        category: "transport",
        description: "Airport pickup",
        amount: 75,
        quantity: 1,
        posted_at: at(-2000),
        posted_by: "Omar Nasser",
        disputed: false,
      },
      {
        id: "li_1003_3",
        folio_id: "fol_1003",
        category: "misc",
        description: "Laundry service",
        amount: 35,
        quantity: 1,
        posted_at: at(-1000),
        posted_by: "Omar Mansour",
        disputed: true,
      },
    ],
    payments: [
      {
        id: "pay_1003_1",
        folio_id: "fol_1003",
        method: "bank_transfer",
        amount: 500,
        paid_at: at(-3000),
        reference: "WIRE-2026-0602",
      },
    ],
    created_at: at(-4320),
    updated_at: at(-1000),
  },
  {
    id: "fol_1004",
    reservation_id: "res_1004",
    guest_name: "Carlos Rivera",
    room_number: null,
    status: "settled",
    currency: "USD",
    total_charges: 825,
    total_payments: 825,
    balance: 0,
    line_items: [
      {
        id: "li_1004_1",
        folio_id: "fol_1004",
        category: "room",
        description: "Deluxe King x3 nights",
        amount: 690,
        quantity: 1,
        posted_at: at(-5760),
        posted_by: "System",
        disputed: false,
      },
      {
        id: "li_1004_2",
        folio_id: "fol_1004",
        category: "food",
        description: "Restaurant dinner x2",
        amount: 96,
        quantity: 1,
        posted_at: at(-4800),
        posted_by: "Mira Haddad",
        disputed: false,
      },
      {
        id: "li_1004_3",
        folio_id: "fol_1004",
        category: "tax",
        description: "Tourism tax 5%",
        amount: 39,
        quantity: 1,
        posted_at: at(-5760),
        posted_by: "System",
        disputed: false,
      },
    ],
    payments: [
      {
        id: "pay_1004_1",
        folio_id: "fol_1004",
        method: "credit_card",
        amount: 825,
        paid_at: at(-480),
        reference: "CHG-8834",
      },
    ],
    created_at: at(-5760),
    updated_at: at(-480),
  },
];

let folios = cloneMockData(foliosSeed);

export function resetFolioMockData() {
  folios = cloneMockData(foliosSeed);
  nextLineItemId = 9001;
}

export function listFolios(params = {}) {
  const filtered = folios
    .filter((f) => !params.status || f.status === params.status)
    .filter((f) => !params.reservation_id || f.reservation_id === params.reservation_id)
    .filter((f) =>
      matchesSearch(
        [f.guest_name, f.room_number, f.reservation_id].filter(Boolean),
        params.query,
      ),
    );

  return paginateItems(cloneMockData(filtered), params);
}

export function getFolioByReservation(reservation_id) {
  const folio = folios.find((f) => f.reservation_id === reservation_id);
  return folio ? cloneMockData(folio) : null;
}

export function getFolioById(id) {
  const folio = folios.find((f) => f.id === id);
  return folio ? cloneMockData(folio) : null;
}

export function postLineItem(folio_id, body) {
  const folio = folios.find((f) => f.id === folio_id);

  if (!folio) {
    throwMockError({ message: "Folio not found", error_code: "folio_not_found" }, 404);
  }

  const lineItem = {
    id: `li_${nextLineItemId++}`,
    folio_id,
    category: body.category,
    description: body.description,
    amount: body.amount,
    quantity: body.quantity ?? 1,
    posted_at: new Date().toISOString(),
    posted_by: body.posted_by ?? "Staff",
    disputed: false,
  };

  folio.line_items.push(lineItem);
  recompute(folio);
  folio.updated_at = lineItem.posted_at;

  return cloneMockData(folio);
}

export function disputeLineItem(folio_id, line_item_id) {
  const folio = folios.find((f) => f.id === folio_id);

  if (!folio) {
    throwMockError({ message: "Folio not found", error_code: "folio_not_found" }, 404);
  }

  const item = folio.line_items.find((li) => li.id === line_item_id);

  if (!item) {
    throwMockError({ message: "Line item not found", error_code: "line_item_not_found" }, 404);
  }

  item.disputed = !item.disputed;

  const anyDisputed = folio.line_items.some((li) => li.disputed);

  if (anyDisputed) {
    folio.status = "disputed";
  } else if (folio.balance <= 0) {
    folio.status = "settled";
  } else {
    folio.status = "open";
  }

  recompute(folio);
  folio.updated_at = new Date().toISOString();

  return cloneMockData(folio);
}

export function settlePayment(folio_id, body) {
  const folio = folios.find((f) => f.id === folio_id);

  if (!folio) {
    throwMockError({ message: "Folio not found", error_code: "folio_not_found" }, 404);
  }

  const payment = {
    id: `pay_${folio_id}_${Date.now()}`,
    folio_id,
    method: body.method,
    amount: body.amount,
    paid_at: new Date().toISOString(),
    reference: body.reference ?? null,
  };

  folio.payments.push(payment);
  recompute(folio);

  if (folio.balance <= 0) {
    folio.status = "settled";
  }

  folio.updated_at = payment.paid_at;

  return cloneMockData(folio);
}
