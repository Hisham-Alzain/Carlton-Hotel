import os
import re

from playwright.sync_api import expect, sync_playwright


APP_URL = os.environ.get("APP_URL", "http://127.0.0.1:5173")


def main():
    browser_errors = []

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 1000})
        context.add_init_script("localStorage.clear();")
        page = context.new_page()
        page.on("pageerror", lambda error: browser_errors.append(str(error)))
        page.on(
            "console",
            lambda message: browser_errors.append(message.text)
            if message.type == "error" and "Failed to load resource" not in message.text
            else None,
        )

        page.goto(APP_URL, wait_until="networkidle")
        expect(page.get_by_role("heading", name="Staff sign in")).to_be_visible()

        page.locator('input[type="email"]').fill("admin@carlton.test")
        page.locator('input[type="password"]').fill("demo1234")
        page.get_by_role("button", name="Enter staff desk").click()

        page.wait_for_url(re.compile(r".*/overview$"))
        expect(page.get_by_role("heading", name="Operations overview")).to_be_visible()
        expect(page.get_by_text("Occupancy")).to_be_visible()

        page.get_by_role("link", name=re.compile(r"^Reservations$")).click()
        page.wait_for_url(re.compile(r".*/reservations$"))
        expect(page.get_by_role("heading", name="Reservations")).to_be_visible()
        first_reservation = page.locator("tbody tr.clickable").first
        expect(first_reservation).to_be_visible()
        first_reservation.click()

        page.wait_for_url(re.compile(r".*/reservations/res_.*"))
        expect(page.get_by_text("Stay details")).to_be_visible()

        if page.get_by_role("button", name="Confirm check-in").is_visible():
            page.locator("select").last.select_option(index=1)
            page.get_by_role("button", name="Confirm check-in").click()
            expect(page.get_by_text("checked in")).to_be_visible()

        page.get_by_role("link", name=re.compile(r"^Live Queue$")).click()
        page.wait_for_url(re.compile(r".*/operations/queue$"))
        expect(page.get_by_role("heading", name="Live operations queue")).to_be_visible()
        expect(page.get_by_text("Live mock connected")).to_be_visible()

        page.get_by_role("link", name=re.compile(r"^Front Desk$")).click()
        page.wait_for_url(re.compile(r".*/front-desk$"))
        expect(page.get_by_role("heading", name="Front Desk")).to_be_visible()
        expect(page.get_by_text("Arrivals today")).to_be_visible()

        browser.close()

    if browser_errors:
        raise AssertionError("\n".join(browser_errors))

    print("Carlton smoke passed: login, overview, reservations, detail, queue, and front desk.")


if __name__ == "__main__":
    main()
