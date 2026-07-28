import { test, expect } from "@playwright/test";
import { getReportModule } from "@/services/report-service";
import { containsSensitiveKeys } from "@/lib/read-only/sensitive-fields";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/reports", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("fixture reports overview loads", async () => {
  const result = await getReportModule(
    {
      datePreset: "last_30_days",
      startDate: "",
      endDate: "",
      comparison: "none",
      granularity: "day",
      currency: "PKR",
      channel: "all",
      supplier: "all",
      airline: "all",
      agent: "all",
      route: "all",
      bookingStatus: "all",
      paymentStatus: "all",
      ticketStatus: "all",
      fulfilmentStatus: "all",
      page: 1,
      pageSize: 25,
      sort: "sales",
      direction: "desc",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    },
    "overview",
  );
  expect(result.metrics.length).toBeGreaterThan(0);
  expect(containsSensitiveKeys(result)).toBe(false);
});

test("fixture source notice on reports", async ({ page }) => {
  await page.goto("/testdash/reports?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("report date range URL sync", async ({ page }) => {
  await page.goto("/testdash/reports?datePreset=last_7_days&currency=PKR", { waitUntil: "load" });
  await expect(page).toHaveURL(/datePreset=last_7_days/);
  await expect(page).toHaveURL(/currency=PKR/);
});

test("report currency filter URL sync", async ({ page }) => {
  await page.goto("/testdash/reports/payments?currency=USD", { waitUntil: "load" });
  await expect(page).toHaveURL(/currency=USD/);
});

test("reports bookings sub-route", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByRole("heading", { name: "Reports" })).toBeVisible();
});

test("report export preview manifest", async () => {
  const result = await getReportModule(
    {
      datePreset: "current_month",
      startDate: "",
      endDate: "",
      comparison: "none",
      granularity: "month",
      currency: "PKR",
      channel: "all",
      supplier: "all",
      airline: "all",
      agent: "all",
      route: "all",
      bookingStatus: "all",
      paymentStatus: "all",
      ticketStatus: "all",
      fulfilmentStatus: "all",
      page: 1,
      pageSize: 25,
      sort: "sales",
      direction: "desc",
      previewError: false,
      previewLoading: false,
      previewEmpty: false,
    },
    "sales",
  );
  expect(result.exportManifest.previewOnly).toBe(true);
  expect(result.exportManifest.rowCount).toBeGreaterThanOrEqual(0);
});

test("reports forbidden preview", async ({ page }) => {
  await page.goto("/testdash/reports?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByText(/Access denied/i)).toBeVisible();
});

test("reports live read-only notice", async ({ page }) => {
  await page.goto("/testdash/reports?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("reports no overflow at 390px", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/reports", { waitUntil: "load" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  expect(overflow).toBeFalsy();
});

test("reports stale preview", async ({ page }) => {
  await page.goto("/testdash/reports?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("reports operations sub-route", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/reports/operations", { waitUntil: "load" });
  await expect(page.getByText(/Ticketing state is informational only/i)).toBeVisible();
});
