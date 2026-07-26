import { findPersonaByCredentials, getPersonaById, getPersonaPermissions, sanitizePersona } from "./personas.js";

const TOKEN_PREFIX = "carlton_mock_token";

export function createMockToken(personaId) {
  return `${TOKEN_PREFIX}.${personaId}.${Date.now().toString(36)}`;
}

export function getPersonaIdFromToken(token) {
  if (!token || typeof token !== "string" || !token.startsWith(`${TOKEN_PREFIX}.`)) {
    return null;
  }

  return token.split(".")[1] || null;
}

export function getPersonaFromToken(token) {
  const personaId = getPersonaIdFromToken(token);
  return personaId ? getPersonaById(personaId) : null;
}

export function validateLoginPayload(payload = {}) {
  const errors = {};

  if (!payload.persona && !payload.email) {
    errors.email = ["Email is required unless a persona key is provided."];
  }

  if (!payload.password) {
    errors.password = ["Password is required."];
  }

  return errors;
}

export function authenticatePersona(payload = {}) {
  const persona = findPersonaByCredentials(payload);

  if (!persona) {
    return null;
  }

  const user = sanitizePersona(persona);
  const permissions = getPersonaPermissions(persona);

  return {
    token: createMockToken(persona.id),
    token_type: "Bearer",
    expires_at: null,
    user,
    permissions,
    locale: user.locale || "en",
  };
}

export function createSessionFromToken(token) {
  const persona = getPersonaFromToken(token);

  if (!persona) {
    return null;
  }

  const user = sanitizePersona(persona);

  return {
    user,
    permissions: getPersonaPermissions(persona),
    locale: user.locale || "en",
  };
}
