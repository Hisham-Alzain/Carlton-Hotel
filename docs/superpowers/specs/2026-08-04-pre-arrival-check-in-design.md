# Pre-Arrival & Digital Check-In — Design Spec

**Date:** 2026-08-04
**Status:** Approved (design). Not yet planned or implemented.
**Target:** `mobile/` — Flutter + GetX
**Scope:** Build all screens in Figma file `cpln3bQzXRnpkItKQkJCVs` against demo data.

---

## 1. Source of truth

Figma file key `cpln3bQzXRnpkItKQkJCVs`, page `0:1`. Nine top-level frames.
Canvas left-to-right order is the user journey.

| # | Node | Frame name | Size | Role |
|---|------|-----------|------|------|
| 1 | `75:133` | Home Pre-checkin | 393×2275 | Home in pre-arrival state |
| 2 | `75:463` | CheckIn | 393×879 | Identity tab — nothing scanned |
| 3 | `75:653` | CheckIn | 393×879 | Scanner — framing |
| 4 | `75:703` | CheckIn | 393×879 | Scanner — scanning |
| 5 | `75:757` | CheckIn | 393×879 | Scanner — success |
| 6 | `75:565` | CheckIn | 393×879 | Identity tab — verified |
| 7 | `75:808` | CheckIn | 393×1114 | Preferences tab |
| 8 | `75:928` | CheckIn | 393×879 | Room Key tab |
| 9 | `75:1218` | Button | 393×252 | Digital Key button, 3 states |

Nine frames collapse to **two routes plus one home state**. Frames 2 and 6 are one
view in two states; frames 3, 4 and 5 are one view in three states.

The file defines **no Figma variables** — colours are raw hex.

---

## 2. Decisions

| Decision | Choice |
|---|---|
| Home | A new pre-arrival **state** on the existing `home_view.dart`, reusing its cards |
| ID scanner | **Fully mocked.** No camera, no native deps, no runtime permissions |
| Icons | Reuse the 96 existing `assets/icons/` SVGs where they match; extract only gaps as `chk_*` |
| State | `CheckInService` (`GetxService` in `InitialBinding`), **in-memory only**, resets on restart |
| Build order | Foundation → wizard → home (producer before consumer, no work written twice) |
| Colours | Zero new. All six design colours already exist in `AppColors` |

### Colour mapping (no additions to `app_colors.dart`)

| Design usage | Existing token |
|---|---|
| Hero stay card | `primary` `#08414D` |
| Primary CTAs | `lagoonTeal` `#2F7D8E` |
| `ROOM 812` chip, room-card circle | `antiqueGold` `#B8975A` |
| Key card background | `cream` `#F0EBE2` |
| Verified / completed check | `successGreen` `#4CAF50` |
| Activated digital key | `forestGreen` `#19541C` |

---

## 3. Gaps and explicit assumptions

These are places where the Figma file is silent or conflicts with existing code.
Each has a decided resolution — none are open questions.

**G1 — "Set arrival time" has no screen.** The home checklist lists four steps, but
the wizard covers three. *"Set arrival time · Tap to add your ETA"* has no destination
in the file. **Resolution:** a time-picker bottom sheet built on the existing
`customWidgets/custom_bottom_sheet.dart`, marking `PreArrivalStep.arrivalTime`. This
is our design, not the designer's, and should be reviewed against intent.

**G2 — ID upload already exists.** `/booking/pre-arrival-documents`
(`views/booking/pre_arrival_documents_view.dart`, 220 lines;
`controllers/booking/pre_arrival_documents_controller.dart`, 137 lines) already uploads
documents via `FilePicker`, POSTing multipart to the **real API**. The Figma Identity
tab is a second path to the same outcome. **Resolution:** do not duplicate or delete it.
The scanner's **Upload** control routes to the existing route. One implementation,
two entry points.

**G3 — Flash button is inert.** The scanner's Flash control is decorative: there is no
camera to light. It renders in its normal style and does nothing on tap.

**G4 — Copy typos in the design.** Fixed in code, not reproduced:
`Complete Ceck-In` → `Complete Check-In`; `Matress Type` → `Mattress Type`;
`Eperiences` → `Experiences`.

**G5 — Tab navigation.** The design never shows free tab switching. Completed tabs are
tappable to go back; unvisited tabs are locked.

---

## 4. Architecture

### 4.1 File layout

```
lib/services/check_in_service.dart        NEW  GetxService, InitialBinding
lib/models/check_in/
    reservation_summary.dart              NEW  room, suite, dates, ref, guest
    pre_arrival_step.dart                 NEW  enum + labels
    identity_document.dart                NEW  status + masked doc number
    stay_preferences.dart                 NEW  bed/pillow/mattress + 4 toggles + notes
    digital_key_status.dart               NEW  idle | activating | activated
lib/controllers/check_in/
    check_in_controller.dart              NEW  wizard step index, per-tab form state
    scan_id_controller.dart               NEW  scanner state machine
lib/views/check_in/
    check_in_view.dart                    NEW  3-tab shell
    scan_id_view.dart                     NEW  mocked scanner
lib/components/check_in/
    check_in_tab_bar.dart                 NEW
    check_in_booking_panel.dart           NEW
    digital_key_button.dart               NEW
    arrival_time_sheet.dart               NEW  (G1)
lib/components/custom_toggle_tile.dart    NEW  app-generic, not check-in specific
lib/constants/demo_data.dart              EDIT +preArrivalReservation, +checklist labels
lib/routes/routes.dart                    EDIT +/check-in, +/check-in/scan-id
lib/bindings/binding.dart                 EDIT +CheckInBinding, +CheckInService
lib/views/home/home_view.dart             EDIT +3 sections, +_preArrivalSections list
```

### 4.2 State ownership

`CheckInService` is the single source of truth. Per project convention, shared
multi-screen state is a `GetxService` registered once in `InitialBinding` — never a
route-scoped controller.

It owns:

- `Rx<ReservationSummary> reservation`
- `RxSet<PreArrivalStep> completed`, seeded with `{contactDetails}` so home reads **1/4**
- `Rx<IdentityStatus> identity` — `notStarted | captured | verified`.
  `captured` is the scanner's success state, before the guest confirms; tapping
  `Continue` on the success screen promotes it to `verified`. Only `verified` enables
  the Identity tab's CTA and ticks `PreArrivalStep.identity`.
- `Rx<StayPreferences> preferences`
- `Rx<DigitalKeyStatus> key` — `idle | activating | activated`
- `bool get isPreArrival` — true when the guest has a reservation that has not been
  checked into. In the demo build it is seeded `true` and flips to `false` when
  `Complete Check-In` is tapped, which returns home to its existing reservation state.
- derived: `int completedCount`, `double progress`

Wizard controllers write to it. `HomeController` observes it. Nothing persists;
a restart returns to the seeded 1/4 state.

### 4.3 Tabs are not a `TabController`

The three tabs are a wizard progress indicator driven by the Continue buttons, so the
shell uses `RxInt activeTab` + `PageView`, not a `TabController`. Two reasons:

1. Gotcha #18 — a `TabController` owned by a type-keyed singleton controller crashes on
   re-entry, because the controller outlives the widget that created its ticker.
2. The design shows no free tab switching (see G5).

---

## 5. Screens

### 5.1 Identity tab — `75:463` (notStarted) and `75:565` (verified)

One view, two states keyed off `identity.status`.

Shared chrome: `CustomAppBar` (back arrow, "Check-In"); `CheckInTabBar`; circle icon
chip with "Identity Verification" and its two-line subtitle (empty state only);
`CheckInBookingPanel` — cream card with four label/value rows (Guest, Room, Check-in,
Check-out).

- **notStarted** — "Tap to scan your ID" card with a dark `Scan ID` button routing to
  `/check-in/scan-id`; security note row; `Continue to Preferences` **disabled**.
- **verified** — green card reading `Identity Verified · Passport #SY-20480831 ·
  Ahmed Al-Hassan`; `Continue to Preferences` **enabled**.

CTA is `CustomFilledButton`.

### 5.2 Scanner — `75:653` → `75:703` → `75:757`

One view, three states in `ScanIdController`:

```
framing ──[tap Scan]──▶ scanning ──[1.8s timer]──▶ success
   ▲                                                 │
   └──────────────[tap "Scan again"]─────────────────┘
                                                     │
                        [tap Continue] ──────────────┘
                                 ↓
        CheckInService: identity = verified, PreArrivalStep.identity ✓, pop
```

A static passport asset sits behind the green corner brackets in every state.
`scanning` adds the animated scan line and the thin progress bar plus the copy
"Scanning… / Please don't move your ID". `success` shows the check badge, "Scan
successful!", the captured-ID card with an `Edit` affordance, and `Continue` /
`Scan again`.

Bottom control row: **Flash** (inert, G3), **Scan**, **Upload** (routes to the existing
`/booking/pre-arrival-documents`, G2).

### 5.3 Preferences tab — `75:808`

Mostly reuse.

- Bed Type, Pillow, Mattress Type → three `CustomDropdownField`
  (`components/account/custom_dropdown_field.dart`, 202 lines) fed by the **existing**
  `DemoData.bedOptions`, `DemoData.pillowOptions`, `DemoData.mattressOptions`.
- Smoking Room, Early Check-in, Late Check-out, Extra Pillows → four `CustomToggleTile`
  (new): title + subtitle + `Switch` in a grey card. This generalises the private
  `_switch` helper currently inlined in `views/account/preferences_view.dart`.
- Special Requests → multiline `CustomTextField`.
- `Continue` marks `PreArrivalStep.requests` and advances to Room Key.

### 5.4 Room Key tab — `75:928`

Key icon chip, title, subtitle. Cream room card: gold circle icon, "Grand Damascus
Suite", large `812`, "3rd floor", "14–16 Aug". White info card: "A key Card will be
waiting for you" with the card image. Then `DigitalKeyButton`, then the helper line
"Works with phone locked · tap and hold", then `Complete Check-In`.

`Complete Check-In` marks remaining steps and pops to home.

### 5.5 `DigitalKeyButton` — `75:1218`

Three states, 2-second simulated activation:

| State | Style | Label | Trailing |
|---|---|---|---|
| `idle` | grey card | Add Digital Key to phone | — |
| `activating` | grey card | Activating Digital Key… | `SpinningIconIndicator` (exists) |
| `activated` | `forestGreen` | Activated Digital Key | check |

---

## 6. Home pre-arrival state — `75:133`

`home_view.dart` already switches between two `static const` section lists
(`_reservationSections`, `_exploreSections`) chosen by one narrow `Obx`. Pre-arrival
becomes a **third list**:

```dart
static const _preArrivalSections = <Widget>[
  _PreArrivalStaySection(),        // NEW  teal hero, 2×2 tiles, progress, Check In Now
  _PreArrivalChecklistSection(),   // NEW  4 rows, tick state from CheckInService
  _AirportTransferSection(),       // NEW  image + CTA
  _AiConciergeSection(),           // reuse
  _DiningCarousel(),               // reuse
  _ExperiencesCarousel(),          // reuse
];
```

The selector becomes three-way, gated on `CheckInService.isPreArrival`.

**Constraint:** the swap must keep returning *const instances*. The existing comment in
`build` is load-bearing — `Element.updateChild` short-circuits the subtree when the same
const list comes back, which is what prevents an unrelated profile edit from rebuilding
every carousel. Do not replace the const lists with computed ones.

Section detail for `_PreArrivalStaySection`: `ROOM 812` gold chip and
`PRE-CHECK-IN AVAILABLE` chip; suite name and date line; a 2×2 grid of info tiles
(Check-in / Check-out / Room / Booking Ref); "Pre-arrival progress" with
`N/4 complete` and a bar; `Check In Now` routing to `/check-in`.

`_PreArrivalChecklistSection`: four rows — Confirm contact details (completed),
Upload ID or Passport, Set arrival time, Special requests — each with a leading icon
chip and a trailing state circle, ticking live from `CheckInService.completed`.

---

## 7. Icons

Procedure:

1. Audit every glyph in the nine frames against the 96 SVGs in `assets/icons/`.
2. Reuse matches. `bed`, `calendar`, `key` and `clock` are expected direct hits.
3. Extract only the gaps via `download_assets`, named `chk_*.svg` following the existing
   prefix convention (`acc_*`, `act_*`).
4. **Inline the `var()` fallbacks.** Figma returns these SVGs with
   `fill="var(--stroke-0, #08414D)"`; `flutter_svg` renders **nothing** for a `var()`.
   The fallback hex must be substituted at extraction time.

`assets/icons/` is registered in `pubspec.yaml` as a **directory glob**, so new files
need no manifest edit.

---

## 8. Demo data

Extend `lib/constants/demo_data.dart`:

- `preArrivalReservation` — Ahmed Al-Hassan · Grand Damascus Suite · Room 812 ·
  3rd floor · Aug 14–16 2026 · check-in 3:00 PM · check-out 12:00 PM · ref `#CLT-0082`
- pre-arrival checklist labels and subtitles

Already present and reused unchanged: `bedOptions`, `pillowOptions`, `mattressOptions`.

No network calls. No `ApiService` usage in any new code, with the single exception of
the existing upload route reached through the scanner's Upload control (G2), which keeps
its current real-API behaviour.

---

## 9. Testing and verification

Five tests, each written so it fails if its feature is reverted:

| # | Test | Guards |
|---|---|---|
| 1 | Completing identity moves progress 1/4 → 2/4 | Service linkage between wizard and home |
| 2 | Scanner: framing → scanning → success; "Scan again" → framing | State machine |
| 3 | Digital key: idle → activating → activated | 3-state button |
| 4 | Home picks `_preArrivalSections` when `isPreArrival` | Third-list selector |
| 5 | Identity `Continue` disabled until verified | Tab gate contract |

Test 4 extends the existing `test/home_reservation_state_test.dart` rather than adding a
file. The suite currently holds 11 test files.

Gates before the work is called done:

- `flutter analyze` → **0 issues** (the project baseline)
- `flutter test` → green, with the actual pass count stated

---

## 10. Out of scope

- Any backend endpoint, migration or API wiring for check-in
- Real camera, OCR or MRZ parsing
- Persistence of check-in progress across app restarts
- Changes to the dashboard or backend packages
- Icon replacement outside this feature's screens
- Refactoring `views/account/preferences_view.dart` to adopt the new `CustomToggleTile`.
  The tile is placed in `lib/components/` because it is app-generic, but adopting it in
  the account screen is a separate change.
- Localisation of the new strings beyond the project's existing pattern

---

## 11. Build sequence

**Pass 1 — Foundation.** Icon audit and extraction; demo-data additions; models;
`CheckInService` + `InitialBinding` registration; routes. Ships no UI.

**Pass 2 — Wizard.** `check_in_view.dart` shell and tab bar; Identity tab (both states);
scanner (three states) and its route; Preferences tab; Room Key tab and
`DigitalKeyButton`; arrival-time sheet.

**Pass 3 — Home.** Three new sections; the three-way selector; wire `Check In Now` and
the checklist to the service.

Producer before consumer: the home progress bar is written once, against real service
state.
