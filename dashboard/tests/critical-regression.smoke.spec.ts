import { test, expect } from "@playwright/test";
import {
  applyCmsLocalPreview,
  applyReportsPresetAndWait,
  closeDrawerWithEscape,
  closeExportDrawerWithEscape,
  expectCmsReady,
  expectReportsReady,
  navigateReportsSection,
  openReportsExportDrawer,
  resetCmsLocalPreview,
} from "./helpers";

test.beforeAll(async ({ request }) => {
  const response = await request.get("/testdash", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

const coreRoutes = [
  { path: "/testdash", heading: "Dashboard" },
  { path: "/testdash/bookings", heading: "Bookings" },
  { path: "/testdash/payments", heading: "Payments" },
  { path: "/testdash/customers", heading: "Customers" },
  { path: "/testdash/suppliers", heading: "Suppliers" },
  { path: "/testdash/agents", heading: "Agents" },
  { path: "/testdash/pnrs", heading: "PNRs" },
  { path: "/testdash/tickets", heading: "Tickets" },
];

for (const route of coreRoutes) {
  test(`core route renders: ${route.path}`, async ({ page }) => {
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: route.heading, level: 1 })).toBeVisible({ timeout: 60_000 });
  });
}

test("reports overview and filter URL behavior", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expectReportsReady(page);
  await applyReportsPresetAndWait(page, "last_7_days", /datePreset=last_7_days/);
});

test("reports GDS and NDC distinction", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByText("GDS PNR count")).toBeVisible();
  await expect(page.getByText("NDC order count")).toBeVisible();
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});

test("reports navigation and export drawer focus", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await navigateReportsSection(page, "Sales", /\/testdash\/reports\/sales/);
  await openReportsExportDrawer(page);
  await closeExportDrawerWithEscape(page);
});

test("cms overview renders", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expectCmsReady(page);
  await expect(page.getByTestId("cms-metric-grid")).toBeVisible();
});

test("cms page drawer opens and closes with Escape", async ({ page }) => {
  await page.goto("/testdash/cms/pages?selected=JP-CMS-PG-001", { waitUntil: "load" });
  await expect(page.getByTestId("cms-page-drawer")).toBeVisible();
  await closeDrawerWithEscape(page, /selected=JP-CMS-PG-001/);
});

test("cms section local preview and reset", async ({ page }) => {
  await page.goto("/testdash/cms/sections?selected=JP-CMS-SC-001", { waitUntil: "load" });
  await applyCmsLocalPreview(page, "Critical regression heading");
  await expect(page.getByTestId("cms-hero-preview").getByText("Critical regression heading")).toBeVisible();
  await resetCmsLocalPreview(page);
  await expect(page.getByTestId("cms-hero-preview")).toContainText("Homepage — hero");
});

test("cms theme preview mode changes", async ({ page }) => {
  await page.goto("/testdash/cms/sections?selected=JP-CMS-SC-001", { waitUntil: "load" });
  const selector = page.getByTestId("cms-preview-mode-selector");
  await selector.getByRole("button", { name: "Desktop night" }).click();
  await expect(page.getByTestId("cms-preview-frame")).toBeVisible();
});

test("mobile navigation opens for reports", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await page.getByRole("button", { name: "Open navigation menu" }).click();
  await expect(page.getByLabel("Dashboard navigation").getByRole("link", { name: "Reports" })).toBeVisible();
});

test("no live mutation controls on bookings", async ({ page }) => {
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /create booking|cancel booking|issue ticket/i })).toHaveCount(0);
});

test("no brand switcher in CMS", async ({ page }) => {
  await page.goto("/testdash/cms", { waitUntil: "load" });
  await expect(page.getByLabel(/brand/i)).toHaveCount(0);
  await expect(page.getByText(/Parwaaz|YoursDomain/i)).toHaveCount(0);
});

test("no sensitive ticket or PCC exposure on tickets route", async ({ page }) => {
  await page.goto("/testdash/tickets", { waitUntil: "load" });
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/\bLNIATA\b/i);
  expect(body).not.toMatch(/\bPCC\b/);
});

test("controlled loading state on reports", async ({ page }) => {
  await page.goto("/testdash/reports?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("reports-loading-state")).toBeVisible({ timeout: 15_000 });
});

test("controlled error state on CMS", async ({ page }) => {
  await page.goto("/testdash/cms?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load CMS/i)).toBeVisible();
});
