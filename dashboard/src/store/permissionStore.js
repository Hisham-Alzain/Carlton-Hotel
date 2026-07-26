import { create } from "zustand";
import {
  NAVIGATION_ITEMS,
  createPermissionSubject,
  getMissingPermissions,
  getVisibleNavigationItems,
  hasAllPermissions,
  hasAnyPermission,
  hasPermission,
} from "../utils/permissions.js";

const initialState = {
  user: null,
  permissions: [],
  deniedPermissions: [],
};

function getSubject(state) {
  return createPermissionSubject(state.user, state.permissions);
}

export const usePermissionStore = create((set, get) => ({
  ...initialState,

  setPermissions(permissions = [], user = null) {
    set({
      user,
      permissions: Array.from(new Set(permissions)),
      deniedPermissions: [],
    });
  },

  reset() {
    set(initialState);
  },

  can(requiredPermission) {
    return hasPermission(getSubject(get()), requiredPermission);
  },

  canAny(requiredPermissions = []) {
    return hasAnyPermission(getSubject(get()), requiredPermissions);
  },

  canAll(requiredPermissions = []) {
    return hasAllPermissions(getSubject(get()), requiredPermissions);
  },

  getMissing(requiredPermissions = []) {
    return getMissingPermissions(getSubject(get()), requiredPermissions);
  },

  getVisibleNavigation(items = NAVIGATION_ITEMS) {
    return getVisibleNavigationItems(getSubject(get()), items);
  },

  markDenied(permission) {
    if (!permission) {
      return;
    }

    set((state) => ({
      deniedPermissions: Array.from(new Set([...state.deniedPermissions, permission])),
    }));
  },
}));
