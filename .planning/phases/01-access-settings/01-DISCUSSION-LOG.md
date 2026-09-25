# Phase 1: Access & Settings - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-25
**Phase:** 01-access-settings
**Areas discussed:** Profile fields & email change, Password change policy, Guest logout scope, Error codes & responses

The user selected all four areas. The first two were answered interactively. For the last two the user said: "so get the rest of the context searching for it in the document on the directory" — those decisions were derived from the repo's own documents (API guides, carlton-tree.html, LOG_REPORT.md, migrations) and are marked as derived in CONTEXT.md.

---

## Profile fields & email change

| Option | Description | Selected |
|--------|-------------|----------|
| Name + email only | No migration; matches the users table today; roles/type/is_active stay admin-only | ✓ |
| Add phone + locale | Migration adds nullable phone (E.164) and locale (en/ar) | |
| Add phone + locale + avatar | As above plus avatar via FileTrait/media | |

**User's choice:** Name + email only

| Option | Description | Selected |
|--------|-------------|----------|
| Require current password | PUT /auth/profile must include current_password whenever email differs | ✓ |
| No confirmation | Email updates like any other field | |
| Email not self-editable | Only admins change email via PUT /staff/{staff} | |

**User's choice:** Require current password
**Notes:** Uniqueness follows UpdateStaffRequest (`Rule::unique('users','email')->ignore($userId)`).

---

## Password change policy

| Option | Description | Selected |
|--------|-------------|----------|
| Revoke other devices, keep current | Delete every token except the one used for the request | ✓ |
| Revoke all, force re-login | Delete every token including the current one | |
| Keep all tokens | Password changes without touching sessions | |

**User's choice:** Revoke other devices, keep current

| Option | Description | Selected |
|--------|-------------|----------|
| min 8, same as CreateStaffRequest | `required|string|min:8|confirmed` | ✓ |
| min 8 + letters + numbers | `Password::min(8)->letters()->numbers()` | |
| min 12 + mixed case + numbers + symbols | Strongest; may annoy staff | |

**User's choice:** min 8, same as CreateStaffRequest
**Notes:** Throttle (`throttle:5,1`) and `different:current_password` were derived from the developer guide's throttle rule and left as decisions D-04/D-06, not asked.

---

## Guest logout scope (derived from documents)

| Option | Description | Selected |
|--------|-------------|----------|
| Current token only | Mirrors AuthStaffService::logout; tree node says "token never revoked" today | ✓ (derived) |
| All tokens for the guest | "Sign out everywhere" | |

| Option | Description | Selected |
|--------|-------------|----------|
| Optional device_token in body, delete that row if owned by this guest | Symmetric to register-on-login (API_GUIDE_MOBILE §POST /api/device-tokens; upsert-by-token per LOG_REPORT P9) | ✓ (derived) |
| Delete all device tokens for the guest | Would kill pushes on other devices of the same guest | |
| Leave device tokens untouched | Pushes could reach a signed-out device until re-registration | |

**Sources:** `docs/carlton-tree.html` ("guest sign out": clears storage · token never revoked), `backend/docs/API_GUIDE_MOBILE.md` (device-token registration semantics), `LOG_REPORT.md` P1/P9, `database/migrations/*device_tokens*` (token unique, guest_id FK).

---

## Error codes & responses (derived from documents)

| Option | Description | Selected |
|--------|-------------|----------|
| 422 validation_failed + errors.current_password | Reuses the field-error contract both clients render; no new error_code | ✓ (derived) |
| Domain exception with error_code invalid_current_password | New code to announce to the Flutter/React teams | |

| Option | Description | Selected |
|--------|-------------|----------|
| Reuse StaffResource (same as /auth/me) for profile responses | Zero new resource; dashboard already parses it | ✓ (derived) |
| Dedicated slim ProfileResource | New shape to document | |

**Sources:** `backend/docs/API_GUIDE_DASHBOARD.md` and `API_GUIDE_MOBILE.md` (envelope, `validation_failed` 422 with `errors` keyed by field path, `unauthenticated` 401), `LOG_REPORT.md` P2 (deactivate revokes tokens), existing lang keys `messages.profile_updated`, `auth.logged_out`.

---

## Claude's Discretion

- `data: null` vs `{ revoked_sessions: n }` on password change
- Exact new lang key names within the existing groups
- Service methods vs new Actions (services are small; keep in services)
- Test file names; throttle limiter implementation detail

## Deferred Ideas

- Staff phone / locale / avatar on the profile (migration + FileTrait) — future phase
- Guest "sign out everywhere" — not requested by the app
