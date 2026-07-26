import { MOCK_LOGIN_HINTS } from "../mocks/personas.js";
import { apiClient } from "./apiClient.js";

export function login(credentials) {
  return apiClient.post("/auth/login", credentials, { token: null });
}

export function me() {
  return apiClient.get("/auth/me");
}

export function logout() {
  return apiClient.post("/auth/logout");
}

export function getMockLoginHints() {
  return MOCK_LOGIN_HINTS;
}

export const authService = {
  login,
  me,
  logout,
  getMockLoginHints,
};
