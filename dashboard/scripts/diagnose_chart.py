"""
diagnose_chart.py
-----------------
Inspects the RevPAR bar chart DOM to find why bars aren't rendering.
Prints computed heights, bounding boxes, and computed styles.
"""
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:5173"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    context = browser.new_context(viewport={"width": 1440, "height": 900})
    page = context.new_page()

    errors = []
    page.on("pageerror", lambda e: errors.append(str(e)))

    # Login as admin
    page.goto(BASE_URL + "/login", wait_until="load", timeout=15000)
    page.locator('input[type="email"]').fill("admin@carlton.test")
    page.locator('input[type="password"]').fill("demo1234")
    page.get_by_role("button", name="Enter staff desk").click()
    page.wait_for_url(lambda url: "/login" not in url, timeout=10000)
    page.wait_for_timeout(600)

    # Navigate to reports
    page.goto(BASE_URL + "/reports", wait_until="load", timeout=15000)
    page.wait_for_timeout(1500)

    if errors:
        print("PAGE ERRORS:", errors)

    # Take screenshot
    page.screenshot(path="artifacts/sim_screens/diag_reports.png", full_page=True)

    # Inspect DOM
    info = page.evaluate("""() => {
        const chart = document.querySelector('.rpt-bar-chart');
        if (!chart) return { error: 'no .rpt-bar-chart found' };

        const wraps = chart.querySelectorAll('.rpt-bar-wrap');
        const firstWrap = wraps[0];
        const firstBar = firstWrap ? firstWrap.querySelector('.rpt-bar') : null;

        const cs = (el) => {
            if (!el) return null;
            const s = window.getComputedStyle(el);
            const r = el.getBoundingClientRect();
            return {
                height: s.height,
                width: s.width,
                display: s.display,
                visibility: s.visibility,
                overflow: s.overflow,
                position: s.position,
                background: s.background,
                inlineStyle: el.style.cssText,
                rect: { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height }
            };
        };

        return {
            chart: cs(chart),
            wrapCount: wraps.length,
            firstWrap: cs(firstWrap),
            firstBar: cs(firstBar),
            chartHTML: chart.innerHTML.slice(0, 500),
        };
    }""")

    import json
    print(json.dumps(info, indent=2))

    context.close()
    browser.close()
