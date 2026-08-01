import { expect, test } from "@playwright/test";
import { assertNoHorizontalOverflow, setSessionFixture } from "./helpers";
import { resultsQuery, setupScenarioMocks } from "../visual-audit/jp-ui-01-fixtures";

const VIEWPORTS = [
  { name: "desktop", width: 1440, height: 1200 },
  { name: "tablet", width: 768, height: 1024 },
  { name: "mobile", width: 390, height: 844 },
] as const;

const REPRESENTATIVE_PAGES = [
  { id: "homepage", path: "/", setup: "public" as const },
  { id: "login", path: "/login", setup: "auth" as const },
  { id: "results", path: `/flights/results?${resultsQuery()}`, setup: "results" as const },
  { id: "fare-selection", path: "/flights/fare-selection?search_id=jp-ui-01-audit-search-id&offer_id=audit-offer-1", setup: "fare-selection" as const },
  { id: "passengers", path: "/booking/passengers", setup: "passengers" as const },
  { id: "payment", path: "/booking/payment/manual", setup: "payment" as const },
  { id: "cms-about", path: "/about-us", setup: "public" as const },
] as const;

for (const pageSpec of REPRESENTATIVE_PAGES) {
  for (const viewport of VIEWPORTS) {
    test(`responsive safety ${pageSpec.id} ${viewport.name}`, async ({ page }) => {
      await setupScenarioMocks(page, pageSpec.setup);
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await page.goto(pageSpec.path, { waitUntil: "domcontentloaded", timeout: 60_000 });
      await expect(page.locator("#main-content, main").first()).toBeVisible();
      await assertNoHorizontalOverflow(page);
    });
  }
}

for (const viewport of VIEWPORTS) {
  test(`responsive safety customer dashboard ${viewport.name}`, async ({ page }) => {
    await setSessionFixture(page, "customer");
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/customer/dashboard", { waitUntil: "domcontentloaded" });
    await expect(page.getByTestId("customer-dashboard-shell")).toBeVisible();
    await assertNoHorizontalOverflow(page);
  });

  test(`responsive safety agent dashboard ${viewport.name}`, async ({ page }) => {
    await setSessionFixture(page, "agent");
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    await page.goto("/agent/dashboard", { waitUntil: "domcontentloaded" });
    await expect(page.getByTestId("agent-dashboard-shell")).toBeVisible();
    await assertNoHorizontalOverflow(page);
  });
}
