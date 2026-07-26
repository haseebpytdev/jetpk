import { test, expect } from "@playwright/test";
import { closeExportDrawerWithEscape, expectReportChartSegment, openReportsExportDrawer } from "./helpers";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/reports/operations", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("GDS and NDC remain distinct", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByText("GDS PNR count")).toBeVisible();
  await expect(page.getByText("NDC order count")).toBeVisible();
});

test("Sabre GDS label appears in channel chart", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expectReportChartSegment(page, "report-chart-pnr_channel", "Sabre GDS");
});

test("Sabre NDC label appears in channel chart", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expectReportChartSegment(page, "report-chart-pnr_channel", "Sabre NDC");
});

test("One API manual mock labels appear", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  const body = await page.locator("body").textContent();
  expect(body).toMatch(/One API|Manual|Mock/);
});

test("PNR lifecycle chart works", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-lifecycle")).toBeVisible();
});

test("ticket document state works", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-ticketing")).toBeVisible();
});

test("fulfilment state works", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-fulfilment")).toBeVisible();
});

test("ticketing blocked metric works", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByTestId("report-metric-review_required_count").getByText("Ticketing blocked")).toBeVisible();
});

test("cancellation eligibility is informational", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByTestId("operations-limitations")).toContainText(/informational only/i);
});

test("no mutation action buttons exist", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByRole("button", { name: /Issue ticket|Cancel PNR|Void|Refund/i })).toHaveCount(0);
});

test("no PCC or LNIATA values exposed", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  const main = await page.locator("main").textContent();
  expect(main).not.toMatch(/\bLNIATA\b/i);
  expect(main).not.toMatch(/\bPCC\b/);
});

test("PNR links work", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  const link = page.getByTestId("reports-table").getByRole("link").first();
  await expect(link).toHaveAttribute("href", /\/pnrs\?/);
});

test("ticketing limitation notice appears", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByTestId("operations-limitations")).toBeVisible();
  await expect(page.getByText(/No live ticket issuance/i)).toBeVisible();
});

test("chart sections have headings", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "GDS versus NDC versus other" })).toBeVisible();
});

test("export drawer closes with escape", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await openReportsExportDrawer(page);
  await closeExportDrawerWithEscape(page);
});

test("no cross brand text appears", async ({ page }) => {
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  const body = await page.locator("body").textContent();
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});
