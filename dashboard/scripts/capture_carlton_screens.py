import os
from pathlib import Path

from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
from playwright.sync_api import expect, sync_playwright


APP_URL = os.environ.get("APP_URL", "http://127.0.0.1:5173")
OUTPUT_DIR = Path("artifacts/screens")

PASSWORD = "demo1234"

SCENARIOS = [
    {"name": "01-login", "public": True},
    {"name": "02-admin-overview", "email": "admin@carlton.test", "route": "/overview", "ready": "Operations overview"},
    {"name": "03-admin-front-desk", "email": "admin@carlton.test", "route": "/front-desk", "ready": "Front Desk"},
    {"name": "04-admin-reservations", "email": "admin@carlton.test", "route": "/reservations", "ready": "Reservations"},
    {"name": "05-admin-reservation-detail", "email": "admin@carlton.test", "route": "/reservations/res_1001", "ready": "Stay details"},
    {"name": "06-admin-live-queue", "email": "admin@carlton.test", "route": "/operations/queue", "ready": "Live operations queue"},
    {"name": "07-admin-support-tickets", "email": "admin@carlton.test", "route": "/operations/tickets", "ready": "Support Tickets"},
    {"name": "08-admin-support-ticket-detail", "email": "admin@carlton.test", "route": "/operations/tickets/q_2004", "ready": "Folio discrepancy"},
    {"name": "09-admin-departures", "email": "admin@carlton.test", "route": "/operations/departures", "ready": "Departure Services"},
    {"name": "10-admin-housekeeping", "email": "admin@carlton.test", "route": "/operations/housekeeping", "ready": "Housekeeping Board"},
    {"name": "11-admin-folio-detail", "email": "admin@carlton.test", "route": "/reservations/res_1001/folio", "ready": "Folio"},
    {"name": "12-admin-guests", "email": "admin@carlton.test", "route": "/guests", "ready": "Guests"},
    {"name": "13-admin-guest-profile", "email": "admin@carlton.test", "route": "/guests/guest_003", "ready": "Dana Abboud"},
    {"name": "14-admin-reports", "email": "admin@carlton.test", "route": "/reports", "ready": "GM Reports"},
    {"name": "15-admin-events", "email": "admin@carlton.test", "route": "/events", "ready": "Events & BEOs"},
    {"name": "16-admin-event-detail", "email": "admin@carlton.test", "route": "/events/ev_001", "ready": "Gulf Tech Conference 2026"},
    {"name": "17-admin-night-audit", "email": "admin@carlton.test", "route": "/operations/night-audit", "ready": "Night Audit"},
    {"name": "18-reception-front-desk", "email": "reception@carlton.test", "route": "/front-desk", "ready": "Front Desk"},
    {"name": "19-reception-reservation-detail", "email": "reception@carlton.test", "route": "/reservations/res_1001", "ready": "Stay details"},
    {"name": "20-reception-guest-profile", "email": "reception@carlton.test", "route": "/guests/guest_003", "ready": "Dana Abboud"},
    {"name": "21-reception-night-audit", "email": "reception@carlton.test", "route": "/operations/night-audit", "ready": "Night Audit"},
    {"name": "22-kitchen-overview", "email": "kitchen@carlton.test", "route": "/overview", "ready": "Operations overview"},
    {"name": "23-kitchen-service-requests", "email": "kitchen@carlton.test", "route": "/operations/service-requests", "ready": "Service requests"},
    {"name": "24-housekeeping-overview-ar", "email": "housekeeping@carlton.test", "route": "/overview", "ready": "Operations overview"},
    {"name": "25-housekeeping-board-ar", "email": "housekeeping@carlton.test", "route": "/operations/housekeeping", "ready": "لوحة التدبير المنزلي"},
    {"name": "26-concierge-overview-ar", "email": "ops@carlton.test", "route": "/overview", "ready": "Operations overview"},
    {"name": "27-concierge-live-queue-ar", "email": "ops@carlton.test", "route": "/operations/queue", "ready": "Live operations queue"},
    {"name": "28-sales-event-detail", "email": "sales@carlton.test", "route": "/events/ev_001", "ready": "Gulf Tech Conference 2026"},
]


def capture(page, name):
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    page.screenshot(path=OUTPUT_DIR / f"{name}.png", full_page=True)


def goto_login(page):
    page.goto(APP_URL, wait_until="networkidle")
    page.evaluate("localStorage.clear(); sessionStorage.clear();")
    page.goto(APP_URL, wait_until="networkidle")
    expect(page.get_by_role("heading", name="Staff sign in")).to_be_visible()


def login(page, email):
    goto_login(page)
    page.locator('input[type="email"]').fill(email)
    page.locator('input[type="password"]').fill(PASSWORD)
    page.get_by_role("button", name="Enter staff desk").click()
    page.wait_for_url("**/overview")
    page.wait_for_load_state("networkidle")


def wait_for_ready(page, ready_text=None):
    if ready_text:
        try:
            page.get_by_role("heading", name=ready_text).first.wait_for(timeout=12000)
        except PlaywrightTimeoutError:
            page.get_by_text(ready_text, exact=False).first.wait_for(timeout=12000)
    else:
        page.locator("h1, h2, [role='heading']").first.wait_for(timeout=12000)


def open_route(page, route, ready_text=None):
    page.goto(f"{APP_URL}{route}", wait_until="networkidle")
    page.wait_for_load_state("networkidle")
    wait_for_ready(page, ready_text)


def run_scenario(browser, scenario):
    context = browser.new_context(viewport={"width": 1440, "height": 1000})
    page = context.new_page()

    try:
        print(f"Capturing {scenario['name']}")
        if scenario.get("public"):
            goto_login(page)
        else:
            login(page, scenario["email"])
            if scenario["route"] == "/overview":
                wait_for_ready(page, scenario.get("ready"))
            else:
                open_route(page, scenario["route"], scenario.get("ready"))

        capture(page, scenario["name"])
    finally:
        context.close()


def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for scenario in SCENARIOS:
                run_scenario(browser, scenario)
        finally:
            browser.close()

    print(f"Captured {len(SCENARIOS)} screenshots in {OUTPUT_DIR.resolve()}")


if __name__ == "__main__":
    main()
