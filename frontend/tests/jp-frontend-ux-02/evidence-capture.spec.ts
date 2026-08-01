import { test } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { join } from "node:path";
import { mockCsrf, setSessionFixture } from "../jp-full-next-frontend/helpers";

const EVIDENCE_DIR = join(process.cwd(), ".evidence", "jp-frontend-ux-02");

test.describe("JP-FRONTEND-UX-02 evidence capture", () => {
  test.beforeAll(() => {
    mkdirSync(EVIDENCE_DIR, { recursive: true });
  });

  test("capture representative interaction states", async ({ page }) => {
    await mockCsrf(page);

    await page.goto("/");
    await page.screenshot({ path: join(EVIDENCE_DIR, "01-homepage-scroll-reveal.png"), fullPage: true });

    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/");
    await page.screenshot({ path: join(EVIDENCE_DIR, "02-homepage-reduced-motion.png"), fullPage: true });
    await page.emulateMedia({ reducedMotion: "no-preference" });

    await page.goto("/");
    await page.locator('a[href="/about-us"]').first().click();
    await page.waitForURL("**/about-us");
    await page.screenshot({ path: join(EVIDENCE_DIR, "03-route-navigation-about.png") });

    await page.goto("/flights/results?search_id=jp-ui-01-audit-search-id&trip_type=one_way&from=LHE&to=DXB&depart=2026-08-02&adults=1&children=0&infants=0&cabin=economy");
    await page.waitForTimeout(1500);
    await page.screenshot({ path: join(EVIDENCE_DIR, "04-results-loading-or-content.png"), fullPage: true });

    await page.goto("/flights/fare-selection?search_id=jp-ui-01-audit-search-id&offer_id=fixture-offer-1");
    await page.waitForTimeout(1500);
    await page.screenshot({ path: join(EVIDENCE_DIR, "05-fare-selection.png"), fullPage: true });

    await page.goto("/login");
    await page.screenshot({ path: join(EVIDENCE_DIR, "06-login.png") });

    await page.route("**/laravel/booking/payment/status**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          ok: true,
          booking_reference: "JP-TEST",
          payment_status: { code: "pending", label: "Pending verification" },
          booking_status: { code: "awaiting_payment", label: "Awaiting payment" },
          poll: { should_poll: true, interval_ms: 5000, max_attempts: 3 },
        }),
      });
    });
    await page.goto("/booking/payment/status");
    await page.waitForTimeout(1000);
    await page.screenshot({ path: join(EVIDENCE_DIR, "07-payment-status-polling.png"), fullPage: true });

    await setSessionFixture(page, "customer");
    await page.goto("/customer/dashboard");
    await page.waitForTimeout(1000);
    await page.screenshot({ path: join(EVIDENCE_DIR, "08-customer-dashboard.png"), fullPage: true });

    await setSessionFixture(page, "agent");
    await page.goto("/agent/dashboard");
    await page.waitForTimeout(1000);
    await page.screenshot({ path: join(EVIDENCE_DIR, "09-agent-dashboard.png"), fullPage: true });

    await page.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await page.goto("/login");
    await page.waitForTimeout(500);
    await page.screenshot({ path: join(EVIDENCE_DIR, "10-dark-theme-login.png") });
  });
});
