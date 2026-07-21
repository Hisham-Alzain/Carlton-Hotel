import { getIntlLocale, normalizeLocale } from "./i18n.js";

function normalizeOptions(optionsOrLocale = {}) {
  if (typeof optionsOrLocale === "string") {
    return { locale: optionsOrLocale };
  }

  return optionsOrLocale || {};
}

export function nowUtcIso() {
  return new Date().toISOString();
}

export function toUtcIso(value) {
  if (!value) return null;

  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

export function parseUtcTimestamp(value) {
  if (!value) return null;

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDateTime(value, optionsOrLocale = {}) {
  const options = normalizeOptions(optionsOrLocale);
  const date = parseUtcTimestamp(value);

  if (!date) return options.emptyValue || "";

  return new Intl.DateTimeFormat(getIntlLocale(options.locale), {
    dateStyle: options.dateStyle || "medium",
    timeStyle: options.timeStyle || "short",
    timeZone: options.timeZone,
  }).format(date);
}

export function formatDate(value, optionsOrLocale = {}) {
  const options = normalizeOptions(optionsOrLocale);
  const date = parseUtcTimestamp(value);

  if (!date) return options.emptyValue || "";

  return new Intl.DateTimeFormat(getIntlLocale(options.locale), {
    dateStyle: options.dateStyle || "medium",
    timeZone: options.timeZone,
  }).format(date);
}

export function formatTime(value, optionsOrLocale = {}) {
  const options = normalizeOptions(optionsOrLocale);
  const date = parseUtcTimestamp(value);

  if (!date) return options.emptyValue || "";

  return new Intl.DateTimeFormat(getIntlLocale(options.locale), {
    timeStyle: options.timeStyle || "short",
    timeZone: options.timeZone,
  }).format(date);
}

export function minutesAgo(value) {
  const date = parseUtcTimestamp(value);
  if (!date) return 0;
  return Math.max(1, Math.round((Date.now() - date.getTime()) / 60000));
}

export function formatRelativeAge(value, optionsOrLocale = {}) {
  const options = normalizeOptions(optionsOrLocale);
  const date = parseUtcTimestamp(value);

  if (!date) return options.emptyValue || "";

  const locale = normalizeLocale(options.locale);
  const minutes = minutesAgo(date);

  if (minutes < 1) return locale === "ar" ? "الآن" : "just now";
  if (minutes < 60) return locale === "ar" ? `منذ ${minutes} دقيقة` : `${minutes}m ago`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return locale === "ar" ? `منذ ${hours} ساعة` : `${hours}h ago`;

  const days = Math.floor(hours / 24);
  return locale === "ar" ? `منذ ${days} يوم` : `${days}d ago`;
}

export function addMinutesUtc(value, minutes) {
  const date = parseUtcTimestamp(value) || new Date();
  return new Date(date.getTime() + minutes * 60000).toISOString();
}

export function startOfBrowserDayIso(value = new Date()) {
  const date = value instanceof Date ? new Date(value) : new Date(value);
  date.setHours(0, 0, 0, 0);
  return date.toISOString();
}

export function formatDurationMinutes(minutes, locale = "en") {
  const safeMinutes = Math.max(0, Number(minutes) || 0);
  const normalizedLocale = normalizeLocale(locale);

  if (safeMinutes < 60) {
    return normalizedLocale === "ar" ? `${safeMinutes} دقيقة` : `${safeMinutes} min`;
  }

  const hours = Math.floor(safeMinutes / 60);
  const remainder = safeMinutes % 60;

  if (!remainder) {
    return normalizedLocale === "ar" ? `${hours} ساعة` : `${hours} hr`;
  }

  return normalizedLocale === "ar" ? `${hours} ساعة ${remainder} دقيقة` : `${hours} hr ${remainder} min`;
}
