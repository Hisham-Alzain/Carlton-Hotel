import { SUPER_ADMIN_ROLE, STAFF_ROLE } from "../utils/permissions.js";
import { MOCK_PERMISSION_SETS } from "./permissions.js";

const DEFAULT_PASSWORD = "demo1234";
const LEGACY_PASSWORD = "password123";

export const STAFF_PERSONAS = Object.freeze([
  {
    id: "usr_super_admin",
    uuid: "staff-super-admin",
    persona: "super_admin",
    role: SUPER_ADMIN_ROLE,
    type: SUPER_ADMIN_ROLE,
    email: "admin@carlton.test",
    password: DEFAULT_PASSWORD,
    name: "Nadia Hariri",
    name_ar: "ناديا حريري",
    title: "General Manager",
    title_ar: "المديرة العامة",
    department: "executive",
    locale: "en",
    preferred_locale: "en",
    permissions: MOCK_PERMISSION_SETS.super_admin,
  },
  {
    id: "usr_reception",
    uuid: "staff-reception",
    persona: "reception",
    role: STAFF_ROLE,
    type: STAFF_ROLE,
    email: "reception@carlton.test",
    password: DEFAULT_PASSWORD,
    name: "Omar Mansour",
    name_ar: "عمر منصور",
    title: "Front Desk Supervisor",
    title_ar: "مشرف الاستقبال",
    department: "reception",
    locale: "en",
    preferred_locale: "en",
    permissions: MOCK_PERMISSION_SETS.reception,
  },
  {
    id: "usr_kitchen",
    uuid: "staff-kitchen",
    persona: "kitchen",
    role: STAFF_ROLE,
    type: STAFF_ROLE,
    email: "kitchen@carlton.test",
    password: DEFAULT_PASSWORD,
    name: "Mira Haddad",
    name_ar: "ميرا حداد",
    title: "Kitchen Coordinator",
    title_ar: "منسقة المطبخ",
    department: "kitchen",
    locale: "en",
    preferred_locale: "en",
    permissions: MOCK_PERMISSION_SETS.kitchen,
  },
  {
    id: "usr_housekeeping",
    uuid: "staff-housekeeping",
    persona: "housekeeping",
    role: STAFF_ROLE,
    type: STAFF_ROLE,
    email: "housekeeping@carlton.test",
    password: DEFAULT_PASSWORD,
    name: "Layth Saleh",
    name_ar: "ليث صالح",
    title: "Housekeeping Lead",
    title_ar: "قائد التدبير الفندقي",
    department: "housekeeping",
    locale: "ar",
    preferred_locale: "ar",
    permissions: MOCK_PERMISSION_SETS.housekeeping,
  },
  {
    id: "usr_concierge",
    uuid: "staff-operations",
    persona: "concierge",
    role: STAFF_ROLE,
    type: STAFF_ROLE,
    email: "ops@carlton.test",
    password: DEFAULT_PASSWORD,
    name: "Omar Nasser",
    name_ar: "عمر ناصر",
    title: "Operations Concierge",
    title_ar: "مشرف العمليات",
    department: "concierge",
    locale: "ar",
    preferred_locale: "ar",
    permissions: MOCK_PERMISSION_SETS.concierge,
  },
  {
    id: "usr_sales",
    uuid: "staff-sales",
    persona: "sales",
    role: STAFF_ROLE,
    type: STAFF_ROLE,
    email: "sales@carlton.test",
    password: DEFAULT_PASSWORD,
    name: "Karim Azzam",
    name_ar: "كريم عزام",
    title: "Events Sales Manager",
    title_ar: "مدير مبيعات الفعاليات",
    department: "sales",
    locale: "en",
    preferred_locale: "en",
    permissions: MOCK_PERMISSION_SETS.sales,
  },
]);

export const personas = STAFF_PERSONAS;

export const MOCK_LOGIN_HINTS = Object.freeze(
  STAFF_PERSONAS.map(({ persona, email }) => ({
    persona,
    email,
    password: DEFAULT_PASSWORD,
  })),
);

export function sanitizePersona(persona) {
  if (!persona) return null;

  const safePersona = { ...persona };
  delete safePersona.password;

  return {
    ...safePersona,
    property_id: "prop_carlton_damascus",
    property_name: "Carlton Hotel",
    avatar_url: null,
  };
}

export function getPersonaById(id) {
  return STAFF_PERSONAS.find((persona) => persona.id === id || persona.uuid === id) || null;
}

export function getPersonaByEmail(email) {
  return STAFF_PERSONAS.find((persona) => persona.email.toLowerCase() === String(email || "").toLowerCase()) || null;
}

export function getPersonaByKey(personaKey) {
  return STAFF_PERSONAS.find((persona) => persona.persona === personaKey) || null;
}

export function getPersonaPermissions(persona) {
  return persona?.permissions || [];
}

export function findPersonaByCredentials({ email, password, persona }) {
  const candidate = persona ? getPersonaByKey(persona) : getPersonaByEmail(email);

  if (!candidate || ![candidate.password, LEGACY_PASSWORD].includes(password)) {
    return null;
  }

  return candidate;
}
