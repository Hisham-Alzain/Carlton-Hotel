export const meta = {
  name: 'carlton-full-build',
  description: 'Carlton Hotel Dashboard — full build orchestrator: 6 tracks, cross-track review, hotel simulation script',
  phases: [
    { title: 'Track D', detail: 'Departure Services' },
    { title: 'Track E', detail: 'Folio Line Items' },
    { title: 'Track F', detail: 'Housekeeping Board' },
    { title: 'Track G', detail: 'Guest Profiles' },
    { title: 'Track H', detail: 'Rate Grid + Create Reservation' },
    { title: 'Track I', detail: 'GM Reports + Events' },
    { title: 'Cross-Track Review', detail: 'Naive code consistency review across all 6 tracks' },
    { title: 'Fix Issues', detail: 'Apply fixes found by cross-track review' },
    { title: 'Simulation', detail: 'Write hotel day Playwright simulation script' },
  ],
}

// ─── Track D: Departure Services ────────────────────────────────────────────
phase('Track D')
const resultD = await workflow({ scriptPath: 'C:\\Users\\TECH SHOP\\Documents\\Carlton\\workflows\\track-d.js' })
log('Track D done. Review snippet: ' + String(resultD?.review || '').substring(0, 200))

// ─── Track E: Folio Line Items ───────────────────────────────────────────────
phase('Track E')
const resultE = await workflow({ scriptPath: 'C:\\Users\\TECH SHOP\\Documents\\Carlton\\workflows\\track-e.js' })
log('Track E done. Review snippet: ' + String(resultE?.review || '').substring(0, 200))

// ─── Track F: Housekeeping Board ─────────────────────────────────────────────
phase('Track F')
const resultF = await workflow({ scriptPath: 'C:\\Users\\TECH SHOP\\Documents\\Carlton\\workflows\\track-f.js' })
log('Track F done. Review snippet: ' + String(resultF?.review || '').substring(0, 200))

// ─── Track G: Guest Profiles ─────────────────────────────────────────────────
phase('Track G')
const resultG = await workflow({ scriptPath: 'C:\\Users\\TECH SHOP\\Documents\\Carlton\\workflows\\track-g.js' })
log('Track G done. Review snippet: ' + String(resultG?.review || '').substring(0, 200))

// ─── Track H: Rate Grid + Reservation Creation ───────────────────────────────
phase('Track H')
const resultH = await workflow({ scriptPath: 'C:\\Users\\TECH SHOP\\Documents\\Carlton\\workflows\\track-h.js' })
log('Track H done. Review snippet: ' + String(resultH?.review || '').substring(0, 200))

// ─── Track I: Reports + Events ───────────────────────────────────────────────
phase('Track I')
const resultI = await workflow({ scriptPath: 'C:\\Users\\TECH SHOP\\Documents\\Carlton\\workflows\\track-i.js' })
log('Track I done. Review snippet: ' + String(resultI?.review || '').substring(0, 200))

// ─── Cross-Track Naive Review ────────────────────────────────────────────────
phase('Cross-Track Review')
const crossReview = await agent(`
Cross-track naive code reviewer for Carlton Hotel Dashboard.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

All 6 tracks have been built. Now check consistency and wiring across the whole system.

Read and inspect:
1. src/App.jsx — verify all 6 tracks have routes wired: /operations/departures (D), /reservations/:uuid/folio (E), /operations/housekeeping (F), /guests (G), /guests/:id (G), /rates (H), /reservations/new (H), /reports (I), /events (I). CRITICAL: verify /reservations/new appears BEFORE /reservations/:uuid.
2. src/components/layout/AppShell.jsx — verify nav items exist for all 6 tracks and icons are imported from lucide-react.
3. src/mocks/mockClient.js — verify imports from all 6 mock data files exist: departureItems.js, folios.js, housekeeping.js, guests.js, rates.js, reports.js, events.js. Spot-check 2-3 route handlers per track.
4. src/mocks/data/housekeeping.js — verify it does NOT export ROOMS (must not conflict with reservations.js).
5. src/mocks/data/folios.js — verify balance computed correctly (charges - payments).
6. src/styles/global.css — verify Track D through I CSS sections were appended (look for "=== Track D" through "=== Track I" comments).

Report cross-track issues as:
  CRITICAL: [file] [line hint] — [description] (blocks runtime)
  WARNING: [file] — [description] (may cause incorrect behavior)
  INFO: [file] — [description] (minor or cosmetic)

Group by severity. If a section looks fine, say "OK: [file/section]".
Limit to ≤30 findings total. Do NOT report style issues.
`, { label: 'cross-track-review' })

log('Cross-track review complete: ' + String(crossReview).substring(0, 400))

// ─── Fix Critical Issues ─────────────────────────────────────────────────────
phase('Fix Issues')
const fixResult = await agent(`
You are the issue-fixer agent for Carlton Hotel Dashboard.
Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton

The cross-track reviewer just produced this report:
---
${String(crossReview)}
---

Your job: Fix every CRITICAL issue found. For each CRITICAL item:
1. Read the file mentioned.
2. Apply the minimal correct fix using Edit.
3. Confirm fix applied.

Do NOT fix WARNING or INFO items. Do NOT refactor working code.
If the reviewer said CRITICAL: /reservations/new must appear before /reservations/:uuid in App.jsx — fix that route order.
If the reviewer said CRITICAL: missing import in mockClient.js — add the import.
If the reviewer said CRITICAL: ROOMS exported from housekeeping.js — remove that export.
If the reviewer said CRITICAL: balance computation wrong in folios.js — fix the arithmetic.

After fixes, report: "Fixed: [count] critical issues" with a brief list.
If no CRITICAL issues were found by the reviewer, output: "No critical issues to fix."
`, { label: 'fix-criticals' })

log('Fix pass complete: ' + String(fixResult).substring(0, 300))

// ─── Hotel Day Simulation Script ──────────────────────────────────────────────
phase('Simulation')
const simResult = await agent(`
Write scripts/simulate_hotel_day.py — a Playwright Python script that simulates a full hotel working day.

Working dir: C:\\Users\\TECH SHOP\\Documents\\Carlton
The app runs at http://localhost:5173. Use sync Playwright (playwright.sync_api).

The script should:
1. Import: from playwright.sync_api import sync_playwright; import os, time
2. Set BASE_URL = 'http://localhost:5173'
3. Set ARTIFACTS = os.path.join(os.path.dirname(__file__), '..', 'artifacts', 'sim_screens')
4. Create artifacts dir: os.makedirs(ARTIFACTS, exist_ok=True)
5. Define screenshot helper: def shot(page, name): page.screenshot(path=os.path.join(ARTIFACTS, name+'.png'))
6. Define login helper:
   def login(page, email, password='demo1234'):
       page.goto(BASE_URL + '/login')
       page.fill('[name=email]', email) (or [type=email] if name not available — use page.get_by_label('Email') or page.locator('input[type=email]'))
       page.fill('[name=password]', password) (or page.locator('input[type=password]'))
       page.click('button[type=submit]') (or page.get_by_role('button', name='Sign in'))
       page.wait_for_url(BASE_URL + '/overview', timeout=5000)
       shot(page, 'login_'+email.split('@')[0])

7. Six persona blocks (each in its own with-context or just sequentially with new_page/new_context):

   PERSONA 1: Nadia (admin@carlton.test) — General Manager
     - Login, screenshot overview
     - Go to /reports, screenshot "nadia_reports"
     - Go to /events, screenshot "nadia_events"
     - Go to /rates, screenshot "nadia_rates"
     - Logout (click user menu or navigate to /login)

   PERSONA 2: Omar Mansour (reception@carlton.test) — Front Desk
     - Login, screenshot overview
     - Go to /front-desk, screenshot "omar_frontdesk"
     - Go to /reservations, screenshot "omar_reservations"
     - Go to first reservation (click first row or navigate to /reservations/res_1001), screenshot "omar_res_detail"
     - Go to /reservations/res_1001/folio, screenshot "omar_folio"
     - Go to /operations/queue, screenshot "omar_queue"
     - Go to /operations/departures, screenshot "omar_departures"

   PERSONA 3: Mira (kitchen@carlton.test) — Kitchen Coordinator
     - Login, screenshot overview
     - Go to /operations/queue, screenshot "mira_queue"
     - Try to go to /reservations — expect redirect or empty (no permission)

   PERSONA 4: Layth (housekeeping@carlton.test) — Housekeeping Lead (Arabic)
     - Login, screenshot overview (should be RTL)
     - Go to /operations/housekeeping, screenshot "layth_housekeeping"
     - Go to /operations/queue, screenshot "layth_queue"

   PERSONA 5: Omar Nasser (ops@carlton.test) — Operations Concierge (Arabic)
     - Login, screenshot overview
     - Go to /guests, screenshot "omar_nasser_guests"
     - Go to /guests/guest_001, screenshot "omar_nasser_guest_profile"
     - Go to /operations/departures, screenshot "omar_nasser_departures"

   PERSONA 6: Karim (sales@carlton.test) — Events Sales Manager
     - Login, screenshot overview
     - Go to /events, screenshot "karim_events"
     - Go to /reports, screenshot "karim_reports"
     - Go to /rates, screenshot "karim_rates"

8. main block:
   with sync_playwright() as p:
       browser = p.chromium.launch(headless=True)
       for persona_fn in [nadia_session, omar_session, mira_session, layth_session, omar_nasser_session, karim_session]:
           context = browser.new_context(viewport={'width':1440,'height':900})
           page = context.new_page()
           try:
               persona_fn(page)
           except Exception as e:
               print(f'Persona failed: {e}')
               shot(page, 'error_'+persona_fn.__name__)
           finally:
               context.close()
       browser.close()
   print(f'Simulation complete. Screenshots in: {ARTIFACTS}')

9. Each persona is a function named nadia_session(page), omar_session(page) etc.

IMPORTANT:
- Use try/except around each page.goto and interaction so failures in one persona don't crash the whole run.
- page.wait_for_load_state('networkidle') or page.wait_for_timeout(500) after navigations to ensure content loads.
- Use page.wait_for_timeout(300) between navigation and screenshot.
- Wrap shot() calls to not crash if the page has an error — just screenshot whatever state it's in.

Write the complete, runnable script. This is the LAST artifact of the build — write it carefully.
`, { label: 'write-simulation' })

// The agent writes the file directly — confirm
log('Simulation script agent complete: ' + String(simResult).substring(0, 200))

return {
  tracks: { D: resultD, E: resultE, F: resultF, G: resultG, H: resultH, I: resultI },
  crossReview,
  fixResult,
  simResult,
  status: 'Carlton full build complete — 6 tracks built, reviewed, and hotel simulation script written.',
}
