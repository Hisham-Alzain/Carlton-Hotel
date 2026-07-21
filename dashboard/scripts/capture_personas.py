"""
Per-persona visual capture for Carlton Hotel Dashboard.
Single browser context — reuses the same page between personas to avoid
Vite HMR WebSocket teardown stalling subsequent context startups.
Output: artifacts/screens/personas/{persona}-{nn}-{page}.png
"""

import os
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

APP_URL = os.environ.get("APP_URL", "http://127.0.0.1:5173")
OUT = Path("artifacts/screens/personas")

PERSONAS = [
    {
        "slug": "nadia",
        "email": "admin@carlton.test",
        "name": "Nadia Hariri - General Manager",
        "pages": [
            ("overview",          "/overview"),
            ("front-desk",        "/front-desk"),
            ("reservations",      "/reservations"),
            ("availability",      "/reservations/availability"),
            ("queue",             "/operations/queue"),
            ("service-requests",  "/operations/service-requests"),
            ("tickets",           "/operations/tickets"),
        ],
    },
    {
        "slug": "omar-reception",
        "email": "reception@carlton.test",
        "name": "Omar Mansour - Front Desk Supervisor",
        "pages": [
            ("front-desk",        "/front-desk"),
            ("reservations",      "/reservations"),
            ("availability",      "/reservations/availability"),
            ("queue",             "/operations/queue"),
            ("service-requests",  "/operations/service-requests"),
            ("tickets",           "/operations/tickets"),
        ],
    },
    {
        "slug": "mira-kitchen",
        "email": "kitchen@carlton.test",
        "name": "Mira Haddad - Kitchen Coordinator",
        "pages": [
            ("queue",            "/operations/queue"),
            ("service-requests", "/operations/service-requests"),
            ("overview",         "/overview"),
        ],
    },
    {
        "slug": "layth-housekeeping",
        "email": "housekeeping@carlton.test",
        "name": "Layth Saleh - Housekeeping Lead (AR)",
        "pages": [
            ("queue",            "/operations/queue"),
            ("reservations",     "/reservations"),
            ("availability",     "/reservations/availability"),
            ("service-requests", "/operations/service-requests"),
        ],
    },
    {
        "slug": "omar-ops",
        "email": "ops@carlton.test",
        "name": "Omar Nasser - Operations Concierge (AR)",
        "pages": [
            ("queue",            "/operations/queue"),
            ("service-requests", "/operations/service-requests"),
            ("tickets",          "/operations/tickets"),
            ("overview",         "/overview"),
        ],
    },
    {
        "slug": "karim-sales",
        "email": "sales@carlton.test",
        "name": "Karim Azzam - Events Sales Manager",
        "pages": [
            ("overview", "/overview"),
        ],
    },
]


def nav(page, path):
    """Client-side navigation — preserves auth token in localStorage."""
    page.evaluate(f"window.history.pushState({{}}, '', '{path}')")
    page.evaluate("window.dispatchEvent(new PopStateEvent('popstate', {state: {}}))")
    page.wait_for_timeout(1500)


def logout(page):
    """Clear auth state and navigate back to login."""
    page.evaluate("""() => {
        localStorage.clear();
        sessionStorage.clear();
    }""")
    page.goto(APP_URL, wait_until="domcontentloaded")
    page.locator('input[type="email"]').wait_for(state="visible", timeout=15000)


def login(page, email):
    email_input = page.locator('input[type="email"]')
    email_input.wait_for(state="visible", timeout=15000)
    email_input.fill(email)
    page.locator('input[type="password"]').fill("demo1234")
    page.get_by_role("button", name="Enter staff desk").click()
    page.wait_for_url("**/overview", timeout=20000)
    page.wait_for_timeout(1500)


def capture(page, name):
    OUT.mkdir(parents=True, exist_ok=True)
    path = OUT / name
    page.screenshot(path=path, full_page=True, timeout=60000)
    print(f"  captured {name}", flush=True)


def wait_for_content(page, path):
    """Best-effort wait for page content to settle after nav."""
    if "/reservations/availability" in path:
        try:
            page.locator("table, .availability-grid, .calendar").first.wait_for(timeout=4000)
        except Exception:
            pass
    elif "/reservations" in path:
        try:
            page.locator("tbody tr").first.wait_for(timeout=4000)
        except Exception:
            pass
    elif "/operations/service-requests" in path:
        try:
            page.locator(".service-request-card, tbody tr, .empty-state").first.wait_for(timeout=4000)
        except Exception:
            pass
    elif "/operations/tickets" in path:
        try:
            page.locator(".ticket-card, tbody tr, .empty-state").first.wait_for(timeout=4000)
        except Exception:
            pass
    page.wait_for_timeout(800)


def main():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        # Initial load — clear any stale state, then stay on login page.
        page.goto(APP_URL, wait_until="domcontentloaded")
        page.evaluate("localStorage.clear(); sessionStorage.clear();")
        page.locator('input[type="email"]').wait_for(state="visible", timeout=30000)
        print("Ready.", flush=True)

        for persona in PERSONAS:
            slug = persona["slug"]
            print(f"\n>> {persona['name']}", flush=True)

            try:
                login(page, persona["email"])

                for i, (page_slug, path) in enumerate(persona["pages"], start=1):
                    try:
                        nav(page, path)
                        wait_for_content(page, path)
                        capture(page, f"{slug}-{i:02d}-{page_slug}.png")
                    except Exception as e:
                        print(f"  ERROR {page_slug}: {e}", file=sys.stderr, flush=True)

                logout(page)

            except Exception as e:
                print(f"  LOGIN ERROR: {e}", file=sys.stderr, flush=True)
                # Try to get back to login page for next persona.
                try:
                    page.evaluate("localStorage.clear(); sessionStorage.clear();")
                    page.goto(APP_URL, wait_until="domcontentloaded")
                    page.locator('input[type="email"]').wait_for(state="visible", timeout=10000)
                except Exception:
                    pass

        context.close()
        browser.close()

    print(f"\nDone. Screenshots in {OUT.resolve()}", flush=True)


if __name__ == "__main__":
    main()
