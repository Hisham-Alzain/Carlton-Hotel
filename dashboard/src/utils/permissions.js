export const SUPER_ADMIN_ROLE = "super_admin";
export const STAFF_ROLE = "staff";

export const PERMISSIONS = Object.freeze({
  DASHBOARD_VIEW: "dashboard.view",
  QUEUE_VIEW: "operations_queue.view",
  QUEUE_MANAGE: "operations_queue.manage",
  QUEUE_ASSIGN: "operations_queue.assign",
  RESERVATIONS_VIEW: "reservations.view",
  RESERVATIONS_CREATE: "reservations.create",
  RESERVATIONS_MANAGE: "reservations.manage",
  RESERVATIONS_CHECK_IN: "reservations.check_in",
  RESERVATIONS_CHECK_OUT: "reservations.check_out",
  AVAILABILITY_VIEW: "availability.view",
  SERVICE_REQUESTS_VIEW: "service_requests.view",
  SERVICE_REQUESTS_ASSIGN: "service_requests.assign",
  SERVICE_REQUESTS_UPDATE: "service_requests.update",
  SERVICE_REQUESTS_MANAGE: "service_requests.manage",
  TICKETS_VIEW: "tickets.view",
  TICKETS_ASSIGN: "tickets.assign",
  TICKETS_MANAGE: "tickets.manage",
  SUPPORT_TICKETS_VIEW: "support_tickets.view",
  SUPPORT_TICKETS_MANAGE: "support_tickets.manage",
  CHAT_VIEW: "guest_chat.view",
  CHAT_REPLY: "guest_chat.reply",
  EVENTS_VIEW: "events.view",
  EVENTS_MANAGE: "events.manage",
  PRE_ARRIVAL_VIEW: "pre_arrival.view",
  PRE_ARRIVAL_APPROVE: "pre_arrival.approve",
  SERVICE_BOOKINGS_VIEW: "service_bookings.view",
  SERVICE_BOOKINGS_MANAGE: "service_bookings.manage",
  CMS_VIEW: "cms.view",
  CMS_MANAGE: "cms.manage",
  RATES_VIEW: "rates.view",
  RATES_MANAGE: "rates.manage",
  FOLIOS_VIEW: "folios.view",
  FOLIOS_SETTLE: "folios.settle",
  GUESTS_VIEW: "guests.view",
  STAFF_VIEW: "staff.view",
  STAFF_MANAGE: "staff.manage",
  PERMISSIONS_MANAGE: "permissions.manage",
  REPORTS_VIEW: "reports.view",
  SETTINGS_VIEW: "settings.view",
});

export const PERMISSION_DEFINITIONS = Object.freeze([
  { key: PERMISSIONS.DASHBOARD_VIEW, module: "dashboard", label: "View overview", label_ar: "عرض النظرة العامة" },
  { key: PERMISSIONS.QUEUE_VIEW, module: "operations", label: "View live queue", label_ar: "عرض قائمة العمليات" },
  { key: PERMISSIONS.QUEUE_MANAGE, module: "operations", label: "Update queue items", label_ar: "تحديث عناصر العمليات" },
  { key: PERMISSIONS.QUEUE_ASSIGN, module: "operations", label: "Assign queue items", label_ar: "إسناد عناصر العمليات" },
  { key: PERMISSIONS.RESERVATIONS_VIEW, module: "reservations", label: "View reservations", label_ar: "عرض الحجوزات" },
  { key: PERMISSIONS.RESERVATIONS_CREATE, module: "reservations", label: "Create reservations", label_ar: "إنشاء الحجوزات" },
  { key: PERMISSIONS.RESERVATIONS_MANAGE, module: "reservations", label: "Manage reservations", label_ar: "إدارة الحجوزات" },
  { key: PERMISSIONS.RESERVATIONS_CHECK_IN, module: "reservations", label: "Check in guests", label_ar: "تسجيل وصول الضيوف" },
  { key: PERMISSIONS.RESERVATIONS_CHECK_OUT, module: "reservations", label: "Check out guests", label_ar: "تسجيل مغادرة الضيوف" },
  { key: PERMISSIONS.AVAILABILITY_VIEW, module: "reservations", label: "View availability", label_ar: "عرض التوفر" },
  { key: PERMISSIONS.SERVICE_REQUESTS_VIEW, module: "services", label: "View service requests", label_ar: "عرض طلبات الخدمة" },
  { key: PERMISSIONS.SERVICE_REQUESTS_ASSIGN, module: "services", label: "Assign service requests", label_ar: "إسناد طلبات الخدمة" },
  { key: PERMISSIONS.SERVICE_REQUESTS_UPDATE, module: "services", label: "Update service requests", label_ar: "تحديث طلبات الخدمة" },
  { key: PERMISSIONS.SERVICE_REQUESTS_MANAGE, module: "services", label: "Manage service requests", label_ar: "إدارة طلبات الخدمة" },
  { key: PERMISSIONS.TICKETS_VIEW, module: "support", label: "View tickets", label_ar: "عرض التذاكر" },
  { key: PERMISSIONS.TICKETS_ASSIGN, module: "support", label: "Assign tickets", label_ar: "إسناد التذاكر" },
  { key: PERMISSIONS.TICKETS_MANAGE, module: "support", label: "Manage tickets", label_ar: "إدارة التذاكر" },
  { key: PERMISSIONS.SUPPORT_TICKETS_VIEW, module: "support", label: "View support tickets", label_ar: "عرض تذاكر الدعم" },
  { key: PERMISSIONS.SUPPORT_TICKETS_MANAGE, module: "support", label: "Manage support tickets", label_ar: "إدارة تذاكر الدعم" },
  { key: PERMISSIONS.CHAT_VIEW, module: "chat", label: "View guest chat", label_ar: "عرض محادثات الضيوف" },
  { key: PERMISSIONS.CHAT_REPLY, module: "chat", label: "Reply to guests", label_ar: "الرد على الضيوف" },
  { key: PERMISSIONS.EVENTS_VIEW, module: "events", label: "View events and RFPs", label_ar: "عرض الفعاليات وطلبات العروض" },
  { key: PERMISSIONS.EVENTS_MANAGE, module: "events", label: "Manage events and RFPs", label_ar: "إدارة الفعاليات وطلبات العروض" },
  { key: PERMISSIONS.PRE_ARRIVAL_VIEW, module: "pre_arrival", label: "View pre-arrival approvals", label_ar: "عرض موافقات ما قبل الوصول" },
  { key: PERMISSIONS.PRE_ARRIVAL_APPROVE, module: "pre_arrival", label: "Approve pre-arrival items", label_ar: "اعتماد عناصر ما قبل الوصول" },
  { key: PERMISSIONS.SERVICE_BOOKINGS_VIEW, module: "service_bookings", label: "View service bookings", label_ar: "عرض حجوزات الخدمات" },
  { key: PERMISSIONS.SERVICE_BOOKINGS_MANAGE, module: "service_bookings", label: "Manage service bookings", label_ar: "إدارة حجوزات الخدمات" },
  { key: PERMISSIONS.CMS_VIEW, module: "cms", label: "View content", label_ar: "عرض المحتوى" },
  { key: PERMISSIONS.CMS_MANAGE, module: "cms", label: "Manage content", label_ar: "إدارة المحتوى" },
  { key: PERMISSIONS.RATES_VIEW, module: "rates", label: "View rates", label_ar: "عرض الأسعار" },
  { key: PERMISSIONS.RATES_MANAGE, module: "rates", label: "Manage rates", label_ar: "إدارة الأسعار" },
  { key: PERMISSIONS.FOLIOS_VIEW, module: "folios", label: "View folios and payments", label_ar: "عرض الحسابات والمدفوعات" },
  { key: PERMISSIONS.FOLIOS_SETTLE, module: "folios", label: "Settle folios", label_ar: "تسوية الحسابات" },
  { key: PERMISSIONS.GUESTS_VIEW, module: "guests", label: "View guest directory", label_ar: "عرض دليل الضيوف" },
  { key: PERMISSIONS.STAFF_VIEW, module: "staff", label: "View staff", label_ar: "عرض الموظفين" },
  { key: PERMISSIONS.STAFF_MANAGE, module: "staff", label: "Manage staff", label_ar: "إدارة الموظفين" },
  { key: PERMISSIONS.PERMISSIONS_MANAGE, module: "staff", label: "Manage permissions", label_ar: "إدارة الصلاحيات" },
  { key: PERMISSIONS.REPORTS_VIEW, module: "reports", label: "View reports", label_ar: "عرض التقارير" },
  { key: PERMISSIONS.SETTINGS_VIEW, module: "settings", label: "View settings", label_ar: "عرض الإعدادات" },
]);

export const ALL_PERMISSION_KEYS = Object.freeze(PERMISSION_DEFINITIONS.map((permission) => permission.key));

export const NAVIGATION_ITEMS = Object.freeze([
  { id: "overview", label: "Overview", label_ar: "النظرة العامة", path: "/overview", href: "/overview", permission: PERMISSIONS.DASHBOARD_VIEW },
  { id: "queue", label: "Live Queue", label_ar: "قائمة العمليات", path: "/operations/queue", href: "/operations/queue", anyOf: [PERMISSIONS.SERVICE_REQUESTS_VIEW, PERMISSIONS.TICKETS_VIEW, PERMISSIONS.QUEUE_VIEW] },
  { id: "reservations", label: "Reservations", label_ar: "الحجوزات", path: "/reservations", href: "/reservations", permission: PERMISSIONS.RESERVATIONS_VIEW },
  { id: "availability", label: "Availability", label_ar: "التوفر", path: "/availability", href: "/availability", permission: PERMISSIONS.AVAILABILITY_VIEW },
  { id: "rates", label: "Rates & Pricing", label_ar: "الأسعار", path: "/rates", href: "/rates", permission: PERMISSIONS.RATES_VIEW },
  { id: "folios", label: "Folios & Payments", label_ar: "الحسابات والمدفوعات", path: "/folios", href: "/folios", permission: PERMISSIONS.FOLIOS_VIEW },
  { id: "service_requests", label: "Service Requests", label_ar: "طلبات الخدمة", path: "/service-requests", href: "/service-requests", permission: PERMISSIONS.SERVICE_REQUESTS_VIEW },
  { id: "support_tickets", label: "Support Tickets", label_ar: "تذاكر الدعم", path: "/support-tickets", href: "/support-tickets", anyOf: [PERMISSIONS.TICKETS_VIEW, PERMISSIONS.SUPPORT_TICKETS_VIEW] },
  { id: "guest_chat", label: "Guest Chat", label_ar: "محادثات الضيوف", path: "/guest-chat", href: "/guest-chat", permission: PERMISSIONS.CHAT_VIEW },
  { id: "events", label: "Events / RFPs", label_ar: "الفعاليات", path: "/events", href: "/events", permission: PERMISSIONS.EVENTS_VIEW },
  { id: "pre_arrival", label: "Pre-Arrival", label_ar: "ما قبل الوصول", path: "/pre-arrival", href: "/pre-arrival", permission: PERMISSIONS.PRE_ARRIVAL_VIEW },
  { id: "service_bookings", label: "Service Bookings", label_ar: "حجوزات الخدمات", path: "/service-bookings", href: "/service-bookings", permission: PERMISSIONS.SERVICE_BOOKINGS_VIEW },
  { id: "cms", label: "CMS / Content", label_ar: "المحتوى", path: "/cms", href: "/cms", permission: PERMISSIONS.CMS_VIEW },
  { id: "guests", label: "Guests", label_ar: "الضيوف", path: "/guests", href: "/guests", permission: PERMISSIONS.GUESTS_VIEW },
  { id: "staff", label: "Staff & Permissions", label_ar: "الموظفون والصلاحيات", path: "/staff", href: "/staff", permission: PERMISSIONS.STAFF_VIEW },
  { id: "reports", label: "Reports", label_ar: "التقارير", path: "/reports", href: "/reports", permission: PERMISSIONS.REPORTS_VIEW },
  { id: "settings", label: "Profile / Settings", label_ar: "الملف والإعدادات", path: "/settings", href: "/settings", permission: PERMISSIONS.SETTINGS_VIEW },
]);

export function extractPermissions(subject) {
  if (!subject) return [];
  if (Array.isArray(subject)) return subject;
  if (Array.isArray(subject.permissions)) return subject.permissions;
  return [];
}

export function isSuperAdmin(subject) {
  return Boolean(
    subject?.role === SUPER_ADMIN_ROLE
    || subject?.type === SUPER_ADMIN_ROLE
    || extractPermissions(subject).includes("*"),
  );
}

export function hasPermission(subject, requiredPermission) {
  if (!requiredPermission) return true;
  if (isSuperAdmin(subject)) return true;

  const required = Array.isArray(requiredPermission) ? requiredPermission : [requiredPermission];
  const permissions = new Set(extractPermissions(subject));
  return required.every((permission) => permissions.has(permission));
}

export function hasAnyPermission(subject, requiredPermissions = []) {
  if (!requiredPermissions.length) return true;
  if (isSuperAdmin(subject)) return true;

  const permissions = new Set(extractPermissions(subject));
  return requiredPermissions.some((permission) => permissions.has(permission));
}

export function hasAllPermissions(subject, requiredPermissions = []) {
  return hasPermission(subject, requiredPermissions);
}

export function getMissingPermissions(subject, requiredPermissions = []) {
  if (isSuperAdmin(subject)) return [];

  const required = Array.isArray(requiredPermissions) ? requiredPermissions : [requiredPermissions];
  const permissions = new Set(extractPermissions(subject));
  return required.filter((permission) => permission && !permissions.has(permission));
}

export function canAccessNavItem(item, user) {
  if (item?.anyOf) return hasAnyPermission(user, item.anyOf);
  return hasPermission(user, item?.permission);
}

export function canAccessNavigationItem(subject, item) {
  return canAccessNavItem(item, subject);
}

export function getVisibleNavigationItems(subject, items = NAVIGATION_ITEMS) {
  return items.filter((item) => canAccessNavigationItem(subject, item));
}

export function getPermissionDefinition(permissionKey) {
  return PERMISSION_DEFINITIONS.find((permission) => permission.key === permissionKey) || null;
}

export function createPermissionSubject(user, permissions = []) {
  return {
    role: user?.role,
    type: user?.type,
    permissions: Array.isArray(permissions) && permissions.length ? permissions : extractPermissions(user),
  };
}
