# Phase 4: Guests & Stay - Context

**Gathered:** 2026-09-26
**Status:** Ready for planning (build after Phase 3 has landed: consumes `ReservationCheckedOut`, `config/hotel.php`, the hotel-local clock and `ReservationStateException` context style)
**Decided by:** Fable 5.1 consultant (owner delegated all decisions); D-04/D-08 and D-11 were deliberated by ai-councils (`wf_c5524cf2-127`, `wf_4a8f883c-16e`) whose amendments are folded in. Full consultant text: `.planning/phases/04-guests-stay/04-DISCUSSION-LOG.md`.

<domain>
## Phase Boundary

Staff get a guest directory and profile (with notes, preferences and a derived pre-arrival checklist); guests save preferences and complete online check-in to receive a display-only digital key code. Routes: `GET /guests`, `GET /guests/{guest}`, `GET|POST /guests/{guest}/notes`, `PATCH /guests/{guest}/preferences`, `PATCH /auth/guest/preferences`, `POST /stays/{reservation}/online-check-in`; ID scan reuses `POST /pre-arrival/documents` (docs only). New permissions `guests.view`, `guests.edit` (reception + concierge). Three additive migrations (`guest_notes`, guest preference columns, reservation online-check-in + digital-key columns). Out of scope: staff editing guest identity, note edit/delete, lock hardware or any key verifier, signed document URLs, duplicate-guest merge.

</domain>

<decisions>
## Implementation Decisions

### Guest directory (GUEST-01)
- **D-01:** Route group `Route::middleware('auth:users')->prefix('guests')`: under `permission:guests.view` → `GET /guests`, `GET /guests/{guest}`, `GET /guests/{guest}/notes`; under `permission:guests.edit` → `POST /guests/{guest}/notes`, `PATCH /guests/{guest}/preferences`. `App\Http\Controllers\Admin\GuestController` (BaseController, custom verbs), `App\Services\Guest\GuestService`, `App\Filters\GuestFilter`, requests in `app/Http/Requests/Guest/`. Seeder adds `guests.view`, `guests.edit` under a `guests` group; presets `reception` and `concierge` get both; no other preset.
- **D-02:** `GuestFilter`: `searchable = ['name','first_name','last_name','phone','email']` (`?search=`); `safeParms` `phone [eq, like]`, `email [eq, like]`, `preferred_locale [eq, in]`; `sortable = ['name','last_name','created_at']`; default order `last_name asc, name asc, id asc`. Derived filter `stay_status` (handled in `apply()`, one of `in_house | departing | arriving | upcoming | past | none`, computed against hotel-local today `T` from `config('hotel.timezone')`): `in_house` = has `checked_in`; `departing` = `checked_in` and `check_out = T`; `arriving` = `confirmed` and `check_in = T`; `upcoming` = `confirmed|pending` and `check_in > T`; `past` = ≥1 reservation and none in `pending|confirmed|checked_in` with `check_out >= T`; `none` = no reservations. Unknown value → 422 `validation_failed` on `stay_status`. Guests with no reservations are listed. Row: `{ uuid, name, first_name, last_name, phone, phone_country, phone_verified, email, email_verified, preferred_locale, stay_status, current_reservation: { uuid, booking_code, status, check_in, check_out, room_number } | null, created_at }`; row `stay_status` precedence `departing > in_house > arriving > upcoming > past > none` computed in PHP from a constrained eager load. Pagination via `perPageParam()` (default 15, max 100). Query bound asserted with `expectsDatabaseQueryCount` (target 5) on the service path.

### Profile (GUEST-02)
- **D-03 (council amendment):** The profile's target reservation is the in-house (`checked_in`) reservation if any, else the NEXT arrival (`check_in >= T`, ascending, status `confirmed|pending`); never `sortByDesc`. Implement as `GuestEntitlement::targetReservation()` (or in `GuestService`); the guest-app uploader may keep `currentReservation()` this phase (deferred alignment noted).
- **D-04:** `GET /guests/{guest}` returns `GuestProfileResource` (staff-only, never reused on guest routes): identity fields, `stay_status`, `stats { stays_count (checked_out), cancelled_count, last_check_out }`, `preferences { bed_type, pillow_type, floor_preference, other, updated_at }`, `current_reservation { uuid, booking_code, status, check_in, check_out, checked_in_at, arrival_time, online_check_in_submitted_at, room {uuid, number, floor} | null, room_type {uuid, name}, check_in_approval {uuid, status, notes, approved_by {uuid,name} | null, updated_at} | null, documents [{uuid, type, created_at}], digital_key {issued_at, expires_at, revoked_at, active} | null } | null`, `pre_arrival_checklist` (D-05), `stay_history` (25 most recent by `check_in desc`, all statuses; item shape designed as the future paginated `/guests/{guest}/stays` item) plus `stays_total` (non-cancelled count) and `has_more`, `notes` (10 newest) plus `notes_count`. Documents are metadata only (no `file_path`, no URL); the key code never appears. Composed in `GuestService`/a Support class from the shared builders (`StayPayload`, `PreArrivalChecklist`, `GuestNoteResource`, `GuestPreferencesResource`), no hand-rolled reservation shape. Query bound: spike the eager-load graph, then freeze the measured count (target ≤ 10) with a per-query comment; assert on the service path; a test asserts `GuestProfileResource` is never used by guest-app routes.
- **D-05:** Derived checklist, never stored: `App\Support\PreArrivalChecklist::for(Reservation)` → `{ reservation_uuid, complete, items: [ documents_uploaded {count}, check_in_approved {status}, preferences_set, arrival_time_set {arrival_time}, room_assigned {room_number}, digital_key_issued {expires_at} ] }` in that order; `preferences_set` is true when at least one of `bed_type|pillow_type|floor_preference` is non-null (`any` counts as a positive choice); `complete` = all six; `null` without a target reservation. Also exposed to guests (D-12).

### Notes (GUEST-03)
- **D-06:** Migration `create_guest_notes_table`: `id`, `uuid` unique, `guest_id` FK cascadeOnDelete (indexed), `user_id` FK users nullOnDelete (indexed), `body` text, timestamps; composite index `(guest_id, created_at)`; no soft deletes. Model `App\Models\GuestNote` (`HasUuid`, `HasFactory`, NO `LogsActivity`, no manual activity entry); `Guest::notes()` hasMany.
- **D-07:** `POST /guests/{guest}/notes` (`guests.edit`), `AddGuestNoteRequest { body: required|string|max:2000 }`, `AddGuestNoteAction::handle(Guest, User $author, string $body)` → 201 `GuestNoteResource { uuid, body, author {uuid, name} | null, created_at }`, message `custom.messages.guest_note_added`. `GET /guests/{guest}/notes` (`guests.view`) paginated newest first. Append-only (no edit/delete). Notes appear only on the profile and this list; a test asserts `GET /auth/guest/me` and `GET /stays/*` carry no `notes` key.

### Preferences (GUEST-04)
- **D-08 (council amendment):** Additive migration `add_preferences_to_guests_table`: `bed_type` string(20) cast to existing `BedType` but validated with `Rule::enum(BedType::class)->except([BedType::EXTRA])` (EXTRA is inventory-only; document in the enum); `pillow_type` string(20) → new enum `PillowType { SOFT, MEDIUM, FIRM, FEATHER, HYPOALLERGENIC }`; `floor_preference` string(10) → new enum `FloorPreference { LOW, HIGH, ANY }` (`any` = guest declared no preference); `preferences_other` string(500) nullable; `preferences_updated_at` nullable. All in `$fillable`; `Guest::getActivitylogOptions()` = defaults + `logExcept(['preferences_other','pillow_type'])` (free text and allergy-adjacent value stay out of the log; a free-text-only edit logs only `preferences_updated_at` by design). No JSON column. Split trigger to a 1:1 table: ≥8 preference fields or per-stay preferences.
- **D-09:** One `UpdateGuestPreferencesRequest` for both routes: `bed_type|pillow_type|floor_preference` `sometimes|nullable|Rule::enum`, `other` `sometimes|nullable|string|max:500`; a body with none of the four keys → 422 on `preferences`. PATCH merges (present keys written, explicit `null` clears). `UpdateGuestPreferencesAction::handle(Guest, array, ?User $actor = null)` (transaction, sets `preferences_updated_at`). Routes: guest `PATCH /auth/guest/preferences` in the existing `auth:guests` → `prefix('guest')` block (tier-2; `GuestAuthController::updatePreferences`); staff `PATCH /guests/{guest}/preferences` (`guests.edit`). Both return 200 `GuestPreferencesResource`, message `custom.messages.preferences_updated`; `GuestResource` (guest `me`) gains the same `preferences` object. Test: a guest-initiated PATCH records the guest as activity causer.

### Online check-in and digital key (GUEST-05)
- **D-10:** `POST /stays/{reservation}/online-check-in` in the existing `auth:guests` → `prefix('stays')` group (declared after `/status`). `SubmitOnlineCheckInRequest::authorize()` = reservation belongs to the guest → else 403 `forbidden`. Body `{ arrival_time: required|date_format:H:i }` (hotel-local wall time). Migration `add_online_check_in_to_reservations_table`: `arrival_time` TIME nullable, `online_check_in_submitted_at` nullable. `SubmitOnlineCheckInAction`: transaction + `lockForUpdate`; status must be `confirmed` else `ReservationStateException` (`{status, allowed:["confirmed"]}`); hotel-today must be `<= check_in` else new `OnlineCheckInClosedException` (`online_check_in_closed`, 422, `{check_in, today}`); writes `arrival_time`, `online_check_in_submitted_at = now()` (resubmission overwrites, 200 both times); `CheckInApproval::firstOrCreate(['reservation_id'], ['status' => pending])` (never downgrades approved/rejected). Response 200 `UpcomingStayResource` (D-12), message `custom.messages.online_check_in_submitted`.
- **D-11 (council amendments folded in):** Columns on `reservations` (same migration): `digital_key_code` string(255) nullable with the `encrypted` cast, in `$hidden`, NOT in `$fillable` (written via `forceFill`), plus `->logExcept(['digital_key_code'])`; `digital_key_hash` string(64) nullable indexed (sha256/HMAC with APP_KEY, written at issue, cleared at revoke; no verify endpoint yet); `digital_key_issued_at`, `digital_key_expires_at`, `digital_key_revoked_at` nullable timestamps; `digital_key_revoked_reason` string(20) nullable (`checked_out | cancelled | rejected | expired`). Code: 12 chars from `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` via `random_int`, formatted `XXXX-XXXX-XXXX` (~60 bits), never derived from ids/phones. `IssueDigitalKeyAction`: mints a fresh code whenever no unrevoked, unexpired code exists (approve → reject → approve yields a new code; re-approval of an active key keeps it); called by `ApproveCheckInAction` when the decision is `approved` and status ∈ `{confirmed, checked_in}`; approving `cancelled`/`checked_out` issues nothing; `approved → rejected` revokes with reason `rejected`; issuance is NOT gated on online check-in. Expiry: `digital_key_expires_at` = live `check_out` at `config('hotel.check_out_time')` (new key, env `HOTEL_CHECK_OUT_TIME`, default `12:00`) in the hotel timezone, stored UTC, recomputed whenever `check_out` changes; `active` computed at read time (issued, not revoked, `expires_at > now()`). `RevokeDigitalKeyAction::handle(Reservation, string $reason)` nulls code + hash and sets `revoked_at/reason` in one transaction (no-op without an active key). A scheduled command revokes keys past expiry with reason `expired`. Decryption wrapped in a `DecryptException` catch that treats the key as absent (document `APP_PREVIOUS_KEYS` in ops notes). Exposure: guest stay resources show `digital_key { code, issued_at, expires_at }` only while active, else `null`, with `Cache-Control: no-store` on guest stay responses; staff resources show `{ issued_at, expires_at, revoked_at, active }` only; the push never contains the code. Leakage test covers `toArray()`, `json_encode`, `activity_log` on issue/revoke/forceFill, the FCM payload, the Firestore mirror and the Postman env refresh output. Documented as NOT lock-grade: preconditions for a real lock are non-static OTP, Sanctum token expiry and a hash-based verifier (note added to PITFALLS #6 and the phase summary).
- **D-12:** `UpcomingStayResource`, `ActiveStayResource` and `CheckInStatusResource.reservation` gain `online_check_in { arrival_time, submitted_at, approval_status }`, `digital_key` (guest shape) and `pre_arrival_checklist` via one helper `App\Support\StayPayload`; `StayService` eager-loads `checkInApproval` (new `Reservation::checkInApproval()` hasOne) and `documents`.

### Staff edits, events, contract
- **D-13:** Staff cannot edit guest identity (no `PATCH /guests/{guest}`); only preferences and notes. Verified contacts stay immutable.
- **D-14:** Listener `RevokeDigitalKeyOnCheckOut` on Phase 3's `ReservationCheckedOut` (reason `checked_out`); `CancelReservationAction` calls `RevokeDigitalKeyAction` (reason `cancelled`) inside its transaction; any status transition out of `{confirmed, checked_in}` revokes. New event `CheckInApproved(Reservation, CheckInApproval)` (`ShouldDispatchAfterCommit`) from `ApproveCheckInAction` on `approved`, listener `SendCheckInApprovedNotification` (new `NotificationType::CHECK_IN_APPROVED`), body says the key is ready in the app, never the code.
- **D-15:** Lang keys (five locales): `errors.online_check_in_closed`, `messages.guest_note_added`, `messages.preferences_updated`, `messages.online_check_in_submitted`, `notifications.check_in_approved.{title,body}`; `BaseRequest::messages()` entries for `date_format`/`enum` if missing. `docs/carlton-tree.html`: flip "guest directory · profile" (`GET /guests`, `GET /guests/{guest}`, `GET /guests/{guest}/notes`, `POST /guests/{guest}/notes`), "guest preferences" (`PATCH /auth/guest/preferences`, `PATCH /guests/{guest}/preferences`), "online check-in" (`POST /stays/{reservation}/online-check-in`, meta "arrival time · digital key code, data field, no lock hardware"), "ID scan" (`api:true`, `ep: POST /pre-arrival/documents`, meta "reuses pre-arrival documents · no OCR"); "check-in approvals" meta += "approve issues the digital key". `API_GUIDE_DASHBOARD.md`: new "Module: Guests" + approvals note; `API_GUIDE_MOBILE.md`: preferences, online check-in, stay payload fields, ID scan paragraph; `CHANGELOG_MOBILE_API.md` entry; Postman. Production notes: `[BLOCKING] php artisan migrate`, `php artisan db:seed --class=RolesAndPermissionsSeeder`, env `HOTEL_CHECK_OUT_TIME`, schedule the key-expiry sweep.

### Claude's Discretion
- File/class names above are defaults; `StayPayload`/`PreArrivalChecklist` as classes, traits or resource methods, as long as one implementation feeds every resource.
- `stay_status` via `whereHas` chains or `EXISTS` subqueries; `PillowType` cases (must stay an enum).
- Test file names (`tests/Feature/Guests/{GuestDirectoryTest,GuestProfileTest,GuestNotesTest,GuestPreferencesTest}.php`, `tests/Feature/Stays/{OnlineCheckInTest,DigitalKeyLifecycleTest}.php`, `tests/Unit/Guest/*`), five-locale wording, Postman ordering, whether `stats` uses `withCount` closures or one aggregate query.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Conventions (hard gate)
- `backend/.claude/skills/tupcode-laravel-backend/SKILL.md` + `references/developer-guide.md`; `.claude/skills/{laravel-conventions,module-slice,test-discipline,naive-reviewer}/SKILL.md`
- `.planning/codebase/CONVENTIONS.md` (Phase Summary Contract), `.planning/phases/03-reservations-front-desk-verbs/03-CONTEXT.md` and its SUMMARY (events, hotel clock, `CheckOutMode`), `.planning/phases/02-rooms-status-lifecycle-grids/02-CONTEXT.md`, `.planning/phases/01-access-settings/01-CONTEXT.md` (guest logout, device tokens)
- `.planning/research/PITFALLS.md` (#6 digital key, #7 PII in notes, #8 N+1), `.planning/research/FEATURES.md` (checklist derived, key as data field)

### Existing code this phase extends
- `backend/app/Models/{Guest,Reservation,GuestDocument,CheckInApproval,DeviceToken}.php` and their migrations; `backend/app/Enums/{BedType,CheckInApprovalStatus,NotificationType}.php`
- `backend/app/Actions/Service/{ApproveCheckInAction,SubmitDocumentsAction}.php`, `backend/app/Actions/Booking/CancelReservationAction.php`, `backend/app/Support/GuestEntitlement.php` (`currentReservation`, `bookedReservations`)
- `backend/app/Http/Controllers/Admin/CheckInApprovalController.php`, `backend/app/Http/Controllers/Api/{PreArrivalController,StayController}.php`, `backend/app/Services/Booking/StayService.php`, `backend/app/Services/Auth/AuthGuestService.php`, `backend/app/Http/Requests/Auth/UpdateGuestProfileRequest.php`
- `backend/app/Http/Resources/{GuestResource,GuestDocumentResource}.php`, stay resources (`UpcomingStayResource`, `ActiveStayResource`, `CheckInStatusResource`)
- `backend/app/Base/{BaseFilter,BaseService,BaseController}.php` (`perPageParam`, `resolvePerPage`), `backend/app/Filters/RoomFilter.php` (filter with `apply()` override precedent)
- `backend/app/Listeners/SendRoomReadyNotification.php`, `backend/app/Services/Notification/NotificationService.php`, `backend/app/Traits/LogsActivity.php`, `backend/config/activitylog.php`
- `backend/database/seeders/RolesAndPermissionsSeeder.php`, `backend/config/hotel.php` (Phase 3), `backend/app/Console/Kernel.php` or `routes/console.php` (scheduler)

### API contract & docs
- `backend/docs/API_GUIDE_DASHBOARD.md` (Check-in approvals module; Guests module to add), `backend/docs/API_GUIDE_MOBILE.md` (Pre-arrival, Stays, Auth/profile), `backend/docs/CHANGELOG_MOBILE_API.md`, `backend/docs/postman/carlton-api.postman_collection.json`, `docs/carlton-tree.html`

### Planning artifacts
- `.planning/REQUIREMENTS.md` GUEST-01..06, DOCS-01, XCUT-01; `.planning/ROADMAP.md` Phase 4

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `GuestEntitlement` (target/current reservation semantics), `StayService` eager loads, `GuestDocumentResource` (hides `file_path`), `ApproveCheckInAction` transaction, `SubmitDocumentsAction` (re-opens approval on upload; online check-in must only create-if-missing)
- `BaseFilter` searchable/safeParms/`apply()`; `BaseService::resolvePerPage` (15/100); `RoomFilter` precedent
- `SendRoomReadyNotification` + `NotificationService::pushToGuest` for the approval push; Phase 3's `ReservationCheckedOut` and hotel clock
- `LogsActivity` trait (`logFillable`, `logOnlyDirty`) and `logExcept` hooks

### Established Patterns
- Derived state over stored flags; status + history; domain exceptions with `error_code` and context; five-locale keys; real-bearer-token tests with per-class `staffToken()`; `expectsDatabaseQueryCount` on service paths
- Guest ownership checks in `FormRequest::authorize()` → 403 (receipt routes keep their historical 404)

### Integration Points
- `routes/api.php`: new `guests` group; `auth:guests` blocks for `PATCH /auth/guest/preferences` and `POST /stays/{reservation}/online-check-in`
- `ApproveCheckInAction` (issue key, dispatch `CheckInApproved`), `CancelReservationAction` (revoke), listener on `ReservationCheckedOut`, scheduler for expiry sweep
- Seeder presets `reception`, `concierge`

</code_context>

<specifics>
## Specific Ideas

- The key is a display credential: keep every exposure path enumerated in one leakage test so later phases cannot regress it silently.
- Profile payload is the front-desk "guest at the counter" screen: one round trip, bounded, with counts and `has_more` so the dashboard can grow into paginated sub-resources later.

</specifics>

<deferred>
## Deferred Ideas

- Hash-only + show-once + rotate key design, dedicated throttled `GET /stays/{r}/digital-key`, staff/kiosk verify endpoint (adopt when a lock or verifier is committed)
- `GET /guests/{guest}/stays` paginated; `guests.notes.view`; note edit/delete; staff editing identity; duplicate-guest merge; CSV export
- Signed-URL document viewer / private disk; aligning receipt routes to 403; aligning `GuestEntitlement::currentReservation` for uploads to "next arrival"
- Late check-out modelled as a change to `check_out`; treating `pillow_type` as special-category data; removing the tracked `backend/database/database.sqlite` from git (APP_KEY hygiene)
- `ReservationCancelled` event; key rotation on guest logout; real OTP delivery and Sanctum token expiry (preconditions for lock-grade keys)

</deferred>

---

*Phase: 04-guests-stay*
*Context gathered: 2026-09-26*
