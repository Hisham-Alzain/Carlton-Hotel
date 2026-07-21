export const DEFAULT_LOCALE = "en";
export const SUPPORTED_LOCALES = Object.freeze(["en", "ar"]);
export const RTL_LOCALES = Object.freeze(["ar"]);

export function normalizeLocale(locale) {
  if (!locale || typeof locale !== "string") {
    return DEFAULT_LOCALE;
  }

  const shortLocale = locale.toLowerCase().split("-")[0];
  return SUPPORTED_LOCALES.includes(shortLocale) ? shortLocale : DEFAULT_LOCALE;
}

export function isRtlLocale(locale) {
  return RTL_LOCALES.includes(normalizeLocale(locale));
}

export function getDirection(locale) {
  return isRtlLocale(locale) ? "rtl" : "ltr";
}

export function getIntlLocale(locale) {
  return normalizeLocale(locale) === "ar" ? "ar-SY" : "en-US";
}

export function applyDocumentDirection(locale) {
  if (typeof document === "undefined") {
    return getDirection(locale);
  }

  const normalizedLocale = normalizeLocale(locale);
  const direction = getDirection(normalizedLocale);

  document.documentElement.lang = normalizedLocale;
  document.documentElement.dir = direction;
  document.documentElement.dataset.locale = normalizedLocale;

  return direction;
}

export function pickLocalized(value, locale = DEFAULT_LOCALE, fallback = "") {
  const normalizedLocale = normalizeLocale(locale);

  if (!value) {
    return fallback;
  }

  if (typeof value === "string") {
    return value;
  }

  return value[normalizedLocale] || value[DEFAULT_LOCALE] || value.ar || fallback;
}

export function createLocalizedLabel(en, ar) {
  return { en, ar: ar || en };
}

export function getLocaleStorageValue(storageKey, fallback = DEFAULT_LOCALE) {
  if (typeof localStorage === "undefined") {
    return normalizeLocale(fallback);
  }

  return normalizeLocale(localStorage.getItem(storageKey) || fallback);
}

export function setLocaleStorageValue(storageKey, locale) {
  const normalizedLocale = normalizeLocale(locale);

  if (typeof localStorage !== "undefined") {
    localStorage.setItem(storageKey, normalizedLocale);
  }

  return normalizedLocale;
}
