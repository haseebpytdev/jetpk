import { test, expect } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { join } from "node:path";
import { mockCsrf, setSessionFixture } from "../jp-full-next-frontend/helpers";
import {
  assertHomepageMarketingSections,
  revealAllScrollTargets,
  waitForHomepageLayout,
} from "./scroll-reveal-helpers";

const EVIDENCE_DIR = join(process.cwd(), ".evidence", "jp-frontend-ux-02");

async function capturePortalBookingsLoadingEvidence(
  page: import("@playwright/test").Page,
  portal: "customer" | "agent",
  outputFile: string,
): Promise<void> {
  const bookingsPath = portal === "customer" ? "/customer/bookings" : "/agent/bookings";
  const apiPattern =
    portal === "customer" ? "**/laravel/customer/bookings?format=json*" : "**/laravel/agent/bookings?format=json*";
  const loadingLabel = portal === "customer" ? "Loading customer bookings" : "Loading agent bookings";

  await page.route(apiPattern, async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 8000));
    await route.continue();
  });

  await setSessionFixture(page, portal);
  await page.goto(bookingsPath, { waitUntil: "domcontentloaded" });

  const loadingIndicator = page
    .getByRole("status", { name: loadingLabel })
    .or(page.getByTestId("skeleton"))
    .or(page.getByText("Loading bookings…"))
    .first();
  await expect(loadingIndicator).toBeVisible({ timeout: 10000 });
  await page.screenshot({ path: join(EVIDENCE_DIR, outputFile), fullPage: true });

  await page.unroute(apiPattern);
}

test.describe("JP-FRONTEND-UX-02 evidence capture", () => {
  test.beforeAll(() => {
    mkdirSync(EVIDENCE_DIR, { recursive: true });
  });

  test("capture representative interaction states", async ({ page, browser }) => {
    await mockCsrf(page);

    await page.emulateMedia({ reducedMotion: "no-preference" });
    await page.goto("/");
    await waitForHomepageLayout(page);
    await page.screenshot({ path: join(EVIDENCE_DIR, "01a-homepage-initial-viewport.png") });

    await revealAllScrollTargets(page);
    await assertHomepageMarketingSections(page);
    await page.screenshot({ path: join(EVIDENCE_DIR, "01-homepage-scroll-reveal.png"), fullPage: true });

    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/");
    await waitForHomepageLayout(page);
    await assertHomepageMarketingSections(page);
    await page.screenshot({ path: join(EVIDENCE_DIR, "02-homepage-reduced-motion.png"), fullPage: true });
    await page.emulateMedia({ reducedMotion: "no-preference" });

    await page.goto("/");
    await page.locator('a[href="/about-us"]').first().click();
    await page.waitForURL("**/about-us");
    await page.screenshot({ path: join(EVIDENCE_DIR, "03-route-navigation-about.png") });

    await page.goto(
      "/flights/results?search_id=jp-ui-01-audit-search-id&trip_type=one_way&from=LHE&to=DXB&depart=2026-08-02&adults=1&children=0&infants=0&cabin=economy",
    );
    await page.waitForLoadState("networkidle");
    await page.screenshot({ path: join(EVIDENCE_DIR, "04-results-loading-or-content.png"), fullPage: true });

    await page.goto("/flights/fare-selection?search_id=jp-ui-01-audit-search-id&offer_id=fixture-offer-1");
    await page.waitForLoadState("networkidle");
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
    await expect(page.getByTestId("payment-status-label")).toHaveText("Pending verification");
    await page.screenshot({ path: join(EVIDENCE_DIR, "07-payment-status-polling.png"), fullPage: true });

    await setSessionFixture(page, "customer");
    await page.goto("/customer/dashboard");
    await page.waitForLoadState("networkidle");
    await page.screenshot({
      path: join(EVIDENCE_DIR, "08-customer-dashboard-backend-error.png"),
      fullPage: true,
    });

    await setSessionFixture(page, "agent");
    await page.goto("/agent/dashboard");
    await page.waitForLoadState("networkidle");
    await page.screenshot({
      path: join(EVIDENCE_DIR, "09-agent-dashboard-backend-error.png"),
      fullPage: true,
    });

    await capturePortalBookingsLoadingEvidence(
      page,
      "customer",
      "12-customer-bookings-loading-skeleton.png",
    );

    await capturePortalBookingsLoadingEvidence(page, "agent", "13-agent-bookings-loading-skeleton.png");

    const darkLoginContext = await browser.newContext();
    const darkLoginPage = await darkLoginContext.newPage();
    await darkLoginPage.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await darkLoginPage.goto("/login");
    await darkLoginPage.waitForLoadState("networkidle");
    await expect(darkLoginPage.locator("#login")).toBeVisible();
    await expect(darkLoginPage.locator('[name="password"]')).toBeVisible();
    await darkLoginPage.screenshot({ path: join(EVIDENCE_DIR, "10-dark-theme-login.png"), fullPage: true });
    await darkLoginContext.close();

    const darkAgentContext = await browser.newContext();
    const darkAgentPage = await darkAgentContext.newPage();
    await mockCsrf(darkAgentPage);
    await setSessionFixture(darkAgentPage, "agent");
    await darkAgentPage.addInitScript(() => localStorage.setItem("jp-theme-preference", "dark"));
    await darkAgentPage.goto("/agent/dashboard");
    await darkAgentPage.waitForLoadState("networkidle");
    await darkAgentPage.screenshot({
      path: join(EVIDENCE_DIR, "11-dark-theme-agent-dashboard.png"),
      fullPage: true,
    });
    await darkAgentContext.close();
  });
});
