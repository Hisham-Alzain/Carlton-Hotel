import { addMinutesUtc } from "../../utils/date.js";
import { cloneMockData, matchesSearch, paginateItems, throwMockError } from "../envelope.js";

const baseDay = "2026-06-28T00:00:00.000Z";

function at(min) {
  return addMinutesUtc(baseDay, min);
}

let nextNoteId = 9001;
const PRE_ARRIVAL_STATUSES = new Set(["pending", "in_progress", "ready", "done", "blocked"]);

function derivePreArrivalReadiness(checklist = []) {
  if (!checklist.length) return "pending";
  if (checklist.some((item) => item.status === "blocked")) return "blocked";
  if (checklist.every((item) => item.status === "done" || item.status === "ready")) return "ready";
  return "in_progress";
}

function text(en, ar) {
  return { en, ar };
}

function person(name, name_ar, title = null, title_ar = null, department = null) {
  return {
    name,
    name_ar,
    title,
    title_ar,
    department,
  };
}

const guestsSeed = [
  {
    id: "guest_001",
    full_name: "Lina Salem",
    full_name_ar: "لينا سالم",
    nationality: "LB",
    vip_level: "gold",
    phone_masked: "+961 **** 1234",
    email_masked: "l***@gmail.com",
    preferences: {
      pillow_type: "soft",
      floor_preference: "high",
      dietary: "vegetarian",
      notes: "Prefers quiet rooms, away from elevator.",
    },
    total_stays: 4,
    total_nights: 12,
    total_spend: 2140,
    first_stay_at: at(-720 * 24 * 60),
    last_stay_at: at(-5),
    stay_history: [
      {
        reservation_id: "res_1001",
        room_type_name: "Deluxe King",
        check_in: at(-2880),
        check_out: at(2 * 1440 - 2880),
        nights: 3,
        amount: 540,
        status: "checked_in",
      },
      {
        reservation_id: "res_0012",
        room_type_name: "Deluxe Twin",
        check_in: at(-180 * 1440),
        check_out: at(-177 * 1440),
        nights: 3,
        amount: 450,
        status: "departed",
      },
    ],
    staff_notes: [
      {
        id: "sn_g001_1",
        note: "Upgraded on arrival due to VIP status. Left glowing review.",
        posted_by: "Omar Mansour",
        posted_at: at(-177 * 1440),
      },
    ],
    pre_arrival_plan: {
      reservation_id: "res_1001",
      arrival_date: at(900),
      arrival_window: "15:00 - 16:00",
      coordinator: "Omar Mansour",
      owner: person("Omar Mansour", "عمر منصور", "Front Desk Supervisor", "مشرف الاستقبال", "reception"),
      status: "in_progress",
      readiness: "in_progress",
      readiness_detail: {
        status: "needs_attention",
        score: 72,
        due_at: at(840),
        note: text(
          "Waiting on the final room-scent check and elevator proximity review.",
          "بانتظار فحص رائحة الغرفة ومراجعة قرب المصعد."
        ),
      },
      airport_transfer: { status: "confirmed", eta: "14:15", provider: "Carlton Chauffeur" },
      transfer: {
        status: "confirmed",
        mode: "airport_pickup",
        provider: "Carlton Chauffeur",
        pickup_at: at(840),
        flight_no: "MEA 306",
        owner: person("Concierge Desk", "مكتب الكونسييرج", "Guest Transport", "نقل الضيوف"),
        note: text(
          "Driver reconfirmed for Terminal 4. Luggage assistance requested.",
          "تم تأكيد السائق للصالة الرابعة مع طلب مساعدة في الأمتعة."
        ),
      },
      welcome_amenity: "Fruit plate with mint tea setup",
      welcome_amenity_detail: {
        status: "packed",
        item: text("Fruit plate, jasmine tea, and chilled water", "طبق فواكه، شاي ياسمين، ومياه باردة"),
        owner: person("Guest Relations", "خدمة الضيوف", "Welcome Desk", "مكتب الترحيب"),
        note: text("Deliver after the room scent check.", "التسليم بعد فحص رائحة الغرفة."),
      },
      room_preference: "High floor away from elevators",
      next_action: text(
        "Confirm the quiet-room allocation and pillow profile.",
        "تأكيد غرفة هادئة وتفضيل الوسادة."
      ),
      checklist: [
        { id: "pa_g001_1", label: "Verify room block", label_ar: "تأكيد حجز الغرفة", owner: "Front Desk", status: "ready", notes: "302 held for arrival day." },
        { id: "pa_g001_2", label: "Confirm vegetarian breakfast note", label_ar: "تأكيد ملاحظة الإفطار النباتي", owner: "Kitchen", status: "pending", notes: "Breakfast note added but not acknowledged." },
        { id: "pa_g001_3", label: "Place welcome amenity", label_ar: "تجهيز ضيافة الترحيب", owner: "Housekeeping", status: "pending", notes: "Deliver once room is released clean." },
      ],
      updated_at: at(-60),
    },
  },
  {
    id: "guest_002",
    full_name: "Samir Khoury",
    full_name_ar: "سمير خوري",
    nationality: "SY",
    vip_level: "standard",
    phone_masked: "+963 **** 9900",
    email_masked: "s***@yahoo.com",
    preferences: {
      pillow_type: "firm",
      floor_preference: "any",
      dietary: null,
      notes: null,
    },
    total_stays: 1,
    total_nights: 1,
    total_spend: 350,
    first_stay_at: at(-1440),
    last_stay_at: at(-1440),
    stay_history: [
      {
        reservation_id: "res_1002",
        room_type_name: "Premium Suite",
        check_in: at(-1440),
        check_out: at(0),
        nights: 1,
        amount: 350,
        status: "due_out",
      },
    ],
    staff_notes: [],
  },
  {
    id: "guest_003",
    full_name: "Dana Abboud",
    full_name_ar: "دانا عبود",
    nationality: "AE",
    vip_level: "platinum",
    phone_masked: "+971 **** 4455",
    email_masked: "d***@icloud.com",
    preferences: {
      pillow_type: "memory_foam",
      floor_preference: "executive",
      dietary: "halal",
      notes: "VIP corporate guest — always upgrade if available. Airport transfer arranged by default.",
    },
    total_stays: 8,
    total_nights: 24,
    total_spend: 7200,
    first_stay_at: at(-365 * 1440),
    last_stay_at: at(-5),
    stay_history: [
      {
        reservation_id: "res_1003",
        room_type_name: "Executive Suite",
        check_in: at(-2880),
        check_out: at(2 * 1440 - 2880),
        nights: 3,
        amount: 890,
        status: "checked_in",
      },
      {
        reservation_id: "res_0008",
        room_type_name: "Executive Suite",
        check_in: at(-90 * 1440),
        check_out: at(-87 * 1440),
        nights: 3,
        amount: 870,
        status: "departed",
      },
    ],
    staff_notes: [
      {
        id: "sn_g003_1",
        note: "Always request early turndown service. Has dietary restrictions — halal meals only. Personal driver drops off at 11am.",
        posted_by: "Nadia Hariri",
        posted_at: at(-90 * 1440),
      },
    ],
    pre_arrival_plan: {
      reservation_id: "res_1003",
      arrival_date: at(-4320),
      arrival_window: "11:00 - 11:30",
      coordinator: "Nadia Hariri",
      owner: person("Nadia Hariri", "ناديا حريري", "General Manager", "المديرة العامة", "executive"),
      status: "complete",
      readiness: "ready",
      readiness_detail: {
        status: "ready",
        score: 100,
        due_at: at(-4380),
        note: text(
          "VIP welcome pack and room brief were completed before arrival.",
          "اكتملت حقيبة الترحيب وتجهيز الغرفة قبل الوصول."
        ),
      },
      airport_transfer: { status: "confirmed", eta: "10:45", provider: "Carlton executive sedan" },
      transfer: {
        status: "confirmed",
        mode: "private_car",
        provider: "Carlton executive sedan",
        pickup_at: at(-4380),
        flight_no: "Private driver",
        owner: person("Concierge Desk", "مكتب الكونسييرج", "Guest Transport", "نقل الضيوف"),
        note: text(
          "Driver met the guest at the main gate.",
          "استقبل السائق الضيف عند البوابة الرئيسية."
        ),
      },
      welcome_amenity: "Arabic sweets, still water, and halal in-room dining menu",
      welcome_amenity_detail: {
        status: "delivered",
        item: text(
          "Arabic sweets, still water, and halal in-room dining menu",
          "حلويات عربية، مياه ثابتة، وقائمة طعام حلال داخل الغرفة"
        ),
        owner: person("Guest Relations", "خدمة الضيوف", "Welcome Desk", "مكتب الترحيب"),
        note: text(
          "Delivered together with the room turn-down.",
          "تم التسليم مع التمهيد المسائي للغرفة."
        ),
      },
      room_preference: "Executive floor, pre-cooled room, prayer mat placed",
      next_action: text(
        "Keep the profile ready for the next corporate return.",
        "الاحتفاظ بالملف جاهزًا للعودة التالية."
      ),
      checklist: [
        { id: "pa_g003_1", label: "Inspect executive suite before release", label_ar: "تفتيش الجناح التنفيذي قبل التسليم", owner: "Housekeeping", status: "ready", notes: "VIP inspection done; minibar check completed." },
        { id: "pa_g003_2", label: "Remove disputed minibar charge before arrival follow-up", label_ar: "إزالة رسم الميني بار المتنازع عليه قبل المتابعة", owner: "Front Desk", status: "done", notes: "Finance confirmation received before check-in." },
        { id: "pa_g003_3", label: "Confirm airport transfer manifest", label_ar: "تأكيد كشف نقل المطار", owner: "Concierge", status: "ready", notes: "Driver booked for 10:45 pickup." },
        { id: "pa_g003_4", label: "Set halal welcome amenity", label_ar: "تجهيز ضيافة ترحيبية حلال", owner: "Kitchen", status: "done", notes: "Set alongside the in-room dining menu." },
      ],
      updated_at: at(-25),
    },
  },
  {
    id: "guest_004",
    full_name: "Carlos Rivera",
    nationality: "ES",
    vip_level: "standard",
    phone_masked: "+34 **** 7721",
    email_masked: "c***@outlook.com",
    preferences: {
      pillow_type: null,
      floor_preference: null,
      dietary: null,
      notes: null,
    },
    total_stays: 2,
    total_nights: 6,
    total_spend: 1200,
    first_stay_at: at(-200 * 1440),
    last_stay_at: at(-180 * 1440),
    stay_history: [
      {
        reservation_id: "res_1004",
        room_type_name: "Deluxe King",
        check_in: at(-3 * 1440),
        check_out: at(0),
        nights: 3,
        amount: 690,
        status: "upcoming",
      },
    ],
    staff_notes: [],
    pre_arrival_plan: {
      reservation_id: "res_1004",
      arrival_date: at(2100),
      arrival_window: "15:00 - 16:00",
      coordinator: "Omar Nasser",
      owner: person("Omar Nasser", "عمر ناصر", "Operations Concierge", "مشرف العمليات", "concierge"),
      status: "blocked",
      readiness: "blocked",
      readiness_detail: {
        status: "blocked",
        score: 46,
        due_at: at(1980),
        note: text(
          "Allergy note has not yet been shared with the minibar team.",
          "لم تُشارك ملاحظة الحساسية مع فريق الميني بار بعد."
        ),
      },
      airport_transfer: { status: "requested", eta: null, provider: "Carlton Chauffeur" },
      transfer: {
        status: "requested",
        mode: "hotel_car",
        provider: "Carlton Chauffeur",
        pickup_at: at(1980),
        flight_no: "IB 744",
        owner: person("Concierge Desk", "مكتب الكونسييرج", "Guest Transport", "نقل الضيوف"),
        note: text(
          "Waiting on a flight update before dispatching the car.",
          "بانتظار تحديث الرحلة قبل إرسال السيارة."
        ),
      },
      welcome_amenity: "Standard check-in setup",
      welcome_amenity_detail: {
        status: "planned",
        item: text(
          "Fruit basket, kids welcome set, and sparkling water",
          "سلة فواكه، طقم ترحيب للأطفال، ومياه فوارة"
        ),
        owner: person("Guest Relations", "خدمة الضيوف", "Welcome Desk", "مكتب الترحيب"),
        note: text(
          "Place after the family room setup is cleared.",
          "يوضع بعد إنهاء تجهيز الغرفة العائلية."
        ),
      },
      room_preference: "Family room near elevator, allergy note added",
      next_action: text(
        "Escalate the minibar allergy note and confirm the family welcome set.",
        "تصعيد ملاحظة الحساسية في الميني بار وتأكيد طقم الترحيب العائلي."
      ),
      checklist: [
        { id: "pa_g004_1", label: "Room assignment pending", label_ar: "تخصيص الغرفة قيد الانتظار", owner: "Front Desk", status: "blocked", notes: "Pending minibar allergy confirmation." },
        { id: "pa_g004_2", label: "Share family setup with housekeeping", label_ar: "مشاركة إعداد العائلة مع التدبير الفندقي", owner: "Housekeeping", status: "in_progress", notes: "Extra bedding is being staged." },
        { id: "pa_g004_3", label: "Confirm chauffeur pickup", label_ar: "تأكيد استلام السائق", owner: "Concierge", status: "pending", notes: "Dispatch waits on flight timing." },
        { id: "pa_g004_4", label: "Stage the kids welcome amenity", label_ar: "تجهيز ضيافة الترحيب للأطفال", owner: "Guest Relations", status: "ready", notes: "Fruit basket and activity pack on hold." },
      ],
      updated_at: at(-10),
    },
  },
];

let guests = guestsSeed.map((g) => cloneMockData(g));

export function resetGuestMockData() {
  guests = guestsSeed.map((g) => cloneMockData(g));
  nextNoteId = 9001;
}

export function listGuests(params = {}) {
  const { query, vip_level } = params;

  let filtered = guests;

  if (vip_level) {
    filtered = filtered.filter((g) => g.vip_level === vip_level);
  }

  if (query) {
    filtered = filtered.filter((g) =>
      matchesSearch([g.full_name, g.email_masked, g.nationality], query)
    );
  }

  return paginateItems(filtered, params);
}

export function getGuestById(id) {
  const guest = guests.find((g) => g.id === id);
  if (!guest) {
    throwMockError({ message: "Guest not found", error_code: "guest_not_found" }, 404);
  }
  return cloneMockData(guest);
}

export function getGuestByReservation(reservation_id) {
  const guest = guests.find((g) =>
    g.stay_history.some((s) => s.reservation_id === reservation_id)
  );
  if (!guest) {
    throwMockError({ message: "Guest not found for reservation", error_code: "guest_not_found" }, 404);
  }
  return cloneMockData(guest);
}

export function addGuestNote(guest_id, note, posted_by) {
  const guest = guests.find((g) => g.id === guest_id);
  if (!guest) {
    throwMockError({ message: "Guest not found", error_code: "guest_not_found" }, 404);
  }
  guest.staff_notes.push({
    id: `sn_g${nextNoteId++}`,
    note,
    posted_by,
    posted_at: new Date().toISOString(),
  });
  return cloneMockData(guest);
}

export function updateGuestPreferences(guest_id, preferences) {
  const guest = guests.find((g) => g.id === guest_id);
  if (!guest) {
    throwMockError({ message: "Guest not found", error_code: "guest_not_found" }, 404);
  }
  guest.preferences = { ...guest.preferences, ...preferences };
  return cloneMockData(guest);
}

export function updateGuestPreArrival(guest_id, payload = {}) {
  const guest = guests.find((g) => g.id === guest_id);
  if (!guest) {
    throwMockError({ message: "Guest not found", error_code: "guest_not_found" }, 404);
  }
  if (!guest.pre_arrival_plan) {
    throwMockError({ message: "Pre-arrival plan not found", error_code: "pre_arrival_not_found" }, 404);
  }

  const { item_id, status, notes, patch } = payload;

  if (item_id) {
    if (!PRE_ARRIVAL_STATUSES.has(status)) {
      throwMockError({
        message: "Unsupported pre-arrival status.",
        error_code: "validation_failed",
        errors: { status: ["Choose pending, in progress, ready, done, or blocked."] },
      }, 422);
    }

    const item = guest.pre_arrival_plan.checklist.find((entry) => entry.id === item_id);
    if (!item) {
      throwMockError({ message: "Checklist item not found", error_code: "pre_arrival_item_not_found" }, 404);
    }

    item.status = status;
    if (notes !== undefined) {
      item.notes = notes;
    }

    const readiness = derivePreArrivalReadiness(guest.pre_arrival_plan.checklist);
    guest.pre_arrival_plan.readiness = readiness;
    if (guest.pre_arrival_plan.readiness_detail) {
      guest.pre_arrival_plan.readiness_detail.status = readiness;
    }
    if (guest.pre_arrival_plan.status !== "complete") {
      guest.pre_arrival_plan.status = readiness === "ready" ? "ready" : readiness === "blocked" ? "blocked" : "in_progress";
    }
    guest.pre_arrival_plan.updated_at = new Date().toISOString();
    return cloneMockData(guest);
  }

  const planPatch = patch && typeof patch === "object" ? patch : null;
  if (!planPatch || !Object.keys(planPatch).length) {
    throwMockError({
      message: "Checklist item or patch is required",
      error_code: "validation_failed",
      errors: { item_id: ["Select a checklist item or provide a plan patch."] },
    }, 422);
  }

  guest.pre_arrival_plan = {
    ...guest.pre_arrival_plan,
    ...cloneMockData(planPatch),
  };

  if (guest.pre_arrival_plan.owner?.name) {
    guest.pre_arrival_plan.coordinator = guest.pre_arrival_plan.owner.name;
  }
  if (guest.pre_arrival_plan.readiness_detail?.status) {
    guest.pre_arrival_plan.readiness = guest.pre_arrival_plan.readiness_detail.status;
  }
  if (guest.pre_arrival_plan.transfer?.status && guest.pre_arrival_plan.airport_transfer) {
    guest.pre_arrival_plan.airport_transfer = {
      ...guest.pre_arrival_plan.airport_transfer,
      status: guest.pre_arrival_plan.transfer.status,
      eta: guest.pre_arrival_plan.transfer.pickup_at || guest.pre_arrival_plan.airport_transfer.eta,
      provider: guest.pre_arrival_plan.transfer.provider || guest.pre_arrival_plan.airport_transfer.provider,
    };
  }
  guest.pre_arrival_plan.updated_at = new Date().toISOString();

  return cloneMockData(guest);
}
