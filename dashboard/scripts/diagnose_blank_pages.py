"""
diagnose_blank_pages.py
-----------------------
Captures JS console errors for the two blank pages:
  - /operations/housekeeping  (HousekeepingBoard)
  - /reservations/res_1001/folio  (FolioDetail)

Usage:
    python scripts/diagnose_blank_pages.py
(Requires dev server running at localhost:5173)
"""

import os
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:5173"
ARTIFACTS = os.path.join(os.path.dirname(__file__), "..", "artifacts", "sim_screens")
os.makedirs(ARTIFACTS, exist_ok=True)

PAGES_TO_TEST = [
    ("admin@carlton.test",       "/operations/housekeeping",     "diag_housekeeping_nadia"),
    ("reception@carlton.test",   "/reservations/res_1001/folio", "diag_folio_omar"),
    ("housekeeping@carlton.test","/operations/housekeeping",     "diag_housekeeping_layth"),
]


def run_diagnostic(browser, email, path, label):
    errors = []
    context = browser.new_context(viewport={"width": 1440, "height": 900})
    page = context.new_page()

    # Capture JS console errors
    page.on("console", lambda msg: errors.append(f"[{msg.type}] {msg.text}") if msg.type in ("error", "warning") else None)
    page.on("pageerror", lambda exc: errors.append(f"[pageerror] {exc}"))

    print(f"\n--- {label} ({email}) ---")

    # Login
    try:
        page.goto(BASE_URL + "/login", wait_until="load", timeout=15000)
        page.locator('input[type="email"]').fill(email)
        page.locator('input[type="password"]').fill("demo1234")
        btn = page.get_by_role("button", name="Enter staff desk")
        if btn.count() > 0:
            btn.click()
        else:
            page.click('button[type="submit"]')
        page.wait_for_url(lambda url: "/login" not in url, timeout=10000)
        page.wait_for_timeout(500)
        print(f"  Logged in as {email}, at {page.url}")
    except Exception as exc:
        print(f"  Login failed: {exc}")
        context.close()
        return

    # Clear errors collected during login
    errors.clear()

    # Navigate to the problem page
    try:
        page.goto(BASE_URL + path, wait_until="load", timeout=15000)
        page.wait_for_timeout(1500)  # longer wait to catch delayed errors
        print(f"  Navigated to {path}, at {page.url}")
    except Exception as exc:
        print(f"  goto failed: {exc}")

    # Screenshot
    shot_path = os.path.join(ARTIFACTS, label + ".png")
    page.screenshot(path=shot_path, full_page=True)
    print(f"  Screenshot saved: {label}.png")

    # Print body content length to check if anything rendered
    body_text = page.evaluate("() => document.body.innerText").strip()
    print(f"  Body text length: {len(body_text)} chars")
    if body_text:
        print(f"  Body text preview: {body_text[:200]!r}")

    # Print console errors
    if errors:
        print(f"  Console errors/warnings ({len(errors)}):")
        for err in errors:
            print(f"    {err}")
    else:
        print("  No console errors captured.")

    context.close()


if __name__ == "__main__":
    start = time.time()
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        for email, path, label in PAGES_TO_TEST:
            run_diagnostic(browser, email, path, label)
        browser.close()
    print(f"\nDiagnostic complete in {round(time.time() - start, 1)}s")
