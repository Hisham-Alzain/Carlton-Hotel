import { CarltonMockError, createErrorEnvelope } from "../mocks/envelope.js";
import { mockRequest } from "../mocks/mockClient.js";
import { DEFAULT_LOCALE, normalizeLocale, setLocaleStorageValue } from "../utils/i18n.js";

export const AUTH_TOKEN_STORAGE_KEY = "carlton.auth.token";
export const LOCALE_STORAGE_KEY = "carlton.locale";

let activeAuthToken = readStorageValue(AUTH_TOKEN_STORAGE_KEY);
let activeLocale = normalizeLocale(readStorageValue(LOCALE_STORAGE_KEY) || DEFAULT_LOCALE);

export class CarltonApiError extends Error {
  constructor(envelope, status = 500) {
    super(envelope?.message || "API request failed");
    this.name = "CarltonApiError";
    this.status = status;
    this.envelope = envelope;
    this.data = envelope?.data || null;
    this.error_code = envelope?.error_code || "request_failed";
    this.errorCode = this.error_code;
    this.errors = envelope?.errors || {};
    this.request_id = envelope?.request_id || null;
    this.requestId = this.request_id;
  }
}

function readStorageValue(key) {
  try {
    return typeof localStorage === "undefined" ? null : localStorage.getItem(key);
  } catch {
    return null;
  }
}

function writeStorageValue(key, value) {
  try {
    if (typeof localStorage === "undefined") {
      return;
    }

    if (value === null || value === undefined || value === "") {
      localStorage.removeItem(key);
      return;
    }

    localStorage.setItem(key, value);
  } catch {
    // Storage can be unavailable in private/SSR contexts. In-memory state still works.
  }
}

export function normalizeEnvelope(envelope) {
  const data = envelope?.data ?? null;

  return {
    success: Boolean(envelope?.success),
    message: envelope?.message || "",
    data,
    items: Array.isArray(data?.items) ? data.items : null,
    meta: data?.meta || null,
    request_id: envelope?.request_id || null,
    requestId: envelope?.request_id || null,
    error_code: envelope?.error_code || null,
    errorCode: envelope?.error_code || null,
    errors: envelope?.errors || null,
    envelope,
  };
}

export function createApiErrorFromEnvelope(envelope, status = 500) {
  return new CarltonApiError(envelope, status);
}

export function setAuthToken(token) {
  activeAuthToken = token || null;
  writeStorageValue(AUTH_TOKEN_STORAGE_KEY, activeAuthToken);
  return activeAuthToken;
}

export function getAuthToken() {
  return activeAuthToken;
}

export function clearAuthToken() {
  return setAuthToken(null);
}

export function setApiLocale(locale) {
  activeLocale = setLocaleStorageValue(LOCALE_STORAGE_KEY, normalizeLocale(locale));
  return activeLocale;
}

export function getApiLocale() {
  return activeLocale;
}

export function getDefaultHeaders(options = {}) {
  const locale = normalizeLocale(options.locale || activeLocale);
  const token = options.token === undefined ? activeAuthToken : options.token;

  return {
    Accept: "application/json",
    "Accept-Language": locale,
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };
}

export async function request(path, options = {}) {
  const method = options.method || "GET";
  const locale = normalizeLocale(options.locale || activeLocale);
  const token = options.token === undefined ? activeAuthToken : options.token;

  try {
    const envelope = await mockRequest({
      method,
      path,
      params: options.params,
      body: options.body,
      token,
      locale,
      latency: options.latency,
      headers: getDefaultHeaders({ ...options, locale, token }),
    });

    const normalized = normalizeEnvelope(envelope);

    if (!normalized.success) {
      throw createApiErrorFromEnvelope(envelope, 500);
    }

    return normalized;
  } catch (error) {
    if (error instanceof CarltonApiError) {
      throw error;
    }

    if (error instanceof CarltonMockError || error?.envelope) {
      throw createApiErrorFromEnvelope(error.envelope, error.status || 500);
    }

    throw createApiErrorFromEnvelope(createErrorEnvelope({
      message: error?.message || "Network request failed",
      error_code: "network_error",
    }), 500);
  }
}

export function get(path, options = {}) {
  return request(path, { ...options, method: "GET" });
}

export function post(path, body, options = {}) {
  return request(path, { ...options, method: "POST", body });
}

export function patch(path, body, options = {}) {
  return request(path, { ...options, method: "PATCH", body });
}

export function put(path, body, options = {}) {
  return request(path, { ...options, method: "PUT", body });
}

export function del(path, options = {}) {
  return request(path, { ...options, method: "DELETE" });
}

export const apiClient = {
  request,
  get,
  post,
  patch,
  put,
  delete: del,
  setAuthToken,
  getAuthToken,
  clearAuthToken,
  setLocale: setApiLocale,
  getLocale: getApiLocale,
  getDefaultHeaders,
};
