import { test, expect } from "@playwright/test";

test.beforeAll(async ({ request }) => {
  expect((await request.get("/testdash/reports/bookings", { timeout: 120_000 })).ok()).toBeTruthy();
});

test("booking status distribution works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-booking_status")).toBeVisible();
});

test("booking trend works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-booking_value")).toBeVisible();
});

test("route distribution works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-route")).toBeVisible();
});

test("supplier distribution works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-supplier")).toBeVisible();
});

test("channel distribution works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByText(/Agent-assisted share|Direct share/).first()).toBeVisible();
});

test("trip type distribution works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-trip_type")).toBeVisible();
});

test("cabin distribution works", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-cabin")).toBeVisible();
});

test("lead time bands work", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("report-chart-lead_time")).toBeVisible();
});

test("booking detail links work", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  const link = page.getByTestId("reports-table").getByRole("link").first();
  await expect(link).toHaveAttribute("href", /\/bookings\?/);
});

test("booking funnel renders", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("reports-funnel")).toBeVisible();
});

test("booking payment pnr states remain distinct in funnel", async ({ page }) => {
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  const funnel = page.getByTestId("reports-funnel");
  await expect(funnel.getByText(/Booking status:/i).first()).toBeVisible();
  await expect(funnel.getByText(/Payment status:/i).first()).toBeVisible();
  await expect(funnel.getByText(/Operational PNR or NDC order/i).first()).toBeVisible();
});

test("bookings mobile cards readable", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/testdash/reports/bookings", { waitUntil: "load" });
  await expect(page.getByTestId("reports-mobile-cards")).toBeVisible();
});
