"""
simulate_hotel_day.py
---------------------
Full hotel-day simulation across all six Carlton personas.
Each persona gets an isolated browser context (new_context = empty storage,
no auth bleed). Do NOT use add_init_script to clear localStorage — it runs
on every navigation including the post-login redirect and wipes the auth token.

Usage:
    python scripts/simulate_hotel_day.py

Requires: playwright install chromium
"""

import os
import time

from playwright.sync_api import sync_playwright


BASE_URL = "http://localhost:5173"
ARTIFACTS = os.path.join(os.path.dirname(__file__), "..", "artifacts", "sim_screens")
os.makedirs(ARTIFACTS, exist_ok=True)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def shot(page, name):
    try:
        path = os.path.join(ARTIFACTS, name + ".png")
        page.screenshot(path=path, full_page=True)
        print(f"  [shot] {name}.png")
    except Exception as exc:
        print(f"  [shot] FAILED {name}: {exc}")


def login(page, email, password="demo1234"):
    """Fill login form and wait until the URL leaves /login."""
    try:
        page.goto(BASE_URL + "/login", wait_until="load", timeout=15000)
    except Exception as exc:
        print(f"  [login] goto failed for {email}: {exc}")

    try:
        page.locator('input[type="email"]').fill(email)
        page.locator('input[type="password"]').fill(password)
    except Exception as exc:
        print(f"  [login] fill failed: {exc}")

    try:
        btn = page.get_by_role("button", name="Enter staff desk")
        if btn.count() > 0:
            btn.click()
        else:
            page.click('button[type="submit"]')
    except Exception as exc:
        print(f"  [login] submit failed: {exc}")

    # Wait for React Router to navigate away from /login
    try:
        page.wait_for_url(lambda url: "/login" not in url, timeout=10000)
        page.wait_for_timeout(600)
    except Exception as exc:
        print(f"  [login] redirect wait failed for {email}: {exc}")

    shot(page, "login_" + email.split("@")[0])


def goto(page, path, label=None):
    """Navigate to a path, wait for load, then settle."""
    try:
        page.goto(BASE_URL + path, wait_until="load", timeout=15000)
        # Wait for any skeleton loaders / data fetches to resolve
        page.wait_for_timeout(800)
    except Exception as exc:
        print(f"  [goto] {label or path} failed: {exc}")


def logout(page):
    try:
        page.goto(BASE_URL + "/login", wait_until="load", timeout=10000)
    except Exception:
        pass


# ---------------------------------------------------------------------------
# Persona 1: Nadia Hariri — General Manager (super_admin)
# ---------------------------------------------------------------------------

def nadia_session(page):
    print("\n[Persona 1] Nadia Hariri — General Manager")
    login(page, "admin@carlton.test")

    goto(page, "/overview")
    shot(page, "nadia_overview")

    goto(page, "/reports")
    shot(page, "nadia_reports")

    goto(page, "/events")
    shot(page, "nadia_events")

    goto(page, "/rates")
    shot(page, "nadia_rates")

    goto(page, "/operations/housekeeping")
    shot(page, "nadia_housekeeping")


# ---------------------------------------------------------------------------
# Persona 2: Omar Mansour — Front Desk Supervisor
# ---------------------------------------------------------------------------

def omar_session(page):
    print("\n[Persona 2] Omar Mansour — Front Desk Supervisor")
    login(page, "reception@carlton.test")

    goto(page, "/overview")
    shot(page, "omar_overview")

    goto(page, "/front-desk")
    shot(page, "omar_frontdesk")

    goto(page, "/reservations")
    shot(page, "omar_reservations")

    goto(page, "/reservations/res_1001")
    shot(page, "omar_res_detail")

    goto(page, "/reservations/res_1001/folio")
    shot(page, "omar_folio")

    goto(page, "/operations/queue")
    shot(page, "omar_queue")

    goto(page, "/operations/departures")
    shot(page, "omar_departures")


# ---------------------------------------------------------------------------
# Persona 3: Mira Haddad — Kitchen Coordinator (limited permissions)
# ---------------------------------------------------------------------------

def mira_session(page):
    print("\n[Persona 3] Mira Haddad — Kitchen Coordinator")
    login(page, "kitchen@carlton.test")

    goto(page, "/overview")
    shot(page, "mira_overview")

    goto(page, "/operations/queue")
    shot(page, "mira_queue")

    # No reservations.view permission — expect redirect to /overview
    goto(page, "/reservations")
    shot(page, "mira_reservations_noperm")


# ---------------------------------------------------------------------------
# Persona 4: Layth Saleh — Housekeeping Lead (Arabic locale, RTL)
# ---------------------------------------------------------------------------

def layth_session(page):
    print("\n[Persona 4] Layth Saleh — Housekeeping Lead (Arabic/RTL)")
    login(page, "housekeeping@carlton.test")

    goto(page, "/overview")
    shot(page, "layth_overview")

    goto(page, "/operations/housekeeping")
    shot(page, "layth_housekeeping")

    goto(page, "/operations/queue")
    shot(page, "layth_queue")


# ---------------------------------------------------------------------------
# Persona 5: Omar Nasser — Operations Concierge (Arabic locale)
# ---------------------------------------------------------------------------

def omar_nasser_session(page):
    print("\n[Persona 5] Omar Nasser — Operations Concierge (Arabic)")
    login(page, "ops@carlton.test")

    goto(page, "/overview")
    shot(page, "omar_nasser_overview")

    goto(page, "/guests")
    shot(page, "omar_nasser_guests")

    goto(page, "/guests/guest_001")
    shot(page, "omar_nasser_guest_profile")

    goto(page, "/operations/departures")
    shot(page, "omar_nasser_departures")


# ---------------------------------------------------------------------------
# Persona 6: Karim Azzam — Events Sales Manager
# ---------------------------------------------------------------------------

def karim_session(page):
    print("\n[Persona 6] Karim Azzam — Events Sales Manager")
    login(page, "sales@carlton.test")

    goto(page, "/overview")
    shot(page, "karim_overview")

    goto(page, "/events")
    shot(page, "karim_events")

    goto(page, "/reports")
    shot(page, "karim_reports")

    goto(page, "/rates")
    shot(page, "karim_rates")


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

PERSONAS = [
    nadia_session,
    omar_session,
    mira_session,
    layth_session,
    omar_nasser_session,
    karim_session,
]

if __name__ == "__main__":
    start = time.time()
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        for persona_fn in PERSONAS:
            # new_context() gives a completely isolated, empty-storage context —
            # no need to manually clear localStorage (that would break post-login nav)
            context = browser.new_context(viewport={"width": 1440, "height": 900})
            page = context.new_page()
            try:
                persona_fn(page)
            except Exception as e:
                print(f"  [ERROR] {persona_fn.__name__} crashed: {e}")
                shot(page, "error_" + persona_fn.__name__)
            finally:
                context.close()
        browser.close()

    elapsed = round(time.time() - start, 1)
    print(f"\nSimulation complete in {elapsed}s. Screenshots in: {os.path.abspath(ARTIFACTS)}")
