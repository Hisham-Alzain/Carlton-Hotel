export const DEFAULT_MOCK_LATENCY_MS = 220;

export class CarltonMockError extends Error {
  constructor(envelope, status = 500) {
    super(envelope?.message || "Mock API error");
    this.name = "CarltonMockError";
    this.status = status;
    this.envelope = envelope;
    this.error_code = envelope?.error_code || "mock_error";
    this.errorCode = this.error_code;
    this.errors = envelope?.errors || {};
    this.request_id = envelope?.request_id;
    this.requestId = this.request_id;
  }
}

export function createRequestId() {
  if (globalThis.crypto?.randomUUID) {
    return globalThis.crypto.randomUUID();
  }

  return `req_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
}

export function createEnvelope({
  success = true,
  message = "Success",
  data = null,
  request_id = createRequestId(),
  error_code = null,
  errors = null,
} = {}) {
  return {
    success,
    message,
    data,
    request_id,
    error_code,
    errors,
  };
}

export function createSuccessEnvelope(data = null, message = "Success") {
  return createEnvelope({
    success: true,
    message,
    data,
    error_code: null,
    errors: null,
  });
}

export function createErrorEnvelope({
  message = "Request failed",
  error_code = "request_failed",
  errors = {},
  data = null,
} = {}) {
  return createEnvelope({
    success: false,
    message,
    data,
    error_code,
    errors,
  });
}

export function throwMockError(options, status = 500) {
  throw new CarltonMockError(createErrorEnvelope(options), status);
}

export function wait(ms = DEFAULT_MOCK_LATENCY_MS) {
  return new Promise((resolve) => {
    globalThis.setTimeout(resolve, ms);
  });
}

export function cloneMockData(value) {
  if (typeof structuredClone === "function") {
    return structuredClone(value);
  }

  return JSON.parse(JSON.stringify(value));
}

export function normalizePagination(params = {}) {
  const page = Math.max(1, Number(params.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(params.per_page || params.perPage) || 10));

  return { page, perPage };
}

export function paginateItems(items, params = {}) {
  const { page, perPage } = normalizePagination(params);
  const total = items.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));
  const start = (page - 1) * perPage;

  return {
    items: items.slice(start, start + perPage),
    meta: {
      page,
      per_page: perPage,
      total,
      last_page: lastPage,
      from: total === 0 ? 0 : start + 1,
      to: Math.min(start + perPage, total),
    },
  };
}

export function parseMockPath(path, params = {}) {
  const url = new URL(path, "https://mock.carlton.local");

  Object.entries(params || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.set(key, value);
    }
  });

  return {
    pathname: url.pathname,
    params: Object.fromEntries(url.searchParams.entries()),
  };
}

export function matchesSearch(value, query) {
  if (!query) {
    return true;
  }

  const haystack = Array.isArray(value) ? value.join(" ") : String(value || "");
  return haystack.toLowerCase().includes(String(query).toLowerCase().trim());
}
