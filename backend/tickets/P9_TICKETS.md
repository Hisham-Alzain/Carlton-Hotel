# P9 — Notifications, Chat & Real-time (Firebase) — Tickets

> Source: `PLAN.md` §P9, `ARCHITECTURE.md` §3.6–3.7, §5.8–5.9, §7. Depends on P0–P8 (all green, 205 tests). First phase to actually wire `kreait/laravel-firebase` (installed since P0, config published, never called).

---

## Scope decisions (PLAN.md/ARCHITECTURE.md are terse or slightly inconsistent here — recording gap-fills before coding)

1. **Firebase sits behind an interface**, same pattern as `PaymentGatewayInterface`/`ChannelAdapterInterface`: `FirebaseServiceInterface` (`sendPush`, `mirrorToFirestore`) + a real `FirebaseService` (kreait SDK) bound in `AppServiceProvider`. This dev environment has no live Firebase credentials, so `FirebaseService`'s two methods are integration code, not exercised by the test suite directly (same class of gap as P5's untested `ManualDriver` failure branch) — tests rebind the interface to a fake/spy, matching P6.5's `PaymentGatewayInterface` stub-binding precedent.

2. **`MirrorsToFirestore` concern** (PLAN's own phrase: "used by `service_requests`, tickets (Phase 10), messages") is a trait wrapping `FirebaseServiceInterface::mirrorToFirestore()`. P9 wires it into `MirrorServiceRequestToFirestore` (fulfilling P7's stub) and `SendMessageAction` (new). Tickets is explicitly P10's job — nothing to fulfil yet since the `Ticket` model doesn't exist.

3a. **Table/model named `guest_notifications` / `GuestNotification`, not `notifications`/`Notification`.** `User` already carries Laravel's `Notifiable` trait (scaffolded in P0, unused since), which owns the conventional `notifications` table with Laravel's own schema (`notifiable_type/id`, etc.). A domain table sharing that name would collide with anything that later calls `$user->notify(...)`. Kept distinct and more precise.

3. **`DeviceToken` is guest-only.** ARCHITECTURE §3.6 describes exactly one push-capable client (the guest app); the staff dashboard is web and subscribes to Firestore directly for live updates (§3, "Firestore ... clients subscribe directly for instant updates"). No staff device-token/push channel is specified anywhere, so none is built. `notifications` can still address a *department* (string column, no device) for the inquiry-routed case below — that's a DB record, not a push.

4. **NotificationService triggers — build only what has a real firing action; defer the rest, matching P6/P7's own precedent for forward seams:**
   - **`welcome-on-connect`** — real. Fires the *first* time a guest registers a device token (new `GuestConnected` event from `RegisterDeviceTokenAction`). Re-registering an existing token (same device, e.g. app reopened) does not re-fire it.
   - **`room-ready`** — real. `AssignRoomAction` (P4) is the only place a physical room is attached to a reservation (and flips it to `checked_in`) — this *is* "room ready". Adds one `event(new RoomAssigned($reservation))` call at the end of its existing transaction; purely additive, no behavior change, P4's suite unaffected.
   - **`inquiry-routed`** — real, fulfils P6's `NotifyDepartmentOnInquiry` stub. Since there is no staff device token and Firestore's mirror is reserved for chat + the ops queue (service_requests/tickets) per §3 — not inquiries — "notify the department" is realized as a `Notification` row addressed by `department` (no `guest_id`), queryable later by the dashboard (P10). Not a push, not a Firestore write; a real, tested, persisted fact.
   - **`order-status`** — **deferred to P10.** Firing this needs a service-request status-change action, and P7 explicitly ruled `UpdateRequestStatusAction` out of its own scope ("belongs to P10"). Nothing to hook today; `NotificationService::pushToGuest()` (built for room-ready/welcome) is the exact primitive P10 will call. No dead/speculative code added now.
   - **`ticket-replied`** — **deferred to P10/P11.** The `Ticket` model doesn't exist until P10. General chat notifications (guest message → notify assigned staff's queue view; staff reply → push the guest) are built for real as part of Chat below; "ticket-replied" is that same mechanism applied to a ticket's linked conversation once `Ticket` exists.

5. **Chat tier: `auth:guests` only (tier-2), not `is_checked_in`.** ARCHITECTURE §3.7 point 2 explicitly lists "my chat/tickets" under **tier-2** ("Authenticated guest — any guest token"), alongside device registration — not under the tier-4 in-stay gate. (§5.6's one-line "in-room chat" mention is a usage example, not a stricter gate; §3.7 is the authoritative access-tier section.) A guest can message staff as soon as they're logged in, before check-in.

6. **One open conversation per guest.** `SendMessageAction` (guest side) reuses the guest's existing `status=open` conversation if one exists, else creates one — mirrors the anti-spam "one thread per issue" shape used for chatbot tickets in §5.11, applied here to keep support chat from fragmenting into duplicate threads.

7. **Staff-side chat gated `tickets.respond`** (already seeded since P0, unused until now) — closest existing precedent for "a staff member replies in a guest-facing thread", same permission-reuse pattern P7 used for `cms.edit`/`reservations.create`.

8. **Message sender is `morphTo`** (`sender_type`/`sender_id` via `$table->morphs('sender')`), morph-mapped `'guest' => Guest::class`, `'staff' => User::class` — mirrors P7's bookable morph-map pattern instead of two nullable FK columns.

## Slice
- **Migrations (4):** `device_tokens` (uuid, guest_id FK, token unique, platform, last_used_at), `guest_notifications` (uuid, guest_id? FK, department? string, type, title, body, data json, sent_at?, read_at?), `conversations` (uuid, guest_id FK, assigned_user_id? FK users, status default open, last_message_at?), `messages` (uuid, conversation_id FK, sender morphs, body? text, attachment_path? string, read_at?).
- **Contracts:** `FirebaseServiceInterface`.
- **Services (infra):** `FirebaseService` (kreait SDK — real send/mirror, integration-only).
- **Trait:** `MirrorsToFirestore`.
- **Models:** `DeviceToken`, `GuestNotification`, `Conversation`, `Message` — all `HasUuid`+`LogsActivity`+`HasFactory` (codebase-wide convention).
- **Events:** `GuestConnected` (first device-token registration), `RoomAssigned` (P4 hook).
- **Listeners:** `SendWelcomeNotification`, `SendRoomReadyNotification`; real bodies for `NotifyDepartmentOnInquiry` and `MirrorServiceRequestToFirestore` (both currently empty stubs from P6/P7).
- **Actions:** `RegisterDeviceTokenAction`, `SendMessageAction` (guest + staff sender).
- **Services (domain):** `NotificationService` (`pushToGuest`, `notifyDepartment`), `DeviceTokenService`, `ChatService` (send/history for both guest and staff sides).
- **Requests/Resources/Controllers:** device-token registration; conversation list/history/send (guest, `auth:guests`); admin conversation list/history/reply (`tickets.respond`/`tickets.view`).
- **AppServiceProvider:** bind `FirebaseServiceInterface`→`FirebaseService`; register `RoomAssigned`/`GuestConnected` listeners; extend morph map with `guest`/`staff` for message sender.
- **Tests:** device-token registration (create + upsert-on-same-token + welcome fires once); room-ready notification fires on `AssignRoomAction`; inquiry-routed writes a department `Notification`; service-request-placed mirrors to Firestore (fake); chat: guest sends (auto-creates conversation), staff replies (permission-gated), history paginated, one-open-conversation reuse, unauthenticated/wrong-guest rejected.

## ✅ P9 done-condition (from PLAN.md)
- [ ] Push sends (fake-verified payload correctness); chat persists in MySQL and mirrors to Firestore (fake-verified).
- [ ] All earlier mirror/notification seams fulfilled (`NotifyDepartmentOnInquiry`, `MirrorServiceRequestToFirestore`) or explicitly re-deferred with a named reason (`order-status`, `ticket-replied` → P10).
- [ ] Full `php artisan test` green; P0–P8 suites unchanged.
- [ ] **Report and wait.**
