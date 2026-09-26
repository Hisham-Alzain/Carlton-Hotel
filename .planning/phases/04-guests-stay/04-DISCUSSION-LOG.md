# Phase 4: Guests & Stay - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-26
**Phase:** 04-guests-stay
**Mode:** fully automatic (owner instruction 2026-09-26: decisions go to the Fable 5.1 consultant, not the owner)

## How the decisions were made

1. A Fable 5.1 consultant agent inspected the codebase read-only and produced the decision set below (verbatim).
2. It flagged D-04/D-08 and D-11 for council review. Two ai-council workflows ran (`wf_c5524cf2-127` profile/preferences: Architect, User Advocate, Skeptic; confidence 80, not split. `wf_4a8f883c-16e` digital key: Risk & Security, Skeptic, Pragmatist; confidence 76, not split).
3. Council amendments folded into CONTEXT.md: target reservation = in-house else next arrival (never latest-dated); `stays_total` + `has_more`; profile composed from shared builders; measured query bound; `BedType::EXTRA` excluded; `any` documented as a positive choice; guest-causer test; `pillow_type` also excluded from the activity log; key code in `$hidden` + `logExcept`; `digital_key_hash` column; fresh code after any revocation; live expiry recomputed on `check_out` changes + expiry sweep with reason `expired`; DecryptException guard; `Cache-Control: no-store`; 'not lock-grade' note.
4. Council open questions were settled by the consultant role: keep the aggregate profile (dashboard mocks one GET); vocabulary fixed in code; no verifier is committed, so the encrypted-readable display-credential model stands; the tracked sqlite file and late-check-out modelling are deferred.

**Dissent kept:** Skeptic (key council): hash-only + show-once + rotate and a dedicated throttled read endpoint; wins if a lock/kiosk verifier is committed within one or two milestones or if real OTP/token expiry do not land first. Skeptic (profile council): the 10-query bound was a guess (freeze the measured number); hypoallergenic pillow is allergy-adjacent (addressed by excluding it from the log).

---

## Consultant decisions (verbatim)

# Phase 4: Guests & Stay — Consultant Decisions

**Consultant:** Fable 5.1 (owner delegated). Codebase inspected read-only on 2026-09-26. Phase 3 is planned but NOT yet built: D-13/D-14 depend on `ReservationCheckedOut`, `ReservationStateException` and `config/hotel.php` landing first — the planner must sequence Phase 4 after Phase 3's build.

## Decisions

### Guest directory (GUEST-01)

- **D-01 Routes, layers, permissions.** New group `Route::middleware('auth:users')->prefix('guests')`: under `permission:guests.view` → `GET /guests`, `GET /guests/{guest}`, `GET /guests/{guest}/notes`; under `permission:guests.edit` → `POST /guests/{guest}/notes`, `PATCH /guests/{guest}/preferences`. Controller `App\Http\Controllers\Admin\GuestController` on `BaseController` (custom verbs, `paginatedSuccess`/`respondFromService`/`perPageParam`), service `App\Services\Guest\GuestService`, filter `App\Filters\GuestFilter`, requests in `app/Http/Requests/Guest/`. Seeder adds `guests.view`, `guests.edit`; presets: `reception` and `concierge` get both; `housekeeping`, `kitchen`, `events`, `content_*` get neither.
  Rationale: `/guests` is a dashboard-named operational domain (PROJECT.md decision), not CMS CRUD; concierge is the role that personalises stays and takes preference calls. `stakes: low`, `convene: false`.

- **D-02 Filters, sort, pagination, N+1 bound.** `GuestFilter`: `searchable = ['name','first_name','last_name','phone','email']` (`?search=` from `BaseFilter`); `safeParms`: `phone` `[eq, like]`, `email` `[eq, like]`, `preferred_locale` `[eq, in]`; `sortable = ['name','last_name','created_at']`; default order `last_name asc, name asc, id asc`. `stay_status` is a derived filter handled in `GuestFilter::apply()` (not `safeParms`), one value from `in_house | departing | arriving | upcoming | past | none`, computed against hotel-local today `T = CarbonImmutable::now(config('hotel.timezone'))->toDateString()` with `whereHas('reservations', …)` / `whereDoesntHave`:
  `in_house` = has `checked_in` reservation (includes today's departures); `departing` = `checked_in` AND `check_out = T`; `arriving` = `confirmed` AND `check_in = T`; `upcoming` = `confirmed|pending` AND `check_in > T`; `past` = has ≥1 reservation and none in `pending|confirmed|checked_in` with `check_out >= T`; `none` = no reservations. Unknown value → 422 `validation_failed` on `stay_status` (via `BaseFilter::reject`). Guests with no reservations ARE listed (directory is the CRM; `stay_status: none`).
  Row shape: `{ uuid, name, first_name, last_name, phone, phone_country, phone_verified, email, email_verified, preferred_locale, stay_status, current_reservation: { uuid, booking_code, status, check_in, check_out, room_number } | null, created_at }`. Row `stay_status` uses precedence `departing > in_house > arriving > upcoming > past > none`, computed in PHP from a constrained eager load `reservations` (status in `pending|confirmed|checked_in`, `check_out >= T`, with `rooms.room`) plus a `withCount('reservations')`. Query bound: `expectsDatabaseQueryCount(5)` for one page (count, guests, reservations, reservation_rooms, rooms). Pagination via `perPageParam()` → `BaseService::resolvePerPage` (default 15, max 100) — not "20/page".
  Rationale: derived status keeps zero stored flags (FEATURES pre-arrival principle); the six values map 1:1 to front-desk questions; bounded eager loads satisfy PITFALLS #8. `stakes: medium`, `convene: false`.

### Profile (GUEST-02)

- **D-03 Target reservation.** Everything reservation-scoped on the profile (checklist, approval, documents, key) is computed for the guest's *target reservation* = `checked_in` stay if any, else the most recent `confirmed` with `check_out >= T` — i.e. reuse `GuestEntitlement::currentReservation()` semantics so the checklist reflects exactly the reservation the guest's `POST /pre-arrival/documents` writes to. Expose it as `current_reservation`; `null` when none. `stakes: low`, `convene: false`.

- **D-04 `GET /guests/{guest}` shape** (`GuestProfileResource`, staff-only, never reused for guest routes):
  ```
  { uuid, name, first_name, last_name, phone, phone_country, phone_verified, email, email_verified, preferred_locale, created_at,
    stay_status,
    stats: { stays_count (checked_out reservations), cancelled_count, last_check_out (date|null) },
    preferences: { bed_type, pillow_type, floor_preference, other, updated_at },   // all null when unset
    current_reservation: { uuid, booking_code, status, check_in, check_out, checked_in_at, arrival_time, online_check_in_submitted_at,
        room: { uuid, number, floor } | null, room_type: { uuid, name },
        check_in_approval: { uuid, status, notes, approved_by: { uuid, name } | null, updated_at } | null,
        documents: [ { uuid, type, created_at } ],
        digital_key: { issued_at, expires_at, revoked_at, active } | null } | null,
    pre_arrival_checklist: { reservation_uuid, complete, items: [ { key, done, detail } ] } | null,
    stay_history: [ { uuid, booking_code, status, check_in, check_out, checked_in_at, checked_out_at, room_number, room_type_name, total_usd } ],  // 25 most recent by check_in desc, all statuses
    notes: [ GuestNoteResource… ],   // 10 newest
    notes_count }
  ```
  Documents are metadata only — never `file_path`, never a URL (files sit on the public disk, CONCERNS.md; a signed-URL viewer is deferred). No `devices_count` (no dashboard use; PII-adjacent surface). Digital key on staff resources is issued/expiry/revoked/active only — the code is never present. Query bound: `expectsDatabaseQueryCount(10)`.
  Rationale: one round-trip renders the dashboard profile; caps (25 stays, 10 notes) plus `GET /guests/{guest}/notes` keep the payload bounded. `stakes: medium`, `convene: true` (API contract the dashboard will bind to).

- **D-05 Derived checklist.** Never stored. Built by `App\Support\PreArrivalChecklist::for(Reservation $r): array` (pure function over already-loaded relations) with fixed-order items:
  1. `documents_uploaded` — ≥1 `guest_documents` row for the reservation; `detail = { count }`.
  2. `check_in_approved` — `check_in_approvals.status === approved`; `detail = { status: pending|approved|rejected|null }`.
  3. `preferences_set` — any of the four guest preference fields non-null; `detail = null`.
  4. `arrival_time_set` — `reservations.arrival_time` not null; `detail = { arrival_time }`.
  5. `room_assigned` — first `reservation_rooms.room_id` not null; `detail = { room_number }`.
  6. `digital_key_issued` — key active per D-11; `detail = { expires_at }`.
  `complete` = all six `done`. `null` when there is no target reservation. Also exposed to the guest (D-12) so both apps render the same list.
  Rationale: FEATURES.md "computed, not stored"; `room_assigned`/`digital_key_issued` cost nothing and answer the front desk's real question. `stakes: low`, `convene: false`.

### Notes (GUEST-03)

- **D-06 Table and model.** Migration `create_guest_notes_table`: `id`, `uuid` unique, `guest_id` FK `cascadeOnDelete` (indexed), `user_id` FK users `nullOnDelete` (indexed), `body` text, `timestamps`; composite index `(guest_id, created_at)`. No soft deletes, no `updated_at` semantics beyond the timestamp. Model `App\Models\GuestNote` with `HasUuid`, `HasFactory`; does NOT use `LogsActivity` (body is PII; the row is its own audit: author + time), and no manual `activity()` entry is written. `Guest::notes()` hasMany, `User::guestNotes()` optional. `stakes: medium`, `convene: false`.

- **D-07 Endpoints.** `POST /guests/{guest}/notes` (`guests.edit`), `AddGuestNoteRequest` `{ body: required|string|max:2000 }`, `AddGuestNoteAction::handle(Guest, User $author, string $body)` (transaction), 201 with `GuestNoteResource { uuid, body, author: { uuid, name } | null, created_at }`, message `custom.messages.guest_note_added`. `GET /guests/{guest}/notes` (`guests.view`) paginated newest first via `perPageParam`. Append-only in v1: no edit, no delete by anyone. Notes appear only in `GuestProfileResource.notes` and this list — never in `GuestResource`, `ReservationResource`, stay resources, operations queue, reports or exports (a test asserts `GET /auth/guest/me` and `GET /stays/*` carry no `notes` key). Visibility rides on `guests.view` in v1 (only reception/concierge hold it); a separate `guests.notes.view` is deferred.
  Rationale: PITFALLS #7 wants least privilege and no activity-log leakage; the two presets holding `guests.view` are exactly the front-of-house roles that need notes. `stakes: medium`, `convene: false`.

### Preferences (GUEST-04)

- **D-08 Schema.** Dedicated nullable columns on `guests` (additive migration `add_preferences_to_guests_table`): `bed_type` string(20) → existing `BedType` enum; `pillow_type` string(20) → new enum `App\Enums\PillowType { SOFT='soft', MEDIUM='medium', FIRM='firm', FEATHER='feather', HYPOALLERGENIC='hypoallergenic' }`; `floor_preference` string(10) → new enum `FloorPreference { LOW='low', HIGH='high', ANY='any' }`; `preferences_other` string(500) nullable; `preferences_updated_at` timestamp nullable. Casts on `Guest`; all five added to `$fillable`. `Guest` overrides `getActivitylogOptions()` = trait defaults + `->logExcept(['preferences_other'])` (free text may carry PII; enum changes stay auditable). No JSON column.
  Rationale: 3NF + enum validation + filterable later (`bed_type` filter is a one-liner); a JSON blob would hide validation and defeat the milestone's 3NF rule. `stakes: medium`, `convene: true` (schema shape).

- **D-09 Endpoints and semantics.** One request class `App\Http\Requests\Guest\UpdateGuestPreferencesRequest` for both routes: `bed_type` `sometimes|nullable|Rule::enum(BedType)`, `pillow_type` `sometimes|nullable|Rule::enum(PillowType)`, `floor_preference` `sometimes|nullable|Rule::enum(FloorPreference)`, `other` `sometimes|nullable|string|max:500` (request key `other`, column `preferences_other`); `withValidator` rejects a body with none of the four keys (422 `validation_failed`, error on `preferences`). PATCH merge: only present keys are written; explicit `null` clears. `UpdateGuestPreferencesAction::handle(Guest, array $data, ?User $actor = null)`: `DB::transaction`, fill, `preferences_updated_at = now()`, save. Routes: guest `PATCH /auth/guest/preferences` inside the existing `auth:guests` → `prefix('guest')` block (tier-2, no booking gate; `GuestAuthController::updatePreferences`); staff `PATCH /guests/{guest}/preferences` (`guests.edit`). Both respond 200 `data` = `GuestPreferencesResource { bed_type, pillow_type, floor_preference, other, updated_at }`, message `custom.messages.preferences_updated`. `GuestResource` (guest `me`) gains the same `preferences` object (additive). `stakes: low`, `convene: false`.

### Online check-in and digital key (GUEST-05)

- **D-10 Online check-in route.** `POST /stays/{reservation}/online-check-in` inside the existing `auth:guests` → `prefix('stays')` group (declared after `/status`). `SubmitOnlineCheckInRequest`: `authorize()` = `$this->route('reservation')->guest_id === $this->user('guests')?->id` → 403 `forbidden` (roadmap criterion; the older receipt routes keep their 404 — not changed). Body `{ "arrival_time": required|date_format:H:i }` — hotel-local wall time; no guest free-text notes (deferred). Columns (additive migration `add_online_check_in_to_reservations_table`): `arrival_time` TIME nullable, `online_check_in_submitted_at` timestamp nullable. `SubmitOnlineCheckInAction::handle(Reservation, string $arrivalTime)`: `DB::transaction` + `lockForUpdate`; status must be `confirmed` else `ReservationStateException` (`reservation_state`, 422, `{status, allowed:["confirmed"]}`); hotel-today must be `<= check_in` else new `OnlineCheckInClosedException` (`online_check_in_closed`, 422, `{check_in, today}`); writes `arrival_time`, `online_check_in_submitted_at = now()` (resubmission overwrites — idempotent, 200 both times); `CheckInApproval::firstOrCreate(['reservation_id'], ['status' => pending])` — creates the pending row only when missing, never downgrades `approved`/`rejected` (unlike `SubmitDocumentsAction`, which intentionally re-opens on new documents). Response 200, `data` = `UpcomingStayResource` (D-12), message `custom.messages.online_check_in_submitted`. Error codes: 401 `unauthenticated`, 403 `forbidden`, 422 `validation_failed | reservation_state | online_check_in_closed`.
  Rationale: `confirmed` is the only state where arrival planning is meaningful; allowing up to and including the check-in day matches how guests actually use it; creating the approval row lets the desk approve a guest whose ID was checked physically. `stakes: medium`, `convene: false`.

- **D-11 Digital key — storage, entropy, lifecycle.** Columns on `reservations` (same migration): `digital_key_code` string(255) nullable with Laravel `encrypted` cast, `digital_key_issued_at`, `digital_key_expires_at`, `digital_key_revoked_at` (timestamps, nullable), `digital_key_revoked_reason` string(20) nullable (`checked_out | cancelled | rejected`). These five columns are NOT in `$fillable` (written via `forceFill`) so `LogsActivity::logFillable()` can never log the code; a test asserts `activity_log.properties` never contains it. Code: 12 characters from the 32-symbol alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no 0/O/1/I) drawn with `random_int`, ≈ 60 bits, formatted `XXXX-XXXX-XXXX`; generated in `IssueDigitalKeyAction::handle(Reservation): Reservation`. Never derived from uuid/booking_code/phone. Expiry = `check_out` date at `config('hotel.check_out_time')` (new key in `config/hotel.php`, env `HOTEL_CHECK_OUT_TIME`, default `12:00`) in `config('hotel.timezone')`, stored UTC. Active ⇔ `issued_at !== null && revoked_at === null && expires_at > now()`. Issue point: `ApproveCheckInAction` when the decision is `approved` AND reservation status ∈ `{confirmed, checked_in}` AND no active key (re-approving is idempotent, keeps the code); `approved → rejected` revokes with reason `rejected`; approving a `cancelled`/`checked_out` reservation records the approval but issues no key. Not gated on `arrival_time` (approval alone issues the key). Revocation via `RevokeDigitalKeyAction::handle(Reservation, string $reason)` (sets `revoked_at`, `revoked_reason`; no-op when no active key). Exposure: guest stay resources show `digital_key: { code, issued_at, expires_at }` only while active, else `null`; staff resources show `{ issued_at, expires_at, revoked_at, active }` and never `code`. No lock hardware; documented as a data field.
  Rationale: PITFALLS #6 — treat it as a credential: high entropy, single active key, bound to the stay lifecycle, absent from logs and staff payloads; plaintext-at-rest is unavoidable because the guest must re-read it, so encryption at rest plus no staff exposure is the mitigation. `stakes: high`, `convene: true` (security of the key).

- **D-12 Stay resources (additive).** `UpcomingStayResource`, `ActiveStayResource` and `CheckInStatusResource.reservation` gain `online_check_in: { arrival_time, submitted_at, approval_status: pending|approved|rejected|null }`, `digital_key` (D-11 guest shape) and `pre_arrival_checklist` (D-05; `null` on `ActiveStayResource` is acceptable but simpler to include). Built by one helper `App\Support\StayPayload` so the four places cannot drift. `StayService` eager-loads `checkInApproval`, `documents` (`Reservation::checkInApproval()` hasOne added); `GuestEntitlement::bookedReservations` is unchanged. `stakes: low`, `convene: false`.

### Staff edits, events, cross-cutting

- **D-13 Staff cannot edit identity.** No `PATCH /guests/{guest}`; staff change only preferences (D-09) and add notes (D-07). `name`/`phone`/`email` stay guest-owned (OTP-verified contacts are immutable per `VerifiedContactImmutableException`). `stakes: low`, `convene: false`.

- **D-14 Events and listeners.** (a) New listener `App\Listeners\RevokeDigitalKeyOnCheckOut` on Phase 3's `ReservationCheckedOut` → `RevokeDigitalKeyAction(reason: checked_out)` (auto-discovered; covers staff, forced and guest-express check-out). (b) `CancelReservationAction` calls `RevokeDigitalKeyAction(reason: cancelled)` inside its transaction (both guest `DELETE /reservations/{r}` and admin cancel pass through it; no `ReservationCancelled` event this phase). (c) New event `App\Events\CheckInApproved(Reservation, CheckInApproval)` (`ShouldDispatchAfterCommit`), dispatched by `ApproveCheckInAction` on `approved`; one listener `SendCheckInApprovedNotification` mirroring `SendRoomReadyNotification`, new `NotificationType::CHECK_IN_APPROVED = 'check_in_approved'`, push body says the key is ready in the app and NEVER includes the code. `stakes: medium`, `convene: false`.

- **D-15 Contract, docs, tests.** Lang keys in all five locales: `errors.online_check_in_closed`, `messages.guest_note_added`, `messages.preferences_updated`, `messages.online_check_in_submitted`, `notifications.check_in_approved.{title,body}` (follow the `room_ready` key layout), plus `BaseRequest::messages()` entries for `date_format`/`enum` if missing. `docs/carlton-tree.html`: flip "guest directory · profile" (`ep`: `GET /guests`, `GET /guests/{guest}`, `GET /guests/{guest}/notes`, `POST /guests/{guest}/notes`), "guest preferences" (`PATCH /auth/guest/preferences`, `PATCH /guests/{guest}/preferences`), "online check-in" (`POST /stays/{reservation}/online-check-in`, meta "arrival time · digital key code — data field, no lock hardware"), "ID scan" (`api:true`, `ep: POST /pre-arrival/documents`, meta "reuses pre-arrival documents, type=id_card · no OCR") to `api:true`; "check-in approvals" meta += "approve issues the digital key". `API_GUIDE_DASHBOARD.md`: new module "Guests (`guests.view`, `guests.edit`)" + note on the approvals module; `API_GUIDE_MOBILE.md`: preferences under the Auth/profile module, online check-in + `digital_key`/`online_check_in`/`pre_arrival_checklist` fields under Stays, an "ID scan" paragraph under `POST /pre-arrival/documents` (`type: id_card`, one file per side); `CHANGELOG_MOBILE_API.md` entry; Postman updated. Tests: happy/401/403/422 per route; unit tests for `IssueDigitalKeyAction` (alphabet, length, uniqueness across 1 000 draws, expiry math), `RevokeDigitalKeyAction`, `PreArrivalChecklist`, `GuestFilter` `stay_status` (six values, hotel-local boundary); feature tests proving the code never appears in staff resources or `activity_log`, notes never in guest resources, key gone after check-out and after cancel, and the query-count bounds of D-02/D-04. `stakes: low`, `convene: false`.

## Roadmap wording fixes

- GUEST-01 / criterion 1: pagination is `per_page` (default 15, max 100, the codebase standard), not 20/page; `stay_status` values are `in_house | departing | arriving | upcoming | past | none`; add `GET /guests/{guest}/notes`.
- GUEST-02 / criterion 1: checklist items are six — `documents_uploaded, check_in_approved, preferences_set, arrival_time_set, room_assigned, digital_key_issued` — computed for the guest's current/next reservation.
- GUEST-03: append-only; notes visible under `guests.view`, written under `guests.edit`; never logged to `activity_log`.
- GUEST-05 / criterion 3: the key is issued on approval regardless of whether an arrival time was submitted; "stay resource" = `GET /stays/upcoming`, `/stays/active`, `/stays/status` and the online-check-in response; non-owner → 403 `forbidden`; closed window → 422 `online_check_in_closed`; wrong status → 422 `reservation_state`.
- Criterion 5: add "the key code never appears in staff-facing responses, pushes or `activity_log`; `guests.view`/`guests.edit` are added to the `reception` and `concierge` presets".
- Phase 4 "Depends on": Phase 3 (built), not only Phase 1 — `ReservationCheckedOut`, `ReservationStateException` context style and `config/hotel.php` are consumed here.

## Claude's Discretion

- File/class names above are defaults; `StayPayload`/`PreArrivalChecklist` may be traits or resource methods as long as one implementation feeds all resources.
- Whether `stay_status` filtering uses `whereHas` chains or one `EXISTS` subquery per branch; the exact `PillowType` cases (must stay an enum).
- Test file names (`tests/Feature/Guests/{GuestDirectoryTest,GuestProfileTest,GuestNotesTest,GuestPreferencesTest}.php`, `tests/Feature/Stays/OnlineCheckInTest.php`, `tests/Feature/Stays/DigitalKeyLifecycleTest.php`, `tests/Unit/Guest/*`), five-locale wording, Postman ordering.
- Whether `stats` on the profile is computed with `withCount` closures or a small aggregate query, within the D-04 query bound.

## Deferred

- `guests.notes.view` as a separate permission; note editing/deletion; staff editing guest identity (`PATCH /guests/{guest}`); merging duplicate guests; CSV export.
- Signed-URL document viewer and moving guest documents to a private disk (CONCERNS.md item).
- Guest free-text notes on online check-in; early-arrival pricing; `ReservationCancelled` event; key rotation endpoint; real lock-hardware integration (the code is a data field only).
- Aligning `GuestEntitlement::currentReservation` to "next arrival" instead of "latest-dated booking" (kept as-is for consistency with document uploads).
- Aligning the receipt routes' 404-on-foreign-reservation with the new 403 convention.
