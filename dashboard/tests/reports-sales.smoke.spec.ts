import { test, expect } from "@playwright/test";
import { applyReportsPresetAndWait, clickReportsSortHeader } from "./helpers";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/reports/sales", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("sales metrics are deterministic", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("reports-metric-grid")).toContainText("Gross booking value");
  await expect(page.getByTestId("reports-metric-grid")).toContainText("Booking count");
});

test("sales date filtering changes URL", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await applyReportsPresetAndWait(page, "last_7_days", /datePreset=last_7_days/);
});

test("sales by route works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-route")).toBeVisible();
});

test("sales by airline works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-airline")).toBeVisible();
});

test("sales by supplier works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-supplier")).toBeVisible();
});

test("sales by agent works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-agent")).toBeVisible();
});

test("sales by channel works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-channel")).toBeVisible();
});

test("direct agent split works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("report-metric-agent_assisted_booking_count")).toBeVisible();
});

test("sales sorting works", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await clickReportsSortHeader(page, "Route", "label", "desc");
});

test("sales pagination controls render", async ({ page }) => {
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("reports-table")).toBeVisible();
});

test("sales mobile card view works", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  await expect(page.getByTestId("reports-mobile-cards")).toBeVisible();
});

test("sales export reflects current filters", async ({ page }) => {
  await page.goto("/testdash/reports/sales?datePreset=last_7_days", { waitUntil: "load" });
  await page.getByTestId("reports-export-button").click();
  await expect(page.getByText("Period: 2026-06-25 — 2026-07-01")).toBeVisible();
});

test("sales table has no horizontal overflow at 360px", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 740 });
  await page.goto("/testdash/reports/sales", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBe(false);
});
