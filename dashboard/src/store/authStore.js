import { create } from "zustand";
import { persist } from "zustand/middleware";
import { authService } from "../services/authService.js";
import {
  clearAuthToken,
  getApiLocale,
  getAuthToken,
  setApiLocale,
  setAuthToken,
} from "../services/apiClient.js";
import { applyDocumentDirection, normalizeLocale } from "../utils/i18n.js";
import { usePermissionStore } from "./permissionStore.js";

function getPayload(responseOrPayload) {
  return responseOrPayload?.data || responseOrPayload || {};
}

function getErrorPayload(error) {
  return error?.payload || error?.envelope || {
    message: error?.message || "Authentication failed.",
    request_id: error?.request_id || error?.requestId,
    error_code: error?.error_code || error?.errorCode,
    errors: error?.errors,
  };
}

function syncPermissionStore(user, permissions) {
  usePermissionStore.getState().setPermissions(permissions, user);
}

export const useAuthStore = create(persist((set, get) => ({
  token: getAuthToken(),
  user: null,
  permissions: [],
  locale: getApiLocale(),
  direction: applyDocumentDirection(getApiLocale()),
  status: "idle",
  isAuthenticated: false,
  isLoading: false,
  isBootstrapped: false,
  error: null,
  requestId: null,

  setSession(responseOrPayload) {
    const payload = getPayload(responseOrPayload);
    const token = payload.token || get().token || getAuthToken();
    const rawUser = payload.user || null;
    const permissions = payload.permissions || rawUser?.permissions || [];
    const user = rawUser ? { ...rawUser, permissions } : null;
    const locale = normalizeLocale(payload.locale || rawUser?.preferred_locale || rawUser?.locale || get().locale);
    const direction = applyDocumentDirection(locale);

    if (token) setAuthToken(token);
    setApiLocale(locale);
    syncPermissionStore(user, permissions);

    set({
      token,
      user,
      permissions,
      locale,
      direction,
      status: user ? "authenticated" : "unauthenticated",
      isAuthenticated: Boolean(token && user),
      isLoading: false,
      isBootstrapped: true,
      error: null,
      requestId: null,
    });

    return { token, user, permissions, locale, direction };
  },

  async login(credentials) {
    set({ isLoading: true, status: "loading", error: null, requestId: null });

    try {
      const response = await authService.login(credentials);
      const session = get().setSession(response);
      return { success: true, ...session };
    } catch (error) {
      const payload = getErrorPayload(error);
      clearAuthToken();
      usePermissionStore.getState().reset();
      set({
        token: null,
        user: null,
        permissions: [],
        status: "unauthenticated",
        isAuthenticated: false,
        isLoading: false,
        isBootstrapped: true,
        error: payload,
        requestId: payload.request_id || null,
      });
      return { success: false, error: payload };
    }
  },

  async rehydrate() {
    const token = getAuthToken() || get().token;

    if (!token) {
      usePermissionStore.getState().reset();
      set({
        token: null,
        user: null,
        permissions: [],
        status: "unauthenticated",
        isAuthenticated: false,
        isLoading: false,
        isBootstrapped: true,
      });
      return null;
    }

    set({ token, isLoading: true, status: "loading", error: null, requestId: null });

    try {
      setAuthToken(token);
      const response = await authService.me();
      return get().setSession({ ...response.data, token });
    } catch (error) {
      const payload = getErrorPayload(error);
      clearAuthToken();
      usePermissionStore.getState().reset();
      set({
        token: null,
        user: null,
        permissions: [],
        status: "unauthenticated",
        isAuthenticated: false,
        isLoading: false,
        isBootstrapped: true,
        error: payload,
        requestId: payload.request_id || null,
      });
      return null;
    }
  },

  async logout() {
    set({ isLoading: true, status: "loading", error: null, requestId: null });

    try {
      await authService.logout();
    } finally {
      clearAuthToken();
      usePermissionStore.getState().reset();
      set({
        token: null,
        user: null,
        permissions: [],
        status: "unauthenticated",
        isAuthenticated: false,
        isLoading: false,
        isBootstrapped: true,
        error: null,
        requestId: null,
      });
    }
  },

  setLocale(locale) {
    const normalizedLocale = setApiLocale(locale);
    const direction = applyDocumentDirection(normalizedLocale);
    set({ locale: normalizedLocale, direction });
    return { locale: normalizedLocale, direction };
  },

  clearError() {
    set({ error: null, requestId: null });
  },
}), {
  name: "carlton-auth",
  partialize: ({ token, user, permissions, locale, isAuthenticated }) => ({
    token,
    user,
    permissions,
    locale,
    isAuthenticated,
  }),
  onRehydrateStorage: () => (state) => {
    if (!state) return;
    if (state.token) setAuthToken(state.token);
    if (state.locale) {
      setApiLocale(state.locale);
      applyDocumentDirection(state.locale);
    }
    if (state.user) syncPermissionStore(state.user, state.permissions || state.user.permissions || []);
  },
}));
