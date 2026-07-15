# Carlton API — Postman Collection

Everything a frontend developer needs to explore and test the live API: a full
Postman collection (155 requests across every module through P10) plus a
pre-populated environment pointing at a freshly-seeded local database.

## Setup

1. **Seed the database** (from `backend/`):
   ```
   php artisan migrate:fresh --seed
   ```
   This runs every migration and populates realistic demo data — see
   "What gets seeded" below.

2. **Serve the app:**
   ```
   php artisan serve
   ```
   Default `base_url` in the environment is `http://localhost:8000/api` —
   change it in Postman if you're running on a different host/port.

3. **Import both files into Postman:**
   - `carlton-api.postman_collection.json`
   - `carlton-api.postman_environment.json`

   Select **"Carlton Hotel — Local (seeded)"** as the active environment
   (top-right dropdown in Postman).

4. **Start testing.** The environment already has a working `staff_token`
   (super admin — can call every admin endpoint) and `guest_token` (Ahmad
   Khalil, a checked-in demo guest — can call every guest-tier endpoint,
   including tier-3b in-room services). Folder `02 - Auth` has additional
   "Login as ..." requests for testing role-specific permission boundaries.

## Keeping tokens fresh

Every `php artisan migrate:fresh --seed` regenerates the database, which
invalidates every token in the environment file (they're real Sanctum
tokens tied to specific seeded rows). After reseeding, run:

```
php artisan postman:refresh-env
```

This regenerates `carlton-api.postman_environment.json` in place against
whatever's currently in the database — re-select the environment in Postman
(or re-import the file) afterward to pick up the new values. You do **not**
need to re-import the collection itself; only the environment changes.

Alternatively, `02 - Auth`'s "Login as ..." requests each carry a test
script that writes the returned token straight back into the active
environment — just hit Send on any of them at any time.

## What gets seeded

- **Staff** (password `password` for all): `super@carlton.demo`
  (super_admin — bypasses every permission check), one account per role
  preset (`reception@`, `kitchen@`, `housekeeping@`, `concierge@`,
  `events@carlton.demo`), plus two no-role accounts (`newstaff@`,
  `trainee@carlton.demo`) for testing the "no permission → 403" path.
- **Guests**: five named demo guests in different states — Ahmad Khalil
  (checked in, full activity), Layla Hassan (confirmed, pre-arrival only),
  Sara Ibrahim (checked out, settled folio), Omar Youssef (cancelled
  booking), Rania Saad (never OTP-verified, mid soft-hold) — plus 10 random
  guests for pagination.
- **Content**: 5 active room types + 1 inactive (27 rooms across them),
  5 facilities, 3 dining venues, 3 event spaces, 4 pages, 3 promotions —
  every gallery has 1–3 generated placeholder photos attached (see
  "Photos vs. videos" below).
- **Booking**: rate plans + seasonal/weekend pricing rules, 3 promo codes
  (`SUMMER10`, `WELCOME20`, and `EXPIRED05` — deliberately expired, for
  testing the `invalid_promo` error), and reservations covering every
  status (`pending_verification`, `pending`, `confirmed`, `checked_in`,
  `checked_out`, `cancelled`).
- **Events/RFP**: 7 inquiries spanning every status and both departments
  (`sales`/`events`).
- **Service layer**: 4 spa services, 12 restaurant tables (across the 3
  dining venues), 5 pool cabanas, 3 transfers, 3 menu categories / 9 items;
  service bookings, service requests, pre-arrival documents + check-in
  approvals (one approved, one pending), folios (one open, one settled).
- **P9/P10**: a device token, welcome/room-ready/inquiry-routed
  notifications, two conversations (one staff-claimed, one unassigned —
  for testing the "unassigned queue" view), and 5 tickets across every
  status/category (chatbot-sourced — nothing else creates tickets until
  P11 ships, so these are the only way to exercise the ops queue's ticket
  half today).

## Photos vs. videos

Every CMS gallery (room types, rooms, facilities, dining venues, event
spaces, promotions) is seeded with real, servable JPEG placeholders —
generated on the fly, saved to `storage/app/public`, and returned as real
`url` fields you can open in a browser.

**Video is not seeded, and isn't supported by the API yet.** The only
upload endpoints in the system (`POST /cms/{type}/{uuid}/images` and the
chat attachment field) validate against `jpg,jpeg,png,webp` — there's no
video mime type accepted anywhere, and no video-specific field in any
model. Seeding a fake `.mp4` would have produced a broken, unplayable file
that looked real in the API response — actively misleading for testing
purposes, so it was left out. If real video support (upload validation,
storage, maybe transcoding) is something you want, that's a real feature
to scope and build, not something seed data can fake into existing.

## Regenerating demo data / photos from scratch

`php artisan migrate:fresh --seed` is fully idempotent — safe to run as
often as you like. Photos are regenerated fresh each time (new random
filenames), so old orphaned files can accumulate in
`storage/app/public/cms/`; periodically clearing that directory is safe
since the DB rows referencing them get dropped by the same `migrate:fresh`.

## Collection structure

Numbered folders match the phase build order and the three API guides
(`API_GUIDE_MOBILE.md`, `API_GUIDE_DASHBOARD.md`, `API_GUIDE_WEBSITE.md`)
in `backend/docs/`) — read those alongside the collection for full field-
level documentation of every request/response shape, error code, and
permission requirement. Requests prefixed `⚠️` or `❌` are intentionally
destructive or intentionally-failing demonstrations; read their
description before running them in a session you care about preserving.
