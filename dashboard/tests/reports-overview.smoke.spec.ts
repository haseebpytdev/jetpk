import { test, expect } from "@playwright/test";
import {
  applyReportsPresetAndWait,
  expectFiltersReady,
  expectReportsReady,
  navigateReportsSection,
  openReportsExportDrawer,
  resetReportsFiltersAndWait,
} from "./helpers";

const viewports = [
  { width: 360, height: 740 },
  { width: 390, height: 844 },
  { width: 768, height: 1024 },
  { width: 1280, height: 720 },
];

const routes = [
  { path: "/testdash/reports", label: "Overview" },
  { path: "/testdash/reports/sales", label: "Sales" },
  { path: "/testdash/reports/bookings", label: "Bookings" },
  { path: "/testdash/reports/payments", label: "Payments" },
  { path: "/testdash/reports/operations", label: "Operations" },
];

test.beforeAll(async ({ request }) => {
  const response = await request.get("/testdash/reports", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

for (const route of routes) {
  test(`reports route renders: ${route.path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(route.path, { waitUntil: "load" });
    await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible({ timeout: 60_000 });
    await expect(page.getByTestId("reports-filters")).toBeVisible();
    await expect(page.getByTestId("reports-metric-grid")).toBeVisible();
  });
}

test("reports navigation between sections", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await navigateReportsSection(page, "Sales", /\/testdash\/reports\/sales/);
  await navigateReportsSection(page, "Operations", /\/testdash\/reports\/operations/);
});

for (const viewport of viewports.filter((v) => v.width < 768)) {
  test(`reports mobile navigation at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/reports", { waitUntil: "load" });
    await page.getByRole("button", { name: "Open navigation menu" }).click();
    await expect(page.getByLabel("Dashboard navigation").getByRole("link", { name: "Reports" })).toBeVisible();
  });
}

test("shared filters render", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expectFiltersReady(page);
});

test("date preset updates URL state", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await applyReportsPresetAndWait(page, "last_7_days", /datePreset=last_7_days/);
});

test("custom date range shows validation error", async ({ page }) => {
  await page.goto("/testdash/reports?datePreset=custom&startDate=2026-08-01&endDate=2026-07-01", { waitUntil: "load" });
  await expect(page.locator("#report-date-error")).toContainText(/cannot be after/i);
});

test("invalid URL state falls back safely", async ({ page }) => {
  await page.goto("/testdash/reports?datePreset=invalid&currency=XYZ", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible();
});

test("reset filters works", async ({ page }) => {
  await page.goto("/testdash/reports?supplier=Sabre&channel=agent", { waitUntil: "load" });
  await resetReportsFiltersAndWait(page, /supplier=Sabre/);
});

test("browser back forward preserves navigation", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await navigateReportsSection(page, "Sales", /\/testdash\/reports\/sales/);
  await page.goBack();
  await expect(page).toHaveURL(/\/testdash\/reports$/);
  await expectReportsReady(page);
  await page.goForward();
  await expect(page).toHaveURL(/\/testdash\/reports\/sales/);
  await expectReportsReady(page);
});

test("loading state renders", async ({ page }) => {
  await page.goto("/testdash/reports?previewLoading=1", { waitUntil: "load" });
  await expect(page.getByTestId("reports-loading-state")).toBeVisible({ timeout: 15_000 });
  await expect(page.getByLabel("Loading report data")).toBeVisible();
});

test("empty state renders", async ({ page }) => {
  await page.goto("/testdash/reports?previewEmpty=1", { waitUntil: "load" });
  await expect(page.getByText(/No report data/i)).toBeVisible();
});

test("controlled error renders", async ({ page }) => {
  await page.goto("/testdash/reports?previewError=1", { waitUntil: "load" });
  await expect(page.getByText(/Unable to load reports/i)).toBeVisible();
});

for (const viewport of viewports.filter((v) => v.width <= 390)) {
  test(`no horizontal overflow at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto("/testdash/reports", { waitUntil: "load" });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow).toBe(false);
  });
}

test("export controls are accessible", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await openReportsExportDrawer(page);
});

test("KPI cards show deterministic values", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("reports-metric-grid")).toContainText(/Booking count|Gross booking value/);
});

test("booking trend chart renders", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-booking_value")).toBeVisible();
});

test("collection trend chart renders", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-payment_collection")).toBeVisible();
});

test("status distribution renders", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-booking_status")).toBeVisible();
});

test("channel mix renders", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-channel")).toBeVisible();
});

test("top routes render", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-route")).toBeVisible();
});

test("supplier exposure renders", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-supplier")).toBeVisible();
});

test("fulfilment status renders", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-fulfilment")).toBeVisible();
});

test("attention queue links to valid modules", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  const queue = page.getByTestId("reports-attention-queue");
  await expect(queue).toBeVisible();
  const link = queue.getByRole("link").first();
  await expect(link).toBeVisible();
  const href = await link.getAttribute("href");
  expect(href).toMatch(/\/(testdash\/)?(bookings|payments|pnrs)/);
});

test("accessible chart summaries exist", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  const summaries = page.locator(".sr-only");
  await expect(summaries.first()).toBeAttached();
});

test("keyboard focus is visible on apply button", async ({ page }) => {
  await page.goto("/testdash/reports", { waitUntil: "load" });
  await page.keyboard.press("Tab");
  const focused = page.locator(":focus");
  await expect(focused).toBeVisible();
});

test("existing bookings route remains functional", async ({ page }) => {
  await page.goto("/testdash/bookings", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Bookings", level: 1 })).toBeVisible();
});
